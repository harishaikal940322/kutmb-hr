<?php

namespace Tests\Feature\Leave;

use App\Models\User;
use App\Services\Leave\TechnicalSessionSimulation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class TechnicalSessionSimulationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_simulation_requires_confirmed_versions_for_each_date_and_counts_half_days(): void
    {
        $this->assertGreaterThan(0, DB::transactionLevel());
        $suffix = Str::lower(Str::random(16));
        $actor = User::factory()->create(['username' => 'sim'.$suffix, 'email' => null, 'status' => 'ACTIVE']);
        $policy = ['label' => 'Synthetic '.$suffix, 'effective_from' => '2099-01-01', 'effective_to' => '2099-12-31', 'schedule_simulated_by' => $actor->id, 'schedule_simulated_at' => now(), 'working_weekdays' => '[1,2,3,4,5]', 'work_start' => '08:00', 'session_boundary' => '13:00', 'work_end' => '17:00'];
        DB::table('leave_policy_versions')->insert($policy);
        $calendar = DB::table('leave_calendar_versions')->insertGetId(['label' => 'Synthetic '.$suffix, 'effective_from' => '2099-01-01', 'effective_to' => '2099-12-31', 'confirmed_by' => $actor->id, 'confirmed_at' => now()]);
        DB::table('leave_calendar_days')->insert(['calendar_version_id' => $calendar, 'date' => '2099-03-03', 'name' => 'Synthetic holiday', 'kind' => 'holiday']);
        $slots = [['date' => '2099-03-02', 'session' => 'AM'], ['date' => '2099-03-02', 'session' => 'PM'], ['date' => '2099-03-03', 'session' => 'AM'], ['date' => '2099-03-07', 'session' => 'PM']];
        $simulation = app(TechnicalSessionSimulation::class);
        $this->assertSame(2, $simulation->countUnits($slots));
        $this->assertSame(1, $simulation->countUnits([['date' => '2099-03-02', 'session' => 'PM']]));
        try {
            $simulation->countUnits([...$slots, ['date' => '2100-01-01', 'session' => 'AM']]);
            $this->fail('A missing version must fail closed for the whole simulation.');
        } catch (RuntimeException) {
            $this->assertTrue(true);
        }
        $this->app['env'] = 'production';
        try {
            $simulation->countUnits([]);
            $this->fail('Production simulation must be unavailable.');
        } catch (RuntimeException) {
            $this->assertTrue(true);
        } finally {
            $this->app['env'] = 'testing';
        }
        $this->assertGreaterThan(0, DB::transactionLevel());
    }
}
