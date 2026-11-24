<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Services\Leave\VersionPeriodOverlap;
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
    public function index(VersionPeriodOverlap $overlap): Response
    {
        return Inertia::render('leave/CalendarDrafts', [
            'versions' => $overlap->annotate(DB::table('leave_calendar_versions')->orderByDesc('effective_from')->orderByDesc('id')->get(['id', 'label', 'effective_from', 'effective_to', 'confirmed_at', 'confirmed_by'])),
            'days' => DB::table('leave_calendar_days')->orderBy('date')->get(['id', 'calendar_version_id', 'date', 'name', 'kind', 'original_day_id', 'cancelled_at', 'cancelled_by']),
            'history' => DB::table('leave_calendar_day_history')->orderByDesc('id')->get(['id', 'calendar_day_id', 'previous_date', 'previous_name', 'created_at']),
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
                    $original = DB::table('leave_calendar_days')->where('id', $data['original_day_id'])->where('calendar_version_id', $versionId)->where('kind', 'holiday')->whereNull('cancelled_at')->whereNull('cancelled_by')->first();
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

    public function updateDay(Request $request, int $versionId, int $dayId): RedirectResponse
    {
        $request->merge(['name' => is_string($request->input('name')) ? trim($request->input('name')) : $request->input('name')]);
        $data = $request->validate(['date' => ['required', 'date_format:Y-m-d'], 'name' => ['required', 'string', 'max:255']]);
        try {
            DB::transaction(function () use ($request, $versionId, $dayId, $data): void {
                $version = DB::table('leave_calendar_versions')->where('id', $versionId)->lockForUpdate()->first();
                abort_if($version === null, 404);
                if ($version->confirmed_at !== null || $version->confirmed_by !== null) {
                    throw ValidationException::withMessages(['date' => 'Kalendar yang disahkan tidak boleh diubah.']);
                }
                $day = DB::table('leave_calendar_days')->where('calendar_version_id', $versionId)->where('id', $dayId)->lockForUpdate()->first();
                abort_if($day === null, 404);
                if ($day->cancelled_at !== null || $day->cancelled_by !== null) {
                    throw ValidationException::withMessages(['date' => 'Pulihkan tarikh sebelum menyunting.']);
                }
                if ($data['date'] < $version->effective_from || ($version->effective_to !== null && $data['date'] > $version->effective_to)) {
                    throw ValidationException::withMessages(['date' => 'Tarikh di luar tempoh versi kalendar.']);
                }
                if ($day->kind === 'replacement' && DB::table('leave_calendar_days')->where('id', $day->original_day_id)->where('calendar_version_id', $versionId)->where('date', $data['date'])->exists()) {
                    throw ValidationException::withMessages(['date' => 'Tarikh gantian tidak boleh sama dengan cuti asal.']);
                }
                if ($day->date === $data['date'] && $day->name === $data['name']) {
                    return;
                }
                DB::table('leave_calendar_day_history')->insert([
                    'calendar_day_id' => $dayId, 'previous_date' => $day->date, 'previous_name' => $day->name,
                    'actor_id' => $request->user()->getKey(), 'created_at' => now(),
                ]);
                DB::table('leave_calendar_days')->where('id', $dayId)->update([...$data, 'updated_at' => now()]);
                $this->audit($request, 'leave.calendar.day_updated', 'calendar_day:'.$dayId, ['calendar_day']);
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['date' => 'Tarikh telah wujud dalam versi ini.']);
        }

        return to_route('leave.calendar.drafts.index')->with('success', 'Tarikh draf dikemas kini.');
    }

    public function setDayCancellation(Request $request, int $versionId, int $dayId): RedirectResponse
    {
        $data = $request->validate(['cancelled' => ['required', 'boolean']]);
        $cancelled = (bool) $data['cancelled'];
        DB::transaction(function () use ($request, $versionId, $dayId, $cancelled): void {
            $version = DB::table('leave_calendar_versions')->where('id', $versionId)->lockForUpdate()->first();
            abort_if($version === null, 404);
            if ($version->confirmed_at !== null || $version->confirmed_by !== null) {
                throw ValidationException::withMessages(['cancelled' => 'Kalendar yang disahkan tidak boleh diubah.']);
            }
            $day = DB::table('leave_calendar_days')->where('calendar_version_id', $versionId)->where('id', $dayId)->lockForUpdate()->first();
            abort_if($day === null, 404);
            $isCancelled = $day->cancelled_at !== null || $day->cancelled_by !== null;
            if ($isCancelled === $cancelled) {
                throw ValidationException::withMessages(['cancelled' => 'Status tarikh ini telah berubah.']);
            }
            if ($cancelled && DB::table('leave_calendar_days')->where('calendar_version_id', $versionId)->where('original_day_id', $dayId)->whereNull('cancelled_at')->whereNull('cancelled_by')->exists()) {
                throw ValidationException::withMessages(['cancelled' => 'Batalkan cuti gantian yang berkaitan terlebih dahulu.']);
            }
            if (! $cancelled && $day->kind === 'replacement') {
                $original = DB::table('leave_calendar_days')->where('id', $day->original_day_id)->where('calendar_version_id', $versionId)->where('kind', 'holiday')->whereNull('cancelled_at')->whereNull('cancelled_by')->first();
                if (! $original) {
                    throw ValidationException::withMessages(['cancelled' => 'Pulihkan cuti umum asal terlebih dahulu.']);
                }
            }
            DB::table('leave_calendar_days')->where('id', $dayId)->update([
                'cancelled_at' => $cancelled ? now() : null, 'cancelled_by' => $cancelled ? $request->user()->getKey() : null,
                'updated_at' => now(),
            ]);
            $this->audit($request, $cancelled ? 'leave.calendar.day_cancelled' : 'leave.calendar.day_restored', 'calendar_day:'.$dayId, ['calendar_day']);
        }, 3);

        return to_route('leave.calendar.drafts.index')->with('success', 'Status tarikh draf dikemas kini.');
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
