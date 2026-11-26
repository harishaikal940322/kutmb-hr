<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserDirectoryRequest;
use App\Models\Role;
use App\Models\User;
use App\Queries\UserDirectoryQuery;
use App\Services\Identity\UserAdministrationAccess;
use Inertia\Inertia;
use Inertia\Response;

class UserDirectoryController extends Controller
{
    public function __invoke(UserDirectoryRequest $request, UserDirectoryQuery $directory, UserAdministrationAccess $access): Response
    {
        $validated = $request->validated();
        $search = trim($validated['search'] ?? '');
        $status = $validated['status'] ?? null;
        $role = isset($validated['role']) ? (int) $validated['role'] : null;
        $page = (int) ($validated['page'] ?? 1);
        $users = $directory->paginate($search === '' ? null : $search, $page, $status, $role);
        $actor = $request->user();
        $canManage = $access->canManage($actor);
        $roleOptions = Role::query()->where('guard_name', 'web')->orderBy('label')->get(['id', 'label']);

        return Inertia::render('users/Index', [
            'users' => $users->getCollection()->map(fn (User $user): array => [
                'id' => $user->id,
                'displayName' => $user->name,
                'username' => $user->username,
                'status' => $user->status,
                'roles' => $user->roles->map(fn (Role $role): array => ['id' => $role->id, 'label' => $role->label])->values()->all(),
                'canManage' => $canManage && $access->canManageTarget($actor, $user),
            ])->values()->all(),
            'roleOptions' => $roleOptions,
            'grantableRoles' => $canManage ? $access->grantableRoles()->orderBy('label')->get(['id', 'label']) : [],
            'canManage' => $canManage,
            'passwordMinimum' => config('iam.mfa_enabled') ? 8 : (int) config('iam.single_factor_password_minimum', 15),
            'pagination' => [
                'currentPage' => $users->currentPage(),
                'lastPage' => $users->lastPage(),
                'perPage' => $users->perPage(),
                'total' => $users->total(),
            ],
            'filters' => ['search' => $search, 'status' => $status, 'role' => $role],
        ]);
    }
}
