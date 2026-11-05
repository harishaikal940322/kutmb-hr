<?php

namespace App\Services\Identity;

use App\Models\Permission;

class PermissionCatalog
{
    /** @return array<string, array{label: string, description: string, group: string, system_only: bool}> */
    public function definitions(): array
    {
        /** @var array<string, array{label: string, description: string, group: string, system_only: bool}> $definitions */
        $definitions = config('iam.permissions', []);

        return $definitions;
    }

    /** @return list<array{code: string, label: string, description: string, group: string, systemOnly: bool}> */
    public function rows(): array
    {
        $storedLabels = Permission::query()->pluck('label', 'code')->all();
        $rows = [];

        foreach ($this->definitions() as $code => $definition) {
            $rows[] = [
                'code' => $code,
                'label' => (string) ($storedLabels[$code] ?? $definition['label']),
                'description' => $definition['description'],
                'group' => $definition['group'],
                'systemOnly' => $definition['system_only'],
            ];
        }

        return $rows;
    }

    /** @return list<string> */
    public function assignableCodes(): array
    {
        $codes = [];

        foreach ($this->definitions() as $code => $definition) {
            if (! $definition['system_only']) {
                $codes[] = $code;
            }
        }

        return $codes;
    }
}
