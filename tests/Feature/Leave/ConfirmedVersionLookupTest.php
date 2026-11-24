<?php

namespace Tests\Feature\Leave;

use App\Models\User;
use App\Services\Leave\ConfirmedVersionLookup;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class ConfirmedVersionLookupTest extends TestCase
{
    use DatabaseTransactions;

    public function test_lookup_rejects_missing_incomplete_ambiguous_and_inconsistent_versions(): void
    {
        $this->assertGreaterThan(0, DB::transactionLevel());
        $suffix = Str::lower(Str::random(16));
        $actor = User::factory()->create(['username' => 'lookup'.$suffix, 'email' => null, 'status' => 'ACTIVE']);
        $service = app(ConfirmedVersionLookup::class);
        $fails = function (callable $operation): void {
            try {
                $operation();
                $this->fail('Expected fail-closed lookup.');
            } catch (RuntimeException) {
                $this->assertTrue(true);
            }
        };
        $fails(fn () => $service->workingScheduleFor('2099-03-01'));
        $policy = DB::table('leave_policy_versions')->insertGetId(['label' => 'Synthetic '.$suffix, 'effective_from' => '2099-01-01', 'effective_to' => '2099-12-31', 'schedule_simulated_by' => $actor->id, 'schedule_simulated_at' => now()]);
        $fails(fn () => $service->workingScheduleFor('2099-03-01'));
        DB::table('leave_policy_versions')->where('id', $policy)->update(['working_weekdays' => '[1,2,3,4,5]', 'work_start' => '08:00', 'session_boundary' => '13:00', 'work_end' => '17:00']);
        $this->assertSame([1, 2, 3, 4, 5], $service->workingScheduleFor('2099-03-01')->working_weekdays);
        $second = DB::table('leave_policy_versions')->insertGetId(['label' => 'Other '.$suffix, 'effective_from' => '2099-01-01', 'effective_to' => '2099-12-31', 'schedule_simulated_by' => $actor->id, 'schedule_simulated_at' => now()]);
        $fails(fn () => $service->workingScheduleFor('2099-03-01'));
        DB::table('leave_policy_versions')->where('id', $second)->update(['schedule_simulated_at' => null, 'schedule_simulated_by' => null, 'cancelled_at' => now(), 'cancelled_by' => $actor->id]);
        $this->assertSame((int) $policy, (int) $service->workingScheduleFor('2099-03-01')->id);
        DB::table('leave_policy_versions')->where('id', $second)->update(['cancelled_by' => null]);
        $fails(fn () => $service->workingScheduleFor('2099-03-01'));
        DB::table('leave_policy_versions')->where('id', $second)->update(['cancelled_by' => $actor->id]);
        $calendar = DB::table('leave_calendar_versions')->insertGetId(['label' => 'Synthetic calendar '.$suffix, 'effective_from' => '2099-01-01', 'effective_to' => '2099-12-31', 'confirmed_by' => $actor->id, 'confirmed_at' => now()]);
        $holiday = DB::table('leave_calendar_days')->insertGetId(['calendar_version_id' => $calendar, 'date' => '2099-03-01', 'name' => 'Synthetic', 'kind' => 'holiday']);
        $replacement = DB::table('leave_calendar_days')->insertGetId(['calendar_version_id' => $calendar, 'date' => '2099-03-02', 'name' => 'Synthetic replacement', 'kind' => 'replacement', 'original_day_id' => $holiday]);
        $result = $service->calendarFor('2099-03-01');
        $this->assertEqualsCanonicalizing(['2099-03-01', '2099-03-02'], $result['holidays']);
        DB::table('leave_calendar_days')->where('id', $replacement)->update(['cancelled_at' => now(), 'cancelled_by' => $actor->id]);
        $this->assertSame(['2099-03-01'], $service->calendarFor('2099-03-01')['holidays']);
        DB::table('leave_calendar_days')->where('id', $replacement)->update(['cancelled_by' => null]);
        $fails(fn () => $service->calendarFor('2099-03-01'));
        DB::table('leave_calendar_days')->where('id', $replacement)->update(['cancelled_by' => $actor->id]);
        DB::table('leave_calendar_days')->where('id', $holiday)->update(['cancelled_at' => now(), 'cancelled_by' => $actor->id]);
        DB::table('leave_calendar_days')->where('id', $replacement)->update(['cancelled_at' => null, 'cancelled_by' => null]);
        $fails(fn () => $service->calendarFor('2099-03-01'));
        $fails(fn () => $service->calendarFor('2099-02-30'));
        $this->app['env'] = 'production';
        try {
            $fails(fn () => $service->workingScheduleFor('2099-03-01'));
        } finally {
            $this->app['env'] = 'testing';
        }
        $this->assertGreaterThan(0, DB::transactionLevel());
    }
}
