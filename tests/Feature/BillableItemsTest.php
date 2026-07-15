<?php

namespace Tests\Feature;

use App\Filament\Resources\BillableItems\Pages\CreateBillableItem;
use App\Models\BillableItem;
use App\Models\Customer;
use App\Models\CustomerBillableItemPrice;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Billing\BillableItemPricingService;
use App\Support\Tenancy\CurrentTenant;
use App\Support\Tenancy\TenantConnectionManager;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class BillableItemsTest extends TestCase
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

    public function test_billable_item_resource_creates_item(): void
    {
        $tenant = $this->createTenant();
        $user = $this->createTenantAdmin($tenant);

        $this->actingAs($user);
        $this->activateTenant($tenant);

        try {
            Livewire::test(CreateBillableItem::class)
                ->fillForm([
                    'name' => 'Lampada UV',
                    'code' => 'UV',
                    'description' => 'Lampada UV fatturabile.',
                    'default_unit_price' => 85,
                    'vat_rate' => 22,
                    'status' => 'active',
                ])
                ->call('create')
                ->assertHasNoFormErrors();

            $item = BillableItem::query()
                ->where('name', 'Lampada UV')
                ->firstOrFail();

            $this->assertSame($tenant->getKey(), $item->tenant_id);
            $this->assertSame('UV', $item->code);
            $this->assertSame('85.00', $item->default_unit_price);
            $this->assertSame('22.00', $item->vat_rate);
        } finally {
            $this->deactivateTenant();
        }
    }

    public function test_billable_item_name_is_unique_inside_tenant(): void
    {
        $tenant = $this->createTenant();

        $this->expectException(QueryException::class);

        $this->withinTenant($tenant, function (Tenant $tenant): void {
            BillableItem::query()->create([
                'tenant_id' => $tenant->getKey(),
                'name' => 'Contenitore esca',
                'status' => 'active',
            ]);

            BillableItem::query()->create([
                'tenant_id' => $tenant->getKey(),
                'name' => 'Contenitore esca',
                'status' => 'active',
            ]);
        });
    }

    public function test_pricing_service_returns_standard_custom_discount_and_custom_priority(): void
    {
        $tenant = $this->createTenant();

        $this->withinTenant($tenant, function (Tenant $tenant): void {
            $customer = Customer::query()->create([
                'tenant_id' => $tenant->getKey(),
                'name' => 'Cliente prezzi',
                'status' => 'active',
            ]);

            $item = BillableItem::query()->create([
                'tenant_id' => $tenant->getKey(),
                'name' => 'Trappola collante',
                'default_unit_price' => 100,
                'status' => 'active',
            ]);

            $pricing = app(BillableItemPricingService::class);

            $this->assertSame(100.0, $pricing->priceForCustomer($item, $customer));
            $this->assertSame('standard', $pricing->priceDetailsForCustomer($item, $customer)['pricing_source']);

            CustomerBillableItemPrice::query()->create([
                'tenant_id' => $tenant->getKey(),
                'customer_id' => $customer->getKey(),
                'billable_item_id' => $item->getKey(),
                'custom_unit_price' => 72.5,
            ]);

            $this->assertSame(72.5, $pricing->priceForCustomer($item, $customer));
            $this->assertSame('custom', $pricing->priceDetailsForCustomer($item, $customer)['pricing_source']);

            CustomerBillableItemPrice::query()
                ->where('customer_id', $customer->getKey())
                ->where('billable_item_id', $item->getKey())
                ->update([
                    'custom_unit_price' => null,
                    'discount_percentage' => 15,
                ]);

            $this->assertSame(85.0, $pricing->priceForCustomer($item->refresh(), $customer));
            $this->assertSame('discount', $pricing->priceDetailsForCustomer($item, $customer)['pricing_source']);

            CustomerBillableItemPrice::query()
                ->where('customer_id', $customer->getKey())
                ->where('billable_item_id', $item->getKey())
                ->update([
                    'custom_unit_price' => 70,
                    'discount_percentage' => 15,
                ]);

            $details = $pricing->priceDetailsForCustomer($item->refresh(), $customer);

            $this->assertSame(70.0, $details['final_price']);
            $this->assertSame('custom', $details['pricing_source']);
        });
    }

    public function test_pricing_service_returns_null_when_no_price_exists(): void
    {
        $tenant = $this->createTenant();

        $this->withinTenant($tenant, function (Tenant $tenant): void {
            $customer = Customer::query()->create([
                'tenant_id' => $tenant->getKey(),
                'name' => 'Cliente senza prezzo',
                'status' => 'active',
            ]);

            $item = BillableItem::query()->create([
                'tenant_id' => $tenant->getKey(),
                'name' => 'Consumabile generico',
                'status' => 'active',
            ]);

            $this->assertNull(app(BillableItemPricingService::class)->priceForCustomer($item, $customer));
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
            'email' => 'tenant-admin-billable-items@example.com',
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
