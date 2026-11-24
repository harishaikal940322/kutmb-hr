<?php

namespace Tests\Feature\Employee;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class FoundationSchemaTest extends TestCase
{
    use DatabaseTransactions;

    public function test_synthetic_foundation_records_obey_unique_links_and_restrict_history_deletion(): void
    {
        $this->assertSame('mysql', DB::connection()->getDriverName());
        $this->assertGreaterThan(0, DB::transactionLevel());
        $suffix = Str::lower(Str::random(16));
        $unit = DB::table('organization_units')->insertGetId(['code' => 'u'.$suffix, 'name' => 'Synthetic unit', 'active' => true]);
        $category = DB::table('employment_categories')->insertGetId(['code' => 'c'.$suffix, 'name' => 'Synthetic category', 'active' => true]);
        $user = User::factory()->create(['username' => 'e'.$suffix, 'email' => null]);
        $employee = DB::table('employees')->insertGetId(['employee_no' => 'e'.$suffix, 'display_name' => 'Synthetic employee', 'service_start' => '2026-01-01', 'user_id' => $user->id]);
        DB::table('employee_assignments')->insert(['employee_id' => $employee, 'unit_id' => $unit, 'category_id' => $category, 'effective_from' => '2026-01-01']);
        DB::table('approval_assignments')->insert(['user_id' => $user->id, 'office_code' => 'secretary', 'effective_from' => '2026-01-01']);
        DB::table('employees')->insert(['employee_no' => 'n'.$suffix, 'display_name' => 'Synthetic unlinked', 'service_start' => '2026-01-01']);
        DB::table('employees')->insert(['employee_no' => 'm'.$suffix, 'display_name' => 'Synthetic unlinked', 'service_start' => '2026-01-01']);

        foreach ([
            fn () => DB::table('employees')->insert(['employee_no' => 'e'.$suffix, 'display_name' => 'Duplicate', 'service_start' => '2026-01-01']),
            fn () => DB::table('employees')->insert(['employee_no' => 'd'.$suffix, 'display_name' => 'Duplicate link', 'service_start' => '2026-01-01', 'user_id' => $user->id]),
            fn () => DB::table('organization_units')->where('id', $unit)->delete(),
            fn () => DB::table('employees')->where('id', $employee)->delete(),
            fn () => DB::table('users')->where('id', $user->id)->delete(),
        ] as $operation) {
            try {
                $operation();
                $this->fail('Expected database constraint rejection.');
            } catch (QueryException $exception) {
                $this->assertSame('23000', $exception->errorInfo[0]);
            }
        }
        $this->assertGreaterThan(0, DB::transactionLevel());
    }
}
