<?php

namespace Tests\Feature\Employee;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class EmployeeEditTest extends TestCase
{
    use DatabaseTransactions;

    public function test_employee_updates_preserve_assignment_dates_and_require_permission(): void
    {
        $this->assertGreaterThan(0, DB::transactionLevel());
        $suffix = Str::lower(Str::random(16));
        $unit = DB::table('organization_units')->insertGetId(['code' => 'u'.$suffix, 'name' => 'Unit', 'active' => true]);
        $category = DB::table('employment_categories')->insertGetId(['code' => 'c'.$suffix, 'name' => 'Category', 'active' => true]);
        $employee = DB::table('employees')->insertGetId(['employee_no' => 'e'.$suffix, 'display_name' => 'Synthetic', 'service_start' => '2026-01-01']);
        DB::table('employee_assignments')->insert(['employee_id' => $employee, 'unit_id' => $unit, 'category_id' => $category, 'effective_from' => '2026-02-01']);
        $actor = User::factory()->create(['username' => 'edit'.$suffix, 'email' => null, 'status' => 'ACTIVE']);
        $actor->forceFill(['two_factor_secret' => 'synthetic', 'two_factor_confirmed_at' => now()])->save();
        $actor->givePermissionTo('hr.employees.view');
        $payload = ['employee_no' => 'e'.$suffix, 'display_name' => 'Revised', 'service_start' => '2026-01-01', 'confirmation_date' => null, 'service_end' => null, 'user_id' => $actor->id];
        $this->actingAs($actor)->patch('/employees/'.$employee, $payload)->assertForbidden();
        $actor->givePermissionTo('hr.employees.manage');
        $this->patch('/employees/'.$employee, [...$payload, 'service_start' => '2026-03-01'])->assertSessionHasErrors('service_start');
        $this->patch('/employees/'.$employee, [...$payload, 'service_end' => '2026-01-15'])->assertSessionHasErrors('service_end');
        $this->assertDatabaseHas('employees', ['id' => $employee, 'display_name' => 'Synthetic', 'user_id' => null]);
        $this->patch('/employees/'.$employee, $payload)->assertRedirect();
        $this->assertDatabaseHas('employees', ['id' => $employee, 'display_name' => 'Revised', 'user_id' => null]);
        $this->assertDatabaseHas('audit_events', ['actor_id' => $actor->id, 'action_code' => 'hr.employee.updated', 'target_ref' => 'employee:'.$employee]);
        $this->patch('/employees/999999999', [...$payload, 'employee_no' => 'x'.$suffix])->assertNotFound();
        $actor->revokePermissionTo('hr.employees.manage');
        $this->patch('/employees/'.$employee, $payload)->assertForbidden();
        $this->assertGreaterThan(0, DB::transactionLevel());
    }
}
