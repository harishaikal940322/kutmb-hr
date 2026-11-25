<?php

namespace App\Services\Leave;

use App\Models\AuditEvent;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use stdClass;

/** Local-only internal P04/P05 transaction boundary; intentionally not connected to a route or UI. */
class LeaveRequestWorkflowService
{
    public function __construct(
        private readonly LeaveApprovalAssignmentResolver $assignments,
        private readonly LeaveBalanceTransactionService $balances,
        private readonly LeaveWorkflowTransitionPlanner $transitions,
        private readonly ConfirmedVersionLookup $versions,
    ) {}

    /**
     * @param  list<array{date:string,session:string}>  $requestedSlots
     * @return array{request_id:int,request_key:string,status:string,current_step_order:?int,reservation_id:int,replayed:bool}
     */
    public function submit(
        int $requesterUserId,
        int $annualAccountId,
        int $tabungAccountId,
        string $submissionKey,
        string $leaveType,
        array $requestedSlots,
        ?string $reason = null,
    ): array {
        $this->assertLocal();
        $this->assertSubmissionKey($submissionKey);
        if (! in_array($leaveType, ['AL', 'EL'], true) || $annualAccountId < 1 || $tabungAccountId < 1 || $requesterUserId < 1) {
            throw new DomainException('A valid leave type, linked requester, and explicit account IDs are required.');
        }

        $slots = $this->normalizeSlots($requestedSlots);
        $reason = $this->normalizeReason($reason);
        if ($leaveType === 'EL' && $reason === null) {
            throw new DomainException('An emergency reason is required for every EL request.');
        }

        $today = now('Asia/Kuala_Lumpur')->toDateString();
        $firstDate = $slots[0]['date'];
        $lastDate = $slots[count($slots) - 1]['date'];
        $leaveYear = (int) substr($firstDate, 0, 4);
        if ((int) substr($lastDate, 0, 4) !== $leaveYear) {
            throw new DomainException('This internal transaction slice accepts one calendar year per request.');
        }
        if ($leaveType === 'AL' && $firstDate < $today) {
            throw new DomainException('Past-date leave must use the separate retrospective EL request type.');
        }
        if ($leaveType === 'EL' && $lastDate > $today) {
            throw new DomainException('EL requests are limited to the current or already elapsed absence dates.');
        }

        $notice = $leaveType === 'AL' ? $this->assessShortNotice($firstDate, $today) : null;
        if ($notice !== null && $notice['short_notice'] && $reason === null) {
            throw new DomainException('An emergency reason is required when AL notice is unambiguously under three working days.');
        }

        $payloadHash = $this->hash([
            'actor_id' => $requesterUserId,
            'annual_account_id' => $annualAccountId,
            'leave_type' => $leaveType,
            'reason' => $reason,
            'requested_slots' => $slots,
            'tabung_account_id' => $tabungAccountId,
        ]);
        $requestKey = 'leave-'.Str::uuid()->toString();
        $reserveKey = 'reserve:'.Str::uuid()->toString();

        return DB::transaction(function () use ($requesterUserId, $annualAccountId, $tabungAccountId, $submissionKey, $leaveType, $slots, $reason, $leaveYear, $notice, $payloadHash, $requestKey, $reserveKey): array {
            $this->lockSubmissionGuards();
            $existingCandidate = DB::table('leave_requests')->where('submission_key', $submissionKey)->first(['id', 'employee_id']);
            if ($existingCandidate !== null) {
                $employee = DB::table('employees')->where('id', $existingCandidate->employee_id)->lockForUpdate()->first();
                $existing = DB::table('leave_requests')->where('id', $existingCandidate->id)->lockForUpdate()->first();
                $actor = DB::table('users')->where('id', $requesterUserId)->lockForUpdate()->first();
                if ($employee === null || $existing === null || (int) $employee->user_id !== $requesterUserId
                    || $actor === null || $actor->status !== 'ACTIVE' || $actor->username === null) {
                    throw new DomainException('Only the linked active employee can replay their submitted request.');
                }
                if ((int) $existing->requester_user_id !== $requesterUserId || ! hash_equals($existing->payload_hash, $payloadHash)) {
                    throw new DomainException('The submission key was already used for a different request payload.');
                }

                return $this->submissionResult($existing, true);
            }

            $annualCandidate = DB::table('leave_annual_accounts')->where('id', $annualAccountId)->first(['employee_id', 'leave_year']);
            if ($annualCandidate === null || (int) $annualCandidate->leave_year !== $leaveYear) {
                throw new DomainException('The annual account must match the requested calendar year.');
            }
            $employeeId = (int) $annualCandidate->employee_id;
            $route = $this->assignments->resolveForEmployee($requesterUserId, $employeeId, count($slots), $slots);
            $firstStep = $route[0];
            $status = $this->transitions->statusForStage($firstStep['stage_code']);

            $requestId = DB::table('leave_requests')->insertGetId([
                'request_key' => $requestKey,
                'submission_key' => $submissionKey,
                'payload_hash' => $payloadHash,
                'employee_id' => $employeeId,
                'requester_user_id' => $requesterUserId,
                'leave_type' => $leaveType,
                'status' => $status,
                'current_revision' => 1,
                'current_step_order' => $firstStep['step_order'],
                'attendance_review_required' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $reservation = $this->balances->reserve(
                $requesterUserId,
                $requestKey,
                1,
                $annualAccountId,
                $tabungAccountId,
                $slots,
                $reserveKey,
            );
            $revisionId = DB::table('leave_request_revisions')->insertGetId([
                'request_id' => $requestId,
                'revision' => 1,
                'leave_year' => $leaveYear,
                'requested_units' => count($slots),
                'reason' => $reason,
                'reservation_id' => $reservation['reservation_id'],
                'created_by' => $requesterUserId,
                'created_at' => now(),
                'is_short_notice_emergency' => $notice['short_notice'] ?? false,
                'notice_working_days_min' => $notice['min_days'] ?? null,
                'notice_working_days_max' => $notice['max_days'] ?? null,
            ]);
            DB::table('leave_request_days')->insert(array_map(fn (array $slot): array => [
                'revision_id' => $revisionId,
                'leave_date' => $slot['date'],
                'session_code' => $slot['session'],
                'created_at' => now(),
            ], $slots));
            DB::table('leave_approval_steps')->insert(array_map(fn (array $step): array => [
                'revision_id' => $revisionId,
                'step_order' => $step['step_order'],
                'stage_code' => $step['stage_code'],
                'office_code' => $step['office_code'],
                'assignment_id' => $step['assignment_id'],
                'assignee_user_id' => $step['assignee_user_id'],
                'unit_id' => $step['unit_id'],
                'is_final' => $step['is_final'],
                'created_at' => now(),
            ], $route));

            $this->audit('hr.leave.request_submitted', $requestId, $requesterUserId, $submissionKey);

            return [
                'request_id' => $requestId,
                'request_key' => $requestKey,
                'status' => $status,
                'current_step_order' => $firstStep['step_order'],
                'reservation_id' => $reservation['reservation_id'],
                'replayed' => false,
            ];
        }, 3);
    }

    /** @return array{request_id:int,status:string,current_step_order:?int,terminal:bool,replayed:bool} */
    public function decide(int $actorId, int $requestId, string $operationKey, string $decision, ?string $reason = null): array
    {
        $this->assertLocal();
        $this->assertOperationKey($operationKey);
        if (! in_array($decision, ['SUPPORT', 'NOT_SUPPORT', 'APPROVE', 'REJECT'], true)) {
            throw new DomainException('Correction and cancellation actions require their later integrated workflow contracts.');
        }
        if ($requestId < 1 || $actorId < 1) {
            throw new DomainException('A request and active actor are required.');
        }
        $reason = $this->normalizeReason($reason);

        return DB::transaction(function () use ($actorId, $requestId, $operationKey, $decision, $reason): array {
            $candidate = DB::table('leave_requests')->where('id', $requestId)->first(['employee_id']);
            if ($candidate === null) {
                throw new DomainException('The leave request does not exist.');
            }
            $employee = DB::table('employees')->where('id', $candidate->employee_id)->lockForUpdate()->first();
            $request = DB::table('leave_requests')->where('id', $requestId)->lockForUpdate()->first();
            if ($employee === null || $request === null) {
                throw new DomainException('A consistent employee request is required.');
            }

            $existingAction = DB::table('leave_workflow_actions')->where('operation_key', $operationKey)->lockForUpdate()->first();
            if ($existingAction !== null) {
                $this->assertReplayActor($existingAction, $actorId);

                return $this->replayDecision($request, $existingAction, $actorId, $requestId, $decision, $reason);
            }

            if ($request->current_step_order === null || (int) $employee->user_id !== (int) $request->requester_user_id) {
                throw new DomainException('Only a pending request with its linked employee can be acted on.');
            }
            $revision = DB::table('leave_request_revisions')->where('request_id', $requestId)
                ->where('revision', $request->current_revision)->lockForUpdate()->first();
            if ($revision === null) {
                throw new RuntimeException('The current leave request revision is missing.');
            }
            $steps = DB::table('leave_approval_steps')->where('revision_id', $revision->id)->orderBy('step_order')->get();
            $route = $this->routeFromRows($steps);
            $currentStep = $steps->firstWhere('step_order', (int) $request->current_step_order);
            if ($currentStep === null) {
                throw new RuntimeException('The current approval step is missing.');
            }
            $this->assertPersistedAssignee($currentStep, $actorId, (int) $request->requester_user_id);
            $plan = $this->transitions->plan($route, (int) $request->current_step_order, $decision, $reason);
            $payloadHash = $this->actionPayloadHash($requestId, (int) $revision->id, $currentStep, $actorId, $decision, $reason);

            $reviewCode = null;
            if ($plan['terminal'] && $decision === 'APPROVE') {
                $this->balances->commitUsage($actorId, (int) $revision->reservation_id, (int) $currentStep->assignment_id, $operationKey.':c');
            } elseif ($plan['terminal'] && $decision === 'REJECT') {
                $dates = DB::table('leave_request_days')->where('revision_id', $revision->id)->orderBy('leave_date')->pluck('leave_date')->all();
                $today = now('Asia/Kuala_Lumpur')->toDateString();
                $hasStarted = collect($dates)->contains(fn (string $date): bool => $date <= $today);
                if ($request->leave_type === 'EL') {
                    if ($dates === [] || collect($dates)->contains(fn (string $date): bool => $date >= $today)) {
                        throw new DomainException('Final rejection of current-day EL requires HR confirmation of actual absence.');
                    }
                    $this->balances->debitRetrospectiveElRejection($actorId, (int) $revision->reservation_id, (int) $currentStep->assignment_id, $operationKey.':e');
                    $reviewCode = 'RETROSPECTIVE_EL_REJECTION';
                } else {
                    $this->balances->release($actorId, (int) $revision->reservation_id, 'FINAL_REJECTION', $operationKey.':r', (int) $currentStep->assignment_id);
                    if ($hasStarted) {
                        $reviewCode = 'LATE_AL_REJECTION';
                    }
                }
            }

            $actionId = DB::table('leave_workflow_actions')->insertGetId([
                'revision_id' => $revision->id,
                'approval_step_id' => $currentStep->id,
                'actor_id' => $actorId,
                'assignment_id' => $currentStep->assignment_id,
                'action_code' => $plan['action_code'],
                'reason' => $reason,
                'operation_key' => $operationKey,
                'payload_hash' => $payloadHash,
                'created_at' => now(),
            ]);
            $update = [
                'status' => $plan['request_status'],
                'current_step_order' => $plan['next_step_order'],
                'updated_at' => now(),
            ];
            if ($reviewCode !== null) {
                $update['attendance_review_required'] = true;
            }
            DB::table('leave_requests')->where('id', $requestId)->update($update);
            if ($reviewCode !== null) {
                DB::table('leave_attendance_reviews')->insert([
                    'revision_id' => $revision->id,
                    'workflow_action_id' => $actionId,
                    'review_code' => $reviewCode,
                    'opened_by' => $actorId,
                    'opened_at' => now(),
                ]);
            }
            $this->audit('hr.leave.request_action_taken', $requestId, $actorId, $operationKey);

            return [
                'request_id' => $requestId,
                'status' => $plan['request_status'],
                'current_step_order' => $plan['next_step_order'],
                'terminal' => $plan['terminal'],
                'replayed' => false,
            ];
        }, 3);
    }

    /** @return array{request_id:int,status:string,current_step_order:null,reservation_id:int,replayed:bool} */
    public function withdrawPendingAnnualLeave(int $requesterUserId, int $requestId, string $operationKey): array
    {
        $this->assertLocal();
        $this->assertOperationKey($operationKey);
        if ($requesterUserId < 1 || $requestId < 1) {
            throw new DomainException('A pending request and linked employee are required.');
        }

        return DB::transaction(function () use ($requesterUserId, $requestId, $operationKey): array {
            $candidate = DB::table('leave_requests')->where('id', $requestId)->first(['employee_id']);
            if ($candidate === null) {
                throw new DomainException('The leave request does not exist.');
            }
            $employee = DB::table('employees')->where('id', $candidate->employee_id)->lockForUpdate()->first();
            $request = DB::table('leave_requests')->where('id', $requestId)->lockForUpdate()->first();
            $actor = DB::table('users')->where('id', $requesterUserId)->lockForUpdate()->first(['status', 'username']);
            if ($employee === null || $request === null || (int) $employee->user_id !== $requesterUserId
                || (int) $request->requester_user_id !== $requesterUserId || $actor === null
                || $actor->status !== 'ACTIVE' || $actor->username === null) {
                throw new DomainException('Only the linked active employee can withdraw their own request.');
            }

            $existing = DB::table('leave_request_withdrawals')->where('operation_key', $operationKey)->lockForUpdate()->first();
            if ($existing !== null) {
                $payloadHash = $this->withdrawalPayloadHash($requesterUserId, $requestId, (int) $existing->revision_id);
                if ((int) $existing->request_id !== $requestId || (int) $existing->actor_id !== $requesterUserId
                    || ! hash_equals($existing->payload_hash, $payloadHash)) {
                    throw new DomainException('The withdrawal key was already used for a different action.');
                }

                return [
                    'request_id' => $requestId,
                    'status' => 'CANCELLED',
                    'current_step_order' => null,
                    'reservation_id' => $this->currentReservationId($requestId, (int) $request->current_revision),
                    'replayed' => true,
                ];
            }
            if (DB::table('leave_workflow_actions')->where('operation_key', $operationKey)->exists()) {
                throw new DomainException('The operation key was already used for a workflow decision.');
            }
            if ($request->leave_type !== 'AL' || $request->current_step_order === null
                || ! in_array($request->status, ['WAITING_UNIT_MANAGER_SUPPORT', 'WAITING_GENERAL_MANAGER_SUPPORT', 'WAITING_GENERAL_MANAGER_FINAL', 'WAITING_SECRETARY_FINAL'], true)) {
                throw new DomainException('Only a pending Annual Leave request can be withdrawn under this operation.');
            }

            $revision = DB::table('leave_request_revisions')->where('request_id', $requestId)
                ->where('revision', $request->current_revision)->lockForUpdate()->first();
            if ($revision === null) {
                throw new RuntimeException('The current leave request revision is missing.');
            }
            $dates = DB::table('leave_request_days')->where('revision_id', $revision->id)->orderBy('leave_date')->pluck('leave_date')->all();
            $today = now('Asia/Kuala_Lumpur')->toDateString();
            if ($dates === [] || collect($dates)->contains(fn (string $date): bool => $date <= $today)) {
                throw new DomainException('Withdrawal is held for HR review once a requested leave date has started.');
            }
            $payloadHash = $this->withdrawalPayloadHash($requesterUserId, $requestId, (int) $revision->id);

            DB::table('leave_request_withdrawals')->insert([
                'request_id' => $requestId,
                'revision_id' => $revision->id,
                'actor_id' => $requesterUserId,
                'operation_key' => $operationKey,
                'payload_hash' => $payloadHash,
                'created_at' => now(),
            ]);
            $reservation = $this->balances->release($requesterUserId, (int) $revision->reservation_id, 'WITHDRAWAL', $operationKey.':w');
            DB::table('leave_requests')->where('id', $requestId)->update([
                'status' => 'CANCELLED',
                'current_step_order' => null,
                'updated_at' => now(),
            ]);
            $this->audit('hr.leave.request_withdrawn', $requestId, $requesterUserId, $operationKey);

            return [
                'request_id' => $requestId,
                'status' => 'CANCELLED',
                'current_step_order' => null,
                'reservation_id' => $reservation['reservation_id'],
                'replayed' => false,
            ];
        }, 3);
    }

    /** @return array{short_notice:bool,min_days:?int,max_days:?int} */
    private function assessShortNotice(string $firstLeaveDate, string $today): array
    {
        $todayDate = $this->parseDate($today);
        $leaveDate = $this->parseDate($firstLeaveDate);
        $cursor = $todayDate;
        $leadWeekdays = 0;
        $todayIsWorking = false;
        $leaveIsWorking = false;
        while ($cursor <= $leaveDate) {
            $date = $cursor->format('Y-m-d');
            if ((int) $cursor->format('N') <= 5) {
                $calendar = $this->versions->calendarFor($date);
                $working = ! in_array($date, $calendar['holidays'], true);
                if ($date === $today && $working) {
                    $todayIsWorking = true;
                }
                if ($date === $firstLeaveDate && $working) {
                    $leaveIsWorking = true;
                }
                if ($date !== $today && $date !== $firstLeaveDate && $working) {
                    $leadWeekdays++;
                    if ($leadWeekdays >= 3) {
                        return ['short_notice' => false, 'min_days' => null, 'max_days' => null];
                    }
                }
            }
            $cursor = $cursor->add(new DateInterval('P1D'));
        }

        $minDays = $leadWeekdays;
        $maxDays = $leadWeekdays + ($todayIsWorking ? 1 : 0) + ($leaveIsWorking ? 1 : 0);
        if ($maxDays < 3) {
            return ['short_notice' => true, 'min_days' => $minDays, 'max_days' => $maxDays];
        }
        if ($minDays >= 3) {
            return ['short_notice' => false, 'min_days' => null, 'max_days' => null];
        }

        throw new DomainException('The request falls on the CT-DEC-009 notice-count boundary; its counting endpoints are not specified.');
    }

    private function parseDate(string $date): DateTimeImmutable
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('Asia/Kuala_Lumpur'));
        if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
            throw new DomainException('A valid Malaysia-local calendar date is required.');
        }

