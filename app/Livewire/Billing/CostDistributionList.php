<?php

namespace App\Livewire\Billing;

use App\Enums\AccountType;
use App\Enums\DistributionBasis;
use App\Enums\DistributionStatus;
use App\Exceptions\InvalidDistributionException;
use App\Livewire\Concerns\WithCrudModal;
use App\Models\Account;
use App\Models\CostDistribution;
use App\Models\Expense;
use App\Models\Utility;
use App\Services\Billing\ApproveDistribution;
use App\Support\CurrentBuilding;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;

/**
 * One-off costs spread across the units.
 *
 * The flow is draft → edit the split → approve. Approving freezes the lines and posts
 * nothing; the money is accrued when the bill is generated.
 *
 * "Spread over N months" creates N sibling distributions, one per month, each with its
 * own frozen lines — rather than instalment rows on one distribution. Same outcome for
 * the operator, but no second level of remainder arithmetic and each month stays
 * independently correctable.
 */
class CostDistributionList extends Component
{
    use WithCrudModal;

    public string $title = '';

    public ?string $description = null;

    public string $totalAmount = '';

    public ?int $recoveryAccountId = null;

    public ?int $sourceExpenseId = null;

    public ?int $utilityId = null;

    public string $basis = 'equal';

    public string $billingMonth = '';

    public int $months = 1;

    /** The distribution whose split is open below the table. */
    public ?int $viewingId = null;

    /**
     * Editable per-unit amounts while a distribution is a draft, keyed by line id.
     *
     * @var array<int, string>
     */
    public array $lineAmounts = [];

