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
use App\Support\Contracts\ContractNumberService;
use App\Support\Contracts\ContractProgrammingService;
use App\Support\Tenancy\CurrentTenant;
use App\Support\Tenancy\TenantConnectionManager;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class ContractV2ModelTest extends TestCase
{
    use RefreshDatabase;

    protected string $tenantDatabasePath;

    protected array $tenantDatabasePaths = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantDatabasePath = $this->createMigratedTenantDatabase();
    }

    protected function tearDown(): void
    {
        DB::purge('tenant');

        foreach (array_unique($this->tenantDatabasePaths) as $tenantDatabasePath) {
            if (is_file($tenantDatabasePath)) {
                @unlink($tenantDatabasePath);
            }
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

    public function test_contract_number_is_unique_for_the_same_tenant(): void
    {
        $tenant = $this->createTenant();

        $this->expectException(QueryException::class);

        $this->withinTenant($tenant, function (Tenant $tenant): void {
            $this->createContractFixture($tenant, '2026/1', 'Cliente Alpha');
            $this->createContractFixture($tenant, '2026/1', 'Cliente Beta');
        });
    }

    public function test_same_contract_number_is_allowed_for_different_tenants(): void
    {
        $tenantA = $this->createTenant('Tenant A', 'tenant-a');
        $tenantB = $this->createTenant('Tenant B', 'tenant-b', $this->createMigratedTenantDatabase());

        $this->withinTenant($tenantA, function (Tenant $tenant): void {
            $this->createContractFixture($tenant, '2026/1', 'Cliente Alpha');
        });

        $this->withinTenant($tenantB, function (Tenant $tenant): void {
            $this->createContractFixture($tenant, '2026/1', 'Cliente Beta');
        });

        $this->assertTrue(true);
    }

    public function test_contract_number_allows_historical_slash_format(): void
    {
        $tenant = $this->createTenant();

        $this->withinTenant($tenant, function (Tenant $tenant): void {
            [$contract] = $this->createContractFixture($tenant, '2025/1', 'Cliente Storico');

            $this->assertSame('2025/1', $contract->contract_number);
        });
    }

    public function test_next_contract_number_ignores_non_numeric_contract_numbers(): void
    {
        $tenant = $this->createTenant();

        $this->withinTenant($tenant, function (Tenant $tenant): void {
            $this->createContractFixture($tenant, '1', 'Cliente Uno');
            $this->createContractFixture($tenant, '2026/1', 'Cliente Storico Slash');
            $this->createContractFixture($tenant, 'AZ-009', 'Cliente Codice Legacy');
            $this->createContractFixture($tenant, '9', 'Cliente Nove');

            $this->assertSame('10', app(ContractNumberService::class)->nextForTenant($tenant));
        });
    }

    public function test_default_contract_service_mode_allows_multiple_services(): void
    {
        $tenant = $this->createTenant();

        $this->assertSame(Tenant::CONTRACT_SERVICE_MODE_MULTIPLE, $tenant->refresh()->contractServiceMode());
    }

    public function test_single_service_tenant_cannot_create_more_than_one_service_per_contract(): void
    {
        $tenant = $this->createTenant(contractServiceMode: Tenant::CONTRACT_SERVICE_MODE_SINGLE);

        $this->expectException(ValidationException::class);

        $this->withinTenant($tenant, function (Tenant $tenant): void {
            [$contract, $serviceType, $site] = $this->createContractFixture($tenant, 'CTR-ONE-SERVICE', 'Cliente Servizio');

            ContractService::query()->create([
                'tenant_id' => $tenant->getKey(),
                'contract_id' => $contract->getKey(),
                'service_type_id' => $serviceType->getKey(),
                'customer_site_id' => $site->getKey(),
                'description' => 'Primo servizio',
                'operational_frequency' => 'monthly',
                'billing_frequency' => 'quarterly',
                'status' => 'active',
            ]);

            ContractService::query()->create([
                'tenant_id' => $tenant->getKey(),
                'contract_id' => $contract->getKey(),
                'service_type_id' => $serviceType->getKey(),
                'customer_site_id' => $site->getKey(),
                'description' => 'Secondo servizio',
                'operational_frequency' => 'yearly',
                'billing_frequency' => 'yearly',
                'status' => 'active',
            ]);
        });
    }

    public function test_multiple_services_tenant_can_create_more_services_per_contract(): void
    {
        $tenant = $this->createTenant(contractServiceMode: Tenant::CONTRACT_SERVICE_MODE_MULTIPLE);

        $this->withinTenant($tenant, function (Tenant $tenant): void {
            [$contract, $serviceType, $site] = $this->createContractFixture($tenant, 'CTR-MANY-SERVICES', 'Cliente Multi Servizio');

            ContractService::query()->create([
                'tenant_id' => $tenant->getKey(),
                'contract_id' => $contract->getKey(),
                'service_type_id' => $serviceType->getKey(),
                'customer_site_id' => $site->getKey(),
                'description' => 'Primo servizio',
                'operational_frequency' => 'monthly',
                'billing_frequency' => 'quarterly',
                'status' => 'active',
            ]);

            ContractService::query()->create([
                'tenant_id' => $tenant->getKey(),
                'contract_id' => $contract->getKey(),
                'service_type_id' => $serviceType->getKey(),
                'customer_site_id' => $site->getKey(),
                'description' => 'Secondo servizio',
                'operational_frequency' => 'yearly',
                'billing_frequency' => 'yearly',
                'status' => 'active',
            ]);

            $this->assertSame(2, $contract->services()->count());
        });
    }

    public function test_tacit_renewal_fields_are_persisted(): void
    {
        $tenant = $this->createTenant();

        $this->withinTenant($tenant, function (Tenant $tenant): void {
            [$contract] = $this->createContractFixture($tenant, 'CTR-RENEWAL', 'Cliente Rinnovo');

            $contract->update([
                'tacit_renewal' => true,
                'renewal_price_increase_percentage' => 4.00,
                'renewal_notice_days' => 45,
            ]);

            $contract->refresh();

            $this->assertTrue($contract->tacit_renewal);
            $this->assertSame('4.00', $contract->renewal_price_increase_percentage);
            $this->assertSame(45, $contract->renewal_notice_days);
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

    public function test_new_contract_form_proposes_next_numeric_contract_number(): void
    {
        $tenant = $this->createTenant();

        $this->withinTenant($tenant, function (Tenant $tenant): void {
            $this->createContractFixture($tenant, '7', 'Cliente Sette');
            $this->createContractFixture($tenant, '2026/1', 'Cliente Storico');
            $this->createContractFixture($tenant, '18', 'Cliente Diciotto');
        });

        $user = $this->createTenantAdmin($tenant);

        $this->actingAs($user);
        app(TenantConnectionManager::class)->activate($tenant);
        app(CurrentTenant::class)->set($tenant);
        Filament::setTenant($tenant, isQuiet: true);

        try {
            Livewire::test(CreateContract::class)
                ->assertFormSet([
                    'contract_number' => '19',
                ]);
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
                ->assertActionVisible('generateInterventions')
                ->assertActionVisible('regenerateInterventions')
                ->assertActionVisible('generateBillingSchedules')
                ->assertActionVisible('regenerateBillingSchedules')
                ->assertActionVisible('renewContract')
                ->assertActionVisible('cancelContract')
                ->assertActionDoesNotExist('closeContract')
                ->assertActionDoesNotExist('reactivateContract')
                ->assertActionDoesNotExist('duplicateContract')
                ->callAction('addManualEvent', [
                    'event_type' => 'manual',
                    'title' => 'Nota operativa test',
                ]);

            $this->assertTrue(ContractEvent::query()
                ->where('contract_id', $contractId)
                ->where('event_type', 'manual')
                ->where('title', 'Nota operativa test')
                ->exists());
        } finally {
            app(CurrentTenant::class)->set(null);
            Filament::setTenant(null, isQuiet: true);
            DB::purge(config('tenancy.database_connection'));
        }
    }

    public function test_renew_contract_action_duplicates_contract_service_and_concludes_old_contract(): void
    {
        $tenant = $this->createTenant();

        [$contractId] = $this->withinTenant($tenant, function (Tenant $tenant): array {
            [$contract, $serviceType, $site] = $this->createContractFixture($tenant, '10', 'Cliente Renewal Action');

            $contract->update([
                'start_date' => '2026-01-01',
                'end_date' => '2026-12-31',
                'payment_terms' => 'Visto fattura',
                'billing_frequency' => 'quarterly',
                'billing_installments_count' => 4,
                'total_value' => 1000,
            ]);

            ContractService::query()->create([
                'tenant_id' => $tenant->getKey(),
                'contract_id' => $contract->getKey(),
                'service_type_id' => $serviceType->getKey(),
                'customer_site_id' => $site->getKey(),
                'description' => 'Servizio da rinnovare',
                'operational_frequency' => 'monthly',
                'status' => 'active',
            ]);

            return [$contract->getKey()];
        });

        $user = $this->createTenantAdmin($tenant);

        $this->actingAs($user);
        app(TenantConnectionManager::class)->activate($tenant);
        app(CurrentTenant::class)->set($tenant);
        Filament::setTenant($tenant, isQuiet: true);

        try {
            Livewire::test(ViewContract::class, ['record' => $contractId])
                ->callAction('renewContract');

            $oldContract = Contract::query()->findOrFail($contractId);
            $newContract = Contract::query()
                ->where('renewed_from_contract_id', $contractId)
                ->firstOrFail();

            $this->assertSame('concluded', $oldContract->status);
            $this->assertSame('active', $newContract->status);
            $this->assertSame('11', $newContract->contract_number);
            $this->assertSame($oldContract->customer_id, $newContract->customer_id);
            $this->assertSame('quarterly', $newContract->billing_frequency);
            $this->assertSame(1, $newContract->services()->count());
            $this->assertTrue($oldContract->renewals()->whereKey($newContract->getKey())->exists());
            $this->assertSame($oldContract->getKey(), $newContract->renewedFrom?->getKey());

            $this->assertTrue(ContractEvent::query()
                ->where('contract_id', $contractId)
                ->where('event_type', 'renewed')
                ->exists());
            $this->assertTrue(ContractEvent::query()
                ->where('contract_id', $newContract->getKey())
                ->where('event_type', 'created_from_renewal')
                ->exists());
        } finally {
            app(CurrentTenant::class)->set(null);
            Filament::setTenant(null, isQuiet: true);
            DB::purge(config('tenancy.database_connection'));
        }
    }

    public function test_renew_contract_action_applies_four_percent_increase_when_tacit_renewal_is_enabled(): void
    {
        $tenant = $this->createTenant();

        [$contractId] = $this->withinTenant($tenant, function (Tenant $tenant): array {
            [$contract, $serviceType, $site] = $this->createContractFixture($tenant, '20', 'Cliente Aumento');

            $contract->update([
                'tacit_renewal' => true,
                'renewal_price_increase_percentage' => 4.00,
                'total_value' => 1000,
            ]);

            ContractService::query()->create([
                'tenant_id' => $tenant->getKey(),
                'contract_id' => $contract->getKey(),
                'service_type_id' => $serviceType->getKey(),
                'customer_site_id' => $site->getKey(),
                'description' => 'Servizio aumento',
                'quantity' => 10,
                'unit_price' => 100,
                'total_price' => 1000,
                'status' => 'active',
            ]);

            return [$contract->getKey()];
        });

        $user = $this->createTenantAdmin($tenant);

        $this->actingAs($user);
        app(TenantConnectionManager::class)->activate($tenant);
        app(CurrentTenant::class)->set($tenant);
        Filament::setTenant($tenant, isQuiet: true);

        try {
            Livewire::test(ViewContract::class, ['record' => $contractId])
                ->callAction('renewContract');

            $newContract = Contract::query()
                ->where('renewed_from_contract_id', $contractId)
                ->firstOrFail();
            $newService = $newContract->services()->firstOrFail();

            $this->assertEqualsWithDelta(1040.00, (float) $newContract->total_value, 0.01);
            $this->assertSame('104.00', $newService->unit_price);
            $this->assertSame('1040.00', $newService->total_price);
        } finally {
            app(CurrentTenant::class)->set(null);
            Filament::setTenant(null, isQuiet: true);
            DB::purge(config('tenancy.database_connection'));
        }
    }

    public function test_cancel_contract_action_sets_status_cancelled(): void
    {
        $tenant = $this->createTenant();

        [$contractId] = $this->withinTenant($tenant, function (Tenant $tenant): array {
            [$contract] = $this->createContractFixture($tenant, 'CTR-CANCEL', 'Cliente Cancel');

            return [$contract->getKey()];
        });

        $user = $this->createTenantAdmin($tenant);

        $this->actingAs($user);
        app(TenantConnectionManager::class)->activate($tenant);
        app(CurrentTenant::class)->set($tenant);
        Filament::setTenant($tenant, isQuiet: true);

        try {
            Livewire::test(ViewContract::class, ['record' => $contractId])
                ->callAction('cancelContract');

            $this->assertSame('cancelled', Contract::query()->findOrFail($contractId)->status);
            $this->assertTrue(ContractEvent::query()
                ->where('contract_id', $contractId)
                ->where('event_type', 'cancelled')
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
                'operational_frequency' => 'monthly',
                'billing_frequency' => 'quarterly',
                'status' => 'active',
            ]);

            $service = app(ContractProgrammingService::class);
            $created = $service->generateScheduledInterventions($contract->refresh());
            $result = $service->lastResult();

            $this->assertSame(3, $created);
            $this->assertSame(3, $result['created']);
            $this->assertSame(0, $result['skipped']);
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

            $secondCreated = $service->generateScheduledInterventions($contract->refresh());
            $secondResult = $service->lastResult();

            $this->assertSame(0, $secondCreated);
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
            $this->assertSame(0, $event->payload['skipped']);
        });
    }

    public function test_contract_programming_recurring_monthly_generates_twelve_interventions(): void
    {
        $tenant = $this->createTenant();

        $this->withinTenant($tenant, function (Tenant $tenant): void {
            [$contract, $serviceType, $site] = $this->createContractFixture($tenant, 'CTR-RECURRING-12', 'Cliente Ricorrente');

            $contract->update([
                'start_date' => '2026-01-01',
                'end_date' => '2026-12-31',
            ]);

            ContractService::query()->create([
                'tenant_id' => $tenant->getKey(),
                'contract_id' => $contract->getKey(),
                'service_type_id' => $serviceType->getKey(),
                'customer_site_id' => $site->getKey(),
                'description' => 'Servizio mensile ricorrente',
                'operational_schedule_mode' => 'recurring',
                'operational_frequency' => 'monthly',
                'status' => 'active',
            ]);

            $service = app(ContractProgrammingService::class);

            $this->assertSame(12, $service->generateScheduledInterventions($contract->refresh()));
            $this->assertSame(12, ScheduledIntervention::query()->count());
        });
    }

    public function test_contract_programming_custom_months_generates_selected_months_and_is_idempotent(): void
    {
        $tenant = $this->createTenant();

        $this->withinTenant($tenant, function (Tenant $tenant): void {
            [$contract, $serviceType, $site] = $this->createContractFixture($tenant, 'CTR-CUSTOM-MONTHS', 'Cliente Mesi');

            $contract->update([
                'start_date' => '2026-01-01',
                'end_date' => '2026-12-31',
            ]);

            ContractService::query()->create([
                'tenant_id' => $tenant->getKey(),
                'contract_id' => $contract->getKey(),
                'service_type_id' => $serviceType->getKey(),
                'customer_site_id' => $site->getKey(),
                'description' => 'Disinfestazioni mesi AZ',
                'operational_schedule_mode' => 'custom_months',
                'scheduled_months' => [2, 3, 5, 6, 7],
                'interventions_per_year' => 5,
                'status' => 'active',
            ]);

            $service = app(ContractProgrammingService::class);
            $created = $service->generateScheduledInterventions($contract->refresh());

            $this->assertSame(5, $created);
            $this->assertSame([
                '2026-02-01',
                '2026-03-01',
                '2026-05-01',
                '2026-06-01',
                '2026-07-01',
            ], ScheduledIntervention::query()
                ->orderBy('planned_date')
                ->get()
                ->map(fn (ScheduledIntervention $intervention): string => $intervention->planned_date->toDateString())
                ->all());

            $this->assertSame(0, $service->generateScheduledInterventions($contract->refresh()));
            $this->assertSame(5, ScheduledIntervention::query()->count());
        });
    }

    public function test_contract_programming_manual_schedule_does_not_generate_and_warns(): void
    {
        $tenant = $this->createTenant();

        $this->withinTenant($tenant, function (Tenant $tenant): void {
            [$contract, $serviceType, $site] = $this->createContractFixture($tenant, 'CTR-MANUAL-SCHEDULE', 'Cliente Manuale');

            $contract->update([
                'start_date' => '2026-01-01',
                'end_date' => '2026-12-31',
            ]);

            ContractService::query()->create([
                'tenant_id' => $tenant->getKey(),
                'contract_id' => $contract->getKey(),
                'service_type_id' => $serviceType->getKey(),
                'customer_site_id' => $site->getKey(),
                'description' => 'Servizio manuale',
                'operational_schedule_mode' => 'manual',
                'status' => 'active',
            ]);

            $service = app(ContractProgrammingService::class);
            $created = $service->generateScheduledInterventions($contract->refresh());
            $result = $service->lastResult();

            $this->assertSame(0, $created);
            $this->assertSame(0, ScheduledIntervention::query()->count());
            $this->assertContains('manual_schedule', collect($result['skipped_records'])->pluck('reason')->all());
        });
    }

    public function test_contract_programming_custom_months_respects_contract_start_and_end_dates(): void
    {
        $tenant = $this->createTenant();

        $this->withinTenant($tenant, function (Tenant $tenant): void {
            [$contract, $serviceType, $site] = $this->createContractFixture($tenant, 'CTR-CUSTOM-BOUNDS', 'Cliente Limiti');

            $contract->update([
                'start_date' => '2026-03-15',
                'end_date' => '2026-06-15',
            ]);

            ContractService::query()->create([
                'tenant_id' => $tenant->getKey(),
                'contract_id' => $contract->getKey(),
                'service_type_id' => $serviceType->getKey(),
                'customer_site_id' => $site->getKey(),
                'description' => 'Servizio mesi con limiti',
                'operational_schedule_mode' => 'custom_months',
                'scheduled_months' => [2, 3, 5, 6, 7],
                'status' => 'active',
            ]);

            $service = app(ContractProgrammingService::class);

            $this->assertSame(3, $service->generateScheduledInterventions($contract->refresh()));
            $this->assertSame([
                '2026-03-15',
                '2026-05-15',
                '2026-06-15',
            ], ScheduledIntervention::query()
                ->orderBy('planned_date')
                ->get()
                ->map(fn (ScheduledIntervention $intervention): string => $intervention->planned_date->toDateString())
                ->all());
        });
    }

    public function test_contract_programming_replace_regenerates_only_future_planned_interventions(): void
    {
        CarbonImmutable::setTestNow('2026-06-01 09:00:00');

        try {
            $tenant = $this->createTenant();

            $this->withinTenant($tenant, function (Tenant $tenant): void {
                [$contract, $serviceType, $site] = $this->createContractFixture($tenant, 'CTR-SCHEDULE-REPLACE', 'Cliente Replace');

                $contract->update([
                    'start_date' => '2026-07-01',
                    'end_date' => '2026-09-30',
                ]);

                $contractService = ContractService::query()->create([
                    'tenant_id' => $tenant->getKey(),
                    'contract_id' => $contract->getKey(),
                    'service_type_id' => $serviceType->getKey(),
                    'customer_site_id' => $site->getKey(),
                    'description' => 'Servizio mensile replace',
                    'operational_frequency' => 'mensile',
                    'billing_frequency' => 'quarterly',
                    'status' => 'active',
                ]);

                $service = app(ContractProgrammingService::class);
                $this->assertSame(3, $service->generateScheduledInterventions($contract->refresh()));

                ScheduledIntervention::query()
                    ->whereDate('planned_date', '2026-08-01')
                    ->firstOrFail()
                    ->update(['status' => 'completed']);

                ScheduledIntervention::query()
                    ->whereDate('planned_date', '2026-09-01')
                    ->firstOrFail()
                    ->update(['status' => 'cancelled']);

                ScheduledIntervention::query()->create([
                    'tenant_id' => $tenant->getKey(),
                    'contract_id' => $contract->getKey(),
                    'contract_service_id' => $contractService->getKey(),
                    'customer_site_id' => $site->getKey(),
                    'service_type_id' => $serviceType->getKey(),
                    'planned_date' => '2026-05-01',
                    'status' => 'planned',
                ]);

                $created = $service->generateScheduledInterventions($contract->refresh(), replace: true);
                $result = $service->lastResult();

                $this->assertSame(1, $created);
                $this->assertSame(1, $result['deleted']);
                $this->assertSame(1, ScheduledIntervention::query()
                    ->whereDate('planned_date', '2026-05-01')
                    ->where('status', 'planned')
                    ->count());
                $this->assertSame(1, ScheduledIntervention::query()
                    ->whereDate('planned_date', '2026-08-01')
                    ->where('status', 'completed')
                    ->count());
                $this->assertSame(1, ScheduledIntervention::query()
                    ->whereDate('planned_date', '2026-09-01')
                    ->where('status', 'cancelled')
                    ->count());
                $this->assertSame([
                    '2026-05-01:planned',
                    '2026-07-01:planned',
                    '2026-08-01:completed',
                    '2026-09-01:cancelled',
                ], ScheduledIntervention::query()
                    ->orderBy('planned_date')
                    ->get()
                    ->map(fn (ScheduledIntervention $intervention): string => $intervention->planned_date->toDateString().':'.$intervention->status)
                    ->all());
            });
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_contract_programming_generates_quarterly_billing_schedule_and_is_idempotent(): void
    {
        $tenant = $this->createTenant();

        $this->withinTenant($tenant, function (Tenant $tenant): void {
            [$contract, $serviceType, $site] = $this->createContractFixture($tenant, 'CTR-BILLING', 'Cliente Billing');

            $contract->update([
                'start_date' => '2026-01-01',
                'end_date' => '2026-09-30',
                'total_value' => 900,
                'currency' => 'EUR',
                'billing_frequency' => 'trimestrale',
            ]);

            ContractService::query()->create([
                'tenant_id' => $tenant->getKey(),
                'contract_id' => $contract->getKey(),
                'service_type_id' => $serviceType->getKey(),
                'customer_site_id' => $site->getKey(),
                'description' => 'Servizio trimestrale',
                'operational_frequency' => 'monthly',
                'billing_frequency' => 'yearly',
                'status' => 'active',
            ]);

            $service = app(ContractProgrammingService::class);
            $created = $service->generateBillingSchedules($contract->refresh());
            $result = $service->lastResult();

            $this->assertSame(3, $created);
            $this->assertSame(3, $result['created']);
            $this->assertSame(0, $result['skipped']);

            $schedules = ContractBillingSchedule::query()
                ->orderBy('due_date')
                ->get();

            $this->assertSame([
                '2026-01-01',
                '2026-04-01',
                '2026-07-01',
            ], $schedules
                ->map(fn (ContractBillingSchedule $schedule): string => $schedule->due_date->toDateString())
                ->all());
            $this->assertSame(['300.00', '300.00', '300.00'], $schedules->pluck('amount')->all());
            $this->assertTrue($schedules->every(fn (ContractBillingSchedule $schedule): bool => (int) $schedule->tenant_id === (int) $tenant->getKey()));

            $secondCreated = $service->generateBillingSchedules($contract->refresh());
            $secondResult = $service->lastResult();

            $this->assertSame(0, $secondCreated);
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
            $this->assertSame('quarterly', $event->payload['parameters']['frequency']);
        });
    }

    public function test_billing_schedule_reads_billing_frequency_from_contract_not_service(): void
    {
        $tenant = $this->createTenant();

        $this->withinTenant($tenant, function (Tenant $tenant): void {
            [$contract, $serviceType, $site] = $this->createContractFixture($tenant, 'CTR-BILLING-CONTRACT', 'Cliente Billing Contract');

            $contract->update([
                'start_date' => '2026-01-01',
                'end_date' => '2026-12-31',
                'total_value' => 1200,
                'currency' => 'EUR',
                'billing_frequency' => 'six_monthly',
            ]);

            ContractService::query()->create([
                'tenant_id' => $tenant->getKey(),
                'contract_id' => $contract->getKey(),
                'service_type_id' => $serviceType->getKey(),
                'customer_site_id' => $site->getKey(),
                'description' => 'Servizio con cadenza legacy diversa',
                'operational_frequency' => 'monthly',
                'billing_frequency' => 'monthly',
                'status' => 'active',
            ]);

            $service = app(ContractProgrammingService::class);
            $created = $service->generateBillingSchedules($contract->refresh());

            $this->assertSame(2, $created);
            $this->assertSame([
                '2026-01-01',
                '2026-07-01',
            ], ContractBillingSchedule::query()
                ->orderBy('due_date')
                ->get()
                ->map(fn (ContractBillingSchedule $schedule): string => $schedule->due_date->toDateString())
                ->all());
        });
    }

    public function test_contract_programming_does_not_generate_billing_without_total_value(): void
    {
        $tenant = $this->createTenant();

        $this->withinTenant($tenant, function (Tenant $tenant): void {
            [$contract, $serviceType, $site] = $this->createContractFixture($tenant, 'CTR-BILLING-MISSING', 'Cliente Billing Missing');

            $contract->update([
                'start_date' => '2026-01-01',
                'end_date' => '2026-12-31',
                'total_value' => null,
                'billing_frequency' => 'quarterly',
            ]);

            ContractService::query()->create([
                'tenant_id' => $tenant->getKey(),
                'contract_id' => $contract->getKey(),
                'service_type_id' => $serviceType->getKey(),
                'customer_site_id' => $site->getKey(),
                'description' => 'Servizio senza valore',
                'operational_frequency' => 'monthly',
                'billing_frequency' => 'quarterly',
                'status' => 'active',
            ]);

            $service = app(ContractProgrammingService::class);
            $created = $service->generateBillingSchedules($contract->refresh());
            $result = $service->lastResult();

            $this->assertSame(0, $created);
            $this->assertSame(0, ContractBillingSchedule::query()->count());
            $this->assertContains('missing_total_value', collect($result['skipped_records'])->pluck('reason')->all());
        });
    }

    protected function createTenant(
        string $name = 'Tenant Demo',
        string $slug = 'tenant-demo',
        ?string $databasePath = null,
        ?string $contractServiceMode = null,
    ): Tenant {
        $data = [
            'name' => $name,
            'slug' => $slug,
            'db_database' => $databasePath ?? $this->tenantDatabasePath,
            'status' => 'active',
        ];

        if ($contractServiceMode !== null) {
            $data['contract_service_mode'] = $contractServiceMode;
        }

        return Tenant::query()->create($data);
    }

    protected function createMigratedTenantDatabase(): string
    {
        $databasePath = tempnam(sys_get_temp_dir(), 'tenant-db-');

        if ($databasePath === false) {
            throw new RuntimeException('Impossibile creare il database temporaneo tenant per i test.');
        }

        $this->tenantDatabasePaths[] = $databasePath;

        config([
            'database.connections.tenant' => [
                'driver' => 'sqlite',
                'database' => $databasePath,
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);

        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => database_path('migrations/tenant'),
            '--realpath' => true,
        ]);

        DB::purge('tenant');

        return $databasePath;
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
