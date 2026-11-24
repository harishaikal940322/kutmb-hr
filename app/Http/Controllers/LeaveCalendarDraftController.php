<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LeaveCalendarDraftController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('leave/CalendarDrafts', [
            'versions' => DB::table('leave_calendar_versions')->orderByDesc('effective_from')->get(['id', 'label', 'effective_from', 'effective_to', 'confirmed_at']),
            'days' => DB::table('leave_calendar_days')->orderBy('date')->get(['id', 'calendar_version_id', 'date', 'name', 'kind', 'original_day_id']),
        ]);
    }

    public function storeVersion(Request $request): RedirectResponse
    {
        $request->merge(['label' => is_string($request->input('label')) ? trim($request->input('label')) : $request->input('label')]);
        $data = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'effective_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:effective_from'],
        ]);
        DB::transaction(function () use ($request, $data): void {
            $id = DB::table('leave_calendar_versions')->insertGetId([
                ...$data, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->audit($request, 'leave.calendar.draft_created', 'calendar_version:'.$id, ['calendar_version']);
        });

        return to_route('leave.calendar.drafts.index')->with('success', 'Draf kalendar ditambah.');
    }

    public function storeDay(Request $request, int $versionId): RedirectResponse
    {
        $request->merge(['name' => is_string($request->input('name')) ? trim($request->input('name')) : $request->input('name')]);
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'name' => ['required', 'string', 'max:255'],
            'kind' => ['required', Rule::in(['holiday', 'replacement'])],
            'original_day_id' => ['nullable', 'integer'],
        ]);
        try {
            DB::transaction(function () use ($request, $versionId, $data): void {
                $version = DB::table('leave_calendar_versions')->where('id', $versionId)->lockForUpdate()->first();
                abort_if($version === null, 404);
                if ($version->confirmed_at !== null || $version->confirmed_by !== null) {
                    throw ValidationException::withMessages(['date' => 'Kalendar yang disahkan tidak boleh diubah.']);
                }
                if ($data['date'] < $version->effective_from || ($version->effective_to !== null && $data['date'] > $version->effective_to)) {
                    throw ValidationException::withMessages(['date' => 'Tarikh di luar tempoh versi kalendar.']);
                }
                if (($data['kind'] === 'replacement') !== isset($data['original_day_id'])) {
                    throw ValidationException::withMessages(['original_day_id' => 'Cuti gantian memerlukan cuti umum asal.']);
                }
                if (isset($data['original_day_id'])) {
                    $original = DB::table('leave_calendar_days')->where('id', $data['original_day_id'])->where('calendar_version_id', $versionId)->where('kind', 'holiday')->first();
                    if (! $original || $original->date === $data['date']) {
                        throw ValidationException::withMessages(['original_day_id' => 'Pilih cuti umum asal yang sah dalam versi ini.']);
                    }
                }
                $id = DB::table('leave_calendar_days')->insertGetId([
                    'calendar_version_id' => $versionId, 'date' => $data['date'], 'name' => $data['name'], 'kind' => $data['kind'],
                    'original_day_id' => $data['original_day_id'] ?? null, 'created_at' => now(), 'updated_at' => now(),
                ]);
                $this->audit($request, 'leave.calendar.day_drafted', 'calendar_day:'.$id, ['calendar_day']);
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['date' => 'Tarikh telah wujud dalam versi ini.']);
        }

        return to_route('leave.calendar.drafts.index')->with('success', 'Tarikh ditambah ke draf.');
    }

    /** @param list<string> $fields */
    private function audit(Request $request, string $action, string $target, array $fields): void
    {
        AuditEvent::query()->create([
            'occurred_at' => now(), 'actor_id' => $request->user()->getKey(), 'action_code' => $action,
            'target_ref' => $target, 'outcome' => 'SUCCESS', 'metadata' => ['source' => 'calendar_draft_ui', 'changed_fields' => $fields],
        ]);
    }
}
