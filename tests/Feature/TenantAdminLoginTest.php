<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantAdminLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_login_pages_are_available(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Entra in Pest Management V2')
            ->assertSee('Accedi al portale');

        $this->get('/tenant/login')
            ->assertOk()
            ->assertSee('Entra in Pest Management V2')
            ->assertSee('Accedi al portale');
    }

    public function test_root_and_admin_login_redirect_to_the_general_login(): void
    {
        $this->get('/')
            ->assertRedirect('/login');

        $this->get('/admin/login')
            ->assertRedirect('/login');
    }

    public function test_unauthenticated_admin_access_redirects_to_the_general_login(): void
    {
        $this->get('/admin')
            ->assertRedirect('/login');
    }

    public function test_tenant_admin_can_access_the_admin_panel(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant Demo',
            'slug' => 'tenant-demo',
            'db_database' => 'tenant_demo',
            'status' => 'active',
        ]);

        $user = User::query()->create([
            'name' => 'Tenant Admin',
            'email' => 'tenant-admin@example.com',
            'password' => 'password123',
            'tenant_id' => $tenant->getKey(),
            'is_superuser' => false,
        ]);

        $this->actingAs($user)
            ->get('/admin')
            ->assertOk();
    }

    public function test_tenant_admin_can_log_in_from_the_custom_page(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant Demo',
            'slug' => 'tenant-demo',
            'db_database' => 'tenant_demo',
            'status' => 'active',
        ]);

        $user = User::query()->create([
            'name' => 'Tenant Admin',
            'email' => 'tenant-admin@example.com',
            'password' => 'password123',
            'tenant_id' => $tenant->getKey(),
            'is_superuser' => false,
        ]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password123',
        ])
            ->assertRedirect('/admin');

        $this->assertAuthenticatedAs($user);
    }

    public function test_authenticated_user_is_redirected_away_from_the_tenant_login_page(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant Demo',
            'slug' => 'tenant-demo',
            'db_database' => 'tenant_demo',
            'status' => 'active',
        ]);

        $user = User::query()->create([
            'name' => 'Tenant Admin',
            'email' => 'tenant-admin@example.com',
            'password' => 'password123',
            'tenant_id' => $tenant->getKey(),
            'is_superuser' => false,
        ]);

        $this->actingAs($user)
            ->get('/tenant/login')
            ->assertRedirect('/admin');
    }
}
