<?php

namespace Tests\Feature;

use App\Filament\Resources\Contracts\Pages\CreateContract;
use App\Filament\Resources\Contracts\Pages\EditContract;
use App\Models\Contract;
use App\Models\ContractService;
use App\Models\Customer;
use App\Models\CustomerSite;
use App\Models\ServiceType;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\CurrentTenant;
use App\Support\Tenancy\TenantConnectionManager;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class CustomerSiteDefaultAndContractServiceFormTest extends TestCase
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

    public function test_creating_customer_with_default_site_enabled_creates_site_from_customer_data(): void
    {
        $tenant = $this->createTenant();

        $this->withinTenant($tenant, function (Tenant $tenant): void {
            $customer = Customer::query()->create([
                'tenant_id' => $tenant->getKey(),
                'name' => 'Referente Angiplast',
                'legal_name' => 'ANGIPLAST SRL',
                'address' => 'Via Roma 1',
                'city' => 'OSTUNI',
                'postcode' => '72017',
                'province' => 'BR',
                'country' => 'IT',
                'phone' => '0831000000',
                'email' => 'info@angiplast.example',
                'default_site_same_as_customer' => true,
                'status' => 'active',
            ]);

            $site = $customer->sites()->firstOrFail();

            $this->assertTrue($customer->shouldUseCustomerDataAsDefaultSite());
            $this->assertTrue($site->auto_created_from_customer);
            $this->assertSame('ANGIPLAST SRL', $site->name);
            $this->assertSame('Referente Angiplast', $site->contact_name);
            $this->assertSame('Via Roma 1', $site->address);
            $this->assertSame('OSTUNI', $site->city);
            $this->assertSame('72017', $site->postcode);
            $this->assertSame('BR', $site->province);
            $this->assertSame('IT', $site->country);
            $this->assertSame('0831000000', $site->contact_phone);
            $this->assertSame('info@angiplast.example', $site->contact_email);
            $this->assertSame('Sede creata automaticamente dai dati cliente.', $site->notes);
        });
    }

    public function test_enabling_default_site_on_customer_without_sites_creates_site(): void
    {
        $tenant = $this->createTenant();

        $this->withinTenant($tenant, function (Tenant $tenant): void {
            $customer = Customer::query()->create([
                'tenant_id' => $tenant->getKey(),
                'name' => 'Cliente senza sede',
                'city' => 'BARI',
                'status' => 'active',
            ]);

            $this->assertSame(0, $customer->sites()->count());

            $customer->update([
                'default_site_same_as_customer' => true,
            ]);

            $this->assertSame(1, $customer->sites()->count());
            $this->assertSame('BARI', $customer->sites()->firstOrFail()->city);
        });
    }

    public function test_default_site_sync_does_not_overwrite_manual_sites(): void
    {
        $tenant = $this->createTenant();

        $this->withinTenant($tenant, function (Tenant $tenant): void {
            $customer = Customer::query()->create([
                'tenant_id' => $tenant->getKey(),
                'name' => 'Cliente manuale',
                'city' => 'BARI',
                'status' => 'active',
            ]);

            $manualSite = CustomerSite::query()->create([
                'tenant_id' => $tenant->getKey(),
                'customer_id' => $customer->getKey(),
                'name' => 'Sede manuale',
                'city' => 'LECCE',
                'status' => 'active',
            ]);

            $customer->update([
                'city' => 'OSTUNI',
                'default_site_same_as_customer' => true,
            ]);

            $manualSite->refresh();

            $this->assertFalse($manualSite->auto_created_from_customer);
            $this->assertSame('Sede manuale', $manualSite->name);
            $this->assertSame('LECCE', $manualSite->city);
            $this->assertSame(1, $customer->sites()->count());
        });
    }

    public function test_single_service_contract_form_proposes_site_dates_and_total_for_primary_service(): void
    {
        $tenant = $this->createTenant(contractServiceMode: Tenant::CONTRACT_SERVICE_MODE_SINGLE);

        [$customer, $site] = $this->withinTenant($tenant, fn (Tenant $tenant): array => $this->createCustomerSiteAndServiceType($tenant));
        $user = $this->createTenantAdmin($tenant);

        $this->actingAs($user);
        $this->activateTenant($tenant);

        try {
            Livewire::test(CreateContract::class)
                ->fillForm([
                    'customer_id' => $customer->getKey(),
                    'customer_site_id' => $site->getKey(),
                    'start_date' => '2026-01-01',
                    'end_date' => '2026-12-31',
                    'total_value' => 1200,
                ])
                ->assertFormSet([
                    'primary_service.customer_site_id' => $site->getKey(),
                    'primary_service.starts_on' => '2026-01-01',
                    'primary_service.ends_on' => '2026-12-31',
                    'primary_service.total_price' => 1200,
                ]);
        } finally {
            $this->deactivateTenant();
        }
    }

    public function test_creating_single_service_contract_with_service_data_creates_contract_service(): void
    {
        $tenant = $this->createTenant(contractServiceMode: Tenant::CONTRACT_SERVICE_MODE_SINGLE);

        [$customer, $site, $serviceType] = $this->withinTenant($tenant, fn (Tenant $tenant): array => $this->createCustomerSiteAndServiceType($tenant));
        $user = $this->createTenantAdmin($tenant);

        $this->actingAs($user);
        $this->activateTenant($tenant);

        try {
            Livewire::test(CreateContract::class)
                ->fillForm([
                    'contract_number' => '5001',
                    'status' => 'active',
                    'customer_id' => $customer->getKey(),
                    'customer_site_id' => $site->getKey(),
                    'start_date' => '2026-01-01',
                    'end_date' => '2026-12-31',
                    'total_value' => 1200,
                    'currency' => 'EUR',
                    'primary_service' => [
                        'service_type_id' => $serviceType->getKey(),
                        'customer_site_id' => $site->getKey(),
                        'description' => 'Servizio principale creato dal contratto',
                        'operational_schedule_mode' => 'recurring',
                        'operational_frequency' => 'monthly',
                        'quantity' => 12,
                        'unit_price' => 100,
                        'starts_on' => '2026-01-01',
                        'ends_on' => '2026-12-31',
                        'status' => 'active',
                    ],
                ])
                ->call('create')
                ->assertHasNoFormErrors();

            $contract = Contract::query()
                ->where('contract_number', '5001')
                ->firstOrFail();
            $service = $contract->service()->firstOrFail();

            $this->assertSame(1, $contract->services()->count());
            $this->assertSame($serviceType->getKey(), $service->service_type_id);
            $this->assertSame($site->getKey(), $service->customer_site_id);
            $this->assertSame('Servizio principale creato dal contratto', $service->description);
            $this->assertSame('monthly', $service->operational_frequency);
            $this->assertSame('1200.00', $service->total_price);
            $this->assertSame('2026-01-01', $service->starts_on->toDateString());
            $this->assertSame('2026-12-31', $service->ends_on->toDateString());
        } finally {
            $this->deactivateTenant();
        }
    }

    public function test_creating_single_service_contract_saves_custom_months_schedule(): void
    {
        $tenant = $this->createTenant(contractServiceMode: Tenant::CONTRACT_SERVICE_MODE_SINGLE);

        [$customer, $site, $serviceType] = $this->withinTenant($tenant, fn (Tenant $tenant): array => $this->createCustomerSiteAndServiceType($tenant));
        $user = $this->createTenantAdmin($tenant);

        $this->actingAs($user);
        $this->activateTenant($tenant);

        try {
            Livewire::test(CreateContract::class)
                ->fillForm([
                    'contract_number' => '5001-CUSTOM-MONTHS',
                    'status' => 'active',
                    'customer_id' => $customer->getKey(),
                    'customer_site_id' => $site->getKey(),
                    'start_date' => '2026-01-01',
                    'end_date' => '2026-12-31',
                    'currency' => 'EUR',
                    'primary_service' => [
                        'service_type_id' => $serviceType->getKey(),
                        'customer_site_id' => $site->getKey(),
                        'description' => 'Disinfestazioni mesi AZ',
                        'operational_schedule_mode' => 'custom_months',
                        'operational_frequency' => 'monthly',
                        'scheduled_months' => [2, 3, 5, 6, 7],
                        'interventions_per_year' => 5,
                        'quantity' => 5,
                        'unit_price' => 100,
                        'currency' => 'EUR',
                        'starts_on' => '2026-01-01',
                        'ends_on' => '2026-12-31',
                        'status' => 'active',
                    ],
                ])
                ->call('create')
                ->assertHasNoFormErrors();

            $service = Contract::query()
                ->where('contract_number', '5001-CUSTOM-MONTHS')
                ->firstOrFail()
                ->service()
                ->firstOrFail();

            $this->assertSame('custom_months', $service->operational_schedule_mode);
            $this->assertNull($service->operational_frequency);
            $this->assertSame([2, 3, 5, 6, 7], $service->scheduled_months);
            $this->assertSame(5, $service->interventions_per_year);
        } finally {
            $this->deactivateTenant();
        }
    }

    public function test_editing_single_service_contract_prefills_custom_months_schedule(): void
    {
        $tenant = $this->createTenant(contractServiceMode: Tenant::CONTRACT_SERVICE_MODE_SINGLE);

        [$contractId] = $this->withinTenant($tenant, function (Tenant $tenant): array {
            [$customer, $site, $serviceType] = $this->createCustomerSiteAndServiceType($tenant);

            $contract = Contract::query()->create([
                'tenant_id' => $tenant->getKey(),
                'contract_number' => '5002-CUSTOM-MONTHS',
                'customer_id' => $customer->getKey(),
                'customer_site_id' => $site->getKey(),
                'start_date' => '2026-01-01',
                'end_date' => '2026-12-31',
                'currency' => 'EUR',
                'status' => 'active',
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
                'currency' => 'EUR',
                'status' => 'active',
            ]);

            return [$contract->getKey()];
        });

        $user = $this->createTenantAdmin($tenant);

        $this->actingAs($user);
        $this->activateTenant($tenant);

        try {
            Livewire::test(EditContract::class, ['record' => $contractId])
                ->assertFormSet([
                    'primary_service.operational_schedule_mode' => 'custom_months',
                    'primary_service.scheduled_months' => [2, 3, 5, 6, 7],
                    'primary_service.interventions_per_year' => 5,
                ]);
        } finally {
            $this->deactivateTenant();
        }
    }

    public function test_editing_single_service_contract_updates_existing_service_without_creating_second_one(): void
    {
        $tenant = $this->createTenant(contractServiceMode: Tenant::CONTRACT_SERVICE_MODE_SINGLE);

        [$contractId, $newServiceTypeId] = $this->withinTenant($tenant, function (Tenant $tenant): array {
            [$customer, $site, $serviceType] = $this->createCustomerSiteAndServiceType($tenant);
            $newServiceType = ServiceType::query()->create([
                'tenant_id' => $tenant->getKey(),
                'name' => 'Disinfestazione',
                'status' => 'active',
            ]);

            $contract = Contract::query()->create([
                'tenant_id' => $tenant->getKey(),
                'contract_number' => '5002',
                'customer_id' => $customer->getKey(),
                'customer_site_id' => $site->getKey(),
                'start_date' => '2026-01-01',
                'end_date' => '2026-12-31',
                'total_value' => 1000,
                'currency' => 'EUR',
                'status' => 'active',
            ]);

            ContractService::query()->create([
                'tenant_id' => $tenant->getKey(),
                'contract_id' => $contract->getKey(),
                'service_type_id' => $serviceType->getKey(),
                'customer_site_id' => $site->getKey(),
                'description' => 'Servizio originale',
                'total_price' => 1000,
                'currency' => 'EUR',
                'status' => 'active',
            ]);

            return [$contract->getKey(), $newServiceType->getKey()];
        });

        $user = $this->createTenantAdmin($tenant);

        $this->actingAs($user);
        $this->activateTenant($tenant);

        try {
            Livewire::test(EditContract::class, ['record' => $contractId])
                ->fillForm([
                    'primary_service' => [
                        'service_type_id' => $newServiceTypeId,
                        'description' => 'Servizio aggiornato',
                        'operational_schedule_mode' => 'manual',
                        'quantity' => 1,
                        'unit_price' => 500,
                        'total_price' => 500,
                        'currency' => 'EUR',
                        'starts_on' => '2026-02-01',
                        'ends_on' => '2026-11-30',
                        'status' => 'active',
                    ],
                ])
                ->call('save')
                ->assertHasNoFormErrors();

            $contract = Contract::query()->findOrFail($contractId);
            $service = $contract->service()->firstOrFail();

            $this->assertSame(1, $contract->services()->count());
            $this->assertSame($newServiceTypeId, $service->service_type_id);
            $this->assertSame('Servizio aggiornato', $service->description);
            $this->assertSame('manual', $service->operational_schedule_mode);
            $this->assertNull($service->operational_frequency);
            $this->assertSame('500.00', $service->total_price);
        } finally {
            $this->deactivateTenant();
        }
    }

    protected function createTenant(?string $contractServiceMode = null): Tenant
    {
        $data = [
            'name' => 'Tenant Demo',
            'slug' => 'tenant-demo',
            'db_database' => $this->tenantDatabasePath,
            'status' => 'active',
        ];

        if ($contractServiceMode !== null) {
            $data['contract_service_mode'] = $contractServiceMode;
        }

        return Tenant::query()->create($data);
    }

    protected function createTenantAdmin(Tenant $tenant): User
    {
        return User::query()->create([
            'name' => 'Tenant Admin',
            'email' => 'tenant-admin-contract-service-form@example.com',
            'password' => 'password123',
            'tenant_id' => $tenant->getKey(),
            'is_superuser' => false,
        ]);
    }

    /**
     * @return array{0: Customer, 1: CustomerSite, 2: ServiceType}
     */
    protected function createCustomerSiteAndServiceType(Tenant $tenant): array
    {
        $customer = Customer::query()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Cliente Contratto',
            'status' => 'active',
        ]);

        $site = CustomerSite::query()->create([
            'tenant_id' => $tenant->getKey(),
            'customer_id' => $customer->getKey(),
            'name' => 'Sede Cliente Contratto',
            'status' => 'active',
        ]);

        $serviceType = ServiceType::query()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Derattizzazione',
            'status' => 'active',
        ]);

        return [$customer, $site, $serviceType];
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