        return $parsed;
    }

    /** @param array<array-key, mixed> $requestedSlots
     * @return list<array{date:string,session:string}>
     */
    private function normalizeSlots(array $requestedSlots): array
    {
        if (! array_is_list($requestedSlots) || $requestedSlots === []) {
            throw new DomainException('At least one half-day session is required.');
        }
        $slots = [];
        $seen = [];
        foreach ($requestedSlots as $slot) {
            if (! is_array($slot) || ! isset($slot['date'], $slot['session']) || ! is_string($slot['date']) || ! is_string($slot['session'])) {
                throw new DomainException('Each half-day session must contain a date and AM/PM code.');
            }
            $this->parseDate($slot['date']);
            if (! in_array($slot['session'], ['AM', 'PM'], true)) {
                throw new DomainException('Each half-day session must be AM or PM.');
            }
            $key = $slot['date'].':'.$slot['session'];
            if (isset($seen[$key])) {
                throw new DomainException('A half-day session cannot be duplicated.');
            }
            $seen[$key] = true;
            $slots[] = ['date' => $slot['date'], 'session' => $slot['session']];
        }
        usort($slots, fn (array $left, array $right): int => [$left['date'], $left['session']] <=> [$right['date'], $right['session']]);

        return $slots;
    }

    /** @param Collection<int, stdClass> $steps
     * @return list<array{step_order:int,stage_code:string,office_code:string,assignment_id:int,assignee_user_id:int,unit_id:?int,is_final:bool}>
     */
    private function routeFromRows(Collection $steps): array
    {
        return array_values(array_map(fn (stdClass $step): array => [
            'step_order' => (int) $step->step_order,
            'stage_code' => (string) $step->stage_code,
            'office_code' => (string) $step->office_code,
            'assignment_id' => (int) $step->assignment_id,
            'assignee_user_id' => (int) $step->assignee_user_id,
            'unit_id' => $step->unit_id === null ? null : (int) $step->unit_id,
            'is_final' => (bool) $step->is_final,
        ], $steps->all()));
    }

    private function assertPersistedAssignee(stdClass $step, int $actorId, int $requesterUserId): void
    {
        if ((int) $step->assignee_user_id !== $actorId || $actorId === $requesterUserId) {
            throw new DomainException('Only the distinct approver recorded for the current step can act.');
        }
        $today = now('Asia/Kuala_Lumpur')->toDateString();
        $assignment = DB::table('approval_assignments')->where('id', $step->assignment_id)->lockForUpdate()->first();
        if ($assignment === null || (int) $assignment->user_id !== $actorId || $assignment->office_code !== $step->office_code
            || ($assignment->unit_id === null ? null : (int) $assignment->unit_id) !== ($step->unit_id === null ? null : (int) $step->unit_id)
            || $assignment->effective_from > $today || ($assignment->effective_to !== null && $assignment->effective_to < $today)) {
            throw new DomainException('The original approval assignment must still be effective; no substitute or escalation is allowed.');
        }
        $actor = DB::table('users')->where('id', $actorId)->lockForUpdate()->first(['status', 'username']);
        if ($actor === null || $actor->status !== 'ACTIVE' || $actor->username === null) {
            throw new DomainException('Only an active assigned account can act on a leave request.');
        }
    }

    private function assertReplayActor(stdClass $action, int $actorId): void
    {
        $assignment = DB::table('approval_assignments')->where('id', $action->assignment_id)->lockForUpdate()->first();
        $actor = DB::table('users')->where('id', $actorId)->lockForUpdate()->first(['status', 'username']);
        if ($assignment === null || (int) $assignment->user_id !== $actorId
            || (int) $action->actor_id !== $actorId || $actor === null || $actor->status !== 'ACTIVE' || $actor->username === null) {
            throw new DomainException('An active original action actor is required to replay the workflow action.');
        }
    }

    /** @return array{request_id:int,status:string,current_step_order:?int,terminal:bool,replayed:bool} */
    private function replayDecision(stdClass $request, stdClass $action, int $actorId, int $requestId, string $decision, ?string $reason): array
    {
        $step = DB::table('leave_approval_steps')->where('id', $action->approval_step_id)->first();
        $revision = DB::table('leave_request_revisions')->where('id', $action->revision_id)->first();
        if ($step === null || $revision === null || (int) $revision->request_id !== $requestId || (int) $action->actor_id !== $actorId
            || $action->action_code !== $decision || $action->reason !== $reason
            || ! hash_equals($action->payload_hash, $this->actionPayloadHash($requestId, (int) $revision->id, $step, $actorId, $decision, $reason))) {
            throw new DomainException('The action key was already used for a different workflow action.');
        }

        return [
            'request_id' => $requestId,
            'status' => $request->status,
            'current_step_order' => $request->current_step_order === null ? null : (int) $request->current_step_order,
            'terminal' => $request->current_step_order === null,
            'replayed' => true,
        ];
    }

    private function actionPayloadHash(int $requestId, int $revisionId, stdClass $step, int $actorId, string $decision, ?string $reason): string
    {
        return $this->hash([
            'action' => $decision,
            'actor_id' => $actorId,
            'approval_step_id' => (int) $step->id,
            'assignment_id' => (int) $step->assignment_id,
            'reason' => $reason,
            'request_id' => $requestId,
            'revision_id' => $revisionId,
        ]);
    }

    private function withdrawalPayloadHash(int $actorId, int $requestId, int $revisionId): string
    {
        return $this->hash([
            'action' => 'WITHDRAW_BEFORE_APPROVAL',
            'actor_id' => $actorId,
            'request_id' => $requestId,
            'revision_id' => $revisionId,
        ]);
    }

    private function currentReservationId(int $requestId, int $revisionNumber): int
    {
        $reservationId = DB::table('leave_request_revisions')->where('request_id', $requestId)
            ->where('revision', $revisionNumber)->value('reservation_id');
        if ($reservationId === null) {
            throw new RuntimeException('The withdrawn request has no current reservation revision.');
        }

        return (int) $reservationId;
    }

    /** @return array{request_id:int,request_key:string,status:string,current_step_order:?int,reservation_id:int,replayed:bool} */
    private function submissionResult(stdClass $request, bool $replayed): array
    {
        $revision = DB::table('leave_request_revisions')->where('request_id', $request->id)
            ->where('revision', $request->current_revision)->first(['reservation_id']);
        if ($revision === null) {
            throw new RuntimeException('The submitted request has no current reservation revision.');
        }

        return [
            'request_id' => (int) $request->id,
            'request_key' => (string) $request->request_key,
            'status' => (string) $request->status,
            'current_step_order' => $request->current_step_order === null ? null : (int) $request->current_step_order,
            'reservation_id' => (int) $revision->reservation_id,
            'replayed' => $replayed,
        ];
    }

    private function lockSubmissionGuards(): void
    {
        foreach (['leave_calendar_confirmation_guard', 'leave_working_schedule_guard'] as $table) {
            if (! Schema::hasTable($table) || DB::table($table)->where('id', 1)->lockForUpdate()->first() === null) {
                throw new RuntimeException('Leave calendar and schedule impact guards must be available before submission.');
            }
        }
        if (! Schema::hasTable('leave_calendar_usage_snapshots') || ! Schema::hasTable('leave_employee_history_markers')) {
            throw new RuntimeException('Leave calendar impact snapshots and employee history markers are required.');
        }
    }

    private function normalizeReason(?string $reason): ?string
    {
        if ($reason === null) {
            return null;
        }
        $trimmed = trim($reason);

        return $trimmed === '' ? null : $trimmed;
    }

    /** @param array<string, mixed> $payload */
    private function hash(array $payload): string
    {
        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    private function audit(string $actionCode, int $requestId, int $actorId, string $correlationId): void
    {
        AuditEvent::query()->create([
            'occurred_at' => now(),
            'actor_id' => $actorId,
            'action_code' => $actionCode,
            'target_ref' => 'leave_request:'.$requestId,
            'outcome' => 'SUCCESS',
            'metadata' => ['source' => 'leave_request_workflow', 'correlation_id' => $correlationId],
        ]);
    }

    private function assertSubmissionKey(string $key): void
    {
        if (preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9._:-]{0,79}\z/D', $key) !== 1) {
            throw new DomainException('The submission key has an invalid format or length.');
        }
    }

    private function assertOperationKey(string $key): void
    {
        if (preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9._:-]{0,79}\z/D', $key) !== 1) {
            throw new DomainException('The workflow action key has an invalid format or length.');
        }
    }

    private function assertLocal(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('The internal leave request workflow is unavailable outside local development and testing.');
        }
    }
}
