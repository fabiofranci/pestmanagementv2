<?php

namespace Tests\Feature;

use App\Filament\Resources\Contracts\ContractResource;
use App\Filament\Resources\Contracts\Pages\CreateContract;
use App\Filament\Resources\Contracts\Pages\ViewContract;
use App\Http\Middleware\BootstrapTenantContext;
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
use App\Support\Contracts\ContractProgrammingService;
use App\Support\Tenancy\CurrentTenant;
use App\Support\Tenancy\TenantConnectionManager;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
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

    public function test_customer_user_cannot_open_contracts_from_other_customers(): void
    {
        $tenant = $this->createTenant();

        [$contractA, $contractB] = $this->withinTenant($tenant, function (Tenant $tenant): array {
            [$contractA] = $this->createContractFixture($tenant, 'CTR-OWN', 'Cliente Own');
            [$contractB] = $this->createContractFixture($tenant, 'CTR-OTHER', 'Cliente Other');

            return [$contractA, $contractB];
        });

        $user = User::query()->create([
            'name' => 'Cliente Portale',
            'email' => 'cliente-view@example.com',
            'password' => 'password123',
            'tenant_id' => $tenant->getKey(),
            'customer_id' => $contractA->customer_id,
            'is_superuser' => false,
        ]);

        $this->actingAs($user)
            ->get(ContractResource::getUrl('view', ['record' => $contractA->getKey()]))
            ->assertOk()
            ->assertSee('CTR-OWN');

        $this->actingAs($user)
            ->get(ContractResource::getUrl('view', ['record' => $contractB->getKey()]))
            ->assertNotFound();
    }

    public function test_contract_resource_index_is_accessible_to_tenant_admin(): void
    {
        $tenant = $this->createTenant();

        $this->withinTenant($tenant, function (Tenant $tenant): void {
            $this->createContractFixture($tenant, 'CTR-ADMIN', 'Cliente Admin');
        });

        $user = $this->createTenantAdmin($tenant);

        $this->actingAs($user)
            ->get(ContractResource::getUrl('index'))
            ->assertOk()
            ->assertSee('CTR-ADMIN')
            ->assertSee('Cliente Admin');
    }

    public function test_livewire_update_route_bootstraps_tenant_context(): void
    {
        $livewireUpdateRoute = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($route): bool => str_ends_with((string) $route->getName(), 'livewire.update'));

        $this->assertNotNull($livewireUpdateRoute);
        $this->assertContains('web', $livewireUpdateRoute->middleware());
        $this->assertContains(BootstrapTenantContext::class, app('router')->getMiddlewareGroups()['web'] ?? []);
    }

    public function test_tenant_admin_can_create_contract_with_customer_selects(): void
    {
        $tenant = $this->createTenant();

        [$customer, $site] = $this->withinTenant($tenant, function (Tenant $tenant): array {
            $customer = Customer::query()->create([
                'tenant_id' => $tenant->getKey(),
                'name' => 'Cliente Select',
                'status' => 'active',
            ]);

            $site = CustomerSite::query()->create([
                'tenant_id' => $tenant->getKey(),
                'customer_id' => $customer->getKey(),
                'name' => 'Sede Select',
                'status' => 'active',
            ]);

            return [$customer, $site];
        });

        $user = $this->createTenantAdmin($tenant);

        $this->actingAs($user);
        app(TenantConnectionManager::class)->activate($tenant);
        app(CurrentTenant::class)->set($tenant);
        Filament::setTenant($tenant, isQuiet: true);

        try {
            $this->get(ContractResource::getUrl('create'))
                ->assertOk();

            Livewire::test(CreateContract::class)
                ->fillForm([
                    'contract_number' => 'CTR-SELECT',
                    'status' => 'active',
                    'customer_id' => $customer->getKey(),
                    'customer_site_id' => $site->getKey(),
                    'start_date' => '2026-07-01',
                    'end_date' => '2027-06-30',
                    'payment_terms' => '30 giorni',
                    'total_value' => 1200,
                    'currency' => 'EUR',
                ])
                ->call('create')
                ->assertHasNoFormErrors();

            $this->assertTrue(Contract::query()
                ->where('contract_number', 'CTR-SELECT')
                ->where('customer_id', $customer->getKey())
                ->where('customer_site_id', $site->getKey())
                ->exists());
        } finally {
            app(CurrentTenant::class)->set(null);
            Filament::setTenant(null, isQuiet: true);
            DB::purge(config('tenancy.database_connection'));
        }
    }

    public function test_contract_view_page_renders_summary_and_related_data(): void
    {
        $tenant = $this->createTenant();

        [$contractId] = $this->withinTenant($tenant, function (Tenant $tenant): array {
            [$contract, $serviceType, $site] = $this->createContractFixture($tenant, 'CTR-VIEW', 'Cliente View');

            $contractService = ContractService::query()->create([
                'tenant_id' => $tenant->getKey(),
                'contract_id' => $contract->getKey(),
                'service_type_id' => $serviceType->getKey(),
                'customer_site_id' => $site->getKey(),
                'description' => 'Servizio vista',
                'status' => 'active',
            ]);

            ScheduledIntervention::query()->create([
                'tenant_id' => $tenant->getKey(),
                'contract_id' => $contract->getKey(),
                'contract_service_id' => $contractService->getKey(),
                'customer_site_id' => $site->getKey(),
                'service_type_id' => $serviceType->getKey(),
                'planned_date' => now()->addDay()->toDateString(),
                'status' => 'planned',
            ]);

            ContractBillingSchedule::query()->create([
                'tenant_id' => $tenant->getKey(),
                'contract_id' => $contract->getKey(),
                'description' => 'Scadenza vista',
                'due_date' => now()->addWeek()->toDateString(),
                'amount' => 250,
                'currency' => 'EUR',
                'status' => 'planned',
            ]);

            $contract->documents()->create([
                'tenant_id' => $tenant->getKey(),
                'type' => 'contract',
                'title' => 'Documento vista',
                'visible_to_customer' => true,
            ]);

            ContractEvent::query()->create([
                'tenant_id' => $tenant->getKey(),
                'contract_id' => $contract->getKey(),
                'event_type' => 'manual',
                'title' => 'Evento vista',
            ]);

            return [$contract->getKey()];
        });

        $user = $this->createTenantAdmin($tenant);

        $this->actingAs($user)
            ->get(ContractResource::getUrl('view', ['record' => $contractId]))
            ->assertOk()
            ->assertSee('CTR-VIEW')
            ->assertSee('Cliente View')
            ->assertSee('Riepilogo operativo')
            ->assertSee('Scadenza vista')
            ->assertSee('Evento vista')
            ->assertSee('Servizi contrattuali');
    }

    public function test_contract_view_page_light_actions_update_contract_and_events(): void
    {
        $tenant = $this->createTenant();

        [$contractId] = $this->withinTenant($tenant, function (Tenant $tenant): array {
            [$contract] = $this->createContractFixture($tenant, 'CTR-ACTIONS', 'Cliente Actions');

            return [$contract->getKey()];
        });

        $user = $this->createTenantAdmin($tenant);

        $this->actingAs($user);
        app(TenantConnectionManager::class)->activate($tenant);
        app(CurrentTenant::class)->set($tenant);
        Filament::setTenant($tenant, isQuiet: true);

        try {
            Livewire::test(ViewContract::class, ['record' => $contractId])
                ->assertActionVisible('addManualEvent')
                ->assertActionVisible('generateScheduledInterventions')
                ->assertActionVisible('generateBillingSchedule')
                ->assertActionVisible('closeContract')
                ->assertActionDoesNotExist('duplicateContract')
                ->callAction('addManualEvent', [
                    'event_type' => 'manual',
                    'title' => 'Nota operativa test',
                ])
                ->callAction('closeContract');

            $this->assertSame('closed', Contract::query()->findOrFail($contractId)->status);
            $this->assertTrue(ContractEvent::query()
                ->where('contract_id', $contractId)
                ->where('event_type', 'manual')
                ->where('title', 'Nota operativa test')
                ->exists());
            $this->assertTrue(ContractEvent::query()
                ->where('contract_id', $contractId)
                ->where('event_type', 'closed')
                ->exists());

            Livewire::test(ViewContract::class, ['record' => $contractId])
                ->assertActionVisible('reactivateContract')
                ->callAction('reactivateContract');

            $this->assertSame('active', Contract::query()->findOrFail($contractId)->status);
            $this->assertTrue(ContractEvent::query()
                ->where('contract_id', $contractId)
                ->where('event_type', 'reactivated')
                ->exists());
        } finally {
            app(CurrentTenant::class)->set(null);
            Filament::setTenant(null, isQuiet: true);
            DB::purge(config('tenancy.database_connection'));
        }
    }

    public function test_contract_programming_generates_scheduled_interventions_and_is_idempotent(): void
    {
        $tenant = $this->createTenant();

        $this->withinTenant($tenant, function (Tenant $tenant): void {
            [$contract, $serviceType, $site] = $this->createContractFixture($tenant, 'CTR-SCHEDULE', 'Cliente Schedule');

            $contract->update([
                'start_date' => '2026-01-01',
                'end_date' => '2026-03-31',
            ]);

            ContractService::query()->create([
                'tenant_id' => $tenant->getKey(),
                'contract_id' => $contract->getKey(),
                'service_type_id' => $serviceType->getKey(),
                'customer_site_id' => $site->getKey(),
                'description' => 'Servizio mensile',
                'frequency' => 'monthly',
                'status' => 'active',
            ]);

            ContractService::query()->create([
                'tenant_id' => $tenant->getKey(),
                'contract_id' => $contract->getKey(),
                'service_type_id' => $serviceType->getKey(),
                'customer_site_id' => $site->getKey(),
                'description' => 'Servizio non supportato',
                'frequency' => 'weekly',
                'status' => 'active',
            ]);

            $result = app(ContractProgrammingService::class)
                ->generateScheduledInterventions($contract->refresh());

            $this->assertSame(3, $result['created']);
            $this->assertSame(1, $result['skipped']);
            $this->assertSame([
                '2026-01-01',
                '2026-02-01',
                '2026-03-01',
            ], ScheduledIntervention::query()
                ->orderBy('planned_date')
                ->get()
                ->map(fn (ScheduledIntervention $intervention): string => $intervention->planned_date->toDateString())
                ->all());
            $this->assertTrue(ScheduledIntervention::query()
                ->where('tenant_id', $tenant->getKey())
                ->where('contract_id', $contract->getKey())
                ->count() === 3);

            $secondResult = app(ContractProgrammingService::class)
                ->generateScheduledInterventions($contract->refresh());

            $this->assertSame(0, $secondResult['created']);
            $this->assertSame(3, ScheduledIntervention::query()->count());
            $this->assertSame(2, ContractEvent::query()
                ->where('contract_id', $contract->getKey())
                ->where('event_type', 'scheduled_interventions_generated')
                ->count());

            $event = ContractEvent::query()
                ->where('contract_id', $contract->getKey())
                ->where('event_type', 'scheduled_interventions_generated')
                ->oldest()
                ->firstOrFail();

            $this->assertSame(3, $event->payload['created']);
            $this->assertSame(1, $event->payload['skipped']);
        });
    }

    public function test_contract_programming_generates_billing_schedule_and_is_idempotent(): void
    {
        $tenant = $this->createTenant();

        $this->withinTenant($tenant, function (Tenant $tenant): void {
            [$contract] = $this->createContractFixture($tenant, 'CTR-BILLING', 'Cliente Billing');

            $contract->update([
                'start_date' => '2026-01-01',
                'end_date' => '2026-03-31',
                'total_value' => 1200,
                'currency' => 'EUR',
            ]);

            $result = app(ContractProgrammingService::class)
                ->generateBillingSchedule($contract->refresh(), 'monthly');

            $this->assertSame(3, $result['created']);
            $this->assertSame(0, $result['skipped']);

            $schedules = ContractBillingSchedule::query()
                ->orderBy('due_date')
                ->get();

            $this->assertSame([
                '2026-01-01',
                '2026-02-01',
                '2026-03-01',
            ], $schedules
                ->map(fn (ContractBillingSchedule $schedule): string => $schedule->due_date->toDateString())
                ->all());
            $this->assertSame(['400.00', '400.00', '400.00'], $schedules->pluck('amount')->all());
            $this->assertTrue($schedules->every(fn (ContractBillingSchedule $schedule): bool => (int) $schedule->tenant_id === (int) $tenant->getKey()));

            $secondResult = app(ContractProgrammingService::class)
                ->generateBillingSchedule($contract->refresh(), 'monthly');

            $this->assertSame(0, $secondResult['created']);
            $this->assertSame(3, ContractBillingSchedule::query()->count());
            $this->assertSame(2, ContractEvent::query()
                ->where('contract_id', $contract->getKey())
                ->where('event_type', 'billing_schedule_generated')
                ->count());

            $event = ContractEvent::query()
                ->where('contract_id', $contract->getKey())
                ->where('event_type', 'billing_schedule_generated')
                ->oldest()
                ->firstOrFail();

            $this->assertSame(3, $event->payload['created']);
            $this->assertSame('monthly', $event->payload['parameters']['frequency']);
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
            'email' => 'tenant-admin-'.uniqid().'@example.com',
            'password' => 'password123',
            'tenant_id' => $tenant->getKey(),
            'customer_id' => null,
            'is_superuser' => false,
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
