<?php

namespace App\Livewire\Setup;

use App\Enums\AccountCode;
use App\Enums\ChargeBasis;
use App\Exceptions\InvalidJournalEntryException;
use App\Models\Account;
use App\Models\Building;
use App\Models\ChargeHead;
use App\Models\Owner;
use App\Services\Accounting\PostOpeningBalances;
use App\Services\Masters\GenerateFlats;
use App\Support\CurrentBuilding;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Livewire\Component;

/**
 * The path from an empty database to a building that can be billed.
 *
 * Each step writes as it goes rather than collecting everything and saving at the
 * end, so an interrupted setup leaves real records behind and can be resumed from
 * the ordinary Masters screens.
 */
class Wizard extends Component
{
    public const TOTAL_STEPS = 6;

    /**
     * A typical Dhaka apartment building's monthly components, offered as a
     * starting point on the charge-heads step.
     *
     * @var list<array{name: string, name_bn: string, basis: string, amount: string}>
     */
    private const SUGGESTED_HEADS = [
        ['name' => 'Guard Salary Share', 'name_bn' => 'নিরাপত্তা কর্মীর বেতন', 'basis' => 'per_flat', 'amount' => '1200.00'],
        ['name' => 'Lift Maintenance', 'name_bn' => 'লিফট রক্ষণাবেক্ষণ', 'basis' => 'per_flat', 'amount' => '500.00'],
        ['name' => 'Common Electricity', 'name_bn' => 'সাধারণ বিদ্যুৎ', 'basis' => 'per_flat', 'amount' => '400.00'],
        ['name' => 'Water (WASA)', 'name_bn' => 'পানি (ওয়াসা)', 'basis' => 'per_sqft', 'amount' => '1.50'],
        ['name' => 'Cleaning Contract', 'name_bn' => 'পরিচ্ছন্নতা চুক্তি', 'basis' => 'equal_share', 'amount' => '8000.00'],
    ];

    public int $step = 1;

    // Step 1 — building
    public string $buildingName = '';

    public ?string $buildingNameBn = null;

    public ?string $address = null;

    public int $dueDayOfMonth = 10;

    public ?int $buildingId = null;

    // Step 2 — flats
    public int $fromLevel = 1;

    public int $toLevel = 6;

    public string $suffixes = 'A,B';

    public string $defaultSizeSqft = '1200';

    // Step 3 — owners
    public string $ownerName = '';

    public ?string $ownerPhone = null;

    // Step 4 — charge heads
    public string $headName = '';

    public string $headBasis = 'per_flat';

    public string $headAmount = '';

    // Step 5 — opening balances
    public string $openingAsOf = '';

    public string $cash = '0';

    public string $bank = '0';

    /**
     * The wizard spans buildings, flats, charge heads and opening balances, and
     * the last of those is administrator-only, so the whole wizard is.
     */
    private function authorizeWizard(): void
    {
        abort_unless(auth()->user()?->isAdmin() === true, 403);
    }

    public function mount(): void
    {
        $this->authorizeWizard();

        $this->openingAsOf = now()->startOfMonth()->toDateString();

        // Resume rather than restart when a building already exists.
        $existing = app(CurrentBuilding::class)->get();

        if ($existing !== null) {
            $this->loadBuilding($existing);
            $this->step = 2;
        }
    }

    private function loadBuilding(Building $building): void
    {
        $this->buildingId = $building->id;
        $this->buildingName = $building->name;
        $this->buildingNameBn = $building->name_bn;
        $this->address = $building->address;
        $this->dueDayOfMonth = $building->due_day_of_month;
    }

    private function building(): Building
    {
        return Building::findOrFail($this->buildingId);
    }

    public function saveBuilding(): void
    {
        $this->authorizeWizard();

        $this->validate([
            'buildingName' => ['required', 'string', 'max:255'],
            'buildingNameBn' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'dueDayOfMonth' => ['required', 'integer', 'min:1', 'max:28'],
        ]);

        $building = $this->buildingId === null ? new Building : $this->building();

        $building->fill([
            'name' => $this->buildingName,
            'name_bn' => $this->buildingNameBn,
            'address' => $this->address,
            'due_day_of_month' => $this->dueDayOfMonth,
        ])->save();

        $this->buildingId = $building->id;
        app(CurrentBuilding::class)->set($building->id);

        $this->step = 2;
    }

