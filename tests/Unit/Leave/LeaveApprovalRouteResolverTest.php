<?php

namespace Tests\Unit\Leave;

use App\Services\Leave\LeaveApprovalRouteResolver;
use DomainException;
use PHPUnit\Framework\TestCase;

class LeaveApprovalRouteResolverTest extends TestCase
{
    public function test_regular_staff_route_uses_unit_support_then_general_manager_at_five_days(): void
    {
        $route = (new LeaveApprovalRouteResolver)->resolve('STAFF', 10, 7, 10, [
            $this->assignment(1, 'PU', 20, 7),
            $this->assignment(2, 'PB', 30),
        ]);

        $this->assertSame(['UNIT_MANAGER_SUPPORT', 'GENERAL_MANAGER_FINAL'], array_column($route, 'stage_code'));
        $this->assertSame([20, 30], array_column($route, 'assignee_user_id'));
        $this->assertSame([false, true], array_column($route, 'is_final'));
    }

    public function test_more_than_five_days_routes_through_general_manager_support_to_secretary(): void
    {
        $route = (new LeaveApprovalRouteResolver)->resolve('STAFF', 10, 7, 11, [
            $this->assignment(1, 'PU', 20, 7),
            $this->assignment(2, 'PB', 30),
            $this->assignment(3, 'SECRETARY_ALK', 40),
        ]);

        $this->assertSame(['UNIT_MANAGER_SUPPORT', 'GENERAL_MANAGER_SUPPORT', 'SECRETARY_FINAL'], array_column($route, 'stage_code'));
        $this->assertSame([false, false, true], array_column($route, 'is_final'));
    }

    public function test_unit_manager_skips_own_support_stage_and_general_manager_uses_secretary_for_any_duration(): void
    {
        $unitManager = (new LeaveApprovalRouteResolver)->resolve('PU', 20, 7, 10, [
            $this->assignment(1, 'PU', 20, 7),
            $this->assignment(2, 'PB', 30),
        ]);
        $generalManager = (new LeaveApprovalRouteResolver)->resolve('PB', 30, null, 1, [
            $this->assignment(3, 'SECRETARY_ALK', 40),
        ]);

        $this->assertSame(['GENERAL_MANAGER_FINAL'], array_column($unitManager, 'stage_code'));
        $this->assertSame(['SECRETARY_FINAL'], array_column($generalManager, 'stage_code'));
    }

    public function test_missing_stage_assignment_fails_closed(): void
    {
        $this->expectException(DomainException::class);
        (new LeaveApprovalRouteResolver)->resolve('STAFF', 10, 7, 10, [$this->assignment(2, 'PB', 30)]);
    }

    public function test_ambiguous_stage_assignments_fail_closed(): void
    {
        $this->expectException(DomainException::class);
        (new LeaveApprovalRouteResolver)->resolve('STAFF', 10, 7, 10, [
            $this->assignment(1, 'PU', 20, 7),
            $this->assignment(2, 'PU', 21, 7),
            $this->assignment(3, 'PB', 30),
        ]);
    }

    public function test_applicant_cannot_become_a_later_approver(): void
    {
        $resolver = new LeaveApprovalRouteResolver;
        $this->expectException(DomainException::class);
        $resolver->resolve('STAFF', 10, 7, 1, [
            $this->assignment(1, 'PU', 20, 7),
            $this->assignment(2, 'PB', 10),
        ]);
    }

    /** @return array{assignment_id:int,office_code:string,user_id:int,unit_id:?int,active:bool} */
    private function assignment(int $id, string $office, int $userId, ?int $unitId = null): array
    {
        return ['assignment_id' => $id, 'office_code' => $office, 'user_id' => $userId, 'unit_id' => $unitId, 'active' => true];
    }
}
