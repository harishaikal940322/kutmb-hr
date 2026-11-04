<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\Role;
use App\Models\User;
use App\Support\Identity\Username;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use RuntimeException;

class LocalSystemAdminBootstrapController extends Controller
{
    public function create(): Response|RedirectResponse
    {
        $this->ensureBootstrapEnvironment();

        if (User::query()->exists()) {
            return to_route('login');
        }

        return Inertia::render('auth/Register');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureBootstrapEnvironment();

        if (User::query()->exists()) {
            return to_route('login');
        }

        $validated = $request->validate([
            'username' => ['required', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:15', 'confirmed'],
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

        $created = DB::transaction(function () use ($validated, $name): bool {
            $guard = DB::table('iam_guard')->where('id', 1)->lockForUpdate()->first();

            if ($guard === null) {
                throw new RuntimeException('Initial account setup is unavailable.');
            }

            if (User::query()->exists()) {
                return false;
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
            $user->roles()->attach($role->getKey());

            AuditEvent::query()->create([
                'occurred_at' => now(),
                'actor_id' => null,
                'action_code' => 'iam.local_system_admin_bootstrap',
                'target_ref' => 'user:'.$user->getKey(),
                'outcome' => 'SUCCESS',
                'metadata' => ['source' => 'local_web'],
            ]);

            return true;
        }, 3);

        if (! $created) {
            return to_route('login');
        }

        return to_route('login')->with('status', 'System Administrator account created. You can now log in.');
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
