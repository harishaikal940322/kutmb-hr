<?php

use App\Http\Controllers\AnnualLeaveController;
use App\Http\Controllers\AnnualLeaveWorkflowController;
use App\Http\Controllers\ApprovalOfficeController;
use App\Http\Controllers\EmployeeAccountLinkController;
use App\Http\Controllers\EmployeeAssignmentController;
use App\Http\Controllers\EmployeeFoundationController;
use App\Http\Controllers\EmployeeSelfProfileController;
use App\Http\Controllers\IdentityMenuAccessController;
use App\Http\Controllers\IdentityPermissionController;
use App\Http\Controllers\IdentityRoleController;
use App\Http\Controllers\KeycloakController;
use App\Http\Controllers\LeaveCalendarDraftController;
use App\Http\Controllers\LeavePolicyDraftController;
use App\Http\Controllers\LeaveSessionSimulationController;
use App\Http\Controllers\LocalSystemAdminBootstrapController;
use App\Http\Controllers\MfaController;
use App\Http\Controllers\OrganizationReferenceController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserDirectoryController;
use App\Http\Middleware\EnsureLocalHrDevelopment;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware('guest')->group(function (): void {
    Route::get('/auth/keycloak', [KeycloakController::class, 'redirect'])->name('keycloak.redirect');
    Route::get('/auth/keycloak/callback', [KeycloakController::class, 'callback'])->name('keycloak.callback');
});

Route::get('register', [LocalSystemAdminBootstrapController::class, 'create'])
    ->name('register');
Route::get('register/mfa-qr-code', [LocalSystemAdminBootstrapController::class, 'qrCode'])
    ->middleware('throttle:10,1')
    ->name('register.mfa.qr-code');
Route::post('register', [LocalSystemAdminBootstrapController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('register.store');

Route::middleware(['auth', 'active-account'])->group(function () {
    Route::get('security/mfa/setup', [MfaController::class, 'show'])
        ->name('mfa.setup');
    Route::get('security/mfa/qr-code', [MfaController::class, 'qrCode'])
        ->middleware('password.confirm')
        ->name('mfa.setup.qr-code');
    Route::post('security/mfa/setup', [MfaController::class, 'start'])
        ->middleware('password.confirm')
        ->name('mfa.setup.start');
    Route::post('security/mfa/confirm', [MfaController::class, 'confirm'])
        ->middleware('password.confirm')
        ->name('mfa.setup.confirm');
});

Route::middleware(['protected', 'can:iam.rbac.manage'])
    ->prefix('administration/access')
    ->name('iam.')
    ->group(function (): void {
        Route::get('roles', [IdentityRoleController::class, 'index'])->name('roles.index');
        Route::post('roles', [IdentityRoleController::class, 'store'])->name('roles.store');
        Route::patch('roles/{role}', [IdentityRoleController::class, 'update'])->name('roles.update');
        Route::delete('roles/{role}', [IdentityRoleController::class, 'destroy'])->name('roles.destroy');
        Route::get('permissions', IdentityPermissionController::class)->name('permissions.index');
        Route::get('menus', [IdentityMenuAccessController::class, 'index'])->name('menus.index');
        Route::put('menus', [IdentityMenuAccessController::class, 'update'])->name('menus.update');
    });

Route::middleware('protected')->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
    Route::get('profile', [ProfileController::class, 'show'])->name('profile');
    Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('users', UserDirectoryController::class)
        ->middleware(['user-directory-preview', 'permission:iam.users.view'])
        ->name('users.index');
});

Route::get('/leave/annual', AnnualLeaveController::class)
    ->middleware(['protected', EnsureLocalHrDevelopment::class])
    ->name('leave.annual.index');

Route::post('/leave/annual/entitlements/{employee}/confirm', [AnnualLeaveController::class, 'confirm'])
    ->middleware(['protected', EnsureLocalHrDevelopment::class, 'permission:leave.entitlement.view', 'permission:leave.entitlement.confirm'])
    ->whereNumber('employee')->name('leave.annual.entitlements.confirm');

