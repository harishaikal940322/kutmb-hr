<?php

namespace Tests\Feature\Leave;

use App\Models\User;
use App\Services\Leave\ConfirmedVersionLookup;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CalendarSimulationConfirmationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_simulation_confirmation_requires_authority_bounded_complete_nonoverlapping_draft(): void
    {
        $this->assertGreaterThan(0, DB::transactionLevel());
        $suffix = Str::lower(Str::random(16));
        $actor = User::factory()->create(['username' => 'calconfirm'.$suffix, 'email' => null, 'status' => 'ACTIVE']);
        $actor->forceFill(['two_factor_secret' => 'synthetic', 'two_factor_confirmed_at' => now()])->save();
        $version = DB::table('leave_calendar_versions')->insertGetId(['label' => 'Synthetic '.$suffix, 'effective_from' => '2098-01-01', 'effective_to' => '2098-12-31']);
        $path = '/leave/calendar-drafts/'.$version.'/simulate-confirmation';
        $this->actingAs($actor)->post($path, ['acknowledge_complete' => true])->assertForbidden();
        $actor->givePermissionTo('leave.calendar.manage');
        $this->post($path, ['acknowledge_complete' => false])->assertSessionHasErrors('acknowledge_complete');
        $open = DB::table('leave_calendar_versions')->insertGetId(['label' => 'Open '.$suffix, 'effective_from' => '2097-01-01']);
        $this->post('/leave/calendar-drafts/'.$open.'/simulate-confirmation', ['acknowledge_complete' => true])->assertSessionHasErrors('acknowledge_complete');
        $day = DB::table('leave_calendar_days')->insertGetId(['calendar_version_id' => $version, 'date' => '2098-03-01', 'name' => 'Synthetic day', 'kind' => 'holiday']);
        $this->post($path, ['acknowledge_complete' => true])->assertRedirect();
        $this->assertDatabaseHas('leave_calendar_versions', ['id' => $version, 'confirmed_by' => $actor->id]);
        $this->assertSame(['2098-03-01'], app(ConfirmedVersionLookup::class)->calendarFor('2098-03-01')['holidays']);
        $this->assertDatabaseHas('audit_events', ['actor_id' => $actor->id, 'action_code' => 'leave.calendar.simulation_confirmed', 'target_ref' => 'calendar_version:'.$version]);
        $this->patch('/leave/calendar-drafts/'.$version.'/days/'.$day, ['date' => '2098-03-02', 'name' => 'Denied'])->assertSessionHasErrors('date');
        $snapshotCalendar = DB::table('leave_calendar_versions')->insertGetId(['label' => 'Snapshot calendar '.$suffix, 'effective_from' => '2100-01-01', 'effective_to' => '2100-12-31']);
        $snapshotPolicy = DB::table('leave_policy_versions')->insertGetId(['label' => 'Snapshot policy '.$suffix, 'effective_from' => '2100-01-01', 'effective_to' => '2100-12-31']);
        $employee = DB::table('employees')->insertGetId(['employee_no' => 'snap'.$suffix, 'display_name' => 'Synthetic snapshot subject', 'service_start' => '2026-01-01']);
        $marker = DB::table('leave_employee_history_markers')->insertGetId(['employee_id' => $employee, 'request_key' => 'snapshot-'.$suffix, 'revision' => 1, 'actor_id' => $actor->id]);
        DB::table('leave_calendar_usage_snapshots')->insert(['history_marker_id' => $marker, 'employee_id' => $employee, 'leave_date' => '2100-03-01', 'leave_units' => 1, 'calendar_version_id' => $snapshotCalendar, 'policy_version_id' => $snapshotPolicy]);
        $this->post('/leave/calendar-drafts/'.$snapshotCalendar.'/simulate-confirmation', ['acknowledge_complete' => true])->assertSessionHasErrors('acknowledge_complete');
        $this->assertGreaterThan(0, DB::transactionLevel());
    }
}
