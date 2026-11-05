<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Services\Identity\RecoveryCodeService;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\Http\Requests\TwoFactorLoginRequest as FortifyTwoFactorLoginRequest;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorLoginRequest extends FortifyTwoFactorLoginRequest
{
    public function hasValidCode(): bool
    {
        $code = $this->input('code');

        if (! is_string($code) || $code === '' || ! $this->hasChallengedUser()) {
            return false;
        }

        $userId = $this->challengedUser()->getKey();

        $matchedTimestamp = DB::transaction(function () use ($userId, $code): int|false {
            $user = User::query()->lockForUpdate()->find($userId);

            if (! $user instanceof User || ! $user->hasEnabledTwoFactorAuthentication()) {
                return false;
            }

            $secret = Fortify::currentEncrypter()->decrypt($user->two_factor_secret);
            $matchedTimestamp = app(Google2FA::class)->verifyKeyNewer(
                $secret,
                $code,
                (int) ($user->two_factor_last_used_at ?? 0),
                1,
            );

            if (! is_int($matchedTimestamp)) {
                return false;
            }

            $user->forceFill(['two_factor_last_used_at' => $matchedTimestamp])->save();

            return $matchedTimestamp;
        }, 3);

        if ($matchedTimestamp === false) {
            return false;
        }

        $this->session()->forget('login.id');

        return true;
    }

    public function validRecoveryCode()
    {
        $code = $this->input('recovery_code');

        if (! is_string($code) || $code === '' || ! $this->hasChallengedUser()) {
            return null;
        }

        $user = $this->challengedUser();

        if (! $user instanceof User) {
            return null;
        }

        return app(RecoveryCodeService::class)->consume($user, $code)
            ? $code
            : null;
    }
}
