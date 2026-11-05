<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\Role;
use App\Models\User;
use App\Services\Identity\RecoveryCodeService;
use App\Support\Identity\Username;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;
use RuntimeException;

class LocalSystemAdminBootstrapController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        $this->ensureBootstrapEnvironment();

        if (User::query()->exists()) {
            return to_route('login');
        }

        $encryptedSecret = $request->session()->get('iam.bootstrap_mfa_secret');

        if (! is_string($encryptedSecret)) {
            $encryptedSecret = encrypt(app(Google2FA::class)->generateSecretKey(32));
            $request->session()->put('iam.bootstrap_mfa_secret', $encryptedSecret);
        }

        return Inertia::render('auth/Register', [
            'mfaSecret' => decrypt($encryptedSecret),
            'mfaIssuer' => config('app.name'),
        ]);
    }

    public function store(Request $request, Google2FA $google2fa, RecoveryCodeService $recoveryCodes): RedirectResponse
    {
        $this->ensureBootstrapEnvironment();

        if (User::query()->exists()) {
            return to_route('login');
        }

        $validated = $request->validate([
            'username' => ['required', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed'],
            'mfa_code' => ['required', 'string', 'digits:6'],
        ]);

        $name = trim($validated['name']);

        if ($name === '') {
            throw ValidationException::withMessages([
                'name' => 'The display name field is required.',
            ]);
        }

        try {
            Username::canonicalize($validated['username']);
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages([
                'username' => 'Username must use 1–64 letters, numbers, dots, underscores or hyphens, and start with a letter or number.',
            ]);
        }

        $encryptedBootstrapSecret = $request->session()->get('iam.bootstrap_mfa_secret');

        if (! is_string($encryptedBootstrapSecret)) {
            throw ValidationException::withMessages([
                'mfa_code' => 'Authenticator setup expired. Reload the page and try again.',
            ]);
        }

        $mfaSecret = decrypt($encryptedBootstrapSecret);

        $matchedTimestamp = $google2fa->verifyKeyNewer($mfaSecret, $validated['mfa_code'], 0, 1);

        if ($matchedTimestamp === false) {
            throw ValidationException::withMessages([
                'mfa_code' => 'The authenticator code is invalid. Try the current code from your app.',
            ]);
        }

        $created = DB::transaction(function () use ($validated, $name, $mfaSecret, $matchedTimestamp, $recoveryCodes): ?array {
            $guard = DB::table('iam_guard')->where('id', 1)->lockForUpdate()->first();

            if ($guard === null) {
                throw new RuntimeException('Initial account setup is unavailable.');
            }

            if (User::query()->exists()) {
                return null;
            }

            $role = Role::query()->firstOrCreate(
                ['code' => 'system-admin'],
                ['name' => 'System Admin'],
            );

            $user = User::query()->create([
                'name' => $name,
                'username' => $validated['username'],
                'status' => 'ACTIVE',
                'password' => $validated['password'],
            ]);
            $user->forceFill([
                'two_factor_secret' => Fortify::currentEncrypter()->encrypt($mfaSecret),
                'two_factor_confirmed_at' => now(),
                'two_factor_last_used_at' => $matchedTimestamp,
            ])->save();
            $user->roles()->attach($role->getKey());
            $codes = $recoveryCodes->issue($user);

            AuditEvent::query()->create([
                'occurred_at' => now(),
                'actor_id' => null,
                'action_code' => 'iam.local_system_admin_bootstrap',
                'target_ref' => 'user:'.$user->getKey(),
                'outcome' => 'SUCCESS',
                'metadata' => ['source' => 'local_web'],
            ]);

            return $codes;
        }, 3);

        if ($created === null) {
            return to_route('login');
        }

        $request->session()->forget('iam.bootstrap_mfa_secret');

        return to_route('login')
            ->with('status', 'System Administrator account created with multi-factor authentication. Save the one-time recovery codes below.')
            ->with('iam.recovery_codes', $created);
    }

    private function ensureBootstrapEnvironment(): void
    {
        abort_unless(
            app()->environment(['local', 'testing'])
                && config('iam.local_account_bootstrap_enabled', false),
            404,
        );
    }
}
