<?php

namespace Tests\Feature\Leave;

use App\Models\User;
use App\Services\Leave\ConfirmedVersionLookup;
use App\Services\Leave\TechnicalSessionSimulation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class ScheduleSimulationConfirmationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_schedule_simulation_is_bounded_separate_from_entitlement_and_locked(): void
    {
        $this->assertGreaterThan(0, DB::transactionLevel());
        $suffix = Str::lower(Str::random(16));
        $actor = User::factory()->create(['username' => 'sched'.$suffix, 'email' => null, 'status' => 'ACTIVE']);
        $actor->forceFill(['two_factor_secret' => 'synthetic', 'two_factor_confirmed_at' => now()])->save();
        $version = DB::table('leave_policy_versions')->insertGetId(['label' => 'Synthetic '.$suffix, 'effective_from' => '2096-01-01', 'effective_to' => '2096-12-31']);
        $path = '/leave/policy-drafts/'.$version.'/simulate-schedule';
        $this->actingAs($actor)->post($path, ['acknowledge_schedule' => true])->assertForbidden();
        $actor->givePermissionTo('leave.policy.manage');
        $this->post($path, ['acknowledge_schedule' => false])->assertSessionHasErrors('acknowledge_schedule');
        $this->post($path, ['acknowledge_schedule' => true])->assertSessionHasErrors('acknowledge_schedule');
        $this->assertDatabaseHas('leave_policy_versions', ['id' => $version, 'schedule_simulated_at' => null]);
        DB::table('leave_policy_versions')->where('id', $version)->update(['working_weekdays' => '[1,2,3,4,5]', 'work_start' => '08:00', 'session_boundary' => '13:00', 'work_end' => '17:00']);
        $this->post($path, ['acknowledge_schedule' => true])->assertRedirect();
        $this->assertDatabaseHas('leave_policy_versions', ['id' => $version, 'schedule_simulated_by' => $actor->id, 'confirmed_by' => null, 'confirmed_at' => null]);
        $this->assertSame([1, 2, 3, 4, 5], app(ConfirmedVersionLookup::class)->workingScheduleFor('2096-03-01')->working_weekdays);
        DB::table('leave_calendar_versions')->insert(['label' => 'Synthetic calendar '.$suffix, 'effective_from' => '2096-01-01', 'effective_to' => '2096-12-31', 'confirmed_by' => $actor->id, 'confirmed_at' => now()]);
        $this->assertSame(1, app(TechnicalSessionSimulation::class)->countUnits([['date' => '2096-03-01', 'session' => 'AM']]));

        $this->assertDatabaseHas('audit_events', ['actor_id' => $actor->id, 'action_code' => 'leave.policy.schedule_simulated', 'target_ref' => 'policy_version:'.$version]);
        $this->patch('/leave/policy-drafts/'.$version, ['label' => 'Denied', 'effective_from' => '2096-01-01', 'working_weekdays' => null])->assertSessionHasErrors('label');
        $overlap = DB::table('leave_policy_versions')->insertGetId(['label' => 'Overlap '.$suffix, 'effective_from' => '2096-12-31', 'effective_to' => '2097-12-31']);
        $this->post('/leave/policy-drafts/'.$overlap.'/simulate-schedule', ['acknowledge_schedule' => true])->assertSessionHasErrors('acknowledge_schedule');
        Schema::shouldReceive('hasTable')->once()->with('leave_requests')->andReturn(true);
        $this->post('/leave/policy-drafts/'.$overlap.'/simulate-schedule', ['acknowledge_schedule' => true])->assertSessionHasErrors('acknowledge_schedule');
        $this->assertGreaterThan(0, DB::transactionLevel());
    }
}
