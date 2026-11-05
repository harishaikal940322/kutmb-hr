<?php

namespace App\Http\Controllers;

use App\Services\Identity\PermissionCatalog;
use Inertia\Inertia;
use Inertia\Response;

class IdentityPermissionController extends Controller
{
    public function __invoke(PermissionCatalog $catalog): Response
    {
        return Inertia::render('identity/Permissions', [
            'permissions' => $catalog->rows(),
        ]);
    }
}
