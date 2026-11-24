<?php

namespace Tests\Feature\Employee;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EmployeeFoundationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_employee_and_initial_assignment_require_authority_and_are_created_atomically(): void
    {
        $this->assertSame('mysql', DB::connection()->getDriverName());
        $this->assertGreaterThan(0, DB::transactionLevel());
        $suffix = Str::lower(Str::random(16));
        $unit = DB::table('organization_units')->insertGetId(['code' => 'u'.$suffix, 'name' => 'Synthetic unit', 'active' => true]);
        $inactiveUnit = DB::table('organization_units')->insertGetId(['code' => 'i'.$suffix, 'name' => 'Inactive unit', 'active' => false]);
        $category = DB::table('employment_categories')->insertGetId(['code' => 'c'.$suffix, 'name' => 'Synthetic category', 'active' => true]);
        $actor = User::factory()->create(['username' => 'emp'.$suffix, 'email' => null, 'status' => 'ACTIVE']);
        $actor->forceFill(['two_factor_secret' => 'synthetic', 'two_factor_confirmed_at' => now()])->save();
        $payload = ['employee_no' => 'e'.$suffix, 'display_name' => 'Synthetic employee', 'service_start' => '2026-01-01', 'effective_from' => '2026-01-02', 'unit_id' => $unit, 'category_id' => $category, 'user_id' => $actor->id];
        $this->actingAs($actor)->get('/employees')->assertForbidden();
        $actor->givePermissionTo('hr.employees.view');
        $this->get('/employees')->assertOk()->assertInertia(fn (Assert $page) => $page->component('employees/Index')->where('canCreate', false));
        $this->post('/employees', $payload)->assertForbidden();
        $actor->givePermissionTo('hr.employees.manage');
        $this->post('/employees', [...$payload, 'unit_id' => $inactiveUnit])->assertSessionHasErrors('unit_id');
        $this->assertDatabaseMissing('employees', ['employee_no' => 'e'.$suffix]);
        $this->post('/employees', [...$payload, 'effective_from' => '2025-12-31'])->assertSessionHasErrors('effective_from');
        $this->post('/employees', [...$payload, 'service_end' => '2025-12-31'])->assertSessionHasErrors('service_end');
        $this->post('/employees', [...$payload, 'confirmation_date' => '2026-03-01', 'service_end' => '2026-02-01'])->assertSessionHasErrors('service_end');
        $this->post('/employees', [...$payload, 'service_end' => '2026-12-31'])->assertRedirect();
        $employee = DB::table('employees')->where('employee_no', 'e'.$suffix)->sole();
        $this->assertNull($employee->user_id);
        $this->assertDatabaseHas('employee_assignments', ['employee_id' => $employee->id, 'unit_id' => $unit, 'category_id' => $category, 'effective_from' => '2026-01-02']);
        $this->assertDatabaseHas('audit_events', ['actor_id' => $actor->id, 'action_code' => 'hr.employee.created', 'target_ref' => 'employee:'.$employee->id]);
        $this->post('/employees', $payload)->assertSessionHasErrors('employee_no');
        $actor->revokePermissionTo('hr.employees.manage');
        $this->post('/employees', [...$payload, 'employee_no' => 'x'.$suffix])->assertForbidden();
        $this->app['env'] = 'production';
        try {
            $this->get('/employees')->assertNotFound();
        } finally {
            $this->app['env'] = 'testing';
        }
        $this->assertGreaterThan(0, DB::transactionLevel());
    }
}
