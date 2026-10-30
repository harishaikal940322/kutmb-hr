<?php

namespace App\Console\Commands;

use App\Models\AuditEvent;
use App\Models\User;
use App\Support\Identity\Username;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

class BootstrapLocalAccount extends Command
{
    protected $signature = 'iam:bootstrap-local-account';

    protected $description = 'Create an opt-in local development account without roles';

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('This command is available only in local or testing environments.');

            return self::FAILURE;
        }

        if (! config('iam.local_account_bootstrap_enabled', false)) {
            $this->error('Local account bootstrap is disabled. Enable the explicit IAM local bootstrap configuration flag first.');

            return self::FAILURE;
        }

        $username = (string) $this->ask('Username');

        try {
            $canonicalUsername = Username::canonicalize($username);
        } catch (InvalidArgumentException) {
            $this->error('Username is invalid.');

            return self::FAILURE;
        }

        if (User::query()->where('username_canonical', $canonicalUsername)->exists()) {
            $this->error('An account with that username already exists.');

            return self::FAILURE;
        }

        $displayName = trim((string) $this->ask('Display name'));

        if ($displayName === '' || mb_strlen($displayName) > 255) {
            $this->error('Display name is invalid.');

            return self::FAILURE;
        }

        $password = $this->secret('Password');
        $passwordConfirmation = $this->secret('Confirm password');

        if (! is_string($password) || mb_strlen($password) < 15 || ! is_string($passwordConfirmation) || ! hash_equals($password, $passwordConfirmation)) {
            $this->error('Password is invalid or confirmation does not match. Use at least 15 characters.');

            return self::FAILURE;
        }

        try {
            $user = DB::transaction(function () use ($username, $displayName, $password): User {
                $user = User::query()->create([
                    'name' => $displayName,
                    'username' => $username,
                    'status' => 'ACTIVE',
                    'password' => $password,
                ]);

                AuditEvent::query()->create([
                    'occurred_at' => now(),
                    'actor_id' => null,
                    'action_code' => 'iam.local_account_bootstrap',
                    'target_ref' => 'user:'.$user->getKey(),
                    'outcome' => 'SUCCESS',
                    'metadata' => ['source' => 'local_console'],
                ]);

                return $user;
            });
        } catch (QueryException $exception) {
            if ($this->isCanonicalUsernameConflict($exception)) {
                $this->error('An account with that username already exists.');

                return self::FAILURE;
            }

            $this->error('The local account could not be created. No account was created.');

            return self::FAILURE;
        } catch (Throwable) {
            $this->error('The local account could not be created. No account was created.');

            return self::FAILURE;
        }

        $this->info('Local account created successfully. No role or permission was assigned.');

        return self::SUCCESS;
    }

    private function isCanonicalUsernameConflict(QueryException $exception): bool
    {
        return ($exception->errorInfo[1] ?? null) === 1062
            && str_contains($exception->getMessage(), 'username_canonical');
    }
}
