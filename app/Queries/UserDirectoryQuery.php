<?php

namespace App\Queries;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class UserDirectoryQuery
{
    public const PER_PAGE = 20;

    /** @return LengthAwarePaginator<int, \stdClass> */
    public function paginate(?string $search, int $page): LengthAwarePaginator
    {
        $query = DB::table('users')
            ->select(['name', 'username', 'status'])
            ->whereNotNull('username');

        if ($search !== null && trim($search) !== '') {
            $term = trim($search);
            $query->where(function ($builder) use ($term): void {
                $builder->where('name', 'like', '%'.$term.'%')
                    ->orWhere('username', 'like', '%'.$term.'%');
            });
        }

        return $query
            ->orderBy('name')
            ->orderBy('username')
            ->orderBy('id')
            ->paginate(self::PER_PAGE, ['name', 'username', 'status'], 'page', $page);
    }
}
