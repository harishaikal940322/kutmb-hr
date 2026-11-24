<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ApprovalOfficeController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('organization/Offices', [
            'offices' => DB::table('approval_assignments')->join('users', 'users.id', '=', 'approval_assignments.user_id')
                ->leftJoin('organization_units', 'organization_units.id', '=', 'approval_assignments.unit_id')
                ->orderByDesc('approval_assignments.effective_from')
                ->get(['approval_assignments.id', 'approval_assignments.user_id', 'approval_assignments.office_code', 'approval_assignments.effective_from', 'approval_assignments.effective_to', 'users.username', 'organization_units.name as unit_name']),
            'users' => DB::table('users')->where('status', 'ACTIVE')->whereNotNull('username')->where('id', '!=', $request->user()->getKey())->orderBy('username')->get(['id', 'username']),
            'units' => DB::table('organization_units')->where('active', true)->orderBy('name')->get(['id', 'code', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer'],
            'office_code' => ['required', Rule::in(['PU', 'PB', 'SECRETARY_ALK'])],
            'unit_id' => ['nullable', 'integer'],
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'effective_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:effective_from'],
        ]);
        if (($data['office_code'] === 'PU') !== isset($data['unit_id'])) {
            throw ValidationException::withMessages(['unit_id' => 'Unit diperlukan untuk Pengurus Unit sahaja.']);
        }
        DB::transaction(function () use ($request, $data): void {
            $this->lockGuard();
            $user = DB::table('users')->where('id', $data['user_id'])->lockForUpdate()->first();
            if (! $user || $user->status !== 'ACTIVE' || $user->username === null || (int) $user->id === (int) $request->user()->getKey()) {
                throw ValidationException::withMessages(['user_id' => 'Pilih akaun aktif milik pegawai lain.']);
            }
            if (isset($data['unit_id'])) {
                $unit = DB::table('organization_units')->where('id', $data['unit_id'])->lockForUpdate()->first();
                if (! $unit || ! $unit->active) {
                    throw ValidationException::withMessages(['unit_id' => 'Pilih unit aktif.']);
                }
            }
            $overlap = DB::table('approval_assignments')->where('office_code', $data['office_code'])
                ->where('unit_id', $data['unit_id'] ?? null)
                ->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>=', $data['effective_from']));
            if (isset($data['effective_to'])) {
                $overlap->where('effective_from', '<=', $data['effective_to']);
            }
            if ($overlap->exists()) {
                throw ValidationException::withMessages(['effective_from' => 'Tempoh bertindih dengan penetapan jawatan sedia ada.']);
            }
            $id = DB::table('approval_assignments')->insertGetId([
                'user_id' => $user->id, 'office_code' => $data['office_code'], 'unit_id' => $data['unit_id'] ?? null,
                'effective_from' => $data['effective_from'], 'effective_to' => $data['effective_to'] ?? null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->audit($request, $id, 'created');
        }, 3);

        return to_route('organization.offices.index')->with('success', 'Penetapan direkodkan.');
    }

    public function end(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate(['effective_to' => ['required', 'date_format:Y-m-d']]);
        DB::transaction(function () use ($request, $id, $data): void {
            $this->lockGuard();
            $assignment = DB::table('approval_assignments')->where('id', $id)->lockForUpdate()->first();
            abort_if($assignment === null, 404);
            if ((int) $assignment->user_id === (int) $request->user()->getKey()) {
                throw ValidationException::withMessages(['effective_to' => 'Penetapan sendiri memerlukan pegawai lain yang diberi kuasa.']);
            }
            if ($assignment->effective_to !== null || $data['effective_to'] < $assignment->effective_from) {
                throw ValidationException::withMessages(['effective_to' => 'Penetapan mesti terbuka dan tarikh tamat mesti sah.']);
            }
            DB::table('approval_assignments')->where('id', $id)->update(['effective_to' => $data['effective_to'], 'updated_at' => now()]);
            $this->audit($request, $id, 'ended');
        }, 3);

        return to_route('organization.offices.index')->with('success', 'Penetapan ditamatkan.');
    }

    private function lockGuard(): void
    {
        abort_if(DB::table('office_assignment_guard')->where('id', 1)->lockForUpdate()->first() === null, 503);
    }

    private function audit(Request $request, int $id, string $action): void
    {
        AuditEvent::query()->create([
            'occurred_at' => now(), 'actor_id' => $request->user()->getKey(), 'action_code' => 'hr.office.'.$action,
            'target_ref' => 'office_assignment:'.$id, 'outcome' => 'SUCCESS',
            'metadata' => ['source' => 'office_ui', 'changed_fields' => ['office_assignment']],
        ]);
    }
}
