<?php

namespace Tests\Feature\Employee;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ApprovalOfficeTest extends TestCase
{
    use DatabaseTransactions;

    public function test_office_appointments_are_independent_scoped_and_nonoverlapping(): void
    {
        $this->assertGreaterThan(0, DB::transactionLevel());
        $suffix = Str::lower(Str::random(16));
        $unit = DB::table('organization_units')->insertGetId(['code' => 'u'.$suffix, 'name' => 'Synthetic unit', 'active' => true]);
        $actor = User::factory()->create(['username' => 'office'.$suffix, 'email' => null, 'status' => 'ACTIVE']);
        $actor->forceFill(['two_factor_secret' => 'synthetic', 'two_factor_confirmed_at' => now()])->save();
        $target = User::factory()->create(['username' => 'holder'.$suffix, 'email' => null, 'status' => 'ACTIVE']);
        $other = User::factory()->create(['username' => 'other'.$suffix, 'email' => null, 'status' => 'ACTIVE']);
        $payload = ['user_id' => $target->id, 'office_code' => 'PU', 'unit_id' => $unit, 'effective_from' => '2026-01-01', 'effective_to' => null];
        $this->actingAs($actor)->get('/organization/offices')->assertForbidden();
        $actor->givePermissionTo('hr.offices.manage');
        $this->get('/organization/offices')->assertOk()->assertInertia(fn (Assert $page) => $page->component('organization/Offices'));
        $this->post('/organization/offices', [...$payload, 'user_id' => $actor->id])->assertSessionHasErrors('user_id');
        $this->post('/organization/offices', [...$payload, 'unit_id' => null])->assertSessionHasErrors('unit_id');
        $this->post('/organization/offices', [...$payload, 'office_code' => 'PB'])->assertSessionHasErrors('unit_id');
        $this->post('/organization/offices', $payload)->assertRedirect();
        $office = DB::table('approval_assignments')->where('user_id', $target->id)->where('office_code', 'PU')->sole();
        $this->assertDatabaseHas('audit_events', ['actor_id' => $actor->id, 'action_code' => 'hr.office.created', 'target_ref' => 'office_assignment:'.$office->id]);
        $this->post('/organization/offices', [...$payload, 'user_id' => $other->id, 'effective_from' => '2026-06-01'])->assertSessionHasErrors('effective_from');
        $this->patch('/organization/offices/'.$office->id.'/end', ['effective_to' => '2025-12-31'])->assertSessionHasErrors('effective_to');
        $this->patch('/organization/offices/'.$office->id.'/end', ['effective_to' => '2026-06-30'])->assertRedirect();
        $this->assertDatabaseHas('approval_assignments', ['id' => $office->id, 'effective_to' => '2026-06-30']);
        $this->post('/organization/offices', [...$payload, 'user_id' => $other->id, 'effective_from' => '2026-06-30'])->assertSessionHasErrors('effective_from');
        $this->post('/organization/offices', [...$payload, 'user_id' => $other->id, 'effective_from' => '2026-07-01'])->assertRedirect();
        $this->post('/organization/offices', ['user_id' => $target->id, 'office_code' => 'SECRETARY_ALK', 'unit_id' => null, 'effective_from' => '2026-01-01'])->assertRedirect();
        $this->assertDatabaseHas('approval_assignments', ['user_id' => $target->id, 'office_code' => 'SECRETARY_ALK', 'unit_id' => null]);
        $this->assertDatabaseMissing('employees', ['user_id' => $target->id]);
        $self = DB::table('approval_assignments')->insertGetId(['user_id' => $actor->id, 'office_code' => 'PB', 'effective_from' => '2026-01-01']);
        $this->patch('/organization/offices/'.$self.'/end', ['effective_to' => '2026-12-31'])->assertSessionHasErrors('effective_to');
        $this->app['env'] = 'production';
        try {
            $this->get('/organization/offices')->assertNotFound();
        } finally {
            $this->app['env'] = 'testing';
        }
        $actor->revokePermissionTo('hr.offices.manage');
        $this->post('/organization/offices', $payload)->assertForbidden();
        $this->assertGreaterThan(0, DB::transactionLevel());
    }
}
