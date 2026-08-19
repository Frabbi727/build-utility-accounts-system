<?php

namespace Tests\Unit\Support;

use App\Support\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_it_rounds_a_half_up(): void
    {
        $this->assertSame('1.01', Money::round('1.005'));
        $this->assertSame('3.00', Money::round('2.995'));
    }

    public function test_it_rounds_just_below_a_half_down(): void
    {
        $this->assertSame('1.00', Money::round('1.0049'));
        $this->assertSame('2.99', Money::round('2.9949999'));
    }

    public function test_it_leaves_an_exact_amount_alone(): void
    {
        $this->assertSame('0.00', Money::round('0'));
        $this->assertSame('1020.00', Money::round('1020.0000'));
        $this->assertSame('8.50', Money::round('8.50'));
    }

    public function test_it_rounds_at_other_scales(): void
    {
        $this->assertSame('1.2346', Money::round('1.23455', 4));
        $this->assertSame('2', Money::round('1.5', 0));
        $this->assertSame('1', Money::round('1.4999', 0));
    }

    public function test_it_refuses_a_negative_amount(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::round('-0.01');
    }

    public function test_it_refuses_a_negative_scale(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::round('1.00', -1);
    }
}
