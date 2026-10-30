<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        return Inertia::render('MyProfile', [
            'profile' => [
                'displayName' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'status' => $user->status,
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        $allowedInput = ['display_name', '_token', '_method'];

        if (array_diff(array_keys($request->all()), $allowedInput) !== []) {
            throw ValidationException::withMessages([
                'display_name' => 'Only the display name can be changed here.',
            ]);
        }

        $validated = $request->validate([
            'display_name' => ['required', 'string', 'max:255'],
        ]);
        $displayName = trim($validated['display_name']);

        if ($displayName === '') {
            throw ValidationException::withMessages([
                'display_name' => 'The display name field is required.',
            ]);
        }

        DB::transaction(function () use ($actor, $displayName): void {
            $user = User::query()->lockForUpdate()->whereKey($actor->getKey())->firstOrFail();

            if ($user->status !== 'ACTIVE' || $user->username === null) {
                abort(403);
            }

            if ($user->name === $displayName) {
                return;
            }

            $user->name = $displayName;
            $user->save();

            AuditEvent::query()->create([
                'occurred_at' => now(),
                'actor_id' => $user->getKey(),
                'action_code' => 'iam.profile.display_name_updated',
                'target_ref' => 'user:'.$user->getKey(),
                'outcome' => 'SUCCESS',
                'metadata' => [
                    'source' => 'self_service',
                    'changed_fields' => ['name'],
                ],
            ]);
        });

        return back()->with('success', 'Display name updated.');
    }
}
