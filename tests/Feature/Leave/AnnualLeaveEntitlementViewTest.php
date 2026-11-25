<?php

namespace Tests\Feature\Leave;

use App\Models\User;
use App\Services\Leave\AnnualEntitlementCreditService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AnnualLeaveEntitlementViewTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_authorized_hr_reads_only_the_selected_year_and_current_assignment(): void
    {
        $this->assertGreaterThan(0, DB::transactionLevel());
        Carbon::setTestNow('2026-11-25T09:00:00+08:00');
        $suffix = Str::lower(Str::random(12));
        $hr = $this->actor('hr'.$suffix);
        $hr->givePermissionTo('leave.entitlement.view');

        $unit = DB::table('organization_units')->insertGetId(['code' => 'u'.$suffix, 'name' => 'Synthetic current unit', 'active' => true]);
        $category = DB::table('employment_categories')->insertGetId(['code' => 'c'.$suffix, 'name' => 'Synthetic category', 'active' => true]);
        $employee = DB::table('employees')->insertGetId([
            'employee_no' => 'e'.$suffix,
            'display_name' => 'Synthetic employee '.$suffix,
            'service_start' => '2020-01-01',
        ]);
        DB::table('employee_assignments')->insert([
            'employee_id' => $employee,
            'unit_id' => $unit,
            'category_id' => $category,
            'effective_from' => '2020-01-01',
        ]);
        $annual = DB::table('leave_annual_accounts')->insertGetId(['employee_id' => $employee, 'leave_year' => 2027]);

        $url = '/leave/annual?search=e'.$suffix.'&year=2027';
        $this->actingAs($hr)->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('leave/Entitlements')
            ->where('canReviewDesign', false)
            ->where('visibleMenuKeys', fn ($keys) => collect($keys)->contains('leave.annual.entitlements'))
            ->where('filters.year', 2027)
            ->has('employees.data', 1)
            ->where('employees.data.0.employee_no', 'e'.$suffix)
            ->where('employees.data.0.unit', 'Synthetic current unit')
            ->where('employees.data.0.category', 'Synthetic category')
            ->where('employees.data.0.entitlement_status', 'UNCONFIRMED')
            ->where('employees.data.0.confirmed_half_day_units', null)
            ->missing('employees.data.0.user_id'));

        $confirmer = $this->actor('cf'.$suffix);
        $confirmer->givePermissionTo('leave.entitlement.confirm');
        $confirmer->givePermissionTo('leave.entitlement.credit');
        $service = app(AnnualEntitlementCreditService::class);
        $confirmation = $service->confirmAnnualEntitlement($confirmer->id, $annual, 25, 'SYNTHETIC-'.$suffix, 'confirm-'.$suffix);
        $this->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('employees.data.0.entitlement_status', 'CONFIRMED')
            ->where('employees.data.0.confirmed_half_day_units', 25));

        $this->get('/leave/annual?search=e'.$suffix.'&year=2026')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('employees.data.0.entitlement_status', 'NO_ACCOUNT')
            ->where('employees.data.0.confirmed_half_day_units', null));

        Carbon::setTestNow('2027-01-01T00:00:00+08:00');
        $service->creditConfirmedEntitlementOnJanuaryFirst($confirmer->id, $confirmation['confirmation_id']);
        $this->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('employees.data.0.entitlement_status', 'CREDITED')
            ->where('employees.data.0.confirmed_half_day_units', 25));
        $this->get('/leave/annual?view=design')->assertForbidden();
        $this->get('/leave/annual?search=missing-'.$suffix)->assertOk()->assertInertia(fn (Assert $page) => $page->has('employees.data', 0));
        $this->assertGreaterThan(0, DB::transactionLevel());
    }

    public function test_admin_preview_is_separate_and_production_is_denied(): void
    {
        $suffix = Str::lower(Str::random(12));
        $actor = $this->actor('ux'.$suffix);
        $actor->givePermissionTo('iam.rbac.manage');
        $this->actingAs($actor)->get('/leave/annual')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('leave/AnnualLeave')->where('canViewEntitlements', false)->missing('employees'));

        $actor->givePermissionTo('leave.entitlement.view');
        $this->get('/leave/annual?search=none-'.$suffix)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('leave/Entitlements')->where('canReviewDesign', true));
        $this->get('/leave/annual?view=design')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('leave/AnnualLeave')->where('canViewEntitlements', true)->missing('employees'));

        $env = app()->environment();
        try {
            app()->instance('env', 'production');
            $this->get('/leave/annual')->assertNotFound();
            $this->get('/leave/annual?view=design')->assertNotFound();
        } finally {
            app()->instance('env', $env);
        }
    }

    private function actor(string $username): User
    {
        $actor = User::factory()->create(['username' => $username, 'email' => null, 'status' => 'ACTIVE']);
        $actor->forceFill(['two_factor_secret' => 'synthetic', 'two_factor_confirmed_at' => now()])->save();

        return $actor;
    }
}
