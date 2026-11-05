<?php

namespace App\Models;

use App\Support\Identity\Username as UsernameCanonicalizer;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string|null $email
 * @property string|null $username
 * @property string|null $username_canonical
 * @property string $status
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property int|null $two_factor_last_used_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'username', 'status'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, TwoFactorAuthenticatable;

    public function setUsernameAttribute(?string $value): void
    {
        if ($value === null) {
            $this->attributes['username'] = null;
            $this->attributes['username_canonical'] = null;

            return;
        }

        $this->attributes['username'] = $value;
        $this->attributes['username_canonical'] = UsernameCanonicalizer::canonicalize($value);
    }

    /**
     * Recovery codes are kept in the user_recovery_codes table as one-way hashes.
     * Fortify's default encrypted-code storage is intentionally not used.
     *
     * @return list<string>
     */
    public function recoveryCodes(): array
    {
        return [];
    }

    /**
     * The custom challenge request consumes recovery codes atomically before
     * Fortify establishes the authenticated session.
     */
    public function replaceRecoveryCode(string $code): void
    {
        // Consumption is handled by RecoveryCodeService from the Fortify request.
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }
}
