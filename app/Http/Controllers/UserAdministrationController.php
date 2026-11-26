<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\User;
use App\Services\Identity\UserAdministrationAccess;
use App\Support\Identity\Username;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;

class UserAdministrationController extends Controller
{
    public function __construct(private readonly UserAdministrationAccess $access) {}

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeActor($request);
        $minimum = config('iam.mfa_enabled') ? 8 : (int) config('iam.single_factor_password_minimum', 15);
        $data = $request->validate([
            'display_name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:64'],
            'password' => ['required', 'string', 'min:'.$minimum, 'max:255', 'confirmed'],
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['required', 'integer', 'distinct'],
        ]);
        $name = $this->displayName($data['display_name']);
        $username = $data['username'];

        try {
            $canonical = Username::canonicalize($username);
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages(['username' => 'Gunakan 1–64 huruf ASCII, angka, titik, garis bawah atau sempang; mulakan dengan huruf atau angka.']);
        }

        try {
            DB::transaction(function () use ($request, $data, $name, $username, $canonical): void {
                $actor = $this->lockedActor($request);
                $roles = $this->roles($data['role_ids']);
                if (User::query()->where('username_canonical', $canonical)->exists()) {
                    throw ValidationException::withMessages(['username' => 'Nama pengguna sudah digunakan.']);
                }
                $user = User::query()->create([
                    'name' => $name, 'username' => $username, 'status' => 'ACTIVE', 'password' => $data['password'],
                ]);
                $user->syncRoles($roles);
                $this->audit($actor, $user, 'created', ['account', 'roles']);
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['username' => 'Nama pengguna sudah digunakan.']);
        }

        return to_route('users.index')->with('success', 'Pengguna ditambah.');
    }

    public function updateProfile(Request $request, User $user): RedirectResponse
    {
        $this->authorizeActor($request);
        $data = $request->validate(['display_name' => ['required', 'string', 'max:255']]);
        $name = $this->displayName($data['display_name']);

        DB::transaction(function () use ($request, $user, $name): void {
            [$actor, $target] = $this->lockedPair($request, $user);
            if ($target->name !== $name) {
                $target->name = $name;
                $target->save();
                $this->audit($actor, $target, 'updated', ['name']);
            }
        }, 3);

        return back()->with('success', 'Nama paparan dikemas kini.');
    }

    public function updateRoles(Request $request, User $user): RedirectResponse
    {
        $this->authorizeActor($request);
        $data = $request->validate(['role_ids' => ['present', 'array'], 'role_ids.*' => ['required', 'integer', 'distinct']]);

        DB::transaction(function () use ($request, $user, $data): void {
            [$actor, $target] = $this->lockedPair($request, $user);
            $roles = $this->roles($data['role_ids']);
            $target->syncRoles($roles);
            $this->revokeSessions($target);
            $this->audit($actor, $target, 'roles_changed', ['roles']);
        }, 3);

        return back()->with('success', 'Peranan dikemas kini.');
    }

    public function updateStatus(Request $request, User $user): RedirectResponse
    {
        $this->authorizeActor($request);
        $data = $request->validate(['status' => ['required', 'in:ACTIVE,INACTIVE']]);

        DB::transaction(function () use ($request, $user, $data): void {
            [$actor, $target] = $this->lockedPair($request, $user);
            if ($target->status === $data['status']) {
                return;
            }
            $target->status = $data['status'];
            $target->save();
            if ($target->status === 'INACTIVE') {
                $this->revokeSessions($target);
            }
            $this->audit($actor, $target, $target->status === 'ACTIVE' ? 'activated' : 'deactivated', ['status']);
        }, 3);

        return back()->with('success', 'Status pengguna dikemas kini.');
    }

    private function authorizeActor(Request $request): void
    {
        abort_unless($this->access->canManage($request->user()), 403);
    }

    private function lockedActor(Request $request): User
    {
        if (DB::table('iam_guard')->where('id', 1)->lockForUpdate()->first() === null) {
            throw new RuntimeException('IAM baseline is missing.');
        }
        $actor = User::query()->whereKey($request->user()->getAuthIdentifier())->lockForUpdate()->firstOrFail();
        abort_unless($actor->status === 'ACTIVE' && $actor->username !== null && $this->access->canManage($actor), 403);

        return $actor;
    }

    private function lockedPair(Request $request, User $user): array
    {
        $actor = $this->lockedActor($request);
        $target = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
        abort_unless($this->access->canManageTarget($actor, $target), 403);

        return [$actor, $target];
    }

    private function roles(array $ids): Collection
    {
        $roles = $this->access->grantableRoles()->whereIn('roles.id', $ids)->orderBy('roles.id')->lockForUpdate()->get();
        if ($roles->count() !== count($ids)) {
            throw ValidationException::withMessages(['role_ids' => 'Pilih peranan yang dibenarkan sahaja.']);
        }

        return $roles;
    }

    private function displayName(string $value): string
    {
        $name = trim($value);
        if ($name === '') {
            throw ValidationException::withMessages(['display_name' => 'Nama paparan diperlukan.']);
        }

        return $name;
    }

    private function revokeSessions(User $target): void
    {
        $target->setRememberToken(null);
        $target->save();
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $target->id)->delete();
        }
    }

    private function audit(User $actor, User $target, string $event, array $fields): void
    {
        AuditEvent::query()->create([
            'occurred_at' => now(), 'actor_id' => $actor->id,
            'action_code' => 'iam.user.'.$event, 'target_ref' => 'user:'.$target->id,
            'outcome' => 'SUCCESS', 'metadata' => ['source' => 'user_admin_ui', 'changed_fields' => $fields],
        ]);
    }
}
