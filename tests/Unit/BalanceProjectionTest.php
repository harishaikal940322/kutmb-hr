<?php

namespace Tests\Unit;

use App\Services\Leave\BalanceProjection;
use InvalidArgumentException;
use OverflowException;
use PHPUnit\Framework\TestCase;

class BalanceProjectionTest extends TestCase
{
    public function test_projection_consumes_annual_then_tabung_and_puts_deficit_in_annual(): void
    {
        $result = (new BalanceProjection)->project(4, 6, 0, 0, 16);
        $this->assertSame(4, $result['annual_available']);
        $this->assertSame(6, $result['tabung_available']);
        $this->assertSame(10, $result['combined_available']);
        $this->assertSame(-6, $result['projected_annual']);
        $this->assertSame(0, $result['projected_tabung']);
    }

    public function test_already_allocated_pending_holds_are_deducted_once(): void
    {
        $result = (new BalanceProjection)->project(4, 6, 2, 2, 2);
        $this->assertSame(2, $result['annual_available']);
        $this->assertSame(4, $result['tabung_available']);
        $this->assertSame(6, $result['combined_available']);
        $this->assertSame(0, $result['projected_annual']);
        $this->assertSame(4, $result['projected_tabung']);
    }

    public function test_existing_negative_annual_does_not_make_tabung_negative(): void
    {
        $result = (new BalanceProjection)->project(-4, 6, 0, 0, 2);
        $this->assertSame(-4, $result['projected_annual']);
        $this->assertSame(4, $result['projected_tabung']);
    }

    public function test_invalid_pending_tabung_fails_closed(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new BalanceProjection)->project(4, 2, 0, 3, 0);
    }

    public function test_integer_underflow_is_rejected(): void
    {
        $this->expectException(OverflowException::class);
        (new BalanceProjection)->project(PHP_INT_MIN, 0, 1, 0, 0);
    }
}
