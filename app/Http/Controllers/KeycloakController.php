<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Identity\Username;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use SocialiteProviders\Keycloak\Provider;
use Throwable;

class KeycloakController extends Controller
{
    private function available(): bool
    {
        return app()->environment('local') && config('services.keycloak.enabled') && ! config('iam.mfa_enabled');
    }

    public function redirect(): RedirectResponse
    {
        abort_unless($this->available(), 404);

        /** @var Provider $provider */
        $provider = Socialite::driver('keycloak');

        return $provider->enablePKCE()->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        abort_unless($this->available(), 404);

        try {
            /** @var Provider $provider */
            $provider = Socialite::driver('keycloak');
            $identity = $provider->enablePKCE()->user();
            $subject = config('services.keycloak.subject');
            $username = config('services.keycloak.username');
            if (! is_string($subject) || $subject === '' || ! is_string($username) || $username === '' || ! hash_equals($subject, (string) $identity->getId())) {
                return to_route('login')->with('status', 'Akaun SSO belum dipautkan.');
            }
            $user = User::query()->where('username_canonical', Username::canonicalize($username))->where('status', 'ACTIVE')->first();
        } catch (Throwable $exception) {
            report($exception);

            return to_route('login')->with('status', 'Log masuk SSO gagal. Sila cuba lagi.');
        }

        if (! $user) {
            return to_route('login')->with('status', 'Akaun HR tidak aktif atau tidak dijumpai.');
        }
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('keycloak.id_token', $identity->accessTokenResponseBody['id_token'] ?? null);
        $request->session()->put('keycloak.login', true);

        return redirect()->intended(route('dashboard'));
    }
}
