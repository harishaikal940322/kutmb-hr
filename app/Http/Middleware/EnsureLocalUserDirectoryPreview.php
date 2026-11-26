<?php

namespace App\Http\Middleware;

use App\Services\Identity\UserAdministrationAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLocalUserDirectoryPreview
{
    public function handle(Request $request, Closure $next): Response
    {
        $access = app(UserAdministrationAccess::class);

        abort_unless(app()->environment(['local', 'testing']), 404);

        if (! $access->canManage($request->user())) {
            abort_unless(config('iam.local_user_directory_preview_enabled'), 404);
        }

        abort_unless($access->canView($request->user()), 403);

        return $next($request);
    }
}
