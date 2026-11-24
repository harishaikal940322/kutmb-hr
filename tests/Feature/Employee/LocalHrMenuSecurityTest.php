<?php

namespace Tests\Feature\Employee;

use App\Models\User;
use App\Services\Identity\NavigationMenuService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class LocalHrMenuSecurityTest extends TestCase
{
    use DatabaseTransactions;

    public function test_fixed_hr_menu_permissions_cannot_be_overridden_by_mapping_or_request(): void
    {
        $suffix = Str::lower(Str::random(16));
        $key = 'synthetic.fixed.'.$suffix;
        config(['iam.menus' => [...config('iam.menus'), $key => ['label' => 'Synthetic', 'href' => '/synthetic', 'icon' => 'user', 'permission' => 'hr.profile.self.view', 'editable' => false]]]);
        $actor = User::factory()->create(['username' => 'menu'.$suffix, 'email' => null, 'status' => 'ACTIVE']);
        $actor->forceFill(['two_factor_secret' => 'synthetic', 'two_factor_confirmed_at' => now()])->save();
        $actor->givePermissionTo('iam.rbac.manage');
        $permissionId = DB::table('permissions')->where('code', 'iam.rbac.manage')->value('id');
        DB::table('menu_permission_assignments')->insert(['menu_key' => $key, 'permission_id' => $permissionId]);
        $service = app(NavigationMenuService::class);
        $this->assertNotContains($key, $service->visibleKeys($actor));
        $rows = collect($service->rows())->keyBy('key');
        $this->assertSame('hr.profile.self.view', $rows[$key]['permission']);
        $mappings = $rows->mapWithKeys(fn (array $row): array => [$row['key'] => $row['permission']])->all();
        $mappings[$key] = 'iam.rbac.manage';
        $this->actingAs($actor)->put('/administration/access/menus', ['mappings' => $mappings])->assertSessionHasErrors('mappings');
        $this->assertDatabaseHas('menu_permission_assignments', ['menu_key' => $key, 'permission_id' => $permissionId]);
        $this->assertGreaterThan(0, DB::transactionLevel());
    }
}
