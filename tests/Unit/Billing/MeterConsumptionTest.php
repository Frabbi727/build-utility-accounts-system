<?php

namespace Tests\Unit\Billing;

use App\Models\Meter;
use App\Services\Billing\MeterConsumption;
use PHPUnit\Framework\TestCase;

class MeterConsumptionTest extends TestCase
{
    private MeterConsumption $consumption;

    protected function setUp(): void
    {
        parent::setUp();

        $this->consumption = new MeterConsumption;
    }

    private function meter(int $digits = 6): Meter
    {
        return new Meter(['digits' => $digits]);
    }

    public function test_it_is_the_difference_between_two_readings(): void
    {
        $this->assertSame('120.000', $this->consumption->between($this->meter(), '1000.000', '1120.000'));
    }

    public function test_an_unchanged_meter_consumed_nothing(): void
    {
        $this->assertSame('0.000', $this->consumption->between($this->meter(), '1000.000', '1000.000'));
    }

    public function test_it_keeps_fractional_precision(): void
    {
        $this->assertSame('123.456', $this->consumption->between($this->meter(), '0.000', '123.456'));
    }

    public function test_a_meter_that_wrapped_past_all_nines_counts_forward_not_backward(): void
    {
        // A 5-digit meter reading 99990 then 10 turned over: 10 units to the ceiling,
        // then 10 more. Not minus 99980.
        $this->assertSame('20.000', $this->consumption->between($this->meter(5), '99990.000', '10.000'));
    }

    public function test_rollover_scales_with_the_dial_count(): void
    {
        $this->assertSame('20.000', $this->consumption->between($this->meter(6), '999990.000', '10.000'));
        $this->assertSame('2.000', $this->consumption->between($this->meter(4), '9999.000', '1.000'));
    }

    public function test_a_reading_wider_than_the_meter_is_implausible(): void
    {
        $this->assertTrue($this->consumption->isImplausible($this->meter(5), '100000.000'));
        $this->assertTrue($this->consumption->isImplausible($this->meter(5), '123456.000'));
    }

    public function test_a_reading_within_the_dials_is_plausible(): void
    {
        $this->assertFalse($this->consumption->isImplausible($this->meter(5), '99999.000'));
        $this->assertFalse($this->consumption->isImplausible($this->meter(5), '0.000'));
    }
}
