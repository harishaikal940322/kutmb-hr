<?php

namespace Tests\Feature\Leave;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PolicyDraftTest extends TestCase
{
    use DatabaseTransactions;

    public function test_policy_drafts_preserve_missing_values_and_validate_explicit_settings(): void
    {
        $this->assertGreaterThan(0, DB::transactionLevel());
        $suffix = Str::lower(Str::random(16));
        $actor = User::factory()->create(['username' => 'policy'.$suffix, 'email' => null, 'status' => 'ACTIVE']);
        $actor->forceFill(['two_factor_secret' => 'synthetic', 'two_factor_confirmed_at' => now()])->save();
        $base = ['label' => 'Synthetic policy '.$suffix, 'effective_from' => '2026-01-01', 'effective_to' => null, 'working_weekdays' => null, 'work_start' => null, 'session_boundary' => null, 'work_end' => null];
        $this->actingAs($actor)->get('/leave/policy-drafts')->assertForbidden();
        $actor->givePermissionTo('leave.policy.manage');
        $this->get('/leave/policy-drafts')->assertOk()->assertInertia(fn (Assert $page) => $page->component('leave/PolicyDrafts'));
        $this->post('/leave/policy-drafts', [...$base, 'working_weekdays' => [1, 1]])->assertSessionHasErrors('working_weekdays.1');
        $this->post('/leave/policy-drafts', [...$base, 'work_start' => '13:00', 'session_boundary' => '08:00'])->assertSessionHasErrors('session_boundary');
        $this->post('/leave/policy-drafts', [...$base, 'effective_to' => '2025-01-01'])->assertSessionHasErrors('effective_to');
        $this->post('/leave/policy-drafts', $base)->assertRedirect();
        $draft = DB::table('leave_policy_versions')->where('label', $base['label'])->sole();
        $this->assertNull($draft->working_weekdays);
        $this->assertNull($draft->work_start);
        $this->assertNull($draft->confirmed_at);
        $this->assertNull($draft->confirmed_by);
        $this->assertDatabaseHas('audit_events', ['actor_id' => $actor->id, 'action_code' => 'leave.policy.draft_created', 'target_ref' => 'policy_version:'.$draft->id]);
        $this->post('/leave/policy-drafts', [...$base, 'label' => 'Configured '.$suffix, 'working_weekdays' => [5, 1], 'work_start' => '08:00', 'session_boundary' => '13:00', 'work_end' => '17:00'])->assertRedirect();
        $configured = DB::table('leave_policy_versions')->where('label', 'Configured '.$suffix)->sole();
        $this->assertSame([1, 5], json_decode($configured->working_weekdays, true));
        $this->assertNull($configured->confirmed_at);
        $this->app['env'] = 'production';
        try {
            $this->get('/leave/policy-drafts')->assertNotFound();
        } finally {
            $this->app['env'] = 'testing';
        }
        $actor->revokePermissionTo('leave.policy.manage');
        $this->post('/leave/policy-drafts', $base)->assertForbidden();
        $this->assertGreaterThan(0, DB::transactionLevel());
    }
}
