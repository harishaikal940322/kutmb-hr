<?php

namespace Tests\Unit\Leave;

use App\Services\Leave\LeaveWorkflowTransitionPlanner;
use DomainException;
use PHPUnit\Framework\TestCase;

class LeaveWorkflowTransitionPlannerTest extends TestCase
{
    public function test_unit_manager_non_support_forwards_with_reason_and_does_not_reject(): void
    {
        $result = (new LeaveWorkflowTransitionPlanner)->plan($this->route(10), 1, 'NOT_SUPPORT', 'Synthetic operational reason');

        $this->assertSame('WAITING_GENERAL_MANAGER_FINAL', $result['request_status']);
        $this->assertSame(2, $result['next_step_order']);
        $this->assertFalse($result['terminal']);
    }

    public function test_general_manager_non_support_on_long_request_forwards_to_secretary(): void
    {
        $result = (new LeaveWorkflowTransitionPlanner)->plan($this->route(11), 2, 'NOT_SUPPORT', 'Synthetic recommendation');

        $this->assertSame('WAITING_SECRETARY_FINAL', $result['request_status']);
        $this->assertSame(3, $result['next_step_order']);
        $this->assertFalse($result['terminal']);
    }

    public function test_every_planned_stage_maps_to_its_persisted_waiting_status(): void
    {
        $planner = new LeaveWorkflowTransitionPlanner;

        $this->assertSame('WAITING_UNIT_MANAGER_SUPPORT', $planner->statusForStage('UNIT_MANAGER_SUPPORT'));
        $this->assertSame('WAITING_GENERAL_MANAGER_SUPPORT', $planner->statusForStage('GENERAL_MANAGER_SUPPORT'));
        $this->assertSame('WAITING_GENERAL_MANAGER_FINAL', $planner->statusForStage('GENERAL_MANAGER_FINAL'));
        $this->assertSame('WAITING_SECRETARY_FINAL', $planner->statusForStage('SECRETARY_FINAL'));
        $this->assertThrows(fn () => $planner->statusForStage('UNKNOWN'));
    }

    public function test_final_approval_rejection_and_correction_are_terminal_decisions(): void
    {
        $planner = new LeaveWorkflowTransitionPlanner;
        $route = $this->route(10);

        $this->assertSame('APPROVED', $planner->plan($route, 2, 'APPROVE')['request_status']);
        $this->assertSame('REJECTED', $planner->plan($route, 2, 'REJECT', 'Synthetic final reason')['request_status']);
        $this->assertSame('DRAFT', $planner->plan($route, 2, 'RETURN_FOR_CORRECTION', 'Synthetic correction request')['request_status']);
        $this->assertSame('DRAFT', $planner->plan($route, 1, 'RETURN_FOR_CORRECTION', 'Synthetic support-stage correction')['request_status']);
    }

    public function test_missing_reason_invalid_stage_action_or_malformed_route_fails_closed(): void
    {
        $planner = new LeaveWorkflowTransitionPlanner;
        foreach ([
            fn () => $planner->plan($this->route(10), 1, 'NOT_SUPPORT'),
            fn () => $planner->plan($this->route(10), 1, 'APPROVE'),
            fn () => $planner->plan($this->route(10), 2, 'RETURN_FOR_CORRECTION'),
            fn () => $planner->plan($this->route(10), 2, 'SUPPORT'),
            fn () => $planner->plan([], 1, 'APPROVE'),
        ] as $invalidAction) {
            $this->assertThrows($invalidAction);
        }
    }

    private function assertThrows(callable $action): void
    {
        try {
            $action();
        } catch (DomainException) {
            $this->addToAssertionCount(1);

            return;
        }

        $this->fail('An invalid workflow transition must fail closed.');
    }

    /** @return list<array{step_order:int,stage_code:string,office_code:string,assignment_id:int,assignee_user_id:int,unit_id:?int,is_final:bool}> */
    private function route(int $units): array
    {
        $route = [
            ['step_order' => 1, 'stage_code' => 'UNIT_MANAGER_SUPPORT', 'office_code' => 'PU', 'assignment_id' => 1, 'assignee_user_id' => 20, 'unit_id' => 7, 'is_final' => false],
        ];
        if ($units <= 10) {
            $route[] = ['step_order' => 2, 'stage_code' => 'GENERAL_MANAGER_FINAL', 'office_code' => 'PB', 'assignment_id' => 2, 'assignee_user_id' => 30, 'unit_id' => null, 'is_final' => true];
        } else {
            $route[] = ['step_order' => 2, 'stage_code' => 'GENERAL_MANAGER_SUPPORT', 'office_code' => 'PB', 'assignment_id' => 2, 'assignee_user_id' => 30, 'unit_id' => null, 'is_final' => false];
            $route[] = ['step_order' => 3, 'stage_code' => 'SECRETARY_FINAL', 'office_code' => 'SECRETARY_ALK', 'assignment_id' => 3, 'assignee_user_id' => 40, 'unit_id' => null, 'is_final' => true];
        }

        return $route;
    }
}
