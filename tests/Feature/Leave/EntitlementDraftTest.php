<?php

namespace Tests\Feature\Leave;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class EntitlementDraftTest extends TestCase
{
    use DatabaseTransactions;

    public function test_entitlement_drafts_do_not_infer_rates_or_allow_confirmed_mutations(): void
    {
        $this->assertGreaterThan(0, DB::transactionLevel());
        $suffix = Str::lower(Str::random(16));
        $actor = User::factory()->create(['username' => 'rate'.$suffix, 'email' => null, 'status' => 'ACTIVE']);
        $actor->forceFill(['two_factor_secret' => 'synthetic', 'two_factor_confirmed_at' => now()])->save();
        $category = DB::table('employment_categories')->insertGetId(['code' => 'c'.$suffix, 'name' => 'Synthetic category', 'active' => true]);
        $inactive = DB::table('employment_categories')->insertGetId(['code' => 'i'.$suffix, 'name' => 'Inactive category', 'active' => false]);
        $version = DB::table('leave_policy_versions')->insertGetId(['label' => 'Synthetic policy '.$suffix, 'effective_from' => '2026-01-01']);
        $path = '/leave/policy-drafts/'.$version.'/entitlements';
        $payload = ['category_id' => $category, 'band_description' => 'Synthetic unspecified band', 'annual_half_day_units' => null];
        $this->actingAs($actor)->post($path, $payload)->assertForbidden();
        $actor->givePermissionTo('leave.policy.manage');
        $this->post($path, [...$payload, 'category_id' => $inactive])->assertSessionHasErrors('category_id');
        $this->post($path, [...$payload, 'annual_half_day_units' => -1])->assertSessionHasErrors('annual_half_day_units');
        $this->post($path, $payload)->assertRedirect();
        $draft = DB::table('leave_entitlement_rule_drafts')->where('policy_version_id', $version)->sole();
        $this->assertNull($draft->annual_half_day_units);
        $this->assertDatabaseHas('audit_events', ['actor_id' => $actor->id, 'action_code' => 'leave.entitlement.drafted', 'target_ref' => 'entitlement_draft:'.$draft->id]);
        $this->post($path, $payload)->assertSessionHasErrors('band_description');
        $this->post($path, [...$payload, 'band_description' => 'Synthetic zero', 'annual_half_day_units' => 0])->assertRedirect();
        $this->assertDatabaseHas('leave_entitlement_rule_drafts', ['policy_version_id' => $version, 'band_description' => 'Synthetic zero', 'annual_half_day_units' => 0]);
        $edit = $path.'/'.$draft->id;
        $this->patch($edit, [...$payload, 'band_description' => 'Synthetic zero', 'annual_half_day_units' => 0])->assertSessionHasErrors('band_description');
        $this->assertDatabaseMissing('leave_entitlement_draft_history', ['entitlement_draft_id' => $draft->id]);
        $this->patch($edit, [...$payload, 'band_description' => 'Synthetic revised', 'annual_half_day_units' => 12])->assertRedirect();
        $this->assertDatabaseHas('leave_entitlement_draft_history', ['entitlement_draft_id' => $draft->id, 'previous_band_description' => 'Synthetic unspecified band', 'previous_annual_half_day_units' => null, 'actor_id' => $actor->id]);
        $this->assertDatabaseHas('leave_entitlement_rule_drafts', ['id' => $draft->id, 'band_description' => 'Synthetic revised', 'annual_half_day_units' => 12]);
        $this->assertDatabaseHas('audit_events', ['action_code' => 'leave.entitlement.draft_updated', 'target_ref' => 'entitlement_draft:'.$draft->id]);
        $this->patch('/leave/policy-drafts/999999999/entitlements/'.$draft->id, $payload)->assertNotFound();
        DB::table('leave_policy_versions')->where('id', $version)->update(['confirmed_by' => $actor->id, 'confirmed_at' => now()]);
        $this->patch($edit, $payload)->assertSessionHasErrors('band_description');
        $this->post($path, [...$payload, 'band_description' => 'Denied'])->assertSessionHasErrors('band_description');
        $actor->revokePermissionTo('leave.policy.manage');
        $this->post($path, $payload)->assertForbidden();
        $this->assertGreaterThan(0, DB::transactionLevel());
    }
}
