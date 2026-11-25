<?php

namespace Tests\Feature\Employee;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EmployeeAccountLinkTest extends TestCase
{
    use DatabaseTransactions;

    public function test_explicit_account_association_requires_independent_authority_and_reason(): void
    {
        $this->assertGreaterThan(0, DB::transactionLevel());
        $suffix = Str::lower(Str::random(16));
        $employee = DB::table('employees')->insertGetId(['employee_no' => 'e'.$suffix, 'display_name' => 'Synthetic', 'service_start' => '2026-01-01']);
        $other = DB::table('employees')->insertGetId(['employee_no' => 'f'.$suffix, 'display_name' => 'Synthetic two', 'service_start' => '2026-01-01']);
        $actor = User::factory()->create(['username' => 'link'.$suffix, 'email' => null, 'status' => 'ACTIVE']);
        $actor->forceFill(['two_factor_secret' => 'synthetic', 'two_factor_confirmed_at' => now()])->save();
        $target = User::factory()->create(['username' => 'target'.$suffix, 'email' => null, 'status' => 'ACTIVE']);
        $actor->givePermissionTo('hr.employees.view');
        $payload = ['user_id' => $target->id, 'reason' => 'Synthetic reason'];
        $this->actingAs($actor)->put('/employees/'.$employee.'/account', $payload)->assertForbidden();
        $this->get('/employees')->assertOk()->assertInertia(fn (Assert $page) => $page->where('accounts', [])->where('canLink', false)->missing('employees.0.user_id'));

        $actor->givePermissionTo('hr.accounts.link');
        $this->put('/employees/'.$employee.'/account', ['user_id' => $actor->id, 'reason' => 'Synthetic'])->assertSessionHasErrors('user_id');
        $this->put('/employees/'.$employee.'/account', ['user_id' => $target->id, 'reason' => ' '])->assertSessionHasErrors('reason');
        $this->put('/employees/'.$employee.'/account', $payload)->assertRedirect();
        $this->assertDatabaseHas('employees', ['id' => $employee, 'user_id' => $target->id]);
        $this->assertDatabaseHas('employee_account_link_history', ['employee_id' => $employee, 'actor_id' => $actor->id, 'new_user_id' => $target->id, 'reason' => 'Synthetic reason']);
        $this->assertDatabaseHas('audit_events', ['actor_id' => $actor->id, 'action_code' => 'hr.employee.account_link_changed', 'target_ref' => 'employee:'.$employee]);
        $this->put('/employees/'.$other.'/account', $payload)->assertSessionHasErrors('user_id');
        $this->put('/employees/'.$employee.'/account', ['user_id' => null, 'reason' => 'Synthetic unlink'])->assertRedirect();
        $this->assertDatabaseHas('employees', ['id' => $employee, 'user_id' => null]);
        $this->assertDatabaseHas('employee_account_link_history', ['employee_id' => $employee, 'previous_user_id' => $target->id, 'new_user_id' => null]);
        DB::table('employees')->where('id', $other)->update(['user_id' => $actor->id]);
        $this->put('/employees/'.$other.'/account', ['user_id' => null, 'reason' => 'Synthetic'])->assertSessionHasErrors('user_id');
        DB::table('leave_employee_history_markers')->insert(['employee_id' => $employee, 'request_key' => 'account-history-'.$suffix, 'revision' => 1, 'actor_id' => $actor->id]);
        $this->put('/employees/'.$employee.'/account', $payload)->assertSessionHasErrors('user_id');
        $actor->revokePermissionTo('hr.accounts.link');
        $this->put('/employees/'.$employee.'/account', $payload)->assertForbidden();
        $this->assertGreaterThan(0, DB::transactionLevel());
    }
}
