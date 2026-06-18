<?php

namespace Tests\Feature;

use App\Filament\Resources\Contracts\ContractResource;
use App\Models\Contract;
use App\Models\ContractBillingSchedule;
use App\Models\ContractEvent;
use App\Models\ContractService;
use App\Models\Customer;
use App\Models\CustomerSite;
use App\Models\Document;
use App\Models\ScheduledIntervention;
use App\Models\ServiceType;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\CurrentTenant;
use App\Support\Tenancy\TenantConnectionManager;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class ContractV2ModelTest extends TestCase
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

    public function test_contract_v2_models_use_the_tenant_connection(): void
    {
        $tenant = $this->createTenant();

        $this->withinTenant($tenant, function (): void {
            $this->assertSame('tenant', (new ContractService)->getConnectionName());
            $this->assertSame('tenant', (new ScheduledIntervention)->getConnectionName());
            $this->assertSame('tenant', (new ContractBillingSchedule)->getConnectionName());
            $this->assertSame('tenant', (new Document)->getConnectionName());
            $this->assertSame('tenant', (new ContractEvent)->getConnectionName());
        });
    }

    public function test_contract_exposes_services_interventions_billing_documents_and_events(): void
    {
        $tenant = $this->createTenant();

        $this->withinTenant($tenant, function (Tenant $tenant): void {
            [$contract, $serviceType, $site] = $this->createContractFixture($tenant, 'CTR-001', 'Cliente Alpha');

            $contractService = ContractService::query()->create([
                'tenant_id' => $tenant->getKey(),
                'contract_id' => $contract->getKey(),
                'service_type_id' => $serviceType->getKey(),
                'customer_site_id' => $site->getKey(),
                'description' => 'Derattizzazione ordinaria',
                'frequency' => 'Mensile',
                'quantity' => 12,
                'unit_price' => 100,
                'total_price' => 1200,
                'currency' => 'EUR',
                'status' => 'active',
            ]);

            ScheduledIntervention::query()->create([
                'tenant_id' => $tenant->getKey(),
                'contract_id' => $contract->getKey(),
                'contract_service_id' => $contractService->getKey(),
                'customer_site_id' => $site->getKey(),
                'service_type_id' => $serviceType->getKey(),
                'planned_date' => '2026-07-01',
                'status' => 'planned',
            ]);

            ContractBillingSchedule::query()->create([
                'tenant_id' => $tenant->getKey(),
                'contract_id' => $contract->getKey(),
                'description' => 'Prima rata',
                'due_date' => '2026-07-15',
                'amount' => 600,
                'currency' => 'EUR',
                'status' => 'planned',
            ]);

            $contract->documents()->create([
                'tenant_id' => $tenant->getKey(),
                'type' => 'contract',
                'title' => 'Contratto firmato',
                'visible_to_customer' => true,
            ]);

            ContractEvent::query()->create([
                'tenant_id' => $tenant->getKey(),
                'contract_id' => $contract->getKey(),
                'event_type' => 'created',
                'title' => 'Contratto creato',
                'payload' => ['source' => 'test'],
            ]);

            $contract->refresh();

            $this->assertCount(1, $contract->services);
            $this->assertCount(1, $contract->scheduledInterventions);
            $this->assertCount(1, $contract->billingSchedules);
            $this->assertCount(1, $contract->documents);
            $this->assertCount(1, $contract->events);
        });
    }

    public function test_customer_user_does_not_see_contracts_from_other_customers(): void
    {
        $tenant = $this->createTenant();

        [$customerA] = $this->withinTenant($tenant, function (Tenant $tenant): array {
            [$contractA] = $this->createContractFixture($tenant, 'CTR-ALPHA', 'Cliente Alpha');
            $this->createContractFixture($tenant, 'CTR-BETA', 'Cliente Beta');

            return [$contractA->customer];
        });

        $user = User::query()->create([
            'name' => 'Cliente Portale',
            'email' => 'cliente-portal@example.com',
            'password' => 'password123',
            'tenant_id' => $tenant->getKey(),
            'customer_id' => $customerA->getKey(),
            'is_superuser' => false,
        ]);

        $this->actingAs($user);
        app(TenantConnectionManager::class)->activate($tenant);
        app(CurrentTenant::class)->set($tenant);
        Filament::setTenant($tenant, isQuiet: true);

        try {
            $contracts = ContractResource::getEloquentQuery()
                ->orderBy('contract_number')
                ->pluck('contract_number')
                ->all();
        } finally {
            app(CurrentTenant::class)->set(null);
            Filament::setTenant(null, isQuiet: true);
            DB::purge(config('tenancy.database_connection'));
        }

        $this->assertSame(['CTR-ALPHA'], $contracts);
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
     * @return array{0: Contract, 1: ServiceType, 2: CustomerSite}
     */
    protected function createContractFixture(Tenant $tenant, string $contractNumber, string $customerName): array
    {
        $customer = Customer::query()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => $customerName,
            'status' => 'active',
        ]);

        $site = CustomerSite::query()->create([
            'tenant_id' => $tenant->getKey(),
            'customer_id' => $customer->getKey(),
            'name' => 'Sede '.$customerName,
            'status' => 'active',
        ]);

        $serviceType = ServiceType::query()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Derattizzazione '.$customerName,
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
