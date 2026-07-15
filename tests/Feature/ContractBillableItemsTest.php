<?php

namespace Tests\Feature;

use App\Filament\Resources\Contracts\ContractResource;
use App\Filament\Resources\Contracts\RelationManagers\ContractBillableItemsRelationManager;
use App\Models\BillableItem;
use App\Models\Contract;
use App\Models\ContractBillableItem;
use App\Models\Customer;
use App\Models\CustomerBillableItemPrice;
use App\Models\CustomerSite;
use App\Models\Tenant;
use App\Support\Billing\ContractBillableItemPricingService;
use App\Support\Tenancy\CurrentTenant;
use App\Support\Tenancy\TenantConnectionManager;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class ContractBillableItemsTest extends TestCase
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

    public function test_contract_can_have_billable_items_and_relations_work(): void
    {
        $tenant = $this->createTenant();

        $this->withinTenant($tenant, function (Tenant $tenant): void {
            [$contract, $customer] = $this->createContractFixture($tenant, 'CTR-MATERIALS');
            [$secondContract] = $this->createContractFixture($tenant, 'CTR-MATERIALS-2', $customer);

            $container = $this->createBillableItem($tenant, 'Contenitore esca', 10);
            $stake = $this->createBillableItem($tenant, 'Paletto di fissaggio', 5);

            ContractBillableItem::query()->create([
                'tenant_id' => $tenant->getKey(),
                'contract_id' => $contract->getKey(),
                'billable_item_id' => $container->getKey(),
                'quantity' => 10,
                'unit_price' => 10,
                'status' => 'active',
            ]);

            ContractBillableItem::query()->create([
                'tenant_id' => $tenant->getKey(),
                'contract_id' => $contract->getKey(),
                'billable_item_id' => $stake->getKey(),
                'quantity' => 10,
                'unit_price' => 5,
                'status' => 'active',
            ]);

            ContractBillableItem::query()->create([
                'tenant_id' => $tenant->getKey(),
                'contract_id' => $secondContract->getKey(),
                'billable_item_id' => $container->getKey(),
                'quantity' => 3,
                'unit_price' => 10,
                'status' => 'active',
            ]);

            $this->assertSame(2, $contract->contractBillableItems()->count());
            $this->assertSame(2, $container->contractBillableItems()->count());
            $this->assertSame('100.00', $contract->contractBillableItems()->where('billable_item_id', $container->getKey())->firstOrFail()->total_price);
        });
    }

    public function test_contract_resource_registers_billable_items_relation_manager(): void
    {
        $this->assertContains(
            ContractBillableItemsRelationManager::class,
            ContractResource::getRelations(),
        );
    }

    public function test_standard_price_is_proposed_and_total_is_calculated(): void
    {
        $tenant = $this->createTenant();

        $this->withinTenant($tenant, function (Tenant $tenant): void {
            [$contract] = $this->createContractFixture($tenant, 'CTR-STANDARD');
            $item = $this->createBillableItem($tenant, 'Lampada UV', 25);
            $state = app(ContractBillableItemPricingService::class)
                ->suggestedStateForContract($contract, $item, 4);

            $this->assertSame(25.0, $state['unit_price']);
            $this->assertNull($state['discount_percentage']);
            $this->assertSame(100.0, $state['total_price']);

            ContractBillableItem::query()->create([
                'tenant_id' => $tenant->getKey(),
                'contract_id' => $contract->getKey(),
                'billable_item_id' => $item->getKey(),
                'quantity' => 4,
                'unit_price' => $state['unit_price'],
                'discount_percentage' => $state['discount_percentage'],
                'total_price' => $state['total_price'],
                'status' => 'active',
            ]);

            $line = ContractBillableItem::query()->firstOrFail();

            $this->assertSame('25.00', $line->unit_price);
            $this->assertNull($line->discount_percentage);
            $this->assertSame('100.00', $line->total_price);
        });
    }

    public function test_contract_billable_item_calculates_discounted_total(): void
    {
        $tenant = $this->createTenant();

        $this->withinTenant($tenant, function (Tenant $tenant): void {
            [$contract] = $this->createContractFixture($tenant, 'CTR-DISCOUNTED-TOTAL');
            $item = $this->createBillableItem($tenant, 'Contenitori esca', 25);

            $discounted = ContractBillableItem::query()->create([
                'tenant_id' => $tenant->getKey(),
                'contract_id' => $contract->getKey(),
                'billable_item_id' => $item->getKey(),
                'quantity' => 10,
                'unit_price' => 25,
                'discount_percentage' => 10,
                'status' => 'active',
            ]);

            $withoutDiscount = ContractBillableItem::query()->create([
                'tenant_id' => $tenant->getKey(),
                'contract_id' => $contract->getKey(),
                'billable_item_id' => $item->getKey(),
                'quantity' => 10,
                'unit_price' => 25,
                'status' => 'active',
            ]);

            $halfDiscount = ContractBillableItem::query()->create([
                'tenant_id' => $tenant->getKey(),
                'contract_id' => $contract->getKey(),
                'billable_item_id' => $item->getKey(),
                'quantity' => 2,
                'unit_price' => 100,
                'discount_percentage' => 50,
                'status' => 'active',
            ]);

            $this->assertSame('225.00', $discounted->refresh()->total_price);
            $this->assertSame('250.00', $withoutDiscount->refresh()->total_price);
            $this->assertSame('100.00', $halfDiscount->refresh()->total_price);
        });
    }

    public function test_customer_custom_price_is_proposed(): void
    {
        $tenant = $this->createTenant();

        $this->withinTenant($tenant, function (Tenant $tenant): void {
            [$contract, $customer] = $this->createContractFixture($tenant, 'CTR-CUSTOM');
            $item = $this->createBillableItem($tenant, 'Trappola collante', 20);

            CustomerBillableItemPrice::query()->create([
                'tenant_id' => $tenant->getKey(),
                'customer_id' => $customer->getKey(),
                'billable_item_id' => $item->getKey(),
                'custom_unit_price' => 7.25,
                'discount_percentage' => 50,
            ]);

            $state = app(ContractBillableItemPricingService::class)
                ->suggestedStateForContract($contract, $item, 2);

            $this->assertSame(7.25, $state['unit_price']);
            $this->assertNull($state['discount_percentage']);
            $this->assertSame(14.5, $state['total_price']);

            $line = ContractBillableItem::query()->create([
                'tenant_id' => $tenant->getKey(),
                'contract_id' => $contract->getKey(),
                'billable_item_id' => $item->getKey(),
                'quantity' => 2,
                'unit_price' => $state['unit_price'],
                'discount_percentage' => $state['discount_percentage'],
                'total_price' => $state['total_price'],
                'status' => 'active',
            ]);

            $this->assertSame($customer->getKey(), $contract->customer_id);
            $this->assertSame('7.25', $line->unit_price);
            $this->assertNull($line->discount_percentage);
            $this->assertSame('14.50', $line->total_price);
        });
    }

    public function test_customer_discounted_price_is_proposed(): void
    {
        $tenant = $this->createTenant();

        $this->withinTenant($tenant, function (Tenant $tenant): void {
            [$contract, $customer] = $this->createContractFixture($tenant, 'CTR-DISCOUNT');
            $item = $this->createBillableItem($tenant, 'Esca', 20);

            CustomerBillableItemPrice::query()->create([
                'tenant_id' => $tenant->getKey(),
                'customer_id' => $customer->getKey(),
                'billable_item_id' => $item->getKey(),
                'discount_percentage' => 10,
            ]);

            $state = app(ContractBillableItemPricingService::class)
                ->suggestedStateForContract($contract, $item, 3);

            $this->assertSame(20.0, $state['unit_price']);
            $this->assertSame(10.0, $state['discount_percentage']);
            $this->assertSame(54.0, $state['total_price']);

            $line = ContractBillableItem::query()->create([
                'tenant_id' => $tenant->getKey(),
                'contract_id' => $contract->getKey(),
                'billable_item_id' => $item->getKey(),
                'quantity' => 3,
                'unit_price' => $state['unit_price'],
                'discount_percentage' => $state['discount_percentage'],
                'total_price' => $state['total_price'],
                'status' => 'active',
            ]);

            $this->assertSame('20.00', $line->unit_price);
            $this->assertSame('10.00', $line->discount_percentage);
            $this->assertSame('54.00', $line->total_price);
        });
    }

    public function test_manual_unit_price_is_respected_and_contract_delete_cascades_lines(): void
    {
        $tenant = $this->createTenant();

        $this->withinTenant($tenant, function (Tenant $tenant): void {
            [$contract] = $this->createContractFixture($tenant, 'CTR-MANUAL');
            $item = $this->createBillableItem($tenant, 'Cartello posizionamento', 20);

            $line = ContractBillableItem::query()->create([
                'tenant_id' => $tenant->getKey(),
                'contract_id' => $contract->getKey(),
                'billable_item_id' => $item->getKey(),
                'quantity' => 5,
                'unit_price' => 13,
                'discount_percentage' => 10,
                'status' => 'active',
            ]);

            $this->assertSame('13.00', $line->unit_price);
            $this->assertSame('58.50', $line->refresh()->total_price);

            $contract->delete();

            $this->assertSame(0, ContractBillableItem::query()->count());
            $this->assertSame(1, BillableItem::query()->count());
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

    /**
     * @return array{0: Contract, 1: Customer}
     */
    protected function createContractFixture(Tenant $tenant, string $contractNumber, ?Customer $customer = null): array
    {
        $customer ??= Customer::query()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Cliente materiali '.$contractNumber,
            'status' => 'active',
        ]);

        $site = CustomerSite::query()->create([
            'tenant_id' => $tenant->getKey(),
            'customer_id' => $customer->getKey(),
            'name' => 'Sede '.$contractNumber,
            'status' => 'active',
        ]);

        $contract = Contract::query()->create([
            'tenant_id' => $tenant->getKey(),
            'customer_id' => $customer->getKey(),
            'customer_site_id' => $site->getKey(),
            'contract_number' => $contractNumber,
            'status' => 'active',
            'currency' => 'EUR',
        ]);

        return [$contract, $customer];
    }

    protected function createBillableItem(Tenant $tenant, string $name, float $price): BillableItem
    {
        return BillableItem::query()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => $name,
            'default_unit_price' => $price,
            'status' => 'active',
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
