<?php

namespace App\Queries;

use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class UserDirectoryQuery
{
    public const PER_PAGE = 20;

    /** @return LengthAwarePaginator<int, User> */
    public function paginate(?string $search, int $page, ?string $status = null, ?int $role = null): LengthAwarePaginator
    {
        $query = User::query()->select(['id', 'name', 'username', 'status'])
            ->whereNotNull('username')->with('roles:id,code,label,is_system');

        if ($search !== null && trim($search) !== '') {
            $term = trim($search);
            $query->where(fn ($builder) => $builder->where('name', 'like', '%'.$term.'%')
                ->orWhere('username', 'like', '%'.$term.'%'));
        }

        if ($status !== null) {
            $query->where('status', $status);
        }
        if ($role !== null) {
            $query->whereHas('roles', fn ($builder) => $builder->where('roles.id', $role));
        }

        return $query->orderBy('name')->orderBy('username')->orderBy('id')
            ->paginate(self::PER_PAGE, ['id', 'name', 'username', 'status'], 'page', $page);
    }
}
