<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $code_hash
 * @property Carbon|null $consumed_at
 */
#[Fillable(['user_id', 'code_hash', 'consumed_at'])]
class UserRecoveryCode extends Model
{
    protected function casts(): array
    {
        return [
            'consumed_at' => 'immutable_datetime',
        ];
    }
}
