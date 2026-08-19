<?php

namespace Tests\Unit\Billing;

use App\Exceptions\InvalidTariffException;
use App\Services\Billing\SlabValidator;
use PHPUnit\Framework\TestCase;

class SlabValidatorTest extends TestCase
{
    private SlabValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = new SlabValidator;
    }

    /**
     * @param  list<array{from_unit: string, to_unit: string|null}>  $slabs
     */
    private function assertRejected(array $slabs, string $because): void
    {
        $this->assertNotNull($this->validator->errorFor($slabs), $because);
    }

    public function test_a_single_open_ended_slab_is_a_valid_flat_rate(): void
    {
        $this->validator->assertValid([
            ['from_unit' => '0.000', 'to_unit' => null],
        ]);

        $this->addToAssertionCount(1);
    }

    public function test_a_contiguous_set_of_bands_is_accepted(): void
    {
        $this->validator->assertValid([
            ['from_unit' => '0.000', 'to_unit' => '75.000'],
            ['from_unit' => '75.000', 'to_unit' => '200.000'],
            ['from_unit' => '200.000', 'to_unit' => '300.000'],
            ['from_unit' => '300.000', 'to_unit' => null],
        ]);

        $this->addToAssertionCount(1);
    }

    public function test_it_accepts_bands_given_out_of_order(): void
    {
        $this->validator->assertValid([
            ['from_unit' => '75.000', 'to_unit' => '200.000'],
            ['from_unit' => '200.000', 'to_unit' => null],
            ['from_unit' => '0.000', 'to_unit' => '75.000'],
        ]);

        $this->addToAssertionCount(1);
    }

    public function test_it_rejects_a_gap_between_bands(): void
    {
        // Units 75 to 100 would fall through and never be charged.
        $this->assertRejected([
            ['from_unit' => '0.000', 'to_unit' => '75.000'],
            ['from_unit' => '100.000', 'to_unit' => null],
        ], 'A gap silently drops units from every bill.');
    }

    public function test_it_rejects_overlapping_bands(): void
    {
        // Units 50 to 75 would be charged at both rates.
        $this->assertRejected([
            ['from_unit' => '0.000', 'to_unit' => '75.000'],
            ['from_unit' => '50.000', 'to_unit' => null],
        ], 'An overlap charges the same units twice.');
    }

    public function test_it_rejects_a_set_that_does_not_start_at_zero(): void
    {
        $this->assertRejected([
            ['from_unit' => '10.000', 'to_unit' => null],
        ], 'The first ten units would be free.');
    }

    public function test_it_rejects_a_bounded_last_band(): void
    {
        $this->assertRejected([
            ['from_unit' => '0.000', 'to_unit' => '75.000'],
            ['from_unit' => '75.000', 'to_unit' => '200.000'],
        ], 'A heavy user would stop being charged above 200 units.');
    }

    public function test_it_rejects_an_open_ended_band_before_the_end(): void
    {
        $this->assertRejected([
            ['from_unit' => '0.000', 'to_unit' => null],
            ['from_unit' => '75.000', 'to_unit' => '200.000'],
        ], 'An unbounded band swallows every later one.');
    }

    public function test_it_rejects_an_empty_set(): void
    {
        $this->assertRejected([], 'A tariff with no slabs prices nothing.');
    }

    public function test_it_throws_a_typed_exception_rather_than_returning_a_message(): void
    {
        $this->expectException(InvalidTariffException::class);

        $this->validator->assertValid([
            ['from_unit' => '0.000', 'to_unit' => '75.000'],
        ]);
    }
}
