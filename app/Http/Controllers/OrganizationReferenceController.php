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

class OrganizationReferenceController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('organization/References', [
            'units' => DB::table('organization_units')->orderBy('name')->get(['id', 'code', 'name', 'active']),
            'categories' => DB::table('employment_categories')->orderBy('name')->get(['id', 'code', 'name', 'active']),
        ]);
    }

    public function store(Request $request, string $kind): RedirectResponse
    {
        $table = $this->table($kind);
        $data = $this->validateInput($request, $table);

        try {
            DB::transaction(function () use ($request, $kind, $table, $data): void {
                $id = DB::table($table)->insertGetId([...$data, 'created_at' => now(), 'updated_at' => now()]);
                $this->audit($request, $kind, $id, 'created');
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['code' => 'Kod ini telah digunakan.']);
        }

        return to_route('organization.references.index')->with('success', 'Rekod ditambah.');
    }

    public function update(Request $request, string $kind, int $id): RedirectResponse
    {
        $table = $this->table($kind);
        $data = $this->validateInput($request, $table, $id);

        try {
            DB::transaction(function () use ($request, $kind, $id, $table, $data): void {
                $record = DB::table($table)->where('id', $id)->lockForUpdate()->first();
                abort_if($record === null, 404);
                DB::table($table)->where('id', $id)->update([...$data, 'updated_at' => now()]);
                $this->audit($request, $kind, $id, 'updated');
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['code' => 'Kod ini telah digunakan.']);
        }

        return to_route('organization.references.index')->with('success', 'Rekod dikemas kini.');
    }

    private function table(string $kind): string
    {
        return match ($kind) {
            'units' => 'organization_units',
            'categories' => 'employment_categories',
            default => abort(404),
        };
    }

    /** @return array{code: string, name: string, active: bool} */
    private function validateInput(Request $request, string $table, ?int $id = null): array
    {
        $request->merge(['code' => is_string($request->input('code')) ? trim($request->input('code')) : $request->input('code'), 'name' => is_string($request->input('name')) ? trim($request->input('name')) : $request->input('name')]);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:64', Rule::unique($table, 'code')->ignore($id)],
            'name' => ['required', 'string', 'max:255'],
            'active' => ['required', 'boolean'],
        ]);
        $data['active'] = (bool) $data['active'];

        return $data;
    }

    private function audit(Request $request, string $kind, int $id, string $action): void
    {
        AuditEvent::query()->create([
            'occurred_at' => now(),
            'actor_id' => $request->user()->getKey(),
            'action_code' => 'hr.organization.'.$action,
            'target_ref' => $kind.':'.$id,
            'outcome' => 'SUCCESS',
            'metadata' => ['source' => 'organization_ui', 'changed_fields' => ['code', 'name', 'active']],
        ]);
    }
}
