<?php

namespace Tests\Feature;

use App\Filament\Resources\Contracts\Pages\ViewContract;
use App\Models\BillableItem;
use App\Models\Contract;
use App\Models\ContractBillableItem;
use App\Models\ContractService;
use App\Models\Customer;
use App\Models\CustomerSite;
use App\Models\InterventionBillableItem;
use App\Models\ScheduledIntervention;
use App\Models\ServiceType;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Contracts\ContractTotalsService;
use App\Support\Tenancy\CurrentTenant;
use App\Support\Tenancy\TenantConnectionManager;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class ContractTotalsServiceTest extends TestCase
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

    public function test_contract_totals_service_calculates_active_services_total(): void
    {
        $tenant = $this->createTenant();

        $this->withinTenant($tenant, function (Tenant $tenant): void {
            [$contract, $serviceType, $site] = $this->createContractFixture($tenant, 'CTR-SERVICES');

            $this->createService($tenant, $contract, $serviceType, $site, totalPrice: 100);
            $this->createService($tenant, $contract, $serviceType, $site, quantity: 2, unitPrice: 75);
            $this->createService($tenant, $contract, $serviceType, $site, totalPrice: 900, status: 'closed');

            $this->assertSame(250.0, app(ContractTotalsService::class)->calculateServicesTotal($contract));
        });
    }

    public function test_contract_totals_service_calculates_active_billable_items_total(): void
    {
        $tenant = $this->createTenant();

        $this->withinTenant($tenant, function (Tenant $tenant): void {
            [$contract] = $this->createContractFixture($tenant, 'CTR-ITEMS');

            $this->createContractBillableItem($tenant, $contract, 'Contenitori esca', totalPrice: 30);
            $this->createContractBillableItem($tenant, $contract, 'Cartelli Posizionamento', quantity: 4, unitPrice: 5);
            $this->createContractBillableItem($tenant, $contract, 'Extra inattivo', totalPrice: 500, status: 'inactive');

            $this->assertSame(50.0, app(ContractTotalsService::class)->calculateBillableItemsTotal($contract));
        });
    }

    public function test_contract_total_sums_services_and_contract_billable_items_excluding_intervention_extras(): void
    {
        $tenant = $this->createTenant();

        $this->withinTenant($tenant, function (Tenant $tenant): void {
            [$contract, $serviceType, $site] = $this->createContractFixture($tenant, 'CTR-TOTAL');

            $this->createService($tenant, $contract, $serviceType, $site, totalPrice: 120);
            $this->createContractBillableItem($tenant, $contract, 'Lampada UV', totalPrice: 80);
            $this->createInterventionBillableItem($tenant, $contract, $serviceType, $site, totalPrice: 999);

            $service = app(ContractTotalsService::class);

            $this->assertSame(120.0, $service->calculateServicesTotal($contract));
            $this->assertSame(80.0, $service->calculateBillableItemsTotal($contract));
            $this->assertSame(200.0, $service->calculateContractTotal($contract));
        });
    }

    public function test_manual_recalculation_action_updates_contract_total_value(): void
    {
        $tenant = $this->createTenant();

        [$contractId] = $this->withinTenant($tenant, function (Tenant $tenant): array {
            [$contract, $serviceType, $site] = $this->createContractFixture($tenant, 'CTR-ACTION');
            $contract->update(['total_value' => 1]);

            $this->createService($tenant, $contract, $serviceType, $site, totalPrice: 125);
            $this->createContractBillableItem($tenant, $contract, 'Trappola collante', totalPrice: 25);
            $this->createInterventionBillableItem($tenant, $contract, $serviceType, $site, totalPrice: 500);

            return [$contract->getKey()];
        });

        $this->actingAs($this->createTenantAdmin($tenant));
        $this->activateTenant($tenant);

        try {
            Livewire::test(ViewContract::class, ['record' => $contractId])
                ->callAction('recalculateContractTotal')
                ->assertHasNoActionErrors();

            $this->assertEqualsWithDelta(150.00, (float) Contract::query()->findOrFail($contractId)->total_value, 0.01);
        } finally {
            $this->deactivateTenant();
        }
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
            'email' => 'tenant-admin-contract-totals@example.com',
            'password' => 'password123',
            'tenant_id' => $tenant->getKey(),
            'is_superuser' => false,
        ]);
    }

    /**
     * @return array{0: Contract, 1: ServiceType, 2: CustomerSite}
     */
    protected function createContractFixture(Tenant $tenant, string $contractNumber): array
    {
        $customer = Customer::query()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Cliente '.$contractNumber,
            'status' => 'active',
        ]);

        $site = CustomerSite::query()->create([
            'tenant_id' => $tenant->getKey(),
            'customer_id' => $customer->getKey(),
            'name' => 'Sede '.$contractNumber,
            'status' => 'active',
        ]);

        $serviceType = ServiceType::query()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Servizio '.$contractNumber,
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

        return [$contract, $serviceType, $site];
    }

    protected function createService(
        Tenant $tenant,
        Contract $contract,
        ServiceType $serviceType,
        CustomerSite $site,
        ?float $totalPrice = null,
        ?float $quantity = null,
        ?float $unitPrice = null,
        string $status = 'active',
    ): ContractService {
        return ContractService::query()->create([
            'tenant_id' => $tenant->getKey(),
            'contract_id' => $contract->getKey(),
            'service_type_id' => $serviceType->getKey(),
            'customer_site_id' => $site->getKey(),
            'description' => 'Servizio',
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_price' => $totalPrice,
            'currency' => 'EUR',
            'status' => $status,
        ]);
    }

    protected function createContractBillableItem(
        Tenant $tenant,
        Contract $contract,
        string $name,
        ?float $totalPrice = null,
        ?float $quantity = null,
        ?float $unitPrice = null,
        string $status = 'active',
    ): ContractBillableItem {
        $item = BillableItem::query()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => $name,
            'status' => 'active',
        ]);

        return ContractBillableItem::query()->create([
            'tenant_id' => $tenant->getKey(),
            'contract_id' => $contract->getKey(),
            'billable_item_id' => $item->getKey(),
            'quantity' => $quantity ?? 1,
            'unit_price' => $unitPrice,
            'total_price' => $totalPrice,
            'status' => $status,
        ]);
    }

    protected function createInterventionBillableItem(
        Tenant $tenant,
        Contract $contract,
        ServiceType $serviceType,
        CustomerSite $site,
        float $totalPrice,
    ): InterventionBillableItem {
        $intervention = ScheduledIntervention::query()->create([
            'tenant_id' => $tenant->getKey(),
            'contract_id' => $contract->getKey(),
            'customer_site_id' => $site->getKey(),
            'service_type_id' => $serviceType->getKey(),
            'planned_date' => '2026-07-15',
            'status' => 'planned',
        ]);

        return InterventionBillableItem::query()->create([
            'tenant_id' => $tenant->getKey(),
            'scheduled_intervention_id' => $intervention->getKey(),
            'contract_id' => $contract->getKey(),
            'description' => 'Extra intervento',
            'quantity' => 1,
            'unit_price' => $totalPrice,
            'status' => 'pending',
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
