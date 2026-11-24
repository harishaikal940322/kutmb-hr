<?php

namespace Tests\Feature\Leave;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SessionSimulationPageTest extends TestCase
{
    use DatabaseTransactions;

    public function test_simulation_page_requires_both_permissions_and_uses_technical_versions_only(): void
    {
        $this->assertGreaterThan(0, DB::transactionLevel());
        $suffix = Str::lower(Str::random(16));
        $actor = User::factory()->create(['username' => 'page'.$suffix, 'email' => null, 'status' => 'ACTIVE']);
        $actor->forceFill(['two_factor_secret' => 'synthetic', 'two_factor_confirmed_at' => now()])->save();
        $this->actingAs($actor)->get('/leave/session-simulation')->assertForbidden();
        $actor->givePermissionTo('leave.calendar.manage');
        $this->get('/leave/session-simulation')->assertForbidden();
        $actor->givePermissionTo('leave.policy.manage');
        $this->get('/leave/session-simulation')->assertOk()->assertInertia(fn (Assert $page) => $page->component('leave/SessionSimulation')->where('units', null));
        $this->get('/leave/session-simulation?date=2095-03-04&sessions%5B%5D=AM')->assertOk()->assertInertia(fn (Assert $page) => $page->where('unavailable', true)->where('units', null));
        DB::table('leave_policy_versions')->insert(['label' => 'Synthetic '.$suffix, 'effective_from' => '2095-01-01', 'effective_to' => '2095-12-31', 'schedule_simulated_by' => $actor->id, 'schedule_simulated_at' => now(), 'working_weekdays' => '[1,2,3,4,5,6,7]', 'work_start' => '08:00', 'session_boundary' => '13:00', 'work_end' => '17:00']);
        DB::table('leave_calendar_versions')->insert(['label' => 'Synthetic '.$suffix, 'effective_from' => '2095-01-01', 'effective_to' => '2095-12-31', 'confirmed_by' => $actor->id, 'confirmed_at' => now()]);
        $this->get('/leave/session-simulation?date=2095-03-04&sessions%5B%5D=AM&sessions%5B%5D=PM')->assertOk()->assertInertia(fn (Assert $page) => $page->where('unavailable', false)->where('units', 2));
        $this->get('/leave/session-simulation?date=2095-03-04&sessions%5B%5D=AM&sessions%5B%5D=AM')->assertSessionHasErrors('sessions.1');
        $this->app['env'] = 'production';
        try {
            $this->get('/leave/session-simulation')->assertNotFound();
        } finally {
            $this->app['env'] = 'testing';
        }
        $this->assertGreaterThan(0, DB::transactionLevel());
    }
}
