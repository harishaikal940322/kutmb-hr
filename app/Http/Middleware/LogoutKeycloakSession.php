<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\Response;

class LogoutKeycloakSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $isSsoLogout = $request->isMethod('POST') && $request->routeIs('logout')
            && $request->session()->get('keycloak.login') === true;
        $idToken = $isSsoLogout ? $request->session()->get('keycloak.id_token') : null;

        $response = $next($request);

        if (! $isSsoLogout || ! $response->isSuccessful() && ! $response->isRedirection()) {
            return $response;
        }

        $provider = Socialite::driver('keycloak');
        $url = $provider->getLogoutUrl(route('login'), config('services.keycloak.client_id'), is_string($idToken) ? $idToken : null);

        return $request->header('X-Inertia') ? Inertia::location($url) : redirect()->away($url);
    }
}
