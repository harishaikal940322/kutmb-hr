<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLocalUserDirectoryPreview
{
    public function handle(Request $request, Closure $next): Response
    {
        $allowedEnvironment = app()->environment(['local', 'testing']);
        $enabled = (bool) config('iam.local_user_directory_preview_enabled');

        abort_unless($allowedEnvironment && $enabled, 404);

        return $next($request);
    }
}
