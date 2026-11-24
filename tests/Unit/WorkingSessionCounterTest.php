<?php

namespace Tests\Unit;

use App\Services\Leave\WorkingSessionCounter;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class WorkingSessionCounterTest extends TestCase
{
    public function test_counts_explicit_working_half_days_without_floating_point(): void
    {
        $counter = new WorkingSessionCounter;
        $slots = [
            ['date' => '2026-03-02', 'session' => 'AM'],
            ['date' => '2026-03-02', 'session' => 'PM'],
            ['date' => '2026-03-03', 'session' => 'AM'],
            ['date' => '2026-03-07', 'session' => 'AM'],
            ['date' => '2026-03-08', 'session' => 'PM'],
        ];
        $this->assertSame(2, $counter->countUnits($slots, [1, 2, 3, 4, 5], ['2026-03-03']));
        $this->assertSame(1, $counter->countUnits([['date' => '2026-03-02', 'session' => 'PM']], [1, 2, 3, 4, 5], []));
        $this->assertSame(1, $counter->countUnits([['date' => '2026-03-07', 'session' => 'AM']], [6], []));
    }

    public function test_missing_calendar_or_schedule_fails_closed(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new WorkingSessionCounter)->countUnits([], [1, 2, 3, 4, 5], null);
    }

    public function test_duplicate_session_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new WorkingSessionCounter)->countUnits([['date' => '2026-03-02', 'session' => 'AM'], ['date' => '2026-03-02', 'session' => 'AM']], [1], []);
    }

    public function test_invalid_date_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new WorkingSessionCounter)->countUnits([['date' => '2026-02-30', 'session' => 'AM']], [1], []);
    }
}
