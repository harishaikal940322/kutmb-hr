<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserDirectoryRequest;
use App\Queries\UserDirectoryQuery;
use Inertia\Inertia;
use Inertia\Response;

class UserDirectoryController extends Controller
{
    public function __invoke(UserDirectoryRequest $request, UserDirectoryQuery $directory): Response
    {
        $validated = $request->validated();
        $search = isset($validated['search']) ? trim($validated['search']) : '';
        $page = (int) ($validated['page'] ?? 1);
        $users = $directory->paginate($search === '' ? null : $search, $page);

        return Inertia::render('users/Index', [
            'users' => $users->getCollection()->map(static fn (object $user): array => [
                'displayName' => (string) $user->name,
                'username' => (string) $user->username,
                'status' => (string) $user->status,
            ])->values()->all(),
            'pagination' => [
                'currentPage' => $users->currentPage(),
                'lastPage' => $users->lastPage(),
                'perPage' => $users->perPage(),
                'total' => $users->total(),
            ],
            'filters' => ['search' => $search],
        ]);
    }
}