    /**
     * @return array<string, mixed>
     */
    protected function formRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'totalAmount' => ['required', 'numeric', 'gt:0'],
            // Income only: recovering a cost is revenue, not a credit back to the
            // expense account the building was billed on.
            'recoveryAccountId' => [
                'required', 'integer',
                Rule::exists('accounts', 'id')
                    ->where('type', AccountType::Income->value)
                    ->where('is_postable', true)
                    ->where('is_active', true),
            ],
            'sourceExpenseId' => ['nullable', 'integer', 'exists:expenses,id'],
            'basis' => ['required', Rule::enum(DistributionBasis::class)],
            'utilityId' => [
                Rule::requiredIf(fn (): bool => $this->basis === DistributionBasis::ByConsumption->value),
                'nullable', 'integer',
                Rule::exists('utilities', 'id')->where('building_id', app(CurrentBuilding::class)->id()),
            ],
            'billingMonth' => ['required', 'date_format:Y-m'],
            'months' => ['required', 'integer', 'min:1', 'max:36'],
        ];
    }

    /**
     * @param  CostDistribution|null  $record
     */
    protected function fillForm(?Model $record): void
    {
        $this->title = $record->title ?? '';
        $this->description = $record?->description;
        $this->totalAmount = $record === null ? '' : (string) $record->total_amount;
        $this->recoveryAccountId = $record?->recovery_account_id;
        $this->sourceExpenseId = $record?->source_expense_id;
        $this->utilityId = $record?->utility_id;
        $this->basis = $record === null ? DistributionBasis::Equal->value : $record->basis->value;
        $this->billingMonth = $record === null
            ? now()->format('Y-m')
            : $record->billing_month->format('Y-m');
        // Editing never re-splits an existing distribution across months.
        $this->months = 1;
    }

    protected function findRecord(int $id): CostDistribution
    {
        return CostDistribution::where('building_id', app(CurrentBuilding::class)->id())
            ->with('lines')
            ->findOrFail($id);
    }

    protected function authorizeAction(string $ability, ?Model $record = null): void
    {
        $this->authorize($ability, $record ?? CostDistribution::class);
    }

    protected function persist(): CostDistribution
    {
        $buildingId = app(CurrentBuilding::class)->getOrFail()->id;
        $firstMonth = Carbon::createFromFormat('Y-m', $this->billingMonth)->startOfMonth();
        $basis = DistributionBasis::from($this->basis);

        return DB::transaction(function () use ($buildingId, $firstMonth, $basis): CostDistribution {
            if ($this->editingId !== null) {
                $distribution = $this->findRecord($this->editingId);
                $distribution->fill($this->attributesFor($buildingId, $firstMonth, $basis, (string) $this->totalAmount))->save();
                // The basis or the amount may have moved, so the draft split is stale.
                $distribution->lines()->delete();

                session()->flash('status', __('masters.saved'));

                return $distribution;
            }

            // One sibling per month rather than instalment rows on a single record.
            $shares = $this->monthlyShares((string) $this->totalAmount, $this->months);
            $first = null;

            foreach ($shares as $index => $share) {
                $distribution = CostDistribution::create([
                    ...$this->attributesFor($buildingId, $firstMonth->copy()->addMonths($index), $basis, $share),
                    'created_by' => Auth::id(),
                ]);

                $first ??= $distribution;
            }

            session()->flash('status', __('masters.saved'));

            return $first;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function attributesFor(int $buildingId, Carbon $month, DistributionBasis $basis, string $amount): array
    {
        return [
            'building_id' => $buildingId,
            'recovery_account_id' => $this->recoveryAccountId,
            'source_expense_id' => $this->sourceExpenseId,
            // The DB constraint ties this to the basis: only by_consumption carries one.
            'utility_id' => $basis->needsUtility() ? $this->utilityId : null,
            'title' => $this->title,
            'description' => $this->description,
            'total_amount' => $amount,
            'basis' => $basis,
            'billing_month' => $month,
            'status' => DistributionStatus::Draft,
        ];
    }

    /**
     * Split a total across N months. bcdiv truncates, so the first month absorbs the
     * remainder — the same rule the per-unit split uses, for the same reason.
     *
     * @return list<string>
     */
    private function monthlyShares(string $total, int $months): array
    {
        $normalised = bcadd($total, '0', 2);

        if ($months === 1) {
            return [$normalised];
        }

        $share = bcdiv($normalised, (string) $months, 2);
        $shares = array_fill(0, $months, $share);
        $shares[0] = bcadd($share, bcsub($normalised, bcmul($share, (string) $months, 2), 2), 2);

        return $shares;
    }

    public function viewSplit(int $id): void
    {
        $distribution = $this->findRecord($id);
        $this->authorizeAction('view', $distribution);

        if ($distribution->lines->isEmpty() && ! $distribution->isApproved()) {
            $this->recalculate($id);

            return;
        }

        $this->viewingId = $id;
        $this->loadLineAmounts($distribution);
    }

    public function recalculate(int $id): void
    {
        $distribution = $this->findRecord($id);
        $this->authorizeAction('update', $distribution);

        try {
            app(ApproveDistribution::class)->recalculate($distribution);
        } catch (InvalidDistributionException $e) {
            $this->addError('lines', $e->getMessage());

            return;
        }

        $this->viewingId = $id;
        $this->loadLineAmounts($distribution->refresh());
    }

    public function saveSplit(): void
    {
        if ($this->viewingId === null) {
            return;
        }

        $distribution = $this->findRecord($this->viewingId);
        $this->authorizeAction('update', $distribution);

        DB::transaction(function () use ($distribution): void {
            foreach ($distribution->lines as $line) {
                if (array_key_exists($line->id, $this->lineAmounts)) {
                    $line->update(['amount' => bcadd((string) $this->lineAmounts[$line->id], '0', 2)]);
                }
            }
        });

        session()->flash('status', __('masters.saved'));
        $this->loadLineAmounts($distribution->refresh());
    }

    public function approve(int $id): void
    {
        $distribution = $this->findRecord($id);
        $this->authorizeAction('approve', $distribution);

        try {
            app(ApproveDistribution::class)->handle($distribution);
        } catch (InvalidDistributionException $e) {
            $this->addError('lines', $e->getMessage());

            return;
        }

        session()->flash('status', __('distributions.approved_notice'));
        $this->viewingId = $id;
    }

    public function revert(int $id): void
    {
        $distribution = $this->findRecord($id);
        $this->authorizeAction('revert', $distribution);

        try {
            app(ApproveDistribution::class)->revert($distribution);
        } catch (InvalidDistributionException $e) {
            $this->addError('lines', $e->getMessage());
        }
    }

    private function loadLineAmounts(CostDistribution $distribution): void
    {
        $this->lineAmounts = $distribution->lines
            ->mapWithKeys(fn ($line): array => [$line->id => (string) $line->amount])
            ->all();
    }

    public function render(): View
    {
        $this->authorize('viewAny', CostDistribution::class);

        $buildingId = app(CurrentBuilding::class)->id();

        $viewing = $this->viewingId === null
            ? null
            : CostDistribution::where('building_id', $buildingId)
                ->with(['lines.flat', 'lines.bill'])
                ->find($this->viewingId);

        return view('livewire.billing.cost-distribution-list', [
            'distributions' => CostDistribution::query()
                ->with(['recoveryAccount', 'utility'])
                ->withCount('lines')
                ->withSum('lines', 'amount')
                ->where('building_id', $buildingId)
                ->orderByDesc('billing_month')
                ->orderByDesc('id')
                ->get(),
            'viewing' => $viewing,
            'bases' => DistributionBasis::cases(),
            'utilities' => Utility::where('building_id', $buildingId)->where('is_active', true)->orderBy('name')->get(),
            'incomeAccounts' => Account::where('is_postable', true)
                ->where('is_active', true)
                ->where('type', AccountType::Income)
                ->orderBy('code')
                ->get(),
            'expenses' => Expense::orderByDesc('spent_on')->limit(50)->get(),
        ])->layout('components.layouts.app');
    }
}
