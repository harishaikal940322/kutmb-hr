<?php

namespace Tests\Feature\Employee;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OrganizationReferenceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_reference_maintenance_is_guarded_validated_and_audited(): void
    {
        $this->assertSame('mysql', DB::connection()->getDriverName());
        $this->assertGreaterThan(0, DB::transactionLevel());
        $suffix = Str::lower(Str::random(16));
        $actor = User::factory()->create(['username' => 'org'.$suffix, 'email' => null, 'status' => 'ACTIVE']);
        $actor->forceFill(['two_factor_secret' => 'synthetic', 'two_factor_confirmed_at' => now()])->save();
        $this->actingAs($actor)->get('/organization/references')->assertForbidden();
        $actor->givePermissionTo('hr.organization.manage');
        $this->get('/organization/references')->assertOk()->assertInertia(fn (Assert $page) => $page->component('organization/References'));
        $this->post('/organization/references/units', ['code' => 'u'.$suffix, 'name' => 'Synthetic unit', 'active' => true])->assertRedirect();
        $unit = DB::table('organization_units')->where('code', 'u'.$suffix)->sole();
        $this->assertDatabaseHas('audit_events', ['actor_id' => $actor->id, 'action_code' => 'hr.organization.created', 'target_ref' => 'units:'.$unit->id]);
        $this->post('/organization/references/categories', ['code' => 'c'.$suffix, 'name' => 'Synthetic category', 'active' => true])->assertRedirect();
        $this->patch('/organization/references/units/'.$unit->id, ['code' => 'u'.$suffix, 'name' => 'Updated unit', 'active' => false])->assertRedirect();
        $this->assertDatabaseHas('organization_units', ['id' => $unit->id, 'name' => 'Updated unit', 'active' => false]);
        $this->assertDatabaseHas('audit_events', ['actor_id' => $actor->id, 'action_code' => 'hr.organization.updated', 'target_ref' => 'units:'.$unit->id]);
        $this->post('/organization/references/units', ['code' => 'u'.$suffix, 'name' => 'Duplicate', 'active' => true])->assertSessionHasErrors('code');
        $this->post('/organization/references/units', ['code' => ' ', 'name' => ' ', 'active' => true])->assertSessionHasErrors(['code', 'name']);
        $this->patch('/organization/references/units/999999999', ['code' => 'x'.$suffix, 'name' => 'Missing', 'active' => true])->assertNotFound();
        $this->post('/organization/references/users', ['code' => 'x', 'name' => 'Invalid', 'active' => true])->assertNotFound();
        $actor->revokePermissionTo('hr.organization.manage');
        $this->post('/organization/references/units', ['code' => 'x'.$suffix, 'name' => 'Denied', 'active' => true])->assertForbidden();
        $actor->givePermissionTo('hr.organization.manage');
        $this->app['env'] = 'production';
        try {
            $this->get('/organization/references')->assertNotFound();
            $this->withSession(['_token' => 'synthetic-token'])->post('/organization/references/units', ['_token' => 'synthetic-token', 'code' => 'p'.$suffix, 'name' => 'Denied', 'active' => true])->assertNotFound();
        } finally {
            $this->app['env'] = 'testing';
        }
        $actor->update(['status' => 'INACTIVE']);
        $this->get('/organization/references')->assertRedirect();
        $this->assertGreaterThan(0, DB::transactionLevel());
    }
}
