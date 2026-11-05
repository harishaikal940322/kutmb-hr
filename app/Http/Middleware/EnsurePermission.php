<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $permissionCode): Response
    {
        $request->user()?->can($permissionCode) || abort(403);

        return $next($request);
    }
}
