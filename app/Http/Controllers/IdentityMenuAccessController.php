<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\Permission;
use App\Services\Identity\NavigationMenuService;
use App\Services\Identity\PermissionCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class IdentityMenuAccessController extends Controller
{
    public function index(NavigationMenuService $menus, PermissionCatalog $catalog): Response
    {
        return Inertia::render('identity/Menus', [
            'menus' => $menus->rows(),
            'permissions' => $catalog->rows(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $menuDefinitions = config('iam.menus', []);
        $permissionCodes = app(PermissionCatalog::class)->definitions();
        $validated = $request->validate([
            'mappings' => ['required', 'array'],
            'mappings.*' => ['required', 'string', Rule::in(array_keys($permissionCodes))],
        ]);

        $expectedKeys = array_keys($menuDefinitions);
        $providedKeys = array_keys($validated['mappings']);

        if (array_diff($expectedKeys, $providedKeys) !== [] || array_diff($providedKeys, $expectedKeys) !== []) {
            throw ValidationException::withMessages([
                'mappings' => 'Choose one valid permission for every registered menu item.',
            ]);
        }

        DB::transaction(function () use ($request, $validated): void {
            foreach ($validated['mappings'] as $menuKey => $permissionCode) {
                $permissionId = Permission::query()
                    ->where('code', $permissionCode)
                    ->where('guard_name', 'web')
                    ->value('id');

                if ($permissionId === null) {
                    throw ValidationException::withMessages([
                        'mappings' => 'A selected permission is not available.',
                    ]);
                }

                DB::table('menu_permission_assignments')->updateOrInsert(
                    ['menu_key' => $menuKey],
                    ['permission_id' => $permissionId, 'updated_at' => now(), 'created_at' => now()],
                );
            }

            AuditEvent::query()->create([
                'occurred_at' => now(),
                'actor_id' => $request->user()->getKey(),
                'action_code' => 'iam.menu_access.updated',
                'target_ref' => 'menu:registered',
                'outcome' => 'SUCCESS',
                'metadata' => ['source' => 'rbac_ui', 'changed_fields' => ['menu_permissions']],
            ]);
        });

        return to_route('iam.menus.index')->with('success', 'Menu access updated.');
    }
}
