<?php

namespace App\Services\Identity;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class UserAdministrationAccess
{
    public function canManage(?User $user): bool
    {
        return app()->environment('local')
            && $user !== null
            && $user->hasRole('system-admin')
            && $user->can('iam.rbac.manage');
    }

    public function canView(?User $user): bool
    {
        return $this->canManage($user)
            || (app()->environment(['local', 'testing'])
                && config('iam.local_user_directory_preview_enabled')
                && $user !== null && $user->can('iam.users.view'));
    }

    public function grantableRoles(): Builder
    {
        return Role::query()->where('is_system', false)->where('guard_name', 'web')
            ->whereDoesntHave('permissions', fn (Builder $query) => $query
                ->whereNotIn('permissions.code', app(PermissionCatalog::class)->assignableCodes()));
    }

    public function canManageTarget(User $actor, User $target): bool
    {
        if ($actor->is($target) || $target->username === null
            || $target->roles->contains(fn (Role $role): bool => (bool) $role->is_system)
            || $target->can('iam.rbac.manage')) {
            return false;
        }

        return $this->grantableRoles()->whereIn('roles.id', $target->roles->modelKeys())->count()
            === $target->roles->count();
    }
}
