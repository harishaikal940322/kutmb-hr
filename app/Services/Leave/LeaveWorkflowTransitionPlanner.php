<?php

namespace App\Services\Leave;

use DomainException;

/** Plans a single P05 decision from a resolved route; persistence and authorization stay with the caller. */
class LeaveWorkflowTransitionPlanner
{
    /**
     * @param  list<array{step_order:int,stage_code:string,office_code:string,assignment_id:int,assignee_user_id:int,unit_id:?int,is_final:bool}>  $route
     * @return array{action_code:string,request_status:string,next_step_order:?int,terminal:bool}
     */
    public function plan(array $route, int $currentStepOrder, string $action, ?string $reason = null): array
    {
        if ($route === [] || $currentStepOrder < 1 || $currentStepOrder > count($route)) {
            throw new DomainException('A current step in a resolved approval route is required.');
        }
        foreach ($route as $index => $step) {
            if ($step['step_order'] !== $index + 1 || $step['assignment_id'] < 1 || $step['assignee_user_id'] < 1) {
                throw new DomainException('The approval route is inconsistent.');
            }
        }

        $current = $route[$currentStepOrder - 1];
        $hasReason = is_string($reason) && trim($reason) !== '';
        if (! $current['is_final']) {
            if (! in_array($action, ['SUPPORT', 'NOT_SUPPORT'], true)) {
                throw new DomainException('A support stage only accepts support or not-support decisions.');
            }
            if ($action === 'NOT_SUPPORT' && ! $hasReason) {
                throw new DomainException('A reason is required when an officer does not support a request.');
            }
            $next = $route[$currentStepOrder] ?? null;
            if ($next === null) {
                throw new DomainException('A support stage must forward to the next authorized stage.');
            }

            return [
                'action_code' => $action,
                'request_status' => $this->statusForStage($next['stage_code']),
                'next_step_order' => $next['step_order'],
                'terminal' => false,
            ];
        }

        $terminalStatuses = [
            'APPROVE' => 'APPROVED',
            'REJECT' => 'REJECTED',
            'RETURN_FOR_CORRECTION' => 'RETURNED_FOR_CORRECTION',
        ];
        if (! isset($terminalStatuses[$action])) {
            throw new DomainException('A final stage only accepts an approval, rejection, or correction request.');
        }
        if ($action === 'RETURN_FOR_CORRECTION' && ! $hasReason) {
            throw new DomainException('A reason is required when a request is returned for correction.');
        }

        return [
            'action_code' => $action,
            'request_status' => $terminalStatuses[$action],
            'next_step_order' => null,
            'terminal' => true,
        ];
    }

    public function statusForStage(string $stage): string
    {
        return match ($stage) {
            'UNIT_MANAGER_SUPPORT' => 'WAITING_UNIT_MANAGER_SUPPORT',
            'GENERAL_MANAGER_SUPPORT' => 'WAITING_GENERAL_MANAGER_SUPPORT',
            'GENERAL_MANAGER_FINAL' => 'WAITING_GENERAL_MANAGER_FINAL',
            'SECRETARY_FINAL' => 'WAITING_SECRETARY_FINAL',
            default => throw new DomainException('The approval route contains an unsupported stage.'),
        };
    }
}
