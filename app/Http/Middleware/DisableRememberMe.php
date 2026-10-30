<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DisableRememberMe
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('post') && $request->routeIs('login.store')) {
            $request->merge(['remember' => false]);
        }

        return $next($request);
    }
}
