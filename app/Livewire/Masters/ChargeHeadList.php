<?php

namespace App\Livewire\Masters;

use App\Enums\AccountType;
use App\Enums\ChargeBasis;
use App\Livewire\Concerns\WithCrudModal;
use App\Models\Account;
use App\Models\Building;
use App\Models\ChargeHead;
use App\Models\Flat;
use App\Services\Billing\ChargeCalculator;
use App\Support\CurrentBuilding;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;

/**
 * What the current building bills every month.
 *
 * The preview underneath the table is the point of this screen: it shows what each
 * active flat would actually be billed on the next run, computed by the same
 * ChargeCalculator that bill generation uses, so there is no second implementation
 * of the arithmetic to drift.
 */
class ChargeHeadList extends Component
{
    use WithCrudModal;

    public string $name = '';

    public ?string $nameBn = null;

    public ?int $accountId = null;

    public string $basis = 'per_flat';

    public string $amount = '';

    public int $sortOrder = 0;

    public bool $isActive = true;

    private function building(): ?Building
    {
        return app(CurrentBuilding::class)->get();
    }

    /**
     * @return array<string, mixed>
     */
    protected function formRules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('charge_heads', 'name')
                    ->where('building_id', app(CurrentBuilding::class)->id())
                    ->ignore($this->editingId),
            ],
            'nameBn' => ['nullable', 'string', 'max:255'],
            'accountId' => ['required', 'integer', 'exists:accounts,id'],
            'basis' => ['required', Rule::enum(ChargeBasis::class)],
            'amount' => ['required', 'numeric', 'min:0'],
            'sortOrder' => ['required', 'integer', 'min:0', 'max:999'],
            'isActive' => ['boolean'],
        ];
    }

    /**
     * @param  ChargeHead|null  $record
     */
    protected function fillForm(?Model $record): void
    {
        $this->name = $record->name ?? '';
        $this->nameBn = $record?->name_bn;
        $this->accountId = $record?->account_id;
        $this->basis = $record?->basis->value ?? ChargeBasis::PerFlat->value;
        $this->amount = $record === null ? '' : (string) $record->amount;
        $this->sortOrder = $record->sort_order ?? 0;
        $this->isActive = $record->is_active ?? true;
    }

    protected function findRecord(int $id): ChargeHead
    {
        return ChargeHead::where('building_id', app(CurrentBuilding::class)->id())->findOrFail($id);
    }

    protected function authorizeAction(string $ability, ?Model $record = null): void
    {
        $this->authorize($ability, $record ?? ChargeHead::class);
    }

    protected function persist(): ChargeHead
    {
        $head = $this->editingId === null ? new ChargeHead : $this->findRecord($this->editingId);

        $head->fill([
            'building_id' => app(CurrentBuilding::class)->getOrFail()->id,
            'account_id' => $this->accountId,
            'name' => $this->name,
            'name_bn' => $this->nameBn,
            'basis' => ChargeBasis::from($this->basis),
            'amount' => $this->amount,
            'sort_order' => $this->sortOrder,
            'is_active' => $this->isActive,
        ])->save();

        session()->flash('status', __('masters.saved'));

        return $head;
    }

    public function toggleActive(int $id): void
    {
        $head = $this->findRecord($id);

        $this->authorizeAction('update', $head);

        $head->is_active = ! $head->is_active;
        $head->save();
    }

    /**
     * What each active flat would be billed next run.
     *
     * @return Collection<int, array{flat: Flat, lines: array<int, string>, total: numeric-string}>
     */
    private function preview(Building $building, ChargeCalculator $charges): Collection
    {
        return $building->activeFlats()->with('chargeOverrides')->orderBy('number')->get()
            ->map(function (Flat $flat) use ($building, $charges): array {
                $flat->setRelation('building', $building);

                $lines = [];
                $total = '0.00';

                foreach ($charges->for($flat) as $line) {
                    $lines[$line['charge_head']->id] = $line['amount'];
                    $total = bcadd($total, $line['amount'], 2);
                }

                return ['flat' => $flat, 'lines' => $lines, 'total' => $total];
            });
    }

    public function render(ChargeCalculator $charges): View
    {
        $this->authorize('viewAny', ChargeHead::class);

        $building = $this->building();

        return view('livewire.masters.charge-head-list', [
            'building' => $building,
            'heads' => $building === null ? collect() : $building->chargeHeads()->with('account')->get(),
            'preview' => $building === null ? collect() : $this->preview($building, $charges),
            'bases' => ChargeBasis::cases(),
            'incomeAccounts' => Account::where('is_postable', true)
                ->where('is_active', true)
                ->where('type', AccountType::Income)
                ->orderBy('code')
                ->get(),
        ])->layout('components.layouts.app');
    }
}
