<?php

namespace Tests\Feature\Leave;

use App\Models\User;
use App\Services\Leave\LeaveApprovalAssignmentResolver;
use DomainException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class LeaveApprovalAssignmentResolverTest extends TestCase
{
    use DatabaseTransactions;

    public function test_resolves_scoped_regular_staff_route_from_current_unit_and_office_assignments(): void
    {
        $fixture = $this->fixture('staff');
        $pu = $this->user('staff-pu');
        $pb = $this->user('staff-pb');
        $secretary = $this->user('staff-secretary');
        $this->assignOffice($pu, 'PU', $fixture['unit_id']);
        $this->assignOffice($pb, 'PB');
        $this->assignOffice($secretary, 'SECRETARY_ALK');

        $route = app(LeaveApprovalAssignmentResolver::class)->resolveForEmployee(
            $fixture['employee_user']->id,
            $fixture['employee_id'],
            11,
            $this->slots(11),
        );

        $this->assertSame(['UNIT_MANAGER_SUPPORT', 'GENERAL_MANAGER_SUPPORT', 'SECRETARY_FINAL'], array_column($route, 'stage_code'));
        $this->assertSame([$pu->id, $pb->id, $secretary->id], array_column($route, 'assignee_user_id'));
        $this->assertSame($fixture['unit_id'], $route[0]['unit_id']);
    }

    public function test_unit_manager_and_general_manager_requests_avoid_self_support(): void
    {
        $unitManager = $this->fixture('unitmanager');
        $this->assignOffice($unitManager['employee_user'], 'PU', $unitManager['unit_id']);
        $pb = $this->user('pb');
        $secretary = $this->user('secretary');
        $this->assignOffice($pb, 'PB');
        $this->assignOffice($secretary, 'SECRETARY_ALK');
        $unitManagerRoute = app(LeaveApprovalAssignmentResolver::class)->resolveForEmployee(
            $unitManager['employee_user']->id, $unitManager['employee_id'], 10, $this->slots(10),
        );

        $generalManager = $this->fixture('generalmanager');
        $this->assignOffice($generalManager['employee_user'], 'PB');
        $generalManagerRoute = app(LeaveApprovalAssignmentResolver::class)->resolveForEmployee(
            $generalManager['employee_user']->id, $generalManager['employee_id'], 1, $generalManager['slots'],
        );

        $this->assertSame(['GENERAL_MANAGER_FINAL'], array_column($unitManagerRoute, 'stage_code'));
        $this->assertSame(['SECRETARY_FINAL'], array_column($generalManagerRoute, 'stage_code'));
    }

    public function test_request_spanning_transfer_units_requires_hr_review(): void
    {
        $fixture = $this->fixture('transfer');
        $newUnit = DB::table('organization_units')->insertGetId(['code' => 'new'.$fixture['suffix'], 'name' => 'Synthetic new unit', 'active' => true]);
        $category = DB::table('employment_categories')->insertGetId(['code' => 'cat'.$fixture['suffix'], 'name' => 'Synthetic category', 'active' => true]);
        DB::table('employee_assignments')->where('employee_id', $fixture['employee_id'])->update(['effective_to' => '2098-03-01']);
        DB::table('employee_assignments')->insert([
            'employee_id' => $fixture['employee_id'], 'unit_id' => $newUnit, 'category_id' => $category,
            'effective_from' => '2098-03-02',
        ]);

        $this->expectException(DomainException::class);
        app(LeaveApprovalAssignmentResolver::class)->resolveForEmployee(
            $fixture['employee_user']->id,
            $fixture['employee_id'],
            2,
            [['date' => '2098-03-01', 'session' => 'AM'], ['date' => '2098-03-02', 'session' => 'AM']],
        );
    }

    public function test_invalid_duration_or_duplicate_session_fails_before_assignment_resolution(): void
    {
        $resolver = app(LeaveApprovalAssignmentResolver::class);
        $oneSession = [['date' => '2098-03-02', 'session' => 'AM']];
        $duplicateSession = [
            ['date' => '2098-03-02', 'session' => 'AM'],
            ['date' => '2098-03-02', 'session' => 'AM'],
        ];

        $this->assertDomainException(fn () => $resolver->resolveForEmployee(1, 1, 2, $oneSession));
        $this->assertDomainException(fn () => $resolver->resolveForEmployee(1, 1, 2, $duplicateSession));
    }

    public function test_missing_ambiguous_or_self_approval_assignment_fails_closed(): void
    {
        $missing = $this->fixture('missing');
        $pb = $this->user('missing-pb');
        $this->assignOffice($pb, 'PB');
        $this->assertDomainException(fn () => app(LeaveApprovalAssignmentResolver::class)->resolveForEmployee(
            $missing['employee_user']->id, $missing['employee_id'], 1, $missing['slots'],
        ));

        $ambiguous = $this->fixture('ambiguous');
        $pu1 = $this->user('pu1');
        $pu2 = $this->user('pu2');
        $pb = $this->user('ambiguous-pb');
        $this->assignOffice($pu1, 'PU', $ambiguous['unit_id']);
        $this->assignOffice($pu2, 'PU', $ambiguous['unit_id']);
        $this->assignOffice($pb, 'PB');
        $this->assertDomainException(fn () => app(LeaveApprovalAssignmentResolver::class)->resolveForEmployee(
            $ambiguous['employee_user']->id, $ambiguous['employee_id'], 1, $ambiguous['slots'],
        ));

        $self = $this->fixture('self');
        $this->assignOffice($self['employee_user'], 'PB');
        $this->assignOffice($self['employee_user'], 'SECRETARY_ALK');
        $this->expectException(DomainException::class);
        app(LeaveApprovalAssignmentResolver::class)->resolveForEmployee(
            $self['employee_user']->id, $self['employee_id'], 1, $self['slots'],
        );
    }

    /** @return array{suffix:string,employee_user:User,employee_id:int,unit_id:int,slots:list<array{date:string,session:string}>} */
    private function fixture(string $label): array
    {
        $suffix = Str::lower(Str::random(8));
        $employeeUser = $this->user($label);
        $unitId = DB::table('organization_units')->insertGetId(['code' => $label.$suffix, 'name' => 'Synthetic unit', 'active' => true]);
        $categoryId = DB::table('employment_categories')->insertGetId(['code' => 'cat'.$label.$suffix, 'name' => 'Synthetic category', 'active' => true]);
        $employeeId = DB::table('employees')->insertGetId([
            'employee_no' => $label.$suffix, 'display_name' => 'Synthetic employee '.$label,
            'user_id' => $employeeUser->id, 'service_start' => '2020-01-01',
        ]);
        DB::table('employee_assignments')->insert([
            'employee_id' => $employeeId, 'unit_id' => $unitId, 'category_id' => $categoryId,
            'effective_from' => '2020-01-01',
        ]);

        return [
            'suffix' => $suffix,
            'employee_user' => $employeeUser,
            'employee_id' => $employeeId,
            'unit_id' => $unitId,
            'slots' => [['date' => '2098-03-02', 'session' => 'AM']],
        ];
    }

    private function user(string $label): User
    {
        return User::factory()->create(['username' => $label.Str::lower(Str::random(8)), 'email' => null, 'status' => 'ACTIVE']);
    }

    private function assignOffice(User $user, string $office, ?int $unitId = null): void
    {
        DB::table('approval_assignments')->insert([
            'user_id' => $user->id, 'office_code' => $office, 'unit_id' => $unitId,
            'effective_from' => '2020-01-01',
        ]);
    }

    /** @return list<array{date:string,session:string}> */
    private function slots(int $count): array
    {
        $slots = [];
        $date = new \DateTimeImmutable('2098-03-02');
        for ($index = 0; $index < $count; $index++) {
            $slots[] = ['date' => $date->modify('+'.$index.' days')->format('Y-m-d'), 'session' => 'AM'];
        }

        return $slots;
    }

    private function assertDomainException(callable $action): void
    {
        try {
            $action();
        } catch (DomainException) {
            $this->addToAssertionCount(1);

            return;
        }
        $this->fail('An incomplete or unsafe route must fail closed.');
    }
}
