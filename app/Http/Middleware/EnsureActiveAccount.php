<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $currentAccount = $user === null
            ? null
            : User::query()->select(['username', 'status'])->whereKey($user->getAuthIdentifier())->first();

        if ($user !== null && ($currentAccount === null || $currentAccount->username === null || $currentAccount->status !== 'ACTIVE')) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login');
        }

        return $next($request);
    }
}
