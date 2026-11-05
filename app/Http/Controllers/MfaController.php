<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\User;
use App\Services\Identity\RecoveryCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;

class MfaController extends Controller
{
    public function show(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $pendingSecret = $user->two_factor_secret !== null && $user->two_factor_confirmed_at === null
            ? Fortify::currentEncrypter()->decrypt($user->two_factor_secret)
            : null;

        return Inertia::render('auth/MfaSetup', [
            'enabled' => $user->hasEnabledTwoFactorAuthentication(),
            'pendingSecret' => $pendingSecret,
            'recoveryCodes' => $request->session()->pull('iam.recovery_codes'),
            'status' => $request->session()->get('status'),
        ]);
    }

    public function start(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_if($user->hasEnabledTwoFactorAuthentication(), 409);

        $secret = app(Google2FA::class)->generateSecretKey(32);

        $user->forceFill([
            'two_factor_secret' => Fortify::currentEncrypter()->encrypt($secret),
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return to_route('mfa.setup')->with('status', 'Scan or manually enter the authenticator key, then verify a code.');
    }

    public function confirm(Request $request, RecoveryCodeService $recoveryCodes): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validate([
            'code' => ['required', 'string', 'digits:6'],
        ]);

        if ($user->two_factor_secret === null || $user->two_factor_confirmed_at !== null) {
            throw ValidationException::withMessages(['code' => 'The authenticator setup is not pending.']);
        }

        $secret = Fortify::currentEncrypter()->decrypt($user->two_factor_secret);

        $matchedTimestamp = app(Google2FA::class)->verifyKeyNewer($secret, $validated['code'], 0, 1);

        if ($matchedTimestamp === false) {
            throw ValidationException::withMessages(['code' => 'The authenticator code is invalid. Try the current code from your app.']);
        }

        $codes = DB::transaction(function () use ($user, $recoveryCodes, $matchedTimestamp): array {
            $user->forceFill([
                'two_factor_confirmed_at' => now(),
                'two_factor_last_used_at' => $matchedTimestamp,
            ])->save();
            $codes = $recoveryCodes->issue($user);

            AuditEvent::query()->create([
                'occurred_at' => now(),
                'actor_id' => $user->getKey(),
                'action_code' => 'iam.mfa.enrolled',
                'target_ref' => 'user:'.$user->getKey(),
                'outcome' => 'SUCCESS',
                'metadata' => ['source' => 'mfa_totp'],
            ]);

            return $codes;
        }, 3);

        return to_route('mfa.setup')
            ->with('status', 'Multi-factor authentication is enabled. Save these one-time recovery codes in a secure place.')
            ->with('iam.recovery_codes', $codes);
    }
}
