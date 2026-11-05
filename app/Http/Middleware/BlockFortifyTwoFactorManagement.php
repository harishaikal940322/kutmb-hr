<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BlockFortifyTwoFactorManagement
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs([
            'two-factor.enable',
            'two-factor.confirm',
            'two-factor.disable',
            'two-factor.qr-code',
            'two-factor.secret-key',
            'two-factor.recovery-codes',
            'two-factor.regenerate-recovery-codes',
        ])) {
            abort(404);
        }

        return $next($request);
    }
}
