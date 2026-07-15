<?php

namespace Tests\Feature;

use App\Filament\Resources\CustomerGroups\Pages\CreateCustomerGroup;
use App\Filament\Resources\CustomerGroups\Pages\ListCustomerGroups;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\CurrentTenant;
use App\Support\Tenancy\TenantConnectionManager;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class CustomerGroupsTest extends TestCase
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
        app(CurrentTenant::class)->set(null);
        Filament::setTenant(null, isQuiet: true);
        DB::purge('tenant');

        if (isset($this->tenantDatabasePath) && is_file($this->tenantDatabasePath)) {
            @unlink($this->tenantDatabasePath);
        }

        parent::tearDown();
    }

    public function test_customer_group_resource_creates_group_and_table_shows_customers_count(): void
    {
        $tenant = $this->createTenant();
        $user = $this->createTenantAdmin($tenant);

        $this->actingAs($user);
        $this->activateTenant($tenant);

        try {
            Livewire::test(CreateCustomerGroup::class)
                ->fillForm([
                    'name' => 'Gruppo ANGIPLAST',
                    'code' => 'ANGIPLAST',
                    'description' => 'Clienti collegati ad ANGIPLAST.',
                    'status' => 'active',
                ])
                ->call('create')
                ->assertHasNoFormErrors();

            $group = CustomerGroup::query()
                ->where('name', 'Gruppo ANGIPLAST')
                ->firstOrFail();

            Customer::query()->create([
                'tenant_id' => $tenant->getKey(),
                'customer_group_id' => $group->getKey(),
                'name' => 'ANGIPLAST SRL',
                'status' => 'active',
            ]);

            Customer::query()->create([
                'tenant_id' => $tenant->getKey(),
                'customer_group_id' => $group->getKey(),
                'name' => 'ANGIPLAST HOLDING',
                'status' => 'active',
            ]);

            $countedGroup = CustomerGroup::query()
                ->withCount('customers')
                ->firstOrFail();

            $this->assertSame(2, $countedGroup->customers_count);

            Livewire::test(ListCustomerGroups::class)
                ->assertSee('Gruppo ANGIPLAST')
                ->assertSee('2');
        } finally {
            $this->deactivateTenant();
        }
    }

    public function test_customer_can_be_assigned_to_group_and_group_delete_nulls_customer_reference(): void
    {
        $tenant = $this->createTenant();

        $this->withinTenant($tenant, function (Tenant $tenant): void {
            $this->assertTrue(Schema::connection('tenant')->hasTable('customer_groups'));
            $this->assertTrue(Schema::connection('tenant')->hasColumn('customers', 'customer_group_id'));

            $group = CustomerGroup::query()->create([
                'tenant_id' => $tenant->getKey(),
                'name' => 'Gruppo amministratore Rossi',
                'code' => 'ROSSI',
                'status' => 'active',
            ]);

            $customer = Customer::query()->create([
                'tenant_id' => $tenant->getKey(),
                'customer_group_id' => $group->getKey(),
                'name' => 'Condominio Verdi',
                'status' => 'active',
            ]);

            $this->assertTrue($customer->customerGroup->is($group));
            $this->assertTrue($group->customers()->whereKey($customer->getKey())->exists());

            $group->delete();

            $this->assertNull($customer->refresh()->customer_group_id);
            $this->assertNull($customer->customerGroup);
        });
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

    protected function createTenantAdmin(Tenant $tenant): User
    {
        return User::query()->create([
            'name' => 'Tenant Admin',
            'email' => 'tenant-admin-customer-groups@example.com',
            'password' => 'password123',
            'tenant_id' => $tenant->getKey(),
            'is_superuser' => false,
        ]);
    }

    protected function withinTenant(Tenant $tenant, callable $callback): mixed
    {
        $this->activateTenant($tenant);

        try {
            return $callback($tenant);
        } finally {
            $this->deactivateTenant();
        }
    }

    protected function activateTenant(Tenant $tenant): void
    {
        app(TenantConnectionManager::class)->activate($tenant);
        app(CurrentTenant::class)->set($tenant);
        Filament::setTenant($tenant, isQuiet: true);
    }

    protected function deactivateTenant(): void
    {
        app(CurrentTenant::class)->set(null);
        Filament::setTenant(null, isQuiet: true);
        DB::purge(config('tenancy.database_connection'));
    }
}
