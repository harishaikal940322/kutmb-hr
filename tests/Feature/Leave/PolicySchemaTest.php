<?php

namespace Tests\Feature\Leave;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PolicySchemaTest extends TestCase
{
    use DatabaseTransactions;

    public function test_unconfigured_policy_does_not_imply_defaults_and_confirmation_requires_account(): void
    {
        $this->assertSame('mysql', DB::connection()->getDriverName());
        $this->assertGreaterThan(0, DB::transactionLevel());
        $id = DB::table('leave_policy_versions')->insertGetId(['label' => 'Synthetic draft', 'effective_from' => '2026-01-01']);
        $policy = DB::table('leave_policy_versions')->where('id', $id)->sole();
        $this->assertNull($policy->working_weekdays);
        $this->assertNull($policy->work_start);
        $this->assertNull($policy->session_boundary);
        $this->assertNull($policy->work_end);
        $this->assertNull($policy->confirmed_by);
        $this->assertNull($policy->confirmed_at);
        try {
            DB::table('leave_policy_versions')->where('id', $id)->update(['confirmed_by' => 999999999]);
            $this->fail('Expected foreign key rejection.');
        } catch (QueryException $exception) {
            $this->assertSame('23000', $exception->errorInfo[0]);
        }
        $this->assertGreaterThan(0, DB::transactionLevel());
    }
}
