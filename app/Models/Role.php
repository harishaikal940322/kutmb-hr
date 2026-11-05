<?php

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;

/**
 * @property string $code
 * @property string $name
 * @property string $label
 * @property bool $is_system
 */
class Role extends SpatieRole
{
    protected $fillable = ['code', 'name', 'guard_name', 'label', 'is_system'];

    /** @param array<string, mixed> $attributes */
    public static function create(array $attributes = [])
    {
        $code = $attributes['code'] ?? $attributes['name'] ?? null;
        $label = $attributes['label'] ?? (isset($attributes['code']) ? ($attributes['name'] ?? $code) : $code);

        $role = parent::create([
            ...$attributes,
            'code' => $code,
            'name' => $code,
            'guard_name' => $attributes['guard_name'] ?? 'web',
            'label' => $label,
        ]);

        assert($role instanceof static);

        return $role;
    }
}
