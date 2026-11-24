<?php

namespace Tests\Feature\Employee;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EmployeeSelfProfileTest extends TestCase
{
    use DatabaseTransactions;

    public function test_only_explicitly_linked_employee_can_view_own_current_profile(): void
    {
        $this->assertGreaterThan(0, DB::transactionLevel());
        $suffix = Str::lower(Str::random(16));
        $user = User::factory()->create(['username' => 'self'.$suffix, 'email' => null, 'status' => 'ACTIVE']);
        $user->forceFill(['two_factor_secret' => 'synthetic', 'two_factor_confirmed_at' => now()])->save();
        $this->actingAs($user)->get('/employee-profile')->assertForbidden();
        $user->givePermissionTo('hr.profile.self.view');
        $this->get('/employee-profile')->assertNotFound();
        $unit = DB::table('organization_units')->insertGetId(['code' => 'u'.$suffix, 'name' => 'Synthetic unit', 'active' => true]);
        $category = DB::table('employment_categories')->insertGetId(['code' => 'c'.$suffix, 'name' => 'Synthetic category', 'active' => true]);
        $employee = DB::table('employees')->insertGetId(['employee_no' => 'e'.$suffix, 'display_name' => 'Own synthetic profile', 'user_id' => $user->id, 'service_start' => '2020-01-01']);
        DB::table('employees')->insert(['employee_no' => 'f'.$suffix, 'display_name' => 'Other synthetic profile', 'service_start' => '2020-01-01']);
        DB::table('employee_assignments')->insert(['employee_id' => $employee, 'unit_id' => $unit, 'category_id' => $category, 'effective_from' => '2020-01-01']);
        $this->get('/employee-profile')->assertOk()->assertInertia(fn (Assert $page) => $page->component('employees/SelfProfile')->where('employee.display_name', 'Own synthetic profile')->where('assignment.unit', 'Synthetic unit'));
        DB::table('employee_assignments')->insert(['employee_id' => $employee, 'unit_id' => $unit, 'category_id' => $category, 'effective_from' => '2020-02-01']);
        $this->get('/employee-profile')->assertOk()->assertInertia(fn (Assert $page) => $page->where('assignment', null));
        $user->revokePermissionTo('hr.profile.self.view');
        $this->get('/employee-profile')->assertForbidden();
        $this->assertGreaterThan(0, DB::transactionLevel());
    }
}
