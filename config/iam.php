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
        ...(in_array($appEnvironment, ['local', 'testing'], true) ? ['leave.calendar.manage' => [
            'label' => 'Manage draft leave calendars', 'description' => 'Manage local development leave calendar drafts.',
            'group' => 'Annual leave', 'submodule' => 'Calendar drafts', 'action' => 'Manage', 'system_only' => false,
        ], 'hr.organization.manage' => [
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
            'hr.profile.self.view' => [
                'label' => 'View own employee profile', 'description' => 'View the linked local development employee profile.',
                'group' => 'Employee and organization', 'submodule' => 'Own profile', 'action' => 'View', 'system_only' => false,
            ],
            'hr.offices.manage' => [
                'label' => 'Manage approval offices', 'description' => 'Manage local development approval office appointments.',
                'group' => 'Employee and organization', 'submodule' => 'Approval offices', 'action' => 'Manage', 'system_only' => false,
            ],
            'hr.accounts.link' => [
                'label' => 'Link employee accounts', 'description' => 'Manage local development employee account associations.',
                'group' => 'Employee and organization', 'submodule' => 'Account association', 'action' => 'Manage', 'system_only' => false,
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
        ...(in_array($appEnvironment, ['local', 'testing'], true) ? [
            'staff-v1' => [
                'label' => 'Staf', 'description' => 'Profil pekerja sendiri yang dipautkan.',
                'permissions' => ['hr.profile.self.view'],
            ],
            'hr-executive-v1' => [
                'label' => 'Eksekutif HR', 'description' => 'Profil pekerja dan penempatan pembangunan.',
                'permissions' => ['hr.employees.view', 'hr.employees.manage', 'hr.assignments.manage'],
            ],
            'hr-manager-v1' => [
                'label' => 'Pengurus HR', 'description' => 'Pekerja, organisasi, pautan akaun dan penetapan pegawai pembangunan.',
                'permissions' => ['hr.employees.view', 'hr.employees.manage', 'hr.assignments.manage', 'hr.organization.manage', 'hr.accounts.link', 'hr.offices.manage'],
            ],
            'unit-manager-v1' => [
                'label' => 'Pengurus Unit', 'description' => 'Profil sendiri dan skop tugasan; skop cuti belum tersedia.',
                'permissions' => ['hr.profile.self.view', 'hr.organization.scoped.view'],
            ],
            'general-manager-v1' => [
                'label' => 'Pengurus Besar', 'description' => 'Profil sendiri dan skop tugasan; skop cuti belum tersedia.',
                'permissions' => ['hr.profile.self.view', 'hr.organization.scoped.view'],
            ],
            'alk-secretary-v1' => [
                'label' => 'Setiausaha ALK', 'description' => 'Skop tugasan kelulusan belum tersedia.',
                'permissions' => ['hr.organization.scoped.view'],
            ],
        ] : []),
    ],
    'menus' => [
        ...(in_array($appEnvironment, ['local', 'testing'], true) ? [
            'employees.self' => [
                'label' => 'Profil Pekerja Saya', 'href' => '/employee-profile', 'icon' => 'user',
                'permission' => 'hr.profile.self.view', 'editable' => false,
            ],
            'organization.offices.index' => [
                'label' => 'Penetapan Pegawai', 'href' => '/organization/offices', 'icon' => 'users',
                'permission' => 'hr.offices.manage', 'editable' => false,
            ],
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
            'leave.calendar.drafts.index' => [
                'label' => 'Draf Kalendar Cuti', 'href' => '/leave/calendar-drafts', 'icon' => 'menu',
                'permission' => 'leave.calendar.manage', 'editable' => false,
            ],
        ] : []),
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
