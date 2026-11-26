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
        ...(in_array($appEnvironment, ['local', 'testing'], true) ? ['leave.entitlement.view' => [
            'label' => 'Lihat profil dan kelayakan cuti',
            'description' => 'View minimum employee profile and confirmed annual entitlement in local development.',
            'group' => 'Annual leave', 'submodule' => 'Profil & kelayakan', 'action' => 'View', 'system_only' => false,
        ], 'leave.request.self' => [
            'label' => 'Urus permohonan cuti sendiri',
            'description' => 'View own leave and submit or manage supported requests for a linked employee.',
            'group' => 'Annual leave', 'submodule' => 'Permohonan saya', 'action' => 'Manage', 'system_only' => false,
        ], 'leave.request.decide' => [
            'label' => 'Sokongan dan kelulusan cuti',
            'description' => 'Act only on requests assigned to a current authorized approval office.',
            'group' => 'Annual leave', 'submodule' => 'Sokongan & kelulusan', 'action' => 'Decide', 'system_only' => false,
        ], 'leave.calendar.team.view' => [
            'label' => 'Lihat jadual cuti unit',
            'description' => 'View approved absence names, dates and sessions for the linked employee’s current unit.',
            'group' => 'Annual leave', 'submodule' => 'Jadual unit', 'action' => 'View', 'system_only' => false,
        ], 'leave.entitlement.confirm' => [
            'label' => 'Sahkan kelayakan cuti',
            'description' => 'Confirm explicit synthetic annual entitlement input in local development.',
            'group' => 'Annual leave', 'submodule' => 'Profil & kelayakan', 'action' => 'Confirm', 'system_only' => false,
        ], 'leave.entitlement.credit' => [
            'label' => 'Kredit kelayakan cuti',
            'description' => 'Post confirmed annual entitlement using the existing once-only credit service.',
            'group' => 'Annual leave', 'submodule' => 'Profil & kelayakan', 'action' => 'Credit', 'system_only' => false,
        ], 'leave.partial-cancellation.review' => [
            'label' => 'Semak pembatalan separa',
            'description' => 'Verify actual return date for a supported partial leave cancellation.',
            'group' => 'Annual leave', 'submodule' => 'Pindaan dan pembatalan', 'action' => 'Review', 'system_only' => false,
        ], 'leave.adjustment.prepare' => [
            'label' => 'Sediakan pelarasan baki',
            'description' => 'Prepare a documented local adjustment without posting any balance.',
            'group' => 'Annual leave', 'submodule' => 'Pelarasan baki', 'action' => 'Prepare', 'system_only' => false,
        ], 'leave.adjustment.decide' => [
            'label' => 'Putuskan pelarasan baki',
            'description' => 'Independently approve or reject a prepared local balance adjustment.',
            'group' => 'Annual leave', 'submodule' => 'Pelarasan baki', 'action' => 'Decide', 'system_only' => false,
        ], 'leave.settlement.prepare' => [
            'label' => 'Sediakan penyelesaian baki',
            'description' => 'Prepare exit settlement and record external settlement evidence in local development.',
            'group' => 'Annual leave', 'submodule' => 'Penyelesaian baki', 'action' => 'Prepare', 'system_only' => false,
        ], 'leave.settlement.confirm' => [
            'label' => 'Sahkan penyelesaian baki',
            'description' => 'Independently verify exit balance and external settlement evidence in local development.',
            'group' => 'Annual leave', 'submodule' => 'Penyelesaian baki', 'action' => 'Confirm', 'system_only' => false,
        ], 'leave.attendance.review' => [
            'label' => 'Rekod hasil semakan HR',
            'description' => 'Record an evidence-referenced finding for a rejected leave review; no payroll or balance mutation.',
            'group' => 'Annual leave', 'submodule' => 'Laporan', 'action' => 'Review', 'system_only' => false,
        ], 'leave.reports.view' => [
            'label' => 'Lihat laporan cuti',
            'description' => 'View HR leave balances, status summaries and attendance review records; no private reasons.',
            'group' => 'Annual leave', 'submodule' => 'Laporan', 'action' => 'View', 'system_only' => false,
        ], 'leave.year-end.post' => [
            'label' => 'Pos pindahan tutup tahun',
            'description' => 'Explicitly post a supported positive year-close transfer after reviewing the balances.',
            'group' => 'Annual leave', 'submodule' => 'Tutup tahun', 'action' => 'Post', 'system_only' => false,
        ], 'leave.year-end.preview' => [
            'label' => 'Pratonton tutup tahun',
            'description' => 'Preview eligible carry-forward using recorded balances and explicit confirmed cap input.',
            'group' => 'Annual leave', 'submodule' => 'Tutup tahun', 'action' => 'Preview', 'system_only' => false,
        ], 'leave.request.record-on-behalf' => [
            'label' => 'Rekod permohonan dengan pengesahan staf',
            'description' => 'Internal consent-based recording authority; no public workflow is implied.',
            'group' => 'Annual leave', 'submodule' => 'Permohonan', 'action' => 'Record', 'system_only' => false,
        ], 'leave.policy.manage' => [
            'label' => 'Manage draft leave policies', 'description' => 'Manage local development leave policy drafts.',
            'group' => 'Annual leave', 'submodule' => 'Policy drafts', 'action' => 'Manage', 'system_only' => false,
        ], 'leave.calendar.manage' => [
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
    // Defaults for new local development roles only; existing assignments are never synchronized by the seeder.
    'development_role_defaults' => in_array($appEnvironment, ['local', 'testing'], true) ? [
        'staff' => [
            'label' => 'Staf',
            'permissions' => ['leave.request.self', 'hr.profile.self.view', 'leave.calendar.team.view'],
        ],
        'unit-manager' => [
            'label' => 'Pengurus Unit',
            'permissions' => ['leave.request.self', 'leave.request.decide', 'hr.profile.self.view', 'leave.calendar.team.view'],
        ],
        'general-manager' => [
            'label' => 'Pengurus Besar',
            'permissions' => ['leave.request.self', 'leave.request.decide', 'hr.profile.self.view', 'leave.calendar.team.view'],
        ],
        'alk-secretary' => [
            'label' => 'Setiausaha ALK',
            // Current approval authority requires an effective office assignment, not this role name.
            'permissions' => ['leave.request.decide'],
        ],
        'hr-executive' => [
            'label' => 'Eksekutif HR',
            'permissions' => ['hr.employees.view', 'hr.employees.manage', 'hr.assignments.manage', 'leave.entitlement.view', 'leave.reports.view', 'leave.request.record-on-behalf', 'leave.adjustment.prepare', 'leave.settlement.prepare'],
        ],
        'hr-manager' => [
            'label' => 'Pengurus HR',
            'permissions' => [
                'hr.employees.view', 'hr.employees.manage', 'hr.assignments.manage',
                'hr.organization.manage', 'hr.accounts.link', 'hr.offices.manage',
                'leave.entitlement.view', 'leave.entitlement.confirm', 'leave.entitlement.credit',
                'leave.calendar.manage', 'leave.policy.manage', 'leave.partial-cancellation.review',
                'leave.reports.view', 'leave.year-end.preview', 'leave.year-end.post', 'leave.request.record-on-behalf', 'leave.adjustment.decide', 'leave.attendance.review', 'leave.settlement.confirm',
            ],
        ],
        'it-admin' => [
            'label' => 'Pentadbir IT',
            'permissions' => ['iam.users.view'],
        ],
    ] : [],
    'role_templates' => [
        'directory-viewer-v1' => [
            'label' => 'User directory viewer',
            'description' => 'View the local development user directory preview.',
            'permissions' => ['iam.users.view'],
        ],
        ...(in_array($appEnvironment, ['local', 'testing'], true) ? [
            'staff-v1' => [
                'label' => 'Staf', 'description' => 'Profil dan permohonan cuti sendiri; pautan pekerja diperlukan.',
                'permissions' => ['hr.profile.self.view', 'leave.request.self', 'leave.calendar.team.view'],
            ],
            'hr-executive-v1' => [
                'label' => 'Eksekutif HR', 'description' => 'Profil pekerja, penempatan dan semakan kelayakan pembangunan.',
                'permissions' => ['hr.employees.view', 'hr.employees.manage', 'hr.assignments.manage', 'leave.entitlement.view', 'leave.reports.view', 'leave.request.record-on-behalf', 'leave.adjustment.prepare', 'leave.settlement.prepare'],
            ],
            'hr-manager-v1' => [
                'label' => 'Pengurus HR', 'description' => 'Pengurusan pekerja, organisasi dan operasi kelayakan cuti pembangunan.',
                'permissions' => [
                    'hr.employees.view', 'hr.employees.manage', 'hr.assignments.manage',
                    'hr.organization.manage', 'hr.accounts.link', 'hr.offices.manage',
                    'leave.entitlement.view', 'leave.entitlement.confirm', 'leave.entitlement.credit',
                    'leave.calendar.manage', 'leave.policy.manage', 'leave.partial-cancellation.review',
                'leave.reports.view', 'leave.year-end.preview', 'leave.year-end.post', 'leave.request.record-on-behalf', 'leave.adjustment.decide', 'leave.attendance.review', 'leave.settlement.confirm',
                ],
            ],
            'unit-manager-v1' => [
                'label' => 'Pengurus Unit', 'description' => 'Cuti sendiri dan sokongan dalam penetapan unit semasa.',
                'permissions' => ['hr.profile.self.view', 'leave.request.self', 'leave.request.decide', 'leave.calendar.team.view'],
            ],
            'general-manager-v1' => [
                'label' => 'Pengurus Besar', 'description' => 'Cuti sendiri dan tindakan mengikut penetapan jawatan semasa.',
                'permissions' => ['hr.profile.self.view', 'leave.request.self', 'leave.request.decide', 'leave.calendar.team.view'],
            ],
            'alk-secretary-v1' => [
                'label' => 'Setiausaha ALK', 'description' => 'Kelulusan dalam penetapan jawatan semasa.',
                'permissions' => ['leave.request.decide'],
            ],
        ] : []),
    ],
    'menus' => [
        ...(in_array($appEnvironment, ['local', 'testing'], true) ? [
            'leave.annual.entitlements' => [
                'label' => 'Cuti Tahunan — Profil & kelayakan', 'href' => '/leave/annual', 'icon' => 'calendar',
                'permission' => 'leave.entitlement.view', 'editable' => false,
            ],
            'leave.annual.index' => [
                'label' => 'Cuti Tahunan', 'href' => '/leave/annual', 'icon' => 'calendar',
                'permission' => 'iam.rbac.manage', 'editable' => false,
            ],
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
            'leave.policy.drafts.index' => [
                'label' => 'Draf Dasar Cuti', 'href' => '/leave/policy-drafts', 'icon' => 'menu',
                'permission' => 'leave.policy.manage', 'editable' => false,
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
