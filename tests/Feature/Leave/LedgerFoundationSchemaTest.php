<?php

namespace Tests\Feature\Leave;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class LedgerFoundationSchemaTest extends TestCase
{
    use DatabaseTransactions;

    public function test_accounts_and_ledger_keys_enforce_basic_integrity(): void
    {
        $this->assertSame('mysql', DB::connection()->getDriverName());
        $this->assertGreaterThan(0, DB::transactionLevel());
        $suffix = Str::lower(Str::random(16));
        $actor = User::factory()->create(['username' => 'ledger'.$suffix, 'email' => null, 'status' => 'ACTIVE']);
        $employee = DB::table('employees')->insertGetId(['employee_no' => 'e'.$suffix, 'display_name' => 'Synthetic', 'service_start' => '2026-01-01']);
        $annual = DB::table('leave_annual_accounts')->insertGetId(['employee_id' => $employee, 'leave_year' => 2026]);
        $tabung = DB::table('leave_tabung_accounts')->insertGetId(['employee_id' => $employee]);
        DB::table('leave_ledger_entries')->insert(['annual_account_id' => $annual, 'signed_half_day_units' => 4, 'kind' => 'synthetic', 'operation_key' => 'annual-'.$suffix, 'actor_id' => $actor->id]);
        DB::table('leave_ledger_entries')->insert(['tabung_account_id' => $tabung, 'signed_half_day_units' => 6, 'kind' => 'synthetic', 'source_year' => 2025, 'operation_key' => 'tabung-'.$suffix, 'actor_id' => $actor->id]);
        $operations = [
            fn () => DB::table('leave_annual_accounts')->insert(['employee_id' => $employee, 'leave_year' => 2026]),
            fn () => DB::table('leave_tabung_accounts')->insert(['employee_id' => $employee]),
            fn () => DB::table('leave_ledger_entries')->insert(['annual_account_id' => $annual, 'signed_half_day_units' => 1, 'kind' => 'synthetic', 'operation_key' => 'annual-'.$suffix, 'actor_id' => $actor->id]),
            fn () => DB::table('leave_ledger_entries')->insert(['signed_half_day_units' => 1, 'kind' => 'synthetic', 'operation_key' => 'none-'.$suffix, 'actor_id' => $actor->id]),
            fn () => DB::table('leave_ledger_entries')->insert(['annual_account_id' => $annual, 'tabung_account_id' => $tabung, 'signed_half_day_units' => 1, 'kind' => 'synthetic', 'operation_key' => 'both-'.$suffix, 'actor_id' => $actor->id]),
            fn () => DB::table('leave_annual_accounts')->where('id', $annual)->delete(),
            fn () => DB::table('leave_ledger_entries')->where('operation_key', 'annual-'.$suffix)->update(['signed_half_day_units' => 8]),
            fn () => DB::table('leave_ledger_entries')->where('operation_key', 'annual-'.$suffix)->delete(),
        ];
        foreach ($operations as $operation) {
            try {
                $operation();
                $this->fail('Expected database constraint rejection.');
            } catch (QueryException $exception) {
                $this->assertContains($exception->errorInfo[0], ['23000', '45000', 'HY000']);
            }
        }
        $this->assertGreaterThan(0, DB::transactionLevel());
    }
}
