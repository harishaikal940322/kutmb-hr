<?php

namespace Tests\Feature\Leave;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AnnualLeaveDashboardDesignTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_can_review_design_without_access_to_employee_data(): void
    {
        $this->assertGreaterThan(0, DB::transactionLevel());
        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->get('/leave/annual')->assertRedirect(route('login'));

        $actor = User::factory()->create([
            'username' => 'ux'.Str::lower(Str::random(16)),
            'email' => null,
            'status' => 'ACTIVE',
        ]);
        $actor->forceFill(['two_factor_secret' => 'synthetic', 'two_factor_confirmed_at' => now()])->save();

        $this->actingAs($actor)->get('/dashboard')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Dashboard')
                ->where('visibleMenuKeys', fn ($keys) => ! collect($keys)->contains('leave.annual.index')));
        $this->get('/leave/annual')->assertForbidden();

        $actor->givePermissionTo('iam.rbac.manage');
        $this->get('/dashboard')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Dashboard')
                ->where('visibleMenuKeys', fn ($keys) => collect($keys)->contains('leave.annual.index'))
                ->missing('annualLeaveDesignReview'));
        $this->get('/leave/annual')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('leave/AnnualLeave')
                ->where('canViewEntitlements', false)->missing('employees'));

        $previousEnvironment = app()->environment();
        try {
            app()->instance('env', 'production');
            $this->get('/dashboard')->assertOk()
                ->assertInertia(fn (Assert $page) => $page->component('Dashboard')
                    ->where('visibleMenuKeys', fn ($keys) => ! collect($keys)->contains('leave.annual.index')));
            $this->get('/leave/annual')->assertNotFound();
        } finally {
            app()->instance('env', $previousEnvironment);
        }

        $this->assertGreaterThan(0, DB::transactionLevel());
    }
}
