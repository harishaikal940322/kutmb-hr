<?php

namespace Tests\Unit\Services\Leave;

use App\Services\Leave\LeaveApprovalAssignmentResolver;
use RuntimeException;
use Tests\TestCase;

class LeaveApprovalAssignmentTransactionGuardTest extends TestCase
{
    public function test_route_cannot_be_resolved_outside_a_transaction(): void
    {
        $this->expectException(RuntimeException::class);
        app(LeaveApprovalAssignmentResolver::class)->resolveForEmployee(
            1,
            1,
            1,
            [['date' => '2098-03-02', 'session' => 'AM']],
        );
    }
}
