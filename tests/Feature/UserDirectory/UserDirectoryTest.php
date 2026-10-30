<?php

namespace Tests\Feature\UserDirectory;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Queries\UserDirectoryQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserDirectoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['iam.local_user_directory_preview_enabled' => true]);
        $this->app->detectEnvironment(fn (): string => 'testing');
    }

    public function test_preview_is_disabled_by_default(): void
    {
        config(['iam.local_user_directory_preview_enabled' => false]);
        $user = $this->authorizedUser();

        $this->actingAs($user)->get('/users')->assertNotFound();
    }

    public function test_production_is_denied_even_when_opted_in(): void
    {
        $user = $this->authorizedUser();
        $this->app->detectEnvironment(fn (): string => 'production');

        $this->actingAs($user)->get('/users')->assertNotFound();
    }

    public function test_guest_is_denied(): void
    {
        $this->get('/users')->assertRedirect(route('login'));
    }

    public function test_inactive_account_is_denied(): void
    {
        $user = $this->authorizedUser(['status' => 'INACTIVE']);

        $this->actingAs($user)->get('/users')->assertRedirect(route('login'));
    }

    public function test_null_username_account_is_denied(): void
    {
        $user = $this->authorizedUser();
        DB::table('users')->where('id', $user->id)->update([
            'username' => null,
            'username_canonical' => null,
        ]);

        $this->actingAs($user)->get('/users')->assertRedirect(route('login'));
    }

    public function test_active_user_without_permission_is_denied(): void
    {
        $this->actingAs(User::factory()->create())->get('/users')->assertForbidden();
    }

    public function test_authorized_user_can_open_local_preview_and_nav_hint_is_boolean(): void
    {
        $user = $this->authorizedUser();

        $this->actingAs($user)->get('/users')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('users/Index')
            ->where('userDirectoryPreview', true)
            ->where('pagination.perPage', 20)
        );
    }

    public function test_search_matches_username_and_display_name(): void
    {
        $reader = $this->authorizedUser();
        User::factory()->create(['name' => 'Synthetic Directory Match', 'username' => 'directorymatch01']);
        User::factory()->create(['name' => 'Unrelated Person', 'username' => 'unrelated01']);

        $this->actingAs($reader)->get('/users?search=Directory%20Match')->assertInertia(fn (Assert $page) => $page
            ->has('users', 1)
            ->where('users.0.displayName', 'Synthetic Directory Match')
        );
        $this->actingAs($reader)->get('/users?search=directorymatch01')->assertInertia(fn (Assert $page) => $page
            ->has('users', 1)
            ->where('users.0.username', 'directorymatch01')
        );
    }

    public function test_invalid_and_oversized_search_inputs_are_rejected(): void
    {
        $reader = $this->authorizedUser();

        $this->actingAs($reader)->get('/users?search[]=bad')->assertSessionHasErrors('search');
        $this->actingAs($reader)->get('/users?search='.str_repeat('x', 101))->assertSessionHasErrors('search');
    }

    public function test_page_bounds_are_enforced_and_page_size_is_twenty(): void
    {
        $reader = $this->authorizedUser();
        foreach (range(1, 22) as $index) {
            User::factory()->create(['name' => sprintf('Synthetic %02d', $index)]);
        }

        $this->actingAs($reader)->get('/users?page=0')->assertSessionHasErrors('page');
        $this->actingAs($reader)->get('/users?page=10001')->assertSessionHasErrors('page');
        $this->actingAs($reader)->get('/users')->assertInertia(fn (Assert $page) => $page
            ->has('users', 20)
            ->where('pagination.total', 23)
            ->where('pagination.lastPage', 2)
        );
    }

    public function test_empty_directory_and_no_results_states_are_supported(): void
    {
        $reader = $this->authorizedUser();
        $this->app->instance(UserDirectoryQuery::class, new class extends UserDirectoryQuery
        {
            public function paginate(?string $search, int $page): LengthAwarePaginator
            {
                return new LengthAwarePaginator([], 0, self::PER_PAGE, $page);
            }
        });

        $this->actingAs($reader)->get('/users')->assertInertia(fn (Assert $page) => $page
            ->has('users', 0)
            ->where('pagination.total', 0)
            ->where('filters.search', '')
        );
        $this->app->forgetInstance(UserDirectoryQuery::class);
        $this->actingAs($reader)->get('/users?search=no-such-user')->assertInertia(fn (Assert $page) => $page
            ->has('users', 0)
            ->where('pagination.total', 0)
            ->where('filters.search', 'no-such-user')
        );
    }

    public function test_directory_serializes_only_the_allowlisted_fields(): void
    {
        $reader = $this->authorizedUser();
        User::factory()->create(['name' => 'Safe Output User', 'username' => 'safeoutput01', 'email' => 'private@example.test']);

        $this->actingAs($reader)->get('/users')->assertInertia(fn (Assert $page) => $page
            ->where('users.1', [
                'displayName' => 'Safe Output User',
                'username' => 'safeoutput01',
                'status' => 'ACTIVE',
            ])
            ->missing('users.1.email')
            ->missing('users.1.password')
            ->missing('users.1.remember_token')
            ->missing('permissions')
            ->missing('permissionGraph')
        );
    }

    public function test_navigation_hint_does_not_bypass_server_authorization(): void
    {
        $unauthorized = User::factory()->create();

        $this->actingAs($unauthorized)->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->where('userDirectoryPreview', false)
        );
        $this->get('/users')->assertForbidden();
    }

    public function test_directory_has_no_administrative_mutation_routes(): void
    {
        $directoryRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route): bool => $route->uri() === 'users');

        $this->assertCount(1, $directoryRoutes);
        $this->assertSame(['GET', 'HEAD'], $directoryRoutes->first()->methods());
    }

    /** @param array<string, mixed> $attributes */
    private function authorizedUser(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $this->grantDirectoryPermission($user);

        return $user;
    }

    private function grantDirectoryPermission(User $user): void
    {
        $role = Role::create(['code' => 'SYNTHETIC_DIRECTORY_READER', 'name' => 'Synthetic directory reader']);
        $permission = Permission::create(['code' => 'iam.users.view', 'name' => 'Synthetic user directory view']);
        $user->roles()->attach($role);
        $role->permissions()->attach($permission);
    }
}
