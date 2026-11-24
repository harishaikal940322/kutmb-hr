<?php

$appEnvironment = strtolower((string) env('APP_ENV', 'production'));
$isProduction = in_array($appEnvironment, ['production', 'prod'], true);
$mfaDefault = $isProduction ? 'yes' : 'no';
$mfaSetting = env('MFA', env('IAM_MFA_ENFORCED', $mfaDefault));
$mfaEnforced = filter_var($mfaSetting, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

if ($mfaEnforced === null || (is_string($mfaSetting) && trim($mfaSetting) === '')) {
    throw new InvalidArgumentException('MFA must be set to yes/no or true/false.');
}

if ($isProduction && ! $mfaEnforced) {
    throw new InvalidArgumentException('MFA must be enabled in production.');
}

return [
    'local_account_bootstrap_enabled' => env('IAM_LOCAL_ACCOUNT_BOOTSTRAP_ENABLED', false),
    'local_user_directory_preview_enabled' => env('IAM_LOCAL_USER_DIRECTORY_PREVIEW_ENABLED', false),
    'mfa_enabled' => $mfaEnforced,
    'mfa_enforced' => $mfaEnforced,
    'single_factor_password_minimum' => $appEnvironment === 'local' && ! $mfaEnforced ? 8 : 15,
    'permissions' => [
        'iam.rbac.manage' => [
            'label' => 'Manage access control',
            'description' => 'Manage system roles, role permissions, and menu access.',
            'group' => 'System administration',
            'submodule' => 'Access control',
            'action' => 'Manage',
            'system_only' => true,
        ],
        ...(in_array($appEnvironment, ['local', 'testing'], true) ? ['hr.organization.manage' => [
            'label' => 'Manage organization references',
            'description' => 'Manage local development units and employment categories.',
            'group' => 'Employee and organization',
            'submodule' => 'Organization references',
            'action' => 'Manage',
            'system_only' => false,
        ],
            'hr.employees.view' => [
                'label' => 'View employees', 'description' => 'View the local development employee directory.',
                'group' => 'Employee and organization', 'submodule' => 'Employees', 'action' => 'View', 'system_only' => false,
            ],
            'hr.assignments.manage' => [
                'label' => 'Manage assignments', 'description' => 'Manage local development employee assignments.',
                'group' => 'Employee and organization', 'submodule' => 'Assignments', 'action' => 'Manage', 'system_only' => false,
            ],
            'hr.employees.manage' => [
                'label' => 'Manage employees', 'description' => 'Create local development employee records.',
                'group' => 'Employee and organization', 'submodule' => 'Employees', 'action' => 'Manage', 'system_only' => false,
            ],
        ] : []),
        'iam.users.view' => [
            'label' => 'View user directory',
            'description' => 'View the local development user directory preview.',
            'group' => 'Identity and access',
            'submodule' => 'User directory',
            'action' => 'View',
            'system_only' => false,
        ],
    ],
    'role_templates' => [
        'directory-viewer-v1' => [
            'label' => 'User directory viewer',
            'description' => 'View the local development user directory preview.',
            'permissions' => ['iam.users.view'],
        ],
    ],
    'menus' => [
        'employees.index' => [
            'label' => 'Pekerja', 'href' => '/employees', 'icon' => 'users',
            'permission' => 'hr.employees.view', 'editable' => false,
        ],
        'organization.references.index' => [
            'label' => 'Unit dan Kategori',
            'href' => '/organization/references',
            'icon' => 'users',
            'permission' => 'hr.organization.manage',
            'editable' => false,
        ],
        'users.index' => [
            'label' => 'User Directory',
            'href' => '/users',
            'icon' => 'users',
            'permission' => 'iam.users.view',
            'editable' => true,
        ],
        'iam.roles.index' => [
            'label' => 'Roles',
            'href' => '/administration/access/roles',
            'icon' => 'shield',
            'permission' => 'iam.rbac.manage',
            'editable' => true,
        ],
        'iam.permissions.index' => [
            'label' => 'Permissions',
            'href' => '/administration/access/permissions',
            'icon' => 'key',
            'permission' => 'iam.rbac.manage',
            'editable' => true,
        ],
        'iam.menus.index' => [
            'label' => 'Menu access',
            'href' => '/administration/access/menus',
            'icon' => 'menu',
            'permission' => 'iam.rbac.manage',
            'editable' => true,
        ],
    ],
];