Route::get('/leave/annual/entitlements/{employee}/prorata', [AnnualLeaveController::class, 'previewNewHireProrata'])
    ->middleware(['protected', EnsureLocalHrDevelopment::class, 'permission:leave.entitlement.view', 'permission:leave.entitlement.confirm'])
    ->whereNumber('employee')->name('leave.annual.entitlements.prorata');

Route::post('/leave/annual/entitlements/{employee}/credit', [AnnualLeaveController::class, 'credit'])
    ->middleware(['protected', EnsureLocalHrDevelopment::class, 'permission:leave.entitlement.view', 'permission:leave.entitlement.credit'])
    ->whereNumber('employee')->name('leave.annual.entitlements.credit');

Route::get('/leave/annual/team', [AnnualLeaveWorkflowController::class, 'team'])
    ->middleware(['protected', EnsureLocalHrDevelopment::class, 'permission:leave.calendar.team.view'])
    ->name('leave.annual.team');

Route::middleware(['protected', EnsureLocalHrDevelopment::class])->prefix('leave/annual/requests')->group(function (): void {
    Route::post('/', [AnnualLeaveWorkflowController::class, 'store'])->name('leave.annual.requests.store');
    Route::post('/{leaveRequest}/decision', [AnnualLeaveWorkflowController::class, 'decide'])
        ->whereNumber('leaveRequest')->name('leave.annual.requests.decide');
    Route::post('/{leaveRequest}/withdraw', [AnnualLeaveWorkflowController::class, 'withdraw'])
        ->whereNumber('leaveRequest')->name('leave.annual.requests.withdraw');
    Route::post('/{leaveRequest}/cancellation', [AnnualLeaveWorkflowController::class, 'requestCancellation'])
        ->whereNumber('leaveRequest')->name('leave.annual.requests.cancellation');
    Route::post('/{leaveRequest}/cancellation/decision', [AnnualLeaveWorkflowController::class, 'decideCancellation'])
        ->whereNumber('leaveRequest')->name('leave.annual.requests.cancellation.decide');
    Route::post('/{leaveRequest}/partial', [AnnualLeaveWorkflowController::class, 'requestPartial'])
        ->whereNumber('leaveRequest')->name('leave.annual.requests.partial');
    Route::post('/partial/{case}/verify', [AnnualLeaveWorkflowController::class, 'verifyPartial'])
        ->whereNumber('case')->name('leave.annual.requests.partial.verify');
    Route::post('/partial/{case}/decision', [AnnualLeaveWorkflowController::class, 'decidePartial'])
        ->whereNumber('case')->name('leave.annual.requests.partial.decide');
});

Route::middleware(['protected', EnsureLocalHrDevelopment::class, 'permission:hr.organization.manage'])
    ->prefix('organization')->name('organization.')->group(function (): void {
        Route::get('references', [OrganizationReferenceController::class, 'index'])->name('references.index');
        Route::post('references/{kind}', [OrganizationReferenceController::class, 'store'])->whereIn('kind', ['units', 'categories'])->name('references.store');
        Route::patch('references/{kind}/{id}', [OrganizationReferenceController::class, 'update'])->whereIn('kind', ['units', 'categories'])->whereNumber('id')->name('references.update');
    });

Route::middleware(['protected', EnsureLocalHrDevelopment::class, 'permission:hr.employees.view'])
    ->prefix('employees')->name('employees.')->group(function (): void {
        Route::get('/', [EmployeeFoundationController::class, 'index'])->name('index');
        Route::post('/', [EmployeeFoundationController::class, 'store'])->middleware('permission:hr.employees.manage')->name('store');
        Route::patch('/{employeeId}', [EmployeeFoundationController::class, 'update'])->whereNumber('employeeId')->middleware('permission:hr.employees.manage')->name('update');
        Route::put('/{employeeId}/account', [EmployeeAccountLinkController::class, 'update'])->whereNumber('employeeId')->middleware('permission:hr.accounts.link')->name('account.update');
        Route::post('/{employeeId}/assignments', [EmployeeAssignmentController::class, 'store'])->whereNumber('employeeId')->middleware('permission:hr.assignments.manage')->name('assignments.store');
    });

