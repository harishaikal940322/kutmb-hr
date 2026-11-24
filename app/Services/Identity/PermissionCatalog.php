<?php

namespace App\Services\Identity;

use App\Models\Permission;

class PermissionCatalog
{
    /** @return array<string, array{label: string, description: string, group: string, submodule: string, action: string, system_only: bool}> */
    public function definitions(): array
    {
        /** @var array<string, array{label: string, description: string, group: string, submodule: string, action: string, system_only: bool}> $definitions */
        $definitions = config('iam.permissions', []);

        return $definitions;
    }

    /** @return list<array{code: string, label: string, description: string, group: string, submodule: string, action: string, systemOnly: bool}> */
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
                'submodule' => $definition['submodule'],
                'action' => $definition['action'],
                'systemOnly' => $definition['system_only'],
            ];
        }

        return $rows;
    }

    /** @return list<array{key: string, label: string, description: string, permissions: list<string>, available: bool}> */
    public function templates(): array
    {
        $registered = Permission::query()->pluck('code')->all();
        $assignable = $this->assignableCodes();
        $templates = [];

        foreach (config('iam.role_templates', []) as $key => $template) {
            $codes = $template['permissions'];
            $templates[] = [
                'key' => $key,
                'label' => $template['label'],
                'description' => $template['description'],
                'permissions' => $codes,
                'available' => count(array_diff($codes, $assignable)) === 0
                    && count(array_diff($codes, $registered)) === 0,
            ];
        }

        return $templates;
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
