<?php

namespace App\Services\Leave;

use DomainException;

/**
 * Builds the documented P04/P05 office sequence from a locked, current assignment snapshot.
 * Callers must resolve and revalidate assignments in their transaction; this class does no IO.
 */
class LeaveApprovalRouteResolver
{
    /**
     * @param  list<array{assignment_id:int,office_code:string,user_id:int,unit_id:?int,active:bool}>  $assignments
     * @return list<array{step_order:int,stage_code:string,office_code:string,assignment_id:int,assignee_user_id:int,unit_id:?int,is_final:bool}>
     */
    public function resolve(string $applicantOffice, int $applicantUserId, ?int $unitId, int $requestedHalfDayUnits, array $assignments): array
    {
        if (! in_array($applicantOffice, ['STAFF', 'PU', 'PB'], true) || $applicantUserId < 1 || $requestedHalfDayUnits < 1) {
            throw new DomainException('A supported applicant office and positive leave duration are required.');
        }
        if ($applicantOffice !== 'PB' && ($unitId === null || $unitId < 1)) {
            throw new DomainException('A current employee unit is required for this approval route.');
        }

        $stages = [];
        if ($applicantOffice === 'PB') {
            $stages[] = ['stage_code' => 'SECRETARY_FINAL', 'office_code' => 'SECRETARY_ALK', 'unit_id' => null, 'is_final' => true];
        } else {
            if ($applicantOffice === 'STAFF') {
                $stages[] = ['stage_code' => 'UNIT_MANAGER_SUPPORT', 'office_code' => 'PU', 'unit_id' => $unitId, 'is_final' => false];
            }

            if ($requestedHalfDayUnits <= 10) {
                $stages[] = ['stage_code' => 'GENERAL_MANAGER_FINAL', 'office_code' => 'PB', 'unit_id' => null, 'is_final' => true];
            } else {
                $stages[] = ['stage_code' => 'GENERAL_MANAGER_SUPPORT', 'office_code' => 'PB', 'unit_id' => null, 'is_final' => false];
                $stages[] = ['stage_code' => 'SECRETARY_FINAL', 'office_code' => 'SECRETARY_ALK', 'unit_id' => null, 'is_final' => true];
            }
        }

        $route = [];
        foreach ($stages as $index => $stage) {
            $matches = array_values(array_filter($assignments, fn (array $assignment): bool => $assignment['active']
                && $assignment['office_code'] === $stage['office_code']
                && $assignment['unit_id'] === $stage['unit_id']
            ));
            if (count($matches) !== 1 || $matches[0]['assignment_id'] < 1 || $matches[0]['user_id'] < 1) {
                throw new DomainException('Exactly one active assignment is required for every approval stage.');
            }
            if ($matches[0]['user_id'] === $applicantUserId) {
                throw new DomainException('An applicant cannot support or approve their own request.');
            }

            $route[] = [
                'step_order' => $index + 1,
                'stage_code' => $stage['stage_code'],
                'office_code' => $stage['office_code'],
                'assignment_id' => $matches[0]['assignment_id'],
                'assignee_user_id' => $matches[0]['user_id'],
                'unit_id' => $stage['unit_id'],
                'is_final' => $stage['is_final'],
            ];
        }

        return $route;
    }
}
