<?php

namespace App\Services\Leave;

use App\Models\AuditEvent;
use App\Models\User;
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
        return $this->submitInternal(
            $requesterUserId,
            $annualAccountId,
            $tabungAccountId,
            $submissionKey,
            $leaveType,
            $requestedSlots,
            $reason,
        );
    }

    /** @param list<array{date:string,session:string}> $requestedSlots
     * @return array{request_id:int,request_key:string,status:string,current_step_order:?int,reservation_id:int,replayed:bool}
     */
    public function submitOnBehalf(
        int $hrActorUserId,
        int $applicantUserId,
        int $annualAccountId,
        int $tabungAccountId,
        string $submissionKey,
        string $leaveType,
        array $requestedSlots,
        ?string $reason,
        ?string $recordingReason,
        ?string $consentEvidenceReference,
        ?string $staffConfirmedAt,
    ): array {
        throw new DomainException('CT-DEC-051 permits staff to submit only their own leave request; HR account support is a separate process.');
    }

    /** @param list<array{date:string,session:string}> $requestedSlots
     * @return array{request_id:int,request_key:string,status:string,current_step_order:?int,reservation_id:int,replayed:bool}
     */
    private function submitInternal(
        int $requesterUserId,
        int $annualAccountId,
        int $tabungAccountId,
        string $submissionKey,
        string $leaveType,
        array $requestedSlots,
        ?string $reason,
    ): array {
        $this->assertLocal();
        $this->assertSubmissionKey($submissionKey);
        if (! in_array($leaveType, ['AL', 'EL'], true) || $annualAccountId < 1 || $tabungAccountId < 1
            || $requesterUserId < 1) {
            throw new DomainException('A valid leave type, linked requester, and explicit account IDs are required.');
        }
        $actorUserId = $requesterUserId;

        $slots = $this->normalizeSlots($requestedSlots);
        $reason = $this->normalizeReason($reason);
        if ($leaveType === 'EL' && $reason === null) {
            throw new DomainException('An emergency reason is required for every EL request.');
        }
        if ($leaveType === 'EL') {
            throw new DomainException('CT-DEC-027 has no HR-confirmed submission window; Emergency Leave submission is held.');
        }

        $today = now('Asia/Kuala_Lumpur')->toDateString();
        $firstDate = $slots[0]['date'];
        $lastDate = $slots[count($slots) - 1]['date'];
        $leaveYear = (int) substr($firstDate, 0, 4);
        if ((int) substr($lastDate, 0, 4) !== $leaveYear) {
            throw new DomainException('This internal transaction slice accepts one calendar year per request.');
        }
        if ($firstDate < $today) {
            throw new DomainException('Past-date leave must use the separate retrospective EL request type.');
        }

        $notice = $this->assessShortNotice($firstDate, $today);
        if ($notice['short_notice'] && $reason === null) {
            throw new DomainException('An emergency reason is required when AL notice is unambiguously under three working days.');
        }

        $payloadHash = $this->hash([
            'annual_account_id' => $annualAccountId,
            'applicant_user_id' => $requesterUserId,
            'leave_type' => $leaveType,
            'reason' => $reason,
            'requested_slots' => $slots,
            'tabung_account_id' => $tabungAccountId,
        ]);
        $requestKey = 'leave-'.Str::uuid()->toString();
        $reserveKey = 'reserve:'.Str::uuid()->toString();

        return DB::transaction(function () use ($actorUserId, $requesterUserId, $annualAccountId, $tabungAccountId, $submissionKey, $leaveType, $slots, $reason, $leaveYear, $notice, $payloadHash, $requestKey, $reserveKey): array {
            $this->lockSubmissionGuards();
            $existingCandidate = DB::table('leave_requests')->where('submission_key', $submissionKey)->first(['id', 'employee_id']);
            if ($existingCandidate !== null) {
                $employee = DB::table('employees')->where('id', $existingCandidate->employee_id)->lockForUpdate()->first();
                $existing = DB::table('leave_requests')->where('id', $existingCandidate->id)->lockForUpdate()->first();
                $actor = DB::table('users')->where('id', $actorUserId)->lockForUpdate()->first();
                if ($employee === null || $existing === null || (int) $employee->user_id !== $requesterUserId
                    || $actor === null || $actor->status !== 'ACTIVE' || $actor->username === null) {
                    throw new DomainException('The linked active applicant and original active recorder are required to replay a submitted request.');
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
            $linkedApplicant = DB::table('employees')->where('id', $employeeId)->where('user_id', $requesterUserId)->exists();
            if (! $linkedApplicant) {
                throw new DomainException('The annual account must belong to the linked leave applicant.');
            }
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
                $actorUserId,
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
                'created_by' => $actorUserId,
                'created_at' => now(),
                'is_short_notice_emergency' => $notice['short_notice'],
                'notice_working_days_min' => $notice['min_days'],
                'notice_working_days_max' => $notice['max_days'],
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

            $this->audit('hr.leave.request_submitted', $requestId, $actorUserId, $submissionKey);

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
        if (! in_array($decision, ['SUPPORT', 'NOT_SUPPORT', 'APPROVE', 'REJECT', 'RETURN_FOR_CORRECTION'], true)) {
            throw new DomainException('The requested leave workflow action is not supported.');
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
            $unitSupportStep = $steps->firstWhere('stage_code', 'UNIT_MANAGER_SUPPORT');
            if ($unitSupportStep !== null) {
                $today = now('Asia/Kuala_Lumpur')->toDateString();
                $currentUnits = DB::table('employee_assignments')->where('employee_id', $employee->id)
                    ->where('effective_from', '<=', $today)
                    ->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>=', $today))
                    ->lockForUpdate()->get(['unit_id']);
                if ($currentUnits->count() !== 1 || $currentUnits[0]->unit_id === null
                    || (int) $currentUnits[0]->unit_id !== (int) $unitSupportStep->unit_id) {
                    throw new DomainException('CT-DEC-032 is unresolved; a pending request cannot use an old or inferred unit route after transfer.');
                }
            }
            $currentStep = $steps->firstWhere('step_order', (int) $request->current_step_order);
            if ($currentStep === null) {
                throw new RuntimeException('The current approval step is missing.');
            }
            $this->assertPersistedAssignee($currentStep, $actorId, (int) $request->requester_user_id);
            $dates = DB::table('leave_request_days')->where('revision_id', $revision->id)->orderBy('leave_date')->pluck('leave_date')->all();
            $today = now('Asia/Kuala_Lumpur')->toDateString();
            if ($request->leave_type === 'EL' && $decision === 'REJECT'
                && collect($dates)->contains(fn (string $date): bool => $date < $today)) {
                throw new DomainException('CT-DEC-029 leaves attendance, balance and payroll outcomes of a rejected retrospective EL to HR.');
            }
            if ($dates === [] || collect($dates)->contains(fn (string $date): bool => $date <= $today)) {
                throw new DomainException('CT-DEC-046 is unresolved; a pending leave request cannot be acted on after a requested date starts.');
            }
            $plan = $this->transitions->plan($route, (int) $request->current_step_order, $decision, $reason);
            $payloadHash = $this->actionPayloadHash($requestId, (int) $revision->id, $currentStep, $actorId, $decision, $reason);

            if ($plan['terminal'] && $decision === 'APPROVE') {
                $this->balances->commitUsage($actorId, (int) $revision->reservation_id, (int) $currentStep->assignment_id, $operationKey.':c');
            } elseif ($plan['terminal'] && $decision === 'REJECT') {
                $this->balances->release($actorId, (int) $revision->reservation_id, 'FINAL_REJECTION', $operationKey.':r', (int) $currentStep->assignment_id);
            } elseif ($plan['terminal'] && $decision === 'RETURN_FOR_CORRECTION') {
                if ($request->leave_type !== 'AL') {
                    throw new DomainException('Only Annual Leave correction is included in this internal workflow slice.');
                }
                $this->assertAllDatesAreFuture((int) $revision->id, 'Correction return is held for HR review once any requested leave date has started.');
                $this->balances->release($actorId, (int) $revision->reservation_id, 'CORRECTION_RETURN', $operationKey.':r', (int) $currentStep->assignment_id);
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
            DB::table('leave_requests')->where('id', $requestId)->update($update);
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

    /** @return array{request_id:int,status:string,current_step_order:null,reservation_id:int,replayed:bool} */
    public function requestApprovedAnnualLeaveCancellation(int $requesterUserId, int $requestId, string $operationKey, ?string $reason): array
    {
        $this->assertLocal();
        $this->assertOperationKey($operationKey);
        $reason = $this->normalizeReason($reason);
        if ($requesterUserId < 1 || $requestId < 1 || $reason === null) {
            throw new DomainException('A linked employee, approved Annual Leave request, and cancellation reason are required.');
        }

        return DB::transaction(function () use ($requesterUserId, $requestId, $operationKey, $reason): array {
            $this->lockCancellationGuards();
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
                throw new DomainException('Only the linked active employee can request cancellation of their own leave.');
            }

            $existing = DB::table('leave_request_cancellation_actions')->where('operation_key', $operationKey)->lockForUpdate()->first();
            if ($existing !== null) {
                $expectedHash = $this->cancellationPayloadHash('REQUESTED', $requesterUserId, $requestId, (int) $existing->revision_id, (int) $existing->cycle, null, $reason);
                if ((int) $existing->request_id !== $requestId || $existing->action_code !== 'REQUESTED'
                    || (int) $existing->actor_id !== $requesterUserId || ! hash_equals($existing->payload_hash, $expectedHash)) {
                    throw new DomainException('The cancellation request key was already used for a different action.');
                }

                return [
                    'request_id' => $requestId,
                    'status' => (string) $request->status,
                    'current_step_order' => null,
                    'reservation_id' => $this->currentReservationId($requestId, (int) $request->current_revision),
                    'replayed' => true,
                ];
            }
            $this->assertCancellationOperationKeyAvailable($operationKey);
            if ($request->leave_type !== 'AL' || $request->status !== 'APPROVED' || $request->current_step_order !== null) {
                throw new DomainException('Only an approved Annual Leave request can enter the cancellation process.');
            }

            $revision = DB::table('leave_request_revisions')->where('request_id', $requestId)
                ->where('revision', $request->current_revision)->lockForUpdate()->first();
            if ($revision === null) {
                throw new RuntimeException('The current leave request revision is missing.');
            }
            $this->assertAllDatesAreFuture((int) $revision->id, 'An approved leave cancellation can be requested only before its first leave date starts.');
            $reservation = DB::table('leave_reservations')->where('id', $revision->reservation_id)->lockForUpdate()->first();
            if ($reservation === null || $reservation->status !== 'COMMITTED') {
                throw new DomainException('The approved leave must have a committed reservation before cancellation.');
            }
            if ((int) $reservation->tabung_units !== 0) {
                throw new DomainException('Tabung-funded cancellation is deferred until the current annual entitlement cap can be verified.');
            }

            $cycle = (int) DB::table('leave_request_cancellation_actions')->where('request_id', $requestId)->max('cycle') + 1;
            $payloadHash = $this->cancellationPayloadHash('REQUESTED', $requesterUserId, $requestId, (int) $revision->id, $cycle, null, $reason);
            DB::table('leave_request_cancellation_actions')->insert([
                'request_id' => $requestId,
                'revision_id' => $revision->id,
                'cycle' => $cycle,
                'action_code' => 'REQUESTED',
                'actor_id' => $requesterUserId,
                'reason' => $reason,
                'operation_key' => $operationKey,
                'payload_hash' => $payloadHash,
                'created_at' => now(),
            ]);
            DB::table('leave_requests')->where('id', $requestId)->update([
                'status' => 'WAITING_CANCELLATION',
                'current_step_order' => null,
                'updated_at' => now(),
            ]);
            $this->audit('hr.leave.cancellation_requested', $requestId, $requesterUserId, $operationKey);

            return [
                'request_id' => $requestId,
                'status' => 'WAITING_CANCELLATION',
                'current_step_order' => null,
                'reservation_id' => (int) $reservation->id,
                'replayed' => false,
            ];
        }, 3);
    }

    /** @return array{request_id:int,status:string,current_step_order:null,reservation_id:int,replayed:bool} */
    public function decideApprovedAnnualLeaveCancellation(int $actorId, int $requestId, string $operationKey, string $decision, ?string $reason = null): array
    {
        $this->assertLocal();
        $this->assertOperationKey($operationKey);
        if (! in_array($decision, ['CONFIRM', 'REJECT'], true) || $actorId < 1 || $requestId < 1) {
            throw new DomainException('A current General Manager and a valid cancellation decision are required.');
        }
        $reason = $this->normalizeReason($reason);

        return DB::transaction(function () use ($actorId, $requestId, $operationKey, $decision, $reason): array {
            $this->lockCancellationGuards();
            $candidate = DB::table('leave_requests')->where('id', $requestId)->first(['employee_id']);
            if ($candidate === null) {
                throw new DomainException('The leave request does not exist.');
            }
            $employee = DB::table('employees')->where('id', $candidate->employee_id)->lockForUpdate()->first();
            $request = DB::table('leave_requests')->where('id', $requestId)->lockForUpdate()->first();
            if ($employee === null || $request === null) {
                throw new DomainException('A consistent employee request is required.');
            }
            $existing = DB::table('leave_request_cancellation_actions')->where('operation_key', $operationKey)->lockForUpdate()->first();
            if ($existing !== null) {
                $expectedCode = $decision === 'CONFIRM' ? 'CONFIRMED' : 'REJECTED';
                $expectedHash = $this->cancellationPayloadHash($expectedCode, $actorId, $requestId, (int) $existing->revision_id,
                    (int) $existing->cycle, (int) $existing->assignment_id, $reason);
                if ((int) $existing->request_id !== $requestId || $existing->action_code !== $expectedCode
                    || (int) $existing->actor_id !== $actorId || $existing->assignment_id === null
                    || ! hash_equals($existing->payload_hash, $expectedHash)) {
                    throw new DomainException('The cancellation decision key was already used for a different action.');
                }

                return [
                    'request_id' => $requestId,
                    'status' => (string) $request->status,
                    'current_step_order' => null,
                    'reservation_id' => $this->currentReservationId($requestId, (int) $request->current_revision),
                    'replayed' => true,
                ];
            }
            $this->assertCancellationOperationKeyAvailable($operationKey);
            if ($request->leave_type !== 'AL' || $request->status !== 'WAITING_CANCELLATION'
                || $request->current_step_order !== null || (int) $employee->user_id === $actorId) {
                throw new DomainException('Only a pending pre-start Annual Leave cancellation can be decided by a distinct General Manager.');
            }
            $this->assertActiveGeneralManager($actorId);
            $revision = DB::table('leave_request_revisions')->where('request_id', $requestId)
                ->where('revision', $request->current_revision)->lockForUpdate()->first();
            if ($revision === null) {
                throw new RuntimeException('The current leave request revision is missing.');
            }
            $this->assertAllDatesAreFuture((int) $revision->id, 'Cancellation confirmation is held for HR review once any leave date has started.');
            $requestAction = DB::table('leave_request_cancellation_actions')->where('request_id', $requestId)
                ->where('revision_id', $revision->id)->where('action_code', 'REQUESTED')
                ->orderByDesc('cycle')->lockForUpdate()->first();
            if ($requestAction === null || DB::table('leave_request_cancellation_actions')->where('request_id', $requestId)
                ->where('cycle', $requestAction->cycle)->whereIn('action_code', ['CONFIRMED', 'REJECTED'])->exists()) {
                throw new DomainException('A current undecided employee cancellation request is required.');
            }
            $assignment = $this->currentGeneralManagerAssignment($actorId);
            $actionCode = $decision === 'CONFIRM' ? 'CONFIRMED' : 'REJECTED';
            $payloadHash = $this->cancellationPayloadHash($actionCode, $actorId, $requestId, (int) $revision->id,
                (int) $requestAction->cycle, (int) $assignment->id, $reason);
            $actionId = DB::table('leave_request_cancellation_actions')->insertGetId([
                'request_id' => $requestId,
                'revision_id' => $revision->id,
                'cycle' => $requestAction->cycle,
                'action_code' => $actionCode,
                'actor_id' => $actorId,
                'assignment_id' => $assignment->id,
                'reason' => $reason,
                'operation_key' => $operationKey,
                'payload_hash' => $payloadHash,
                'created_at' => now(),
            ]);

            if ($decision === 'REJECT') {
                DB::table('leave_requests')->where('id', $requestId)->update([
                    'status' => 'APPROVED',
                    'current_step_order' => null,
                    'updated_at' => now(),
                ]);
                $this->audit('hr.leave.cancellation_rejected', $requestId, $actorId, $operationKey);

                return [
                    'request_id' => $requestId,
                    'status' => 'APPROVED',
                    'current_step_order' => null,
                    'reservation_id' => (int) $revision->reservation_id,
                    'replayed' => false,
                ];
            }

            $this->balances->cancelApprovedAnnualLeave($actorId, (int) $revision->reservation_id, $actionId, (int) $assignment->id, $operationKey.':balance');
            DB::table('leave_requests')->where('id', $requestId)->update([
                'status' => 'IN_PROCESS_CANCELLATION',
                'current_step_order' => null,
                'updated_at' => now(),
            ]);
            DB::table('leave_requests')->where('id', $requestId)->update([
                'status' => 'CANCELLED',
                'current_step_order' => null,
                'updated_at' => now(),
            ]);
            $this->audit('hr.leave.cancellation_confirmed', $requestId, $actorId, $operationKey);

            return [
                'request_id' => $requestId,
                'status' => 'CANCELLED',
                'current_step_order' => null,
                'reservation_id' => (int) $revision->reservation_id,
                'replayed' => false,
            ];
        }, 3);
    }

    /** @return array{request_id:int,status:string,current_step_order:null,reservation_id:int,partial_cancellation_id:int,replayed:bool} */
    public function requestApprovedAnnualLeavePartialCancellation(int $requesterUserId, int $requestId, string $operationKey, string $reason): array
    {
        $this->assertLocal();
        $this->assertOperationKey($operationKey);
        $reason = $this->normalizeReason($reason);
        if ($requesterUserId < 1 || $requestId < 1 || $reason === null) {
            throw new DomainException('The linked employee and a reason are required to request partial cancellation.');
        }

        return DB::transaction(function () use ($requesterUserId, $requestId, $operationKey, $reason): array {
            $this->lockCancellationGuards();
            $candidate = DB::table('leave_requests')->where('id', $requestId)->first(['employee_id']);
            if ($candidate === null) {
                throw new DomainException('The leave request does not exist.');
            }
            $employee = DB::table('employees')->where('id', $candidate->employee_id)->lockForUpdate()->first();
            $request = DB::table('leave_requests')->where('id', $requestId)->lockForUpdate()->first();
            if ($employee === null || $request === null) {
                throw new DomainException('A consistent employee request is required.');
            }
            $existing = DB::table('leave_request_partial_cancellations')->where('request_operation_key', $operationKey)->lockForUpdate()->first();
            if ($existing !== null) {
                $expectedHash = $this->partialCancellationPayloadHash('REQUESTED', $requesterUserId, $requestId, (int) $existing->revision_id,
                    (int) $existing->cycle, null, $reason, null, null);
                if ((int) $existing->request_id !== $requestId || (int) $existing->requested_by !== $requesterUserId
                    || ! hash_equals($existing->request_payload_hash, $expectedHash)) {
                    throw new DomainException('The partial cancellation request key was already used for a different action.');
                }

                return [
                    'request_id' => $requestId,
                    'status' => (string) $request->status,
                    'current_step_order' => null,
                    'reservation_id' => $this->currentReservationId($requestId, (int) $request->current_revision),
                    'partial_cancellation_id' => (int) $existing->id,
                    'replayed' => true,
                ];
            }
            $this->assertPartialCancellationOperationKeyAvailable($operationKey);
            $this->assertActiveEmployeeRequester($requesterUserId, $employee, $request);
            if (DB::table('leave_request_partial_cancellations')->where('request_id', $requestId)
                ->whereIn('status', ['REQUESTED', 'HR_VERIFIED'])->exists()) {
                throw new DomainException('A partial cancellation review is already open for this leave request.');
            }
            if ($request->leave_type !== 'AL' || $request->status !== 'APPROVED' || $request->current_step_order !== null) {
                throw new DomainException('Only an approved Annual Leave request can use partial cancellation.');
            }
            $revision = DB::table('leave_request_revisions')->where('request_id', $requestId)
                ->where('revision', $request->current_revision)->lockForUpdate()->first();
            if ($revision === null) {
                throw new RuntimeException('The current leave request revision is missing.');
            }
            $dates = DB::table('leave_request_days')->where('revision_id', $revision->id)->orderBy('leave_date')->pluck('leave_date')->all();
            $today = now('Asia/Kuala_Lumpur')->toDateString();
            if ($dates === [] || ! collect($dates)->contains(fn (string $date): bool => $date <= $today)
                || ! collect($dates)->contains(fn (string $date): bool => $date > $today)) {
                throw new DomainException('Partial cancellation is limited to an approved leave period that has started and not yet ended.');
            }
            $reservation = DB::table('leave_reservations')->where('id', $revision->reservation_id)->lockForUpdate()->first();
            if ($reservation === null || $reservation->status !== 'COMMITTED' || (int) $reservation->annual_units < 1) {
                throw new DomainException('A committed approved Annual Leave reservation is required.');
            }
            if ((int) $reservation->tabung_units !== 0) {
                throw new DomainException('Partial cancellation of Tabung-funded leave is deferred until the current entitlement cap and source split can be verified.');
            }

            $cycle = (int) DB::table('leave_request_partial_cancellations')->where('request_id', $requestId)->max('cycle') + 1;
            $payloadHash = $this->partialCancellationPayloadHash('REQUESTED', $requesterUserId, $requestId, (int) $revision->id,
                $cycle, null, $reason, null, null);
            $partialCancellationId = DB::table('leave_request_partial_cancellations')->insertGetId([
                'request_id' => $requestId,
                'revision_id' => $revision->id,
                'cycle' => $cycle,
                'status' => 'REQUESTED',
                'requested_by' => $requesterUserId,
                'request_reason' => $reason,
                'request_operation_key' => $operationKey,
                'request_payload_hash' => $payloadHash,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->recordPartialCancellationEvent($partialCancellationId, 'REQUESTED', $requesterUserId, null, $reason, null, null, $operationKey, $payloadHash);
            $this->audit('hr.leave.partial_cancellation_requested', $requestId, $requesterUserId, $operationKey);

            return [
                'request_id' => $requestId,
                'status' => 'APPROVED',
                'current_step_order' => null,
                'reservation_id' => (int) $reservation->id,
                'partial_cancellation_id' => $partialCancellationId,
                'replayed' => false,
            ];
        }, 3);
    }

    /** @return array{request_id:int,status:string,current_step_order:null,reservation_id:int,partial_cancellation_id:int,actual_return_date:string,unused_half_day_units:int,replayed:bool} */
    public function verifyApprovedAnnualLeavePartialCancellation(int $hrActorId, int $requestId, int $partialCancellationId, string $operationKey, string $actualReturnDate, string $reason): array
    {
        $this->assertLocal();
        $this->assertOperationKey($operationKey);
        $actualReturnDate = $this->parseDate($actualReturnDate)->format('Y-m-d');
        $reason = $this->normalizeReason($reason);
        if ($hrActorId < 1 || $requestId < 1 || $partialCancellationId < 1 || $reason === null) {
            throw new DomainException('An active HR reviewer, actual return date, and review reason are required.');
        }

        return DB::transaction(function () use ($hrActorId, $requestId, $partialCancellationId, $operationKey, $actualReturnDate, $reason): array {
            $this->lockCancellationGuards();
            $candidate = DB::table('leave_requests')->where('id', $requestId)->first(['employee_id']);
            if ($candidate === null) {
                throw new DomainException('The leave request does not exist.');
            }
            $employee = DB::table('employees')->where('id', $candidate->employee_id)->lockForUpdate()->first();
            $request = DB::table('leave_requests')->where('id', $requestId)->lockForUpdate()->first();
            $partialCancellation = DB::table('leave_request_partial_cancellations')->where('id', $partialCancellationId)
                ->where('request_id', $requestId)->lockForUpdate()->first();
            if ($employee === null || $request === null || $partialCancellation === null) {
                throw new DomainException('A current partial cancellation request is required.');
            }
            $existingEvent = DB::table('leave_request_partial_cancellation_events')->where('operation_key', $operationKey)->lockForUpdate()->first();
            if ($existingEvent !== null) {
                $expectedHash = $this->partialCancellationPayloadHash('HR_VERIFIED', $hrActorId, $requestId, (int) $partialCancellation->revision_id,
                    (int) $partialCancellation->cycle, null, $reason, $actualReturnDate, (int) $existingEvent->unused_half_day_units);
                if ((int) $existingEvent->partial_cancellation_id !== $partialCancellationId || $existingEvent->event_code !== 'HR_VERIFIED'
                    || (int) $existingEvent->actor_id !== $hrActorId || $existingEvent->actual_return_date !== $actualReturnDate
                    || $existingEvent->reason !== $reason || ! hash_equals($existingEvent->payload_hash, $expectedHash)) {
                    throw new DomainException('The HR verification key was already used for a different review.');
                }

                return [
                    'request_id' => $requestId,
                    'status' => (string) $request->status,
                    'current_step_order' => null,
                    'reservation_id' => $this->currentReservationId($requestId, (int) $request->current_revision),
                    'partial_cancellation_id' => $partialCancellationId,
                    'actual_return_date' => $actualReturnDate,
                    'unused_half_day_units' => (int) $existingEvent->unused_half_day_units,
                    'replayed' => true,
                ];
            }
            $this->assertPartialCancellationOperationKeyAvailable($operationKey);
            $hrActor = User::query()->whereKey($hrActorId)->lockForUpdate()->first();
            if ($hrActor === null || $hrActor->status !== 'ACTIVE' || $hrActor->username === null
                || ! $hrActor->can('leave.partial-cancellation.review') || $hrActorId === (int) $employee->user_id) {
                throw new DomainException('An active, separately authorized HR reviewer is required.');
            }
            $currentRevisionId = (int) DB::table('leave_request_revisions')->where('request_id', $requestId)
                ->where('revision', $request->current_revision)->value('id');
            if ($request->leave_type !== 'AL' || $request->status !== 'APPROVED'
                || $currentRevisionId !== (int) $partialCancellation->revision_id
                || $partialCancellation->status !== 'REQUESTED') {
                throw new DomainException('Only a current, undecided Annual Leave partial cancellation can be reviewed.');
            }
            $revision = DB::table('leave_request_revisions')->where('id', $partialCancellation->revision_id)
                ->where('request_id', $requestId)->lockForUpdate()->first();
            if ($revision === null) {
                throw new RuntimeException('The partial cancellation revision is missing.');
            }
            $slots = DB::table('leave_request_days')->where('revision_id', $revision->id)->orderBy('leave_date')->get(['leave_date', 'session_code']);
            $dates = $slots->pluck('leave_date')->unique()->values();
            $today = now('Asia/Kuala_Lumpur')->toDateString();
            if ($dates->isEmpty() || ! $dates->contains(fn (string $date): bool => $date <= $today)
                || ! $dates->contains(fn (string $date): bool => $date > $today)
                || $actualReturnDate > $today || $actualReturnDate < (string) $dates->first()
                || $actualReturnDate >= (string) $dates->last() || $slots->contains(fn (object $slot): bool => $slot->leave_date === $actualReturnDate)) {
                throw new DomainException('The verified return date must be during the active leave period and cannot share a requested half-day date.');
            }
            $unusedUnits = $slots->filter(fn (object $slot): bool => $slot->leave_date > $actualReturnDate)->count();
            if ($unusedUnits < 1) {
                throw new DomainException('At least one unused future half-day session must remain after the verified return date.');
            }
            $reservation = DB::table('leave_reservations')->where('id', $revision->reservation_id)->lockForUpdate()->first();
            if ($reservation === null || $reservation->status !== 'COMMITTED' || (int) $reservation->tabung_units !== 0
                || (int) $reservation->annual_units < $unusedUnits) {
                throw new DomainException('Only a committed Annual-funded reservation with enough refundable units is supported.');
            }
            $payloadHash = $this->partialCancellationPayloadHash('HR_VERIFIED', $hrActorId, $requestId, (int) $revision->id,
                (int) $partialCancellation->cycle, null, $reason, $actualReturnDate, $unusedUnits);
            DB::table('leave_request_partial_cancellations')->where('id', $partialCancellationId)->update([
                'status' => 'HR_VERIFIED',
                'actual_return_date' => $actualReturnDate,
                'unused_half_day_units' => $unusedUnits,
                'hr_verified_by' => $hrActorId,
                'hr_reason' => $reason,
                'hr_operation_key' => $operationKey,
                'hr_payload_hash' => $payloadHash,
                'verified_at' => now(),
                'updated_at' => now(),
            ]);
            $this->recordPartialCancellationEvent($partialCancellationId, 'HR_VERIFIED', $hrActorId, null, $reason, $actualReturnDate, $unusedUnits, $operationKey, $payloadHash);
            $this->audit('hr.leave.partial_cancellation_hr_verified', $requestId, $hrActorId, $operationKey);

            return [
                'request_id' => $requestId,
                'status' => 'APPROVED',
                'current_step_order' => null,
                'reservation_id' => (int) $reservation->id,
                'partial_cancellation_id' => $partialCancellationId,
                'actual_return_date' => $actualReturnDate,
                'unused_half_day_units' => $unusedUnits,
                'replayed' => false,
            ];
        }, 3);
    }

    /** @return array{request_id:int,status:string,current_step_order:null,reservation_id:int,refunded_half_day_units:int,replayed:bool} */
    public function decideApprovedAnnualLeavePartialCancellation(int $actorId, int $requestId, int $partialCancellationId, string $operationKey, string $decision, ?string $reason = null): array
    {
        $this->assertLocal();
        $this->assertOperationKey($operationKey);
        if (! in_array($decision, ['CONFIRM', 'REJECT'], true) || $actorId < 1 || $requestId < 1 || $partialCancellationId < 1) {
            throw new DomainException('A current General Manager and a valid partial cancellation decision are required.');
        }
        $reason = $this->normalizeReason($reason);

        return DB::transaction(function () use ($actorId, $requestId, $partialCancellationId, $operationKey, $decision, $reason): array {
            $this->lockCancellationGuards();
            $candidate = DB::table('leave_requests')->where('id', $requestId)->first(['employee_id']);
            if ($candidate === null) {
                throw new DomainException('The leave request does not exist.');
            }
            $employee = DB::table('employees')->where('id', $candidate->employee_id)->lockForUpdate()->first();
            $request = DB::table('leave_requests')->where('id', $requestId)->lockForUpdate()->first();
            $partialCancellation = DB::table('leave_request_partial_cancellations')->where('id', $partialCancellationId)
                ->where('request_id', $requestId)->lockForUpdate()->first();
            if ($employee === null || $request === null || $partialCancellation === null) {
                throw new DomainException('A current partial cancellation request is required.');
            }
            $eventCode = $decision === 'CONFIRM' ? 'CONFIRMED' : 'REJECTED';
            $existingEvent = DB::table('leave_request_partial_cancellation_events')->where('operation_key', $operationKey)->lockForUpdate()->first();
            if ($existingEvent !== null) {
                $expectedHash = $this->partialCancellationPayloadHash($eventCode, $actorId, $requestId, (int) $partialCancellation->revision_id,
                    (int) $partialCancellation->cycle, (int) $existingEvent->assignment_id, $reason, null, null);
                if ((int) $existingEvent->partial_cancellation_id !== $partialCancellationId || $existingEvent->event_code !== $eventCode
                    || (int) $existingEvent->actor_id !== $actorId || $existingEvent->assignment_id === null
                    || ! hash_equals($existingEvent->payload_hash, $expectedHash)) {
                    throw new DomainException('The partial cancellation decision key was already used for a different action.');
                }

                return [
                    'request_id' => $requestId,
                    'status' => (string) $request->status,
                    'current_step_order' => null,
                    'reservation_id' => $this->currentReservationId($requestId, (int) $request->current_revision),
                    'refunded_half_day_units' => $eventCode === 'CONFIRMED' ? (int) $partialCancellation->unused_half_day_units : 0,
                    'replayed' => true,
                ];
            }
            $this->assertPartialCancellationOperationKeyAvailable($operationKey);
            $currentRevisionId = (int) DB::table('leave_request_revisions')->where('request_id', $requestId)
                ->where('revision', $request->current_revision)->value('id');
            if ($request->leave_type !== 'AL' || $request->status !== 'APPROVED'
                || $currentRevisionId !== (int) $partialCancellation->revision_id
                || $partialCancellation->status !== 'HR_VERIFIED' || (int) $employee->user_id === $actorId) {
                throw new DomainException('A current HR-verified partial cancellation requires a distinct General Manager decision.');
            }
            $this->assertActiveGeneralManager($actorId);
            $assignment = $this->currentGeneralManagerAssignment($actorId);
            $payloadHash = $this->partialCancellationPayloadHash($eventCode, $actorId, $requestId, (int) $partialCancellation->revision_id,
                (int) $partialCancellation->cycle, (int) $assignment->id, $reason, null, null);
            DB::table('leave_request_partial_cancellations')->where('id', $partialCancellationId)->update([
                'status' => $eventCode,
                'general_manager_user_id' => $actorId,
                'general_manager_assignment_id' => $assignment->id,
                'decision_reason' => $reason,
                'decision_operation_key' => $operationKey,
                'decision_payload_hash' => $payloadHash,
                'decided_at' => now(),
                'updated_at' => now(),
            ]);
            $this->recordPartialCancellationEvent($partialCancellationId, $eventCode, $actorId, (int) $assignment->id, $reason, null, null, $operationKey, $payloadHash);
            $refundedUnits = 0;
            if ($decision === 'CONFIRM') {
                $balanceKey = 'partial-balance:'.hash('sha256', $operationKey);
                $result = $this->balances->refundPartiallyCancelledAnnualLeave(
                    $actorId,
                    $this->currentReservationId($requestId, (int) $request->current_revision),
                    $partialCancellationId,
                    (int) $assignment->id,
                    $balanceKey,
                );
                $refundedUnits = $result['refunded_units'];
            }
            $this->audit($decision === 'CONFIRM' ? 'hr.leave.partial_cancellation_confirmed' : 'hr.leave.partial_cancellation_rejected',
                $requestId, $actorId, $operationKey);

            return [
                'request_id' => $requestId,
                'status' => 'APPROVED',
                'current_step_order' => null,
                'reservation_id' => $this->currentReservationId($requestId, (int) $request->current_revision),
                'refunded_half_day_units' => $refundedUnits,
                'replayed' => false,
            ];
        }, 3);
    }

    private function lockCancellationGuards(): void
    {
        foreach (['leave_calendar_confirmation_guard', 'leave_working_schedule_guard', 'office_assignment_guard'] as $table) {
            if (! Schema::hasTable($table) || DB::table($table)->where('id', 1)->lockForUpdate()->first() === null) {
                throw new RuntimeException('Leave calendar, schedule, and office assignment guards are required for cancellation.');
            }
        }
    }

    private function assertAllDatesAreFuture(int $revisionId, string $message): void
    {
        $dates = DB::table('leave_request_days')->where('revision_id', $revisionId)->pluck('leave_date')->all();
        $today = now('Asia/Kuala_Lumpur')->toDateString();
        if ($dates === [] || collect($dates)->contains(fn (string $date): bool => $date <= $today)) {
            throw new DomainException($message);
        }
    }

    private function assertActiveGeneralManager(int $actorId): void
    {
        $actor = DB::table('users')->where('id', $actorId)->lockForUpdate()->first(['status', 'username']);
        if ($actor === null || $actor->status !== 'ACTIVE' || $actor->username === null) {
            throw new DomainException('An active General Manager account is required for cancellation.');
        }
    }

    private function currentGeneralManagerAssignment(int $actorId): stdClass
    {
        $today = now('Asia/Kuala_Lumpur')->toDateString();
        $assignments = DB::table('approval_assignments')->where('office_code', 'PB')
            ->where('effective_from', '<=', $today)
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>=', $today))
            ->orderBy('id')->lockForUpdate()->get(['id', 'user_id']);
        $assignment = $assignments->firstWhere('user_id', $actorId);
        if ($assignments->count() !== 1 || $assignment === null) {
            throw new DomainException('Exactly one current General Manager assignment is required for cancellation.');
        }

        return $assignment;
    }

    private function assertCancellationOperationKeyAvailable(string $operationKey): void
    {
        if (DB::table('leave_workflow_actions')->where('operation_key', $operationKey)->exists()
            || DB::table('leave_request_withdrawals')->where('operation_key', $operationKey)->exists()
            || DB::table('leave_balance_operations')->where('operation_key', $operationKey)->exists()) {
            throw new DomainException('The operation key was already used for a different leave transaction.');
        }
    }

    private function assertPartialCancellationOperationKeyAvailable(string $operationKey): void
    {
        $partialKeyExists = DB::table('leave_request_partial_cancellations')->where(function ($query) use ($operationKey): void {
            $query->where('request_operation_key', $operationKey)->orWhere('hr_operation_key', $operationKey)->orWhere('decision_operation_key', $operationKey);
        })->exists();
        if ($partialKeyExists || DB::table('leave_request_partial_cancellation_events')->where('operation_key', $operationKey)->exists()
            || DB::table('leave_request_cancellation_actions')->where('operation_key', $operationKey)->exists()
            || DB::table('leave_workflow_actions')->where('operation_key', $operationKey)->exists()
            || DB::table('leave_request_withdrawals')->where('operation_key', $operationKey)->exists()
            || DB::table('leave_balance_operations')->where('operation_key', $operationKey)->exists()) {
            throw new DomainException('The operation key was already used for a different leave transaction.');
        }
    }

    private function assertActiveEmployeeRequester(int $requesterUserId, stdClass $employee, stdClass $request): void
    {
        $actor = DB::table('users')->where('id', $requesterUserId)->lockForUpdate()->first(['status', 'username']);
        if ((int) $employee->user_id !== $requesterUserId || (int) $request->requester_user_id !== $requesterUserId
            || $actor === null || $actor->status !== 'ACTIVE' || $actor->username === null) {
            throw new DomainException('Only the linked active employee can request partial cancellation of their own leave.');
        }
    }

    private function partialCancellationPayloadHash(
        string $eventCode,
        int $actorId,
        int $requestId,
        int $revisionId,
        int $cycle,
        ?int $assignmentId,
        ?string $reason,
        ?string $actualReturnDate,
        ?int $unusedUnits,
    ): string {
        return $this->hash([
            'action_code' => $eventCode,
            'actor_id' => $actorId,
            'actual_return_date' => $actualReturnDate,
            'assignment_id' => $assignmentId,
            'cycle' => $cycle,
            'reason' => $reason,
            'request_id' => $requestId,
            'revision_id' => $revisionId,
            'unused_half_day_units' => $unusedUnits,
        ]);
    }

    private function recordPartialCancellationEvent(
        int $partialCancellationId,
        string $eventCode,
        int $actorId,
        ?int $assignmentId,
        ?string $reason,
        ?string $actualReturnDate,
        ?int $unusedUnits,
        string $operationKey,
        string $payloadHash,
    ): void {
        DB::table('leave_request_partial_cancellation_events')->insert([
            'partial_cancellation_id' => $partialCancellationId,
            'event_code' => $eventCode,
            'actor_id' => $actorId,
            'assignment_id' => $assignmentId,
            'reason' => $reason,
            'actual_return_date' => $actualReturnDate,
            'unused_half_day_units' => $unusedUnits,
            'operation_key' => $operationKey,
            'payload_hash' => $payloadHash,
            'created_at' => now(),
        ]);
    }

    private function cancellationPayloadHash(string $actionCode, int $actorId, int $requestId, int $revisionId, int $cycle, ?int $assignmentId, ?string $reason): string
    {
        return $this->hash([
            'action_code' => $actionCode,
            'actor_id' => $actorId,
            'assignment_id' => $assignmentId,
            'cycle' => $cycle,
            'reason' => $reason,
            'request_id' => $requestId,
            'revision_id' => $revisionId,
        ]);
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
