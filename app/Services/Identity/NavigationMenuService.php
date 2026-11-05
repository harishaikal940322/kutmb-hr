<?php

namespace App\Services\Identity;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class NavigationMenuService
{
    /** @return list<string> */
    public function visibleKeys(User $user): array
    {
        /** @var array<string, array{label: string, href: string, icon: string, permission: string, editable: bool}> $menus */
        $menus = config('iam.menus', []);
        $permissionMap = $this->permissionMap();
        $visible = [];

        foreach ($menus as $key => $menu) {
            if ($key === 'users.index' && (! app()->environment(['local', 'testing'])
                || ! config('iam.local_user_directory_preview_enabled'))) {
                continue;
            }

            $permission = $permissionMap[$key] ?? $menu['permission'];

            if ($user->can($permission)) {
                $visible[] = $key;
            }
        }

        return $visible;
    }

    /** @return list<array{key: string, label: string, href: string, permission: string, editable: bool}> */
    public function rows(): array
    {
        /** @var array<string, array{label: string, href: string, icon: string, permission: string, editable: bool}> $menus */
        $menus = config('iam.menus', []);
        $permissionMap = $this->permissionMap();
        $rows = [];

        foreach ($menus as $key => $menu) {
            $rows[] = [
                'key' => $key,
                'label' => $menu['label'],
                'href' => $menu['href'],
                'permission' => $permissionMap[$key] ?? $menu['permission'],
                'editable' => $menu['editable'],
            ];
        }

        return $rows;
    }

    /** @return array<string, string> */
    private function permissionMap(): array
    {
        $rows = DB::table('menu_permission_assignments')
            ->join('permissions', 'permissions.id', '=', 'menu_permission_assignments.permission_id')
            ->get(['menu_permission_assignments.menu_key', 'permissions.code']);
        $map = [];

        foreach ($rows as $row) {
            $map[(string) $row->menu_key] = (string) $row->code;
        }

        return $map;
    }
}
