<?php

namespace Tests\Feature\Employee;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EmployeeSearchTest extends TestCase
{
    use DatabaseTransactions;

    public function test_directory_search_is_authorized_and_limits_assignment_props_to_results(): void
    {
        $this->assertGreaterThan(0, DB::transactionLevel());
        $suffix = Str::lower(Str::random(16));
        $actor = User::factory()->create(['username' => 'search'.$suffix, 'email' => null, 'status' => 'ACTIVE']);
        $actor->forceFill(['two_factor_secret' => 'synthetic', 'two_factor_confirmed_at' => now()])->save();
        $unit = DB::table('organization_units')->insertGetId(['code' => 'u'.$suffix, 'name' => 'Synthetic unit', 'active' => true]);
        $category = DB::table('employment_categories')->insertGetId(['code' => 'c'.$suffix, 'name' => 'Synthetic category', 'active' => true]);
        $match = DB::table('employees')->insertGetId(['employee_no' => 'match'.$suffix, 'display_name' => 'Synthetic Match', 'service_start' => '2026-01-01']);
        $other = DB::table('employees')->insertGetId(['employee_no' => 'other'.$suffix, 'display_name' => 'Synthetic Other', 'service_start' => '2026-01-01']);
        foreach ([$match, $other] as $id) {
            DB::table('employee_assignments')->insert(['employee_id' => $id, 'unit_id' => $unit, 'category_id' => $category, 'effective_from' => '2026-01-01']);
        }
        $this->actingAs($actor)->get('/employees?search=match'.$suffix)->assertForbidden();
        $actor->givePermissionTo('hr.employees.view');
        $this->get('/employees?search=match'.$suffix)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('filters.search', 'match'.$suffix)
            ->has('employees', 1)->where('employees.0.id', $match)
            ->has('assignments', 1)->where('assignments.0.employee_id', $match));
        $this->get('/employees?search='.urlencode('Synthetic Other'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('employees', 1)->where('employees.0.id', $other));
        $this->get('/employees?search='.str_repeat('x', 101))->assertSessionHasErrors('search');
        $this->assertGreaterThan(0, DB::transactionLevel());
    }
}
