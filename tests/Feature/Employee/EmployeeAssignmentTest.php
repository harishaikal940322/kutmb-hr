<?php

namespace Tests\Feature\Employee;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class EmployeeAssignmentTest extends TestCase
{
    use DatabaseTransactions;

    public function test_transfer_preserves_history_and_rejects_overlap_and_unauthorized_access(): void
    {
        $this->assertSame('mysql', DB::connection()->getDriverName());
        $this->assertGreaterThan(0, DB::transactionLevel());
        $suffix = Str::lower(Str::random(16));
        $unit = DB::table('organization_units')->insertGetId(['code' => 'u'.$suffix, 'name' => 'Unit', 'active' => true]);
        $unit2 = DB::table('organization_units')->insertGetId(['code' => 'v'.$suffix, 'name' => 'Unit 2', 'active' => true]);
        $category = DB::table('employment_categories')->insertGetId(['code' => 'c'.$suffix, 'name' => 'Category', 'active' => true]);
        $employee = DB::table('employees')->insertGetId(['employee_no' => 'e'.$suffix, 'display_name' => 'Synthetic', 'service_start' => '2026-01-01', 'service_end' => '2026-12-31']);
        $assignment = DB::table('employee_assignments')->insertGetId(['employee_id' => $employee, 'unit_id' => $unit, 'category_id' => $category, 'effective_from' => '2026-01-01']);
        $actor = User::factory()->create(['username' => 'assign'.$suffix, 'email' => null, 'status' => 'ACTIVE']);
        $actor->forceFill(['two_factor_secret' => 'synthetic', 'two_factor_confirmed_at' => now()])->save();
        $actor->givePermissionTo('hr.employees.view');
        $payload = ['unit_id' => $unit2, 'category_id' => $category, 'effective_from' => '2026-05-01'];
        $this->actingAs($actor)->post('/employees/'.$employee.'/assignments', $payload)->assertForbidden();
        $actor->givePermissionTo('hr.assignments.manage');
        $this->post('/employees/'.$employee.'/assignments', [...$payload, 'effective_from' => '2026-01-01'])->assertSessionHasErrors('effective_from');
        $this->post('/employees/'.$employee.'/assignments', [...$payload, 'effective_from' => '2027-01-01'])->assertSessionHasErrors('effective_from');
        $this->post('/employees/'.$employee.'/assignments', [...$payload, 'unit_id' => 999999999])->assertSessionHasErrors('unit_id');
        $this->assertDatabaseHas('employee_assignments', ['id' => $assignment, 'effective_to' => null]);
        $this->post('/employees/'.$employee.'/assignments', $payload)->assertRedirect();
        $this->assertDatabaseHas('employee_assignments', ['id' => $assignment, 'effective_to' => '2026-04-30']);
        $this->assertDatabaseHas('employee_assignments', ['employee_id' => $employee, 'unit_id' => $unit2, 'effective_from' => '2026-05-01', 'effective_to' => null]);
        $this->assertDatabaseHas('audit_events', ['actor_id' => $actor->id, 'action_code' => 'hr.assignment.created', 'target_ref' => 'employee:'.$employee]);
        $this->post('/employees/'.$employee.'/assignments', $payload)->assertSessionHasErrors('effective_from');
        $this->assertSame(2, DB::table('employee_assignments')->where('employee_id', $employee)->count());
        $actor->revokePermissionTo('hr.assignments.manage');
        $this->post('/employees/'.$employee.'/assignments', [...$payload, 'effective_from' => '2026-06-01'])->assertForbidden();
        $this->assertGreaterThan(0, DB::transactionLevel());
    }
}
