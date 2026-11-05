<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\Role;
use App\Services\Identity\PermissionCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class IdentityRoleController extends Controller
{
    public function index(PermissionCatalog $catalog): Response
    {
        return Inertia::render('identity/Roles', [
            'roles' => Role::query()
                ->with('permissions:id,code,name,label')
                ->withCount('users')
                ->orderBy('label')
                ->get(['id', 'code', 'name', 'label', 'is_system'])
                ->map(fn (Role $role): array => [
                    'id' => $role->getKey(),
                    'code' => $role->code,
                    'label' => $role->label,
                    'isSystem' => (bool) $role->is_system,
                    'userCount' => $role->users_count,
                    'permissionCodes' => $role->permissions->pluck('code')->all(),
                ])
                ->all(),
            'permissions' => $catalog->rows(),
        ]);
    }

    public function store(Request $request, PermissionCatalog $catalog): RedirectResponse
    {
        $assignable = $catalog->assignableCodes();
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:80', 'regex:/\A[a-z][a-z0-9-]*\z/', 'unique:roles,code'],
            'label' => ['required', 'string', 'max:160'],
            'permission_codes' => ['present', 'array'],
            'permission_codes.*' => ['string', 'distinct', Rule::in($assignable)],
        ]);

        DB::transaction(function () use ($request, $validated): void {
            $role = Role::query()->create([
                'code' => $validated['code'],
                'name' => $validated['code'],
                'guard_name' => 'web',
                'label' => trim($validated['label']),
                'is_system' => false,
            ]);
            $role->syncPermissions($validated['permission_codes']);

            $this->audit($request, 'iam.role.created', $role, ['role', 'permissions']);
        });

        return to_route('iam.roles.index')->with('success', 'Role created.');
    }

    public function update(Request $request, Role $role, PermissionCatalog $catalog): RedirectResponse
    {
        if ($role->is_system) {
            throw ValidationException::withMessages(['role' => 'The protected System Admin role cannot be changed here.']);
        }

        $validated = $request->validate([
            'label' => ['required', 'string', 'max:160'],
            'permission_codes' => ['present', 'array'],
            'permission_codes.*' => ['string', 'distinct', Rule::in($catalog->assignableCodes())],
        ]);

        DB::transaction(function () use ($request, $role, $validated): void {
            $lockedRole = Role::query()->whereKey($role->getKey())->lockForUpdate()->firstOrFail();

            if ($lockedRole->is_system) {
                throw ValidationException::withMessages(['role' => 'The protected System Admin role cannot be changed here.']);
            }

            $lockedRole->label = trim($validated['label']);
            $lockedRole->save();
            $lockedRole->syncPermissions($validated['permission_codes']);

            $this->audit($request, 'iam.role.updated', $lockedRole, ['role', 'permissions']);
        });

        return to_route('iam.roles.index')->with('success', 'Role updated.');
    }

    public function destroy(Request $request, Role $role): RedirectResponse
    {
        DB::transaction(function () use ($request, $role): void {
            $lockedRole = Role::query()->whereKey($role->getKey())->lockForUpdate()->firstOrFail();

            if ($lockedRole->is_system) {
                throw ValidationException::withMessages(['role' => 'The protected System Admin role cannot be deleted.']);
            }

            if ($lockedRole->users()->exists()) {
                throw ValidationException::withMessages(['role' => 'A role assigned to an account cannot be deleted.']);
            }

            DB::table('legacy_user_roles')->where('role_id', $lockedRole->getKey())->delete();
            DB::table('legacy_role_permissions')->where('role_id', $lockedRole->getKey())->delete();
            $target = 'role:'.$lockedRole->getKey();
            $lockedRole->delete();

            AuditEvent::query()->create([
                'occurred_at' => now(),
                'actor_id' => $request->user()->getKey(),
                'action_code' => 'iam.role.deleted',
                'target_ref' => $target,
                'outcome' => 'SUCCESS',
                'metadata' => ['source' => 'rbac_ui', 'changed_fields' => ['role']],
            ]);
        });

        return to_route('iam.roles.index')->with('success', 'Role deleted.');
    }

    /** @param list<string> $changedFields */
    private function audit(Request $request, string $action, Role $role, array $changedFields): void
    {
        AuditEvent::query()->create([
            'occurred_at' => now(),
            'actor_id' => $request->user()->getKey(),
            'action_code' => $action,
            'target_ref' => 'role:'.$role->getKey(),
            'outcome' => 'SUCCESS',
            'metadata' => ['source' => 'rbac_ui', 'changed_fields' => $changedFields],
        ]);
    }
}