    public function saveFlats(GenerateFlats $generator): void
    {
        $this->authorizeWizard();

        $this->validate([
            'fromLevel' => ['required', 'integer', 'min:0', 'max:200'],
            'toLevel' => ['required', 'integer', 'min:0', 'max:200', 'gte:fromLevel'],
            'suffixes' => ['required', 'string', 'max:255'],
            'defaultSizeSqft' => ['required', 'numeric', 'min:0'],
        ]);

        $suffixes = $generator->parseSuffixes($this->suffixes);

        if ($suffixes === []) {
            $this->addError('suffixes', __('validation.required', ['attribute' => __('masters.flat_suffixes')]));

            return;
        }

        $generator->handle(
            $this->building(),
            $this->fromLevel,
            $this->toLevel,
            $suffixes,
            $this->defaultSizeSqft,
        );

        $this->step = 3;
    }

    public function addOwner(): void
    {
        $this->authorizeWizard();

        $this->validate([
            'ownerName' => ['required', 'string', 'max:255'],
            'ownerPhone' => ['nullable', 'string', 'max:50'],
        ]);

        Owner::create(['name' => $this->ownerName, 'phone' => $this->ownerPhone]);

        $this->reset(['ownerName', 'ownerPhone']);
    }

    public function addSuggestedHeads(): void
    {
        $this->authorizeWizard();

        $income = Account::firstWhere('code', AccountCode::ServiceChargeIncome->value);

        if ($income === null) {
            return;
        }

        foreach (self::SUGGESTED_HEADS as $index => $head) {
            ChargeHead::firstOrCreate(
                ['building_id' => $this->buildingId, 'name' => $head['name']],
                [
                    'account_id' => $income->id,
                    'name_bn' => $head['name_bn'],
                    'basis' => ChargeBasis::from($head['basis']),
                    'amount' => $head['amount'],
                    'sort_order' => $index,
                    'is_active' => true,
                ],
            );
        }
    }

    public function addHead(): void
    {
        $this->authorizeWizard();

        $this->validate([
            'headName' => ['required', 'string', 'max:255'],
            'headBasis' => ['required', 'in:per_flat,per_sqft,equal_share'],
            'headAmount' => ['required', 'numeric', 'min:0'],
        ]);

        $income = Account::firstWhere('code', AccountCode::ServiceChargeIncome->value);

        if ($income === null) {
            return;
        }

        ChargeHead::updateOrCreate(
            ['building_id' => $this->buildingId, 'name' => $this->headName],
            [
                'account_id' => $income->id,
                'basis' => ChargeBasis::from($this->headBasis),
                'amount' => $this->headAmount,
                'sort_order' => ChargeHead::where('building_id', $this->buildingId)->count(),
                'is_active' => true,
            ],
        );

        $this->reset(['headName', 'headAmount']);
    }

    public function saveOpeningBalances(PostOpeningBalances $poster): void
    {
        $this->authorizeWizard();

        $this->validate([
            'openingAsOf' => ['required', 'date'],
            'cash' => ['required', 'numeric', 'min:0'],
            'bank' => ['required', 'numeric', 'min:0'],
        ]);

        try {
            $poster->handle(Carbon::parse($this->openingAsOf), $this->cash, $this->bank);
        } catch (InvalidJournalEntryException) {
            // Nothing to post, or already posted: neither should block finishing.
        }

        $this->step = 6;
    }

    public function goToStep(int $step): void
    {
        // Only ever move within the wizard, and never past the building step
        // before a building exists.
        $this->step = max(1, min(self::TOTAL_STEPS, $this->buildingId === null ? 1 : $step));
    }

    public function render(): View
    {
        $this->authorizeWizard();

        $building = $this->buildingId === null ? null : $this->building();

        return view('livewire.setup.wizard', [
            'building' => $building,
            'flatCount' => $building?->flats()->count() ?? 0,
            'floorCount' => $building?->floors()->count() ?? 0,
            'owners' => Owner::orderBy('name')->get(),
            'heads' => $building === null ? collect() : $building->chargeHeads()->get(),
            'bases' => ChargeBasis::cases(),
            'totalSteps' => self::TOTAL_STEPS,
        ])->layout('components.layouts.app');
    }
}
