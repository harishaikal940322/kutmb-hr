<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

#[Fillable(['occurred_at', 'actor_id', 'action_code', 'target_ref', 'outcome', 'metadata'])]
class AuditEvent extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'occurred_at' => 'immutable_datetime',
            'metadata' => AsArrayObject::class,
        ];
    }

    /** @param array<string, mixed>|null $metadata */
    public function setMetadataAttribute(?array $metadata): void
    {
        $allowed = ['source', 'reason_code', 'changed_fields', 'correlation_id'];
        $keys = array_keys($metadata ?? []);

        if (array_diff($keys, $allowed) !== []) {
            throw new InvalidArgumentException('Audit metadata contains an unsupported field.');
        }

        foreach (['source', 'reason_code', 'correlation_id'] as $key) {
            if (isset($metadata[$key]) && (! is_string($metadata[$key]) || strlen($metadata[$key]) > 100)) {
                throw new InvalidArgumentException('Audit metadata values must be short strings.');
            }
        }

        if (isset($metadata['changed_fields'])) {
            $fields = $metadata['changed_fields'];
            $valid = is_array($fields)
                && array_is_list($fields)
                && collect($fields)->every(fn (mixed $field): bool => is_string($field)
                    && preg_match('/\A[a-z][a-z0-9_]{0,63}\z/D', $field) === 1
                    && preg_match('/password|secret|token|credential|hash/i', $field) !== 1);

            if (! $valid) {
                throw new InvalidArgumentException('Audit changed_fields must contain safe field names only.');
            }
        }

        $this->attributes['metadata'] = $metadata === null ? null : json_encode($metadata, JSON_THROW_ON_ERROR);
    }
}
