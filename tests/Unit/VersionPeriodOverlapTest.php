<?php

namespace Tests\Unit;

use App\Services\Leave\VersionPeriodOverlap;
use PHPUnit\Framework\TestCase;

class VersionPeriodOverlapTest extends TestCase
{
    public function test_effective_periods_are_inclusive_and_open_ends_overlap(): void
    {
        $overlap = new VersionPeriodOverlap;
        $this->assertTrue($overlap->overlaps('2026-01-01', '2026-06-30', '2026-06-30', '2026-12-31'));
        $this->assertFalse($overlap->overlaps('2026-01-01', '2026-06-30', '2026-07-01', null));
        $this->assertTrue($overlap->overlaps('2026-01-01', null, '2027-01-01', null));
        $this->assertFalse($overlap->overlaps('2027-01-01', null, '2026-01-01', '2026-12-31'));
        $versions = collect([(object) ['id' => 1, 'effective_from' => '2026-01-01', 'effective_to' => null], (object) ['id' => 2, 'effective_from' => '2027-01-01', 'effective_to' => null]]);
        $rows = $overlap->annotate($versions);
        $this->assertSame([2], $rows[0]->overlapping_ids);
        $this->assertSame([1], $rows[1]->overlapping_ids);
    }
}
