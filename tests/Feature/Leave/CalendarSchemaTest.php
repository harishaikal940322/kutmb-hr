<?php

namespace Tests\Feature\Leave;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CalendarSchemaTest extends TestCase
{
    use DatabaseTransactions;

    public function test_calendar_dates_are_unique_per_version_and_history_is_restricted(): void
    {
        $this->assertSame('mysql', DB::connection()->getDriverName());
        $this->assertGreaterThan(0, DB::transactionLevel());
        $first = DB::table('leave_calendar_versions')->insertGetId(['label' => 'Synthetic version', 'effective_from' => '2026-01-01']);
        $second = DB::table('leave_calendar_versions')->insertGetId(['label' => 'Synthetic revision', 'effective_from' => '2026-01-01']);
        $day = DB::table('leave_calendar_days')->insertGetId(['calendar_version_id' => $first, 'date' => '2026-03-01', 'name' => 'Synthetic holiday', 'kind' => 'holiday']);
        DB::table('leave_calendar_days')->insert(['calendar_version_id' => $first, 'date' => '2026-03-02', 'name' => 'Synthetic replacement', 'kind' => 'replacement', 'original_day_id' => $day]);
        DB::table('leave_calendar_days')->insert(['calendar_version_id' => $second, 'date' => '2026-03-01', 'name' => 'Synthetic revision', 'kind' => 'holiday']);
        foreach ([
            fn () => DB::table('leave_calendar_days')->insert(['calendar_version_id' => $first, 'date' => '2026-03-01', 'name' => 'Duplicate', 'kind' => 'holiday']),
            fn () => DB::table('leave_calendar_days')->where('id', $day)->delete(),
            fn () => DB::table('leave_calendar_versions')->where('id', $first)->delete(),
        ] as $operation) {
            try {
                $operation();
                $this->fail('Expected database constraint rejection.');
            } catch (QueryException $exception) {
                $this->assertSame('23000', $exception->errorInfo[0]);
            }
        }
        $this->assertGreaterThan(0, DB::transactionLevel());
    }
}
