<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMfaEnrolled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('iam.mfa_enforced') && ! $request->user()?->hasEnabledTwoFactorAuthentication()) {
            return redirect()->route('mfa.setup');
        }

        return $next($request);
    }
}
