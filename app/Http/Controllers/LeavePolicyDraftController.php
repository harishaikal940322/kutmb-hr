<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Services\Leave\ConfirmedVersionLookup;
use App\Services\Leave\VersionPeriodOverlap;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LeavePolicyDraftController extends Controller
{
    public function index(VersionPeriodOverlap $overlap): Response
    {
        $versions = DB::table('leave_policy_versions')->orderByDesc('effective_from')->orderByDesc('id')->get();
        $versions->transform(function ($version) {
            $version->working_weekdays = $version->working_weekdays === null ? null : json_decode($version->working_weekdays, true, 512, JSON_THROW_ON_ERROR);

            return $version;
        });

        $history = DB::table('leave_policy_history')->orderByDesc('id')->get(['id', 'policy_version_id', 'previous_state', 'created_at']);
        $history->transform(function ($entry) {
            $entry->previous_state = json_decode($entry->previous_state, true, 512, JSON_THROW_ON_ERROR);

            return $entry;
        });

        return Inertia::render('leave/PolicyDrafts', [
            'versions' => $overlap->annotate($versions), 'history' => $history,
            'categories' => DB::table('employment_categories')->where('active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'entitlementDrafts' => DB::table('leave_entitlement_rule_drafts')->join('employment_categories', 'employment_categories.id', '=', 'leave_entitlement_rule_drafts.category_id')->orderBy('band_description')->get(['leave_entitlement_rule_drafts.id', 'policy_version_id', 'category_id', 'band_description', 'annual_half_day_units', 'leave_entitlement_rule_drafts.cancelled_at', 'leave_entitlement_rule_drafts.cancelled_by', 'employment_categories.name as category_name']),
            'entitlementHistory' => DB::table('leave_entitlement_draft_history')->join('employment_categories', 'employment_categories.id', '=', 'leave_entitlement_draft_history.previous_category_id')->orderByDesc('leave_entitlement_draft_history.id')->get(['leave_entitlement_draft_history.id', 'entitlement_draft_id', 'previous_band_description', 'previous_annual_half_day_units', 'leave_entitlement_draft_history.created_at', 'employment_categories.name as previous_category_name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedInput($request);
        DB::transaction(function () use ($request, $data): void {
            $weekdays = $data['working_weekdays'] ?? null;
            if ($weekdays !== null) {
                $weekdays = array_map('intval', $weekdays);
                sort($weekdays);
            }
            $id = DB::table('leave_policy_versions')->insertGetId([
                'label' => $data['label'], 'effective_from' => $data['effective_from'], 'effective_to' => $data['effective_to'] ?? null,
                'working_weekdays' => $weekdays === null ? null : json_encode($weekdays, JSON_THROW_ON_ERROR),
                'work_start' => $data['work_start'] ?? null, 'session_boundary' => $data['session_boundary'] ?? null, 'work_end' => $data['work_end'] ?? null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            AuditEvent::query()->create([
                'occurred_at' => now(), 'actor_id' => $request->user()->getKey(), 'action_code' => 'leave.policy.draft_created',
                'target_ref' => 'policy_version:'.$id, 'outcome' => 'SUCCESS',
                'metadata' => ['source' => 'policy_draft_ui', 'changed_fields' => ['policy_version']],
            ]);
        });

        return to_route('leave.policy.drafts.index')->with('success', 'Draf dasar ditambah.');
    }

    public function update(Request $request, int $versionId): RedirectResponse
    {
        $data = $this->validatedInput($request);
        DB::transaction(function () use ($request, $versionId, $data): void {
            $version = DB::table('leave_policy_versions')->where('id', $versionId)->lockForUpdate()->first();
            abort_if($version === null, 404);
            if ($version->confirmed_at !== null || $version->confirmed_by !== null || $version->schedule_simulated_at !== null || $version->schedule_simulated_by !== null || $version->cancelled_at !== null || $version->cancelled_by !== null) {
                throw ValidationException::withMessages(['label' => 'Dasar yang disahkan tidak boleh diubah.']);
            }
            $previous = [
                'label' => $version->label, 'effective_from' => $version->effective_from, 'effective_to' => $version->effective_to,
                'working_weekdays' => $version->working_weekdays === null ? null : json_decode($version->working_weekdays, true, 512, JSON_THROW_ON_ERROR),
                'work_start' => $version->work_start, 'session_boundary' => $version->session_boundary, 'work_end' => $version->work_end,
            ];
            $weekdays = $data['working_weekdays'] ?? null;
            if ($weekdays !== null) {
                $weekdays = array_map('intval', $weekdays);
                sort($weekdays);
            }
            DB::table('leave_policy_history')->insert([
                'policy_version_id' => $versionId, 'previous_state' => json_encode($previous, JSON_THROW_ON_ERROR),
                'actor_id' => $request->user()->getKey(), 'created_at' => now(),
            ]);
            DB::table('leave_policy_versions')->where('id', $versionId)->update([
                'label' => $data['label'], 'effective_from' => $data['effective_from'], 'effective_to' => $data['effective_to'] ?? null,
                'working_weekdays' => $weekdays === null ? null : json_encode($weekdays, JSON_THROW_ON_ERROR),
                'work_start' => $data['work_start'] ?? null, 'session_boundary' => $data['session_boundary'] ?? null,
                'work_end' => $data['work_end'] ?? null, 'updated_at' => now(),
            ]);
            AuditEvent::query()->create([
                'occurred_at' => now(), 'actor_id' => $request->user()->getKey(), 'action_code' => 'leave.policy.draft_updated',
                'target_ref' => 'policy_version:'.$versionId, 'outcome' => 'SUCCESS',
                'metadata' => ['source' => 'policy_draft_ui', 'changed_fields' => ['policy_version']],
            ]);
        }, 3);

        return to_route('leave.policy.drafts.index')->with('success', 'Draf dasar dikemas kini.');
    }

    public function storeEntitlementDraft(Request $request, int $versionId): RedirectResponse
    {
        $data = $this->validatedEntitlement($request);
        try {
            DB::transaction(function () use ($request, $versionId, $data): void {
                $version = DB::table('leave_policy_versions')->where('id', $versionId)->lockForUpdate()->first();
                abort_if($version === null, 404);
                if ($version->confirmed_at !== null || $version->confirmed_by !== null || $version->schedule_simulated_at !== null || $version->schedule_simulated_by !== null || $version->cancelled_at !== null || $version->cancelled_by !== null) {
                    throw ValidationException::withMessages(['band_description' => 'Dasar yang disahkan tidak boleh diubah.']);
                }
                $category = DB::table('employment_categories')->where('id', $data['category_id'])->lockForUpdate()->first();
                if (! $category || ! $category->active) {
                    throw ValidationException::withMessages(['category_id' => 'Pilih kategori aktif.']);
                }
                $id = DB::table('leave_entitlement_rule_drafts')->insertGetId([
                    'policy_version_id' => $versionId, 'category_id' => $category->id, 'band_description' => $data['band_description'],
                    'annual_half_day_units' => $data['annual_half_day_units'], 'created_at' => now(), 'updated_at' => now(),
                ]);
                AuditEvent::query()->create([
                    'occurred_at' => now(), 'actor_id' => $request->user()->getKey(), 'action_code' => 'leave.entitlement.drafted',
                    'target_ref' => 'entitlement_draft:'.$id, 'outcome' => 'SUCCESS',
                    'metadata' => ['source' => 'policy_draft_ui', 'changed_fields' => ['entitlement_rule']],
                ]);
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['band_description' => 'Label ini telah wujud bagi kategori dan versi tersebut.']);
        }

        return to_route('leave.policy.drafts.index')->with('success', 'Cadangan kelayakan ditambah ke draf.');
    }

    public function updateEntitlementDraft(Request $request, int $versionId, int $ruleId): RedirectResponse
    {
        $data = $this->validatedEntitlement($request);
        try {
            DB::transaction(function () use ($request, $versionId, $ruleId, $data): void {
                $version = DB::table('leave_policy_versions')->where('id', $versionId)->lockForUpdate()->first();
                abort_if($version === null, 404);
                if ($version->confirmed_at !== null || $version->confirmed_by !== null || $version->schedule_simulated_at !== null || $version->schedule_simulated_by !== null || $version->cancelled_at !== null || $version->cancelled_by !== null) {
                    throw ValidationException::withMessages(['band_description' => 'Dasar yang disahkan tidak boleh diubah.']);
                }
                $rule = DB::table('leave_entitlement_rule_drafts')->where('id', $ruleId)->where('policy_version_id', $versionId)->lockForUpdate()->first();
                abort_if($rule === null, 404);
                if ($rule->cancelled_at !== null || $rule->cancelled_by !== null) {
                    throw ValidationException::withMessages(['band_description' => 'Pulihkan cadangan sebelum menyunting.']);
                }
                $category = DB::table('employment_categories')->where('id', $data['category_id'])->lockForUpdate()->first();
                if (! $category || ! $category->active) {
                    throw ValidationException::withMessages(['category_id' => 'Pilih kategori aktif.']);
                }
                DB::table('leave_entitlement_draft_history')->insert([
                    'entitlement_draft_id' => $ruleId, 'previous_category_id' => $rule->category_id,
                    'previous_band_description' => $rule->band_description, 'previous_annual_half_day_units' => $rule->annual_half_day_units,
                    'actor_id' => $request->user()->getKey(), 'created_at' => now(),
                ]);
                DB::table('leave_entitlement_rule_drafts')->where('id', $ruleId)->update([
                    'category_id' => $category->id, 'band_description' => $data['band_description'],
                    'annual_half_day_units' => $data['annual_half_day_units'], 'updated_at' => now(),
                ]);
                AuditEvent::query()->create([
                    'occurred_at' => now(), 'actor_id' => $request->user()->getKey(), 'action_code' => 'leave.entitlement.draft_updated',
                    'target_ref' => 'entitlement_draft:'.$ruleId, 'outcome' => 'SUCCESS',
                    'metadata' => ['source' => 'policy_draft_ui', 'changed_fields' => ['entitlement_rule']],
                ]);
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['band_description' => 'Label ini telah wujud bagi kategori dan versi tersebut.']);
        }

        return to_route('leave.policy.drafts.index')->with('success', 'Cadangan kelayakan dikemas kini.');
    }

    public function setEntitlementCancellation(Request $request, int $versionId, int $ruleId): RedirectResponse
    {
        $data = $request->validate(['cancelled' => ['required', 'boolean']]);
        $cancelled = (bool) $data['cancelled'];
        DB::transaction(function () use ($request, $versionId, $ruleId, $cancelled): void {
            $version = DB::table('leave_policy_versions')->where('id', $versionId)->lockForUpdate()->first();
            abort_if($version === null, 404);
            if ($version->confirmed_at !== null || $version->confirmed_by !== null || $version->schedule_simulated_at !== null || $version->schedule_simulated_by !== null || $version->cancelled_at !== null || $version->cancelled_by !== null) {
                throw ValidationException::withMessages(['cancelled' => 'Dasar yang disahkan tidak boleh diubah.']);
            }
            $rule = DB::table('leave_entitlement_rule_drafts')->where('id', $ruleId)->where('policy_version_id', $versionId)->lockForUpdate()->first();
            abort_if($rule === null, 404);
            $isCancelled = $rule->cancelled_at !== null || $rule->cancelled_by !== null;
            if ($isCancelled === $cancelled) {
                throw ValidationException::withMessages(['cancelled' => 'Status cadangan ini telah berubah.']);
            }
            DB::table('leave_entitlement_rule_drafts')->where('id', $ruleId)->update([
                'cancelled_at' => $cancelled ? now() : null, 'cancelled_by' => $cancelled ? $request->user()->getKey() : null,
                'updated_at' => now(),
            ]);
            AuditEvent::query()->create([
                'occurred_at' => now(), 'actor_id' => $request->user()->getKey(),
                'action_code' => $cancelled ? 'leave.entitlement.draft_cancelled' : 'leave.entitlement.draft_restored',
                'target_ref' => 'entitlement_draft:'.$ruleId, 'outcome' => 'SUCCESS',
                'metadata' => ['source' => 'policy_draft_ui', 'changed_fields' => ['entitlement_rule']],
            ]);
        }, 3);

        return to_route('leave.policy.drafts.index')->with('success', 'Status cadangan dikemas kini.');
    }

    public function setVersionCancellation(Request $request, int $versionId): RedirectResponse
    {
        $data = $request->validate(['cancelled' => ['required', 'boolean']]);
        $cancelled = (bool) $data['cancelled'];
        DB::transaction(function () use ($request, $versionId, $cancelled): void {
            $version = DB::table('leave_policy_versions')->where('id', $versionId)->lockForUpdate()->first();
            abort_if($version === null, 404);
            if ($version->confirmed_at !== null || $version->confirmed_by !== null || $version->schedule_simulated_at !== null || $version->schedule_simulated_by !== null) {
                throw ValidationException::withMessages(['cancelled' => 'Versi yang disahkan tidak boleh diubah.']);
            }
            $isCancelled = $version->cancelled_at !== null || $version->cancelled_by !== null;
            if ($isCancelled === $cancelled) {
                throw ValidationException::withMessages(['cancelled' => 'Status versi ini telah berubah.']);
            }
            DB::table('leave_policy_versions')->where('id', $versionId)->update([
                'cancelled_at' => $cancelled ? now() : null, 'cancelled_by' => $cancelled ? $request->user()->getKey() : null,
                'updated_at' => now(),
            ]);
            AuditEvent::query()->create([
                'occurred_at' => now(), 'actor_id' => $request->user()->getKey(),
                'action_code' => $cancelled ? 'leave.policy.version_cancelled' : 'leave.policy.version_restored',
                'target_ref' => 'policy_version:'.$versionId, 'outcome' => 'SUCCESS',
                'metadata' => ['source' => 'policy_draft_ui', 'changed_fields' => ['policy_version']],
            ]);
        }, 3);

        return to_route('leave.policy.drafts.index')->with('success', 'Status draf dikemas kini.');
    }

    public function confirmScheduleSimulation(Request $request, int $versionId, ConfirmedVersionLookup $lookup): RedirectResponse
    {
        $request->validate(['acknowledge_schedule' => ['accepted']]);
        if (Schema::hasTable('leave_requests')) {
            throw ValidationException::withMessages(['acknowledge_schedule' => 'Simulasi ditangguhkan sehingga kesan permohonan disokong.']);
        }
        DB::transaction(function () use ($request, $versionId, $lookup): void {
            abort_if(DB::table('leave_working_schedule_guard')->where('id', 1)->lockForUpdate()->first() === null, 503);
            $version = DB::table('leave_policy_versions')->where('id', $versionId)->lockForUpdate()->first();
            abort_if($version === null, 404);
            if ($version->confirmed_at !== null || $version->confirmed_by !== null || $version->schedule_simulated_at !== null || $version->schedule_simulated_by !== null || $version->cancelled_at !== null || $version->cancelled_by !== null || $version->effective_to === null || $version->effective_to < $version->effective_from) {
                throw ValidationException::withMessages(['acknowledge_schedule' => 'Pilih draf aktif dengan tempoh mula dan tamat yang sah.']);
            }
            $others = DB::table('leave_policy_versions')->where('id', '!=', $versionId)->where('effective_from', '<=', $version->effective_to)
                ->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>=', $version->effective_from))->get();
            foreach ($others as $other) {
                if (($other->schedule_simulated_at === null) !== ($other->schedule_simulated_by === null)
                    || ($other->confirmed_at === null) !== ($other->confirmed_by === null)
                    || ($other->cancelled_at === null) !== ($other->cancelled_by === null)
                    || $other->schedule_simulated_at !== null || $other->confirmed_at !== null) {
                    throw ValidationException::withMessages(['acknowledge_schedule' => 'Tempoh bertindih dengan jadual simulasi atau versi tidak konsisten.']);
                }
            }
            DB::table('leave_policy_versions')->where('id', $versionId)->update([
                'schedule_simulated_by' => $request->user()->getKey(), 'schedule_simulated_at' => now(), 'updated_at' => now(),
            ]);
            try {
                $lookup->workingScheduleFor($version->effective_from);
            } catch (\RuntimeException) {
                throw ValidationException::withMessages(['acknowledge_schedule' => 'Hari bekerja dan masa sesi mesti lengkap dan sah.']);
            }
            AuditEvent::query()->create([
                'occurred_at' => now(), 'actor_id' => $request->user()->getKey(), 'action_code' => 'leave.policy.schedule_simulated',
                'target_ref' => 'policy_version:'.$versionId, 'outcome' => 'SUCCESS',
                'metadata' => ['source' => 'policy_draft_ui', 'changed_fields' => ['working_schedule']],
            ]);
        }, 3);

        return to_route('leave.policy.drafts.index')->with('success', 'Jadual sintetik disahkan untuk simulasi tempatan sahaja.');
    }

    /** @return array<string, mixed> */
    private function validatedEntitlement(Request $request): array
    {
        $request->merge(['band_description' => is_string($request->input('band_description')) ? trim($request->input('band_description')) : $request->input('band_description')]);
        $data = $request->validate([
            'category_id' => ['required', 'integer'],
            'band_description' => ['required', 'string', 'max:255'],
            'annual_half_day_units' => ['present', 'nullable', 'integer', 'min:0', 'max:4294967295'],
        ]);

        return $data;
    }

    /** @return array<string, mixed> */
    private function validatedInput(Request $request): array
    {
        $request->merge(['label' => is_string($request->input('label')) ? trim($request->input('label')) : $request->input('label')]);
        $data = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'effective_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:effective_from'],
            'working_weekdays' => ['present', 'nullable', 'array', 'min:1', 'max:7'],
            'working_weekdays.*' => ['integer', 'distinct', Rule::in([1, 2, 3, 4, 5, 6, 7])],
            'work_start' => ['nullable', 'date_format:H:i'],
            'session_boundary' => ['nullable', 'date_format:H:i'],
            'work_end' => ['nullable', 'date_format:H:i'],
        ]);
        foreach ([['work_start', 'session_boundary'], ['session_boundary', 'work_end'], ['work_start', 'work_end']] as [$first, $last]) {
            if (isset($data[$first], $data[$last]) && $data[$first] >= $data[$last]) {
                throw ValidationException::withMessages([$last => 'Masa mesti mengikut urutan yang sah.']);
            }
        }

        return $data;
    }
}
