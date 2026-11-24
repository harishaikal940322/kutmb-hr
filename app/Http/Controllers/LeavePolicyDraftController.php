<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Services\Leave\VersionPeriodOverlap;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        return Inertia::render('leave/PolicyDrafts', ['versions' => $overlap->annotate($versions), 'history' => $history]);
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
            if ($version->confirmed_at !== null || $version->confirmed_by !== null) {
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
