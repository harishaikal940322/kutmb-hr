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
        $annualOperation = $this->operation($employee, $actor->id, 'annual-op-'.$suffix);
        $tabungOperation = $this->operation($employee, $actor->id, 'tabung-op-'.$suffix);
        $reversalOperation = $this->operation($employee, $actor->id, 'reverse-op-'.$suffix);
        $annualEntry = DB::table('leave_ledger_entries')->insertGetId(['annual_account_id' => $annual, 'balance_operation_id' => $annualOperation, 'signed_half_day_units' => 4, 'kind' => 'synthetic', 'operation_key' => 'annual-'.$suffix, 'actor_id' => $actor->id]);
        DB::table('leave_ledger_entries')->insert(['tabung_account_id' => $tabung, 'balance_operation_id' => $tabungOperation, 'signed_half_day_units' => 6, 'kind' => 'synthetic', 'source_year' => 2025, 'operation_key' => 'tabung-'.$suffix, 'actor_id' => $actor->id]);
        DB::table('leave_ledger_entries')->insert(['annual_account_id' => $annual, 'balance_operation_id' => $reversalOperation, 'signed_half_day_units' => -2, 'kind' => 'synthetic_reversal', 'operation_key' => 'reverse-good-'.$suffix, 'reverses_entry_id' => $annualEntry, 'actor_id' => $actor->id]);
        $this->assertSame(1, DB::table('leave_ledger_entries')->where('operation_key', 'reverse-good-'.$suffix)->count());
        $duplicateOperation = $this->operation($employee, $actor->id, 'duplicate-op-'.$suffix);
        $noneOperation = $this->operation($employee, $actor->id, 'none-op-'.$suffix);
        $bothOperation = $this->operation($employee, $actor->id, 'both-op-'.$suffix);
        $wrongBucketOperation = $this->operation($employee, $actor->id, 'wrong-bucket-op-'.$suffix);
        $wrongSignOperation = $this->operation($employee, $actor->id, 'wrong-sign-op-'.$suffix);
        $operations = [
            fn () => DB::table('leave_annual_accounts')->insert(['employee_id' => $employee, 'leave_year' => 2026]),
            fn () => DB::table('leave_tabung_accounts')->insert(['employee_id' => $employee]),
            fn () => DB::table('leave_ledger_entries')->insert(['annual_account_id' => $annual, 'balance_operation_id' => $duplicateOperation, 'signed_half_day_units' => 1, 'kind' => 'synthetic', 'operation_key' => 'annual-'.$suffix, 'actor_id' => $actor->id]),
            fn () => DB::table('leave_ledger_entries')->insert(['balance_operation_id' => $noneOperation, 'signed_half_day_units' => 1, 'kind' => 'synthetic', 'operation_key' => 'none-'.$suffix, 'actor_id' => $actor->id]),
            fn () => DB::table('leave_ledger_entries')->insert(['annual_account_id' => $annual, 'tabung_account_id' => $tabung, 'balance_operation_id' => $bothOperation, 'signed_half_day_units' => 1, 'kind' => 'synthetic', 'operation_key' => 'both-'.$suffix, 'actor_id' => $actor->id]),
            fn () => DB::table('leave_annual_accounts')->where('id', $annual)->delete(),
            fn () => DB::table('leave_ledger_entries')->where('operation_key', 'annual-'.$suffix)->update(['signed_half_day_units' => 8]),
            fn () => DB::table('leave_ledger_entries')->where('operation_key', 'annual-'.$suffix)->delete(),
            fn () => DB::table('leave_ledger_entries')->insert(['tabung_account_id' => $tabung, 'balance_operation_id' => $wrongBucketOperation, 'signed_half_day_units' => -1, 'kind' => 'synthetic_reversal', 'operation_key' => 'reverse-wrong-bucket-'.$suffix, 'reverses_entry_id' => $annualEntry, 'actor_id' => $actor->id]),
            fn () => DB::table('leave_ledger_entries')->insert(['annual_account_id' => $annual, 'balance_operation_id' => $wrongSignOperation, 'signed_half_day_units' => 1, 'kind' => 'synthetic_reversal', 'operation_key' => 'reverse-wrong-sign-'.$suffix, 'reverses_entry_id' => $annualEntry, 'actor_id' => $actor->id]),
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

    private function operation(int $employeeId, int $actorId, string $key): int
    {
        return DB::table('leave_balance_operations')->insertGetId([
            'employee_id' => $employeeId,
            'actor_id' => $actorId,
            'operation_key' => $key,
            'operation_type' => 'SYNTHETIC_TEST',
            'payload_hash' => hash('sha256', $key),
            'created_at' => now(),
        ]);
    }
}
