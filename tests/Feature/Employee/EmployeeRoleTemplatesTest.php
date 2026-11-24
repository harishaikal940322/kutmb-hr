<?php

namespace Tests\Feature\Employee;

use App\Services\Identity\PermissionCatalog;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EmployeeRoleTemplatesTest extends TestCase
{
    use DatabaseTransactions;

    public function test_local_templates_use_exact_available_permissions_without_grants(): void
    {
        $beforeRoles = DB::table('roles')->count();
        $beforeGrants = DB::table('role_has_permissions')->count();
        $templates = collect(app(PermissionCatalog::class)->templates())->keyBy('key');
        $this->assertTrue($templates['staff-v1']['available']);
        $this->assertSame(['hr.profile.self.view'], $templates['staff-v1']['permissions']);
        $this->assertTrue($templates['hr-executive-v1']['available']);
        $this->assertSame(['hr.employees.view', 'hr.employees.manage', 'hr.assignments.manage'], $templates['hr-executive-v1']['permissions']);
        $this->assertTrue($templates['hr-manager-v1']['available']);
        $this->assertSame(['hr.employees.view', 'hr.employees.manage', 'hr.assignments.manage', 'hr.organization.manage', 'hr.accounts.link', 'hr.offices.manage'], $templates['hr-manager-v1']['permissions']);
        $this->assertFalse($templates['unit-manager-v1']['available']);
        $this->assertFalse($templates['general-manager-v1']['available']);
        $this->assertFalse($templates['alk-secretary-v1']['available']);
        $this->assertSame($beforeRoles, DB::table('roles')->count());
        $this->assertSame($beforeGrants, DB::table('role_has_permissions')->count());
    }
}