Route::middleware(['protected', EnsureLocalHrDevelopment::class, 'permission:hr.offices.manage'])
    ->prefix('organization/offices')->name('organization.offices.')->group(function (): void {
        Route::get('/', [ApprovalOfficeController::class, 'index'])->name('index');
        Route::post('/', [ApprovalOfficeController::class, 'store'])->name('store');
        Route::patch('/{id}/end', [ApprovalOfficeController::class, 'end'])->whereNumber('id')->name('end');
    });

Route::get('/employee-profile', EmployeeSelfProfileController::class)
    ->middleware(['protected', EnsureLocalHrDevelopment::class, 'permission:hr.profile.self.view'])->name('employees.self');

Route::middleware(['protected', EnsureLocalHrDevelopment::class, 'permission:leave.calendar.manage'])
    ->prefix('leave/calendar-drafts')->name('leave.calendar.drafts.')->group(function (): void {
        Route::get('/', [LeaveCalendarDraftController::class, 'index'])->name('index');
        Route::post('/', [LeaveCalendarDraftController::class, 'storeVersion'])->name('store');
        Route::patch('/{versionId}/cancellation', [LeaveCalendarDraftController::class, 'setVersionCancellation'])->whereNumber('versionId')->name('cancellation');
        Route::post('/{versionId}/simulate-confirmation', [LeaveCalendarDraftController::class, 'confirmSimulation'])->whereNumber('versionId')->name('simulate-confirmation');
        Route::post('/{versionId}/days', [LeaveCalendarDraftController::class, 'storeDay'])->whereNumber('versionId')->name('days.store');
        Route::patch('/{versionId}/days/{dayId}', [LeaveCalendarDraftController::class, 'updateDay'])->whereNumber(['versionId', 'dayId'])->name('days.update');
        Route::patch('/{versionId}/days/{dayId}/cancellation', [LeaveCalendarDraftController::class, 'setDayCancellation'])->whereNumber(['versionId', 'dayId'])->name('days.cancellation');
    });

Route::middleware(['protected', EnsureLocalHrDevelopment::class, 'permission:leave.policy.manage'])
    ->prefix('leave/policy-drafts')->name('leave.policy.drafts.')->group(function (): void {
        Route::get('/', [LeavePolicyDraftController::class, 'index'])->name('index');
        Route::post('/', [LeavePolicyDraftController::class, 'store'])->name('store');
        Route::patch('/{versionId}/cancellation', [LeavePolicyDraftController::class, 'setVersionCancellation'])->whereNumber('versionId')->name('cancellation');
        Route::post('/{versionId}/simulate-schedule', [LeavePolicyDraftController::class, 'confirmScheduleSimulation'])->whereNumber('versionId')->name('simulate-schedule');
        Route::patch('/{versionId}', [LeavePolicyDraftController::class, 'update'])->whereNumber('versionId')->name('update');
        Route::post('/{versionId}/entitlements', [LeavePolicyDraftController::class, 'storeEntitlementDraft'])->whereNumber('versionId')->name('entitlements.store');
        Route::patch('/{versionId}/entitlements/{ruleId}', [LeavePolicyDraftController::class, 'updateEntitlementDraft'])->whereNumber(['versionId', 'ruleId'])->name('entitlements.update');
        Route::patch('/{versionId}/entitlements/{ruleId}/cancellation', [LeavePolicyDraftController::class, 'setEntitlementCancellation'])->whereNumber(['versionId', 'ruleId'])->name('entitlements.cancellation');
    });

Route::get('/leave/session-simulation', LeaveSessionSimulationController::class)
    ->middleware(['protected', EnsureLocalHrDevelopment::class, 'permission:leave.calendar.manage', 'permission:leave.policy.manage'])
    ->name('leave.session-simulation');
