<?php

namespace App\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * @property string $code
 * @property string $name
 * @property string $label
 * @property string|null $description
 */
class Permission extends SpatiePermission
{
    protected $fillable = ['code', 'name', 'guard_name', 'label', 'description'];

    /** @param array<string, mixed> $attributes */
    public static function create(array $attributes = [])
    {
        $code = $attributes['code'] ?? $attributes['name'] ?? null;
        $label = $attributes['label'] ?? (isset($attributes['code']) ? ($attributes['name'] ?? $code) : $code);

        $permission = parent::create([
            ...$attributes,
            'code' => $code,
            'name' => $code,
            'guard_name' => $attributes['guard_name'] ?? 'web',
            'label' => $label,
        ]);

        assert($permission instanceof static);

        return $permission;
    }
}
