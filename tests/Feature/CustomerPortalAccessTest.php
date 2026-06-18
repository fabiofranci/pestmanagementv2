<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerSite;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\CurrentTenant;
use App\Support\Tenancy\CustomerPortalUserManager;
use App\Support\Tenancy\TenantConnectionManager;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class CustomerPortalAccessTest extends TestCase
{
    use RefreshDatabase;

    protected string $tenantDatabasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $databasePath = tempnam(sys_get_temp_dir(), 'tenant-db-');

        if ($databasePath === false) {
            throw new RuntimeException('Impossibile creare il database temporaneo tenant per i test.');
        }

        $this->tenantDatabasePath = $databasePath;

        config([
            'database.connections.tenant' => [
                'driver' => 'sqlite',
                'database' => $this->tenantDatabasePath,
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);

        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => database_path('migrations/tenant'),
            '--realpath' => true,
        ]);
    }

    protected function tearDown(): void
    {
        DB::purge('tenant');

        if (isset($this->tenantDatabasePath) && is_file($this->tenantDatabasePath)) {
            @unlink($this->tenantDatabasePath);
        }

        parent::tearDown();
    }

    public function test_it_can_create_update_and_delete_a_customer_portal_user(): void
    {
        $tenant = $this->createTenant();
        $customer = $this->createTenantCustomer($tenant, 'Cliente Demo');
        $manager = app(CustomerPortalUserManager::class);

        $user = $manager->createUser($customer, [
            'name' => 'Referente Cliente',
            'email' => 'cliente-demo@example.com',
            'password' => 'password123',
        ]);

        $this->assertSame($tenant->getKey(), $user->tenant_id);
        $this->assertSame($customer->getKey(), $user->customer_id);
        $this->assertTrue($user->isTenantCustomer());

        $updatedUser = $manager->updateUser($customer, $user, [
            'name' => 'Nuovo Referente',
            'email' => 'cliente-aggiornato@example.com',
            'password' => 'password456',
        ]);

        $this->assertSame('Nuovo Referente', $updatedUser->name);
        $this->assertSame('cliente-aggiornato@example.com', $updatedUser->email);
        $this->assertTrue(Hash::check('password456', $updatedUser->password));

        $manager->deleteUser($customer, $updatedUser);

        $this->assertDatabaseMissing('users', [
            'id' => $updatedUser->getKey(),
        ]);
    }

    public function test_customer_portal_user_sees_only_own_records_and_cannot_access_catalog_resources(): void
    {
        $tenant = $this->createTenant();

        [$customerA, $customerB, $siteA, $siteB] = $this->withinTenant($tenant, function (Tenant $tenant): array {
            $customerA = Customer::query()->create([
                'tenant_id' => $tenant->getKey(),
                'name' => 'Cliente Alpha',
                'email' => 'alpha@example.com',
                'status' => 'active',
            ]);

            $customerB = Customer::query()->create([
                'tenant_id' => $tenant->getKey(),
                'name' => 'Cliente Beta',
                'email' => 'beta@example.com',
                'status' => 'active',
            ]);

            $siteA = CustomerSite::query()->create([
                'tenant_id' => $tenant->getKey(),
                'customer_id' => $customerA->getKey(),
                'name' => 'Sede Alpha',
                'status' => 'active',
            ]);

            $siteB = CustomerSite::query()->create([
                'tenant_id' => $tenant->getKey(),
                'customer_id' => $customerB->getKey(),
                'name' => 'Sede Beta',
                'status' => 'active',
            ]);

            return [$customerA, $customerB, $siteA, $siteB];
        });

        $user = User::query()->create([
            'name' => 'Cliente Portale',
            'email' => 'cliente-portal@example.com',
            'password' => 'password123',
            'tenant_id' => $tenant->getKey(),
            'customer_id' => $customerA->getKey(),
            'is_superuser' => false,
        ]);

        $this->actingAs($user)
            ->get('/admin/customers')
            ->assertOk()
            ->assertSee('Cliente Alpha')
            ->assertDontSee('Cliente Beta');

        $this->actingAs($user)
            ->get('/admin/customer-sites')
            ->assertOk()
            ->assertSee('Sede Alpha')
            ->assertDontSee('Sede Beta');

        $this->actingAs($user)
            ->get('/admin/service-types')
            ->assertForbidden();
    }

    protected function createTenant(): Tenant
    {
        return Tenant::query()->create([
            'name' => 'Tenant Demo',
            'slug' => 'tenant-demo',
            'db_database' => $this->tenantDatabasePath,
            'status' => 'active',
        ]);
    }

    protected function createTenantCustomer(Tenant $tenant, string $name): Customer
    {
        return $this->withinTenant($tenant, fn (Tenant $tenant): Customer => Customer::query()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => $name,
            'email' => strtolower(str_replace(' ', '.', $name)).'@example.com',
            'status' => 'active',
        ]));
    }

    protected function withinTenant(Tenant $tenant, callable $callback): mixed
    {
        app(TenantConnectionManager::class)->activate($tenant);
        app(CurrentTenant::class)->set($tenant);
        Filament::setTenant($tenant, isQuiet: true);

        try {
            return $callback($tenant);
        } finally {
            app(CurrentTenant::class)->set(null);
            Filament::setTenant(null, isQuiet: true);
            DB::purge(config('tenancy.database_connection'));
        }
    }
}
