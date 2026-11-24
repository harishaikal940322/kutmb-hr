<?php

namespace Tests\Feature\Leave;

use App\Models\User;
use App\Services\Leave\ConfirmedVersionLookup;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
        $overlap = DB::table('leave_calendar_versions')->insertGetId(['label' => 'Overlap '.$suffix, 'effective_from' => '2098-12-31', 'effective_to' => '2099-12-31']);
        $this->post('/leave/calendar-drafts/'.$overlap.'/simulate-confirmation', ['acknowledge_complete' => true])->assertSessionHasErrors('acknowledge_complete');
        Schema::shouldReceive('hasTable')->once()->with('leave_requests')->andReturn(true);
        $this->post('/leave/calendar-drafts/'.$overlap.'/simulate-confirmation', ['acknowledge_complete' => true])->assertSessionHasErrors('acknowledge_complete');
        $this->assertGreaterThan(0, DB::transactionLevel());
    }
}
