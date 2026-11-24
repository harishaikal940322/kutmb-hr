<?php

namespace Tests\Feature\Leave;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CalendarDraftTest extends TestCase
{
    use DatabaseTransactions;

    public function test_calendar_drafts_require_permission_validate_sources_and_remain_unconfirmed(): void
    {
        $this->assertGreaterThan(0, DB::transactionLevel());
        $suffix = Str::lower(Str::random(16));
        $actor = User::factory()->create(['username' => 'cal'.$suffix, 'email' => null, 'status' => 'ACTIVE']);
        $actor->forceFill(['two_factor_secret' => 'synthetic', 'two_factor_confirmed_at' => now()])->save();
        $versionData = ['label' => 'Synthetic calendar '.$suffix, 'effective_from' => '2026-01-01', 'effective_to' => '2026-12-31'];
        $this->actingAs($actor)->get('/leave/calendar-drafts')->assertForbidden();
        $actor->givePermissionTo('leave.calendar.manage');
        $this->get('/leave/calendar-drafts')->assertOk()->assertInertia(fn (Assert $page) => $page->component('leave/CalendarDrafts'));
        $this->post('/leave/calendar-drafts', [...$versionData, 'effective_to' => '2025-01-01'])->assertSessionHasErrors('effective_to');
        $this->post('/leave/calendar-drafts', $versionData)->assertRedirect();
        $version = DB::table('leave_calendar_versions')->where('label', $versionData['label'])->sole();
        $this->assertNull($version->confirmed_at);
        $this->assertNull($version->confirmed_by);
        $this->assertDatabaseHas('audit_events', ['actor_id' => $actor->id, 'action_code' => 'leave.calendar.draft_created', 'target_ref' => 'calendar_version:'.$version->id]);
        $holiday = ['date' => '2026-03-01', 'name' => 'Synthetic holiday', 'kind' => 'holiday', 'original_day_id' => null];
        $path = '/leave/calendar-drafts/'.$version->id.'/days';
        $this->post($path, [...$holiday, 'date' => '2027-01-01'])->assertSessionHasErrors('date');
        $this->post($path, $holiday)->assertRedirect();
        $day = DB::table('leave_calendar_days')->where('calendar_version_id', $version->id)->sole();
        $this->assertDatabaseHas('audit_events', ['actor_id' => $actor->id, 'action_code' => 'leave.calendar.day_drafted', 'target_ref' => 'calendar_day:'.$day->id]);
        $this->post($path, $holiday)->assertSessionHasErrors('date');
        $this->post($path, ['date' => '2026-03-02', 'name' => 'Replacement', 'kind' => 'replacement', 'original_day_id' => null])->assertSessionHasErrors('original_day_id');
        $this->post($path, ['date' => '2026-03-02', 'name' => 'Replacement', 'kind' => 'replacement', 'original_day_id' => $day->id])->assertRedirect();
        $replacement = DB::table('leave_calendar_days')->where('calendar_version_id', $version->id)->where('kind', 'replacement')->sole();
        $cancelHoliday = $path.'/'.$day->id.'/cancellation';
        $cancelReplacement = $path.'/'.$replacement->id.'/cancellation';
        $this->patch($cancelHoliday, ['cancelled' => true])->assertSessionHasErrors('cancelled');
        $this->patch($cancelReplacement, ['cancelled' => true])->assertRedirect();
        $this->patch($cancelHoliday, ['cancelled' => true])->assertRedirect();
        $this->assertDatabaseHas('audit_events', ['action_code' => 'leave.calendar.day_cancelled', 'target_ref' => 'calendar_day:'.$day->id]);
        $this->post($path, $holiday)->assertSessionHasErrors('date');
        $this->patch($cancelReplacement, ['cancelled' => false])->assertSessionHasErrors('cancelled');
        $this->patch($cancelHoliday, ['cancelled' => false])->assertRedirect();
        $this->patch($cancelReplacement, ['cancelled' => false])->assertRedirect();
        $this->assertDatabaseHas('audit_events', ['action_code' => 'leave.calendar.day_restored', 'target_ref' => 'calendar_day:'.$day->id]);
        $other = DB::table('leave_calendar_versions')->insertGetId(['label' => 'Other synthetic '.$suffix, 'effective_from' => '2026-01-01']);
        $this->get('/leave/calendar-drafts')->assertOk()->assertInertia(fn (Assert $page) => $page->where('versions.0.overlapping_ids', [$version->id]));
        $this->post('/leave/calendar-drafts/'.$other.'/days', ['date' => '2026-03-02', 'name' => 'Invalid', 'kind' => 'replacement', 'original_day_id' => $day->id])->assertSessionHasErrors('original_day_id');
        $editPath = $path.'/'.$day->id;
        $this->patch($editPath, ['date' => '2026-03-02', 'name' => 'Collision'])->assertSessionHasErrors('date');
        $this->assertDatabaseMissing('leave_calendar_day_history', ['calendar_day_id' => $day->id]);
        $this->patch($editPath, ['date' => '2026-03-04', 'name' => 'Revised'])->assertRedirect();
        $this->assertDatabaseHas('leave_calendar_day_history', ['calendar_day_id' => $day->id, 'previous_date' => '2026-03-01', 'previous_name' => 'Synthetic holiday', 'actor_id' => $actor->id]);
        $this->assertDatabaseHas('leave_calendar_days', ['id' => $day->id, 'date' => '2026-03-04', 'name' => 'Revised']);
        $this->assertDatabaseHas('audit_events', ['action_code' => 'leave.calendar.day_updated', 'target_ref' => 'calendar_day:'.$day->id]);
        $this->patch('/leave/calendar-drafts/'.$other.'/days/'.$day->id, ['date' => '2026-03-04', 'name' => 'Invalid'])->assertNotFound();
        DB::table('leave_calendar_versions')->where('id', $version->id)->update(['confirmed_at' => now(), 'confirmed_by' => $actor->id]);
        $this->patch($editPath, ['date' => '2026-03-05', 'name' => 'Denied'])->assertSessionHasErrors('date');
        $this->patch($cancelHoliday, ['cancelled' => true])->assertSessionHasErrors('cancelled');
        $this->post($path, [...$holiday, 'date' => '2026-04-01'])->assertSessionHasErrors('date');
        $this->app['env'] = 'production';
        try {
            $this->get('/leave/calendar-drafts')->assertNotFound();
        } finally {
            $this->app['env'] = 'testing';
        }
        $actor->revokePermissionTo('leave.calendar.manage');
        $this->post('/leave/calendar-drafts', $versionData)->assertForbidden();
        $this->assertGreaterThan(0, DB::transactionLevel());
    }
}
