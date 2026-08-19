<?php

namespace Database\Factories;

use App\Enums\ReadingStatus;
use App\Models\Meter;
use App\Models\MeterReading;
use App\Models\UtilityTariff;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/** @extends Factory<MeterReading> */
class MeterReadingFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'meter_id' => Meter::factory(),
            'utility_tariff_id' => null,
            'billing_month' => Carbon::parse('2026-08-01'),
            'reading_date' => Carbon::parse('2026-08-28'),
            'previous_reading' => '0.000',
            'current_reading' => '100.000',
            'consumption' => '100.000',
            'is_estimated' => false,
            'note' => null,
            'recorded_by' => null,
            'status' => ReadingStatus::Draft,
            'applied_to_bill_id' => null,
        ];
    }

    /**
     * Confirmed against a tariff, and therefore billable.
     *
     * The tariff is stamped here rather than resolved later, exactly as ConfirmReading
     * does it — the DB constraint refuses a confirmed reading without one.
     */
    public function confirmed(?UtilityTariff $tariff = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ReadingStatus::Confirmed,
            'utility_tariff_id' => $tariff instanceof UtilityTariff
                ? $tariff->id
                : UtilityTariff::factory()->flatRate(),
        ]);
    }

    public function consumed(string $units): static
    {
        return $this->state(fn (array $attributes): array => [
            'previous_reading' => '0.000',
            'current_reading' => $units,
            'consumption' => $units,
        ]);
    }
}
