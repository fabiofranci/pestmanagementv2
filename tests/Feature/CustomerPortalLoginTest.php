<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerPortalLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_portal_login_pages_are_available(): void
    {
        $this->get('/area-riservata')
            ->assertOk()
            ->assertSee('Entra nell area riservata')
            ->assertSee('Accedi all area riservata');

        $this->get('/area-riservata/login')
            ->assertOk()
            ->assertSee('Entra nell area riservata')
            ->assertSee('Accedi all area riservata');
    }

    public function test_customer_portal_user_can_log_in_from_the_reserved_area(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant Demo',
            'slug' => 'tenant-demo',
            'db_database' => 'tenant_demo',
            'status' => 'active',
        ]);

        $user = User::query()->create([
            'name' => 'Cliente Portale',
            'email' => 'cliente@example.com',
            'password' => 'password123',
            'tenant_id' => $tenant->getKey(),
            'customer_id' => 33,
            'is_superuser' => false,
        ]);

        $this->post(route('customer.portal.login.store'), [
            'email' => $user->email,
            'password' => 'password123',
        ])
            ->assertRedirect('/admin');

        $this->assertAuthenticatedAs($user);
    }

    public function test_admin_user_is_rejected_from_the_customer_portal_login(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant Demo',
            'slug' => 'tenant-demo',
            'db_database' => 'tenant_demo',
            'status' => 'active',
        ]);

        User::query()->create([
            'name' => 'Tenant Admin',
            'email' => 'tenant-admin@example.com',
            'password' => 'password123',
            'tenant_id' => $tenant->getKey(),
            'customer_id' => null,
            'is_superuser' => false,
        ]);

        $this->from('/area-riservata')
            ->post(route('customer.portal.login.store'), [
                'email' => 'tenant-admin@example.com',
                'password' => 'password123',
            ])
            ->assertRedirect('/area-riservata')
            ->assertSessionHasErrors([
                'email' => 'Questo accesso e riservato ai clienti. Usa il login gestionale.',
            ]);

        $this->assertGuest();
    }
}
