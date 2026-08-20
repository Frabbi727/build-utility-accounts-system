<?php

namespace App\Livewire\Masters;

use App\Livewire\Concerns\WithNotices;
use App\Models\ChargeHead;
use App\Models\Flat;
use App\Models\FlatChargeOverride;
use App\Services\Billing\ChargeCalculator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Which charge heads one flat pays differently, or not at all.
 *
 * The screen edits every head at once rather than a row at a time: an operator
 * setting up a ground-floor shop that pays no lift charge wants to see the whole
 * picture, not a modal per head.
 *
 * @property-read Flat $flat
 */
class FlatChargeOverrides extends Component
{
    use WithNotices;

    public Flat $flat;

    /**
     * Per charge head id: whether the flat is exempt, and the negotiated amount.
     *
     * @var array<int, array{mode: string, amount: string}>
     */
    public array $rows = [];

    public function mount(Flat $flat): void
    {
        $this->authorize('view', $flat);

        $this->flat = $flat;

        $existing = $flat->chargeOverrides()->get()->keyBy('charge_head_id');

        foreach ($this->heads() as $head) {
            $override = $existing->get($head->id);

            $this->rows[$head->id] = [
                'mode' => match (true) {
                    $override === null => 'standard',
                    $override->is_exempt => 'exempt',
                    default => 'override',
                },
                'amount' => $override?->amount === null ? '' : (string) $override->amount,
            ];
        }
    }

    /**
     * @return Collection<int, ChargeHead>
     */
    private function heads()
    {
        return $this->flat->building->chargeHeads()->with('account')->get();
    }

    public function save(): void
    {
        $this->authorize('manage', Flat::class);

        $this->validate([
            'rows.*.mode' => ['required', 'in:standard,override,exempt'],
            // Only an "override" row needs a number, hence the conditional rule below.
            'rows.*.amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        foreach ($this->rows as $headId => $row) {
            if ($row['mode'] === 'override' && trim($row['amount']) === '') {
                $this->addError("rows.{$headId}.amount", __('validation.required', ['attribute' => __('masters.override_amount')]));
            }
        }

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        DB::transaction(function (): void {
            foreach ($this->rows as $headId => $row) {
                if ($row['mode'] === 'standard') {
                    FlatChargeOverride::where('flat_id', $this->flat->id)
                        ->where('charge_head_id', $headId)
                        ->delete();

                    continue;
                }

                FlatChargeOverride::updateOrCreate(
                    ['flat_id' => $this->flat->id, 'charge_head_id' => $headId],
                    [
                        'is_exempt' => $row['mode'] === 'exempt',
                        'amount' => $row['mode'] === 'exempt' ? null : $row['amount'],
                    ],
                );
            }
        });

        $this->notify(__('masters.saved'));
    }

    public function render(ChargeCalculator $charges): View
    {
        $this->authorize('view', $this->flat);

        $heads = $this->heads();
        $flat = $this->flat->fresh(['chargeOverrides', 'building']);

        $standard = $heads->mapWithKeys(fn (ChargeHead $head): array => [
            $head->id => $charges->computed($head, $flat, $flat->building),
        ]);

        $effective = collect($charges->for($flat))
            ->mapWithKeys(fn (array $line): array => [$line['charge_head']->id => $line['amount']]);

        return view('livewire.masters.flat-charge-overrides', [
            'heads' => $heads,
            'standard' => $standard,
            'effective' => $effective,
            'total' => $charges->totalFor($flat),
        ])->layout('components.layouts.app');
    }
}
