<?php

namespace Tests\Feature\Leave;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class LeaveLedgerAggregateReversalTest extends TestCase
{
    use DatabaseTransactions;

    public function test_partial_reversals_cannot_exceed_original_and_preserve_source_year(): void
    {
        $suffix = Str::lower(Str::random(10));
        $actor = User::factory()->create(['username' => 'reverse'.$suffix, 'email' => null, 'status' => 'ACTIVE']);
        $employee = DB::table('employees')->insertGetId(['employee_no' => 'reverse'.$suffix, 'display_name' => 'Synthetic reversal', 'service_start' => '2026-01-01']);
        $tabung = DB::table('leave_tabung_accounts')->insertGetId(['employee_id' => $employee]);
        $original = DB::table('leave_ledger_entries')->insertGetId([
            'tabung_account_id' => $tabung,
            'balance_operation_id' => $this->operation($employee, $actor->id, 'original-op-'.$suffix),
            'signed_half_day_units' => 4,
            'kind' => 'synthetic_opening',
            'source_year' => 2025,
            'operation_key' => 'original-'.$suffix,
            'actor_id' => $actor->id,
        ]);

        foreach ([1, 2] as $part) {
            DB::table('leave_ledger_entries')->insert([
                'tabung_account_id' => $tabung,
                'balance_operation_id' => $this->operation($employee, $actor->id, 'partial-op-'.$part.'-'.$suffix),
                'signed_half_day_units' => -2,
                'kind' => 'synthetic_reversal',
                'source_year' => 2025,
                'operation_key' => 'partial-'.$part.'-'.$suffix,
                'reverses_entry_id' => $original,
                'actor_id' => $actor->id,
            ]);
        }

        $totals = DB::table('leave_ledger_reversal_totals')->where('original_entry_id', $original)->sole();
        $this->assertSame(4, (int) $totals->reversed_units);
        $this->assertSame(2, DB::table('leave_ledger_entries')->where('reverses_entry_id', $original)->count());

        $this->assertDatabaseRejects(fn () => DB::table('leave_ledger_entries')->insert([
            'tabung_account_id' => $tabung,
            'balance_operation_id' => $this->operation($employee, $actor->id, 'excess-op-'.$suffix),
            'signed_half_day_units' => -1,
            'kind' => 'synthetic_reversal',
            'source_year' => 2025,
            'operation_key' => 'excess-'.$suffix,
            'reverses_entry_id' => $original,
            'actor_id' => $actor->id,
        ]));

        $differentSource = DB::table('leave_ledger_entries')->insertGetId([
            'tabung_account_id' => $tabung,
            'balance_operation_id' => $this->operation($employee, $actor->id, 'other-source-op-'.$suffix),
            'signed_half_day_units' => 2,
            'kind' => 'synthetic_opening',
            'source_year' => 2024,
            'operation_key' => 'other-source-'.$suffix,
            'actor_id' => $actor->id,
        ]);
        $this->assertDatabaseRejects(fn () => DB::table('leave_ledger_entries')->insert([
            'tabung_account_id' => $tabung,
            'balance_operation_id' => $this->operation($employee, $actor->id, 'wrong-source-op-'.$suffix),
            'signed_half_day_units' => -1,
            'kind' => 'synthetic_reversal',
            'source_year' => 2025,
            'operation_key' => 'wrong-source-'.$suffix,
            'reverses_entry_id' => $differentSource,
            'actor_id' => $actor->id,
        ]));

        $this->assertDatabaseRejects(fn () => DB::table('leave_ledger_reversal_totals')->where('original_entry_id', $original)->update(['reversed_units' => 1]));
        $this->assertDatabaseRejects(fn () => DB::table('leave_ledger_reversal_totals')->where('original_entry_id', $original)->delete());
        $this->assertSame(4, (int) DB::table('leave_ledger_reversal_totals')->where('original_entry_id', $original)->value('reversed_units'));
    }

    private function assertDatabaseRejects(callable $operation): void
    {
        try {
            $operation();
            $this->fail('Expected MySQL integrity enforcement to reject the operation.');
        } catch (QueryException $exception) {
            $this->assertContains($exception->errorInfo[0], ['23000', '45000', 'HY000']);
        }
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
