<?php

namespace Tests\Feature;

use App\Models\BillableItem;
use App\Models\Contract;
use App\Models\ContractService;
use App\Models\Customer;
use App\Models\CustomerSite;
use App\Models\ServiceType;
use App\Models\Tenant;
use App\Support\Tenancy\CurrentTenant;
use App\Support\Tenancy\TenantConnectionManager;
use App\Support\Tenancy\TenantModules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class AzSeedDemoDataCommandTest extends TestCase
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
        DB::purge('tenant');

        if (isset($this->tenantDatabasePath) && is_file($this->tenantDatabasePath)) {
            @unlink($this->tenantDatabasePath);
        }

        parent::tearDown();
    }

    public function test_command_seeds_az_demo_data_and_is_idempotent(): void
    {
        $tenant = $this->createAzTenant();

        $this->artisan('az:seed-demo-data')
            ->expectsOutputToContain('Tenant AZ configurato: single_service.')
            ->expectsOutputToContain('Seed dati AZ completato.')
            ->assertExitCode(0);

        $this->artisan('az:seed-demo-data')
            ->assertExitCode(0);

        $tenant->refresh();

        $expectedModules = [
            TenantModules::DASHBOARD,
            TenantModules::CONTRACTS,
            TenantModules::CUSTOMER_SITES,
            TenantModules::CUSTOMERS,
            TenantModules::SERVICE_TYPES,
            TenantModules::CUSTOMER_GROUPS,
            TenantModules::BILLABLE_ITEMS,
        ];

        $this->assertSame(Tenant::CONTRACT_SERVICE_MODE_SINGLE, $tenant->contract_service_mode);
        $this->assertSame($expectedModules, $tenant->enabled_modules);
        $this->assertSame($expectedModules, $tenant->module_order);

        $this->withinTenant($tenant, function (Tenant $tenant): void {
            $expectedServiceTypes = [
                'Derattizzazione',
                'Disinfestazione Alati',
                'Disinfestazione Striscianti',
                'Disinfezione',
                'Contenitori esca',
                'Monit. Insetti striscianti',
                'Monit. Insetti alati',
                'Servizio Multiplo',
                'Oidi',
                'Monit. Insetti Strisc. e Roditori',
                'Derattizzazione - Monit. Roditori',
                'Lampada UV',
                'Cartelli Posizionamento',
                'Paletti di fissaggio',
                'Contenitori per monitoraggio',
                'Servizio Antilarvale',
                'Servizio contro Formiche',
                'Servizio contro Scarafaggi',
                'Servizio contro Pulci e Zecche',
                'Fumigazione',
            ];

            $this->assertSame(20, ServiceType::query()->count());
            $this->assertEqualsCanonicalizing(
                $expectedServiceTypes,
                ServiceType::query()->pluck('name')->all(),
            );

            $expectedBillableItems = [
                'Contenitori esca',
                'Contenitori per monitoraggio',
                'Lampada UV',
                'Cartelli Posizionamento',
                'Paletti di fissaggio',
                'Trappola collante',
                'Esca',
                'Consumabile generico',
            ];

            $this->assertSame(8, BillableItem::query()->count());
            $this->assertEqualsCanonicalizing(
                $expectedBillableItems,
                BillableItem::query()->pluck('name')->all(),
            );
            $this->assertTrue(BillableItem::query()
                ->whereNull('default_unit_price')
                ->whereNull('vat_rate')
                ->count() === 8);
            $this->assertSame([
                'CARTELLI_POSIZIONAMENTO',
                'CONSUMABILE_GENERICO',
                'CONTENITORI_ESCA',
                'CONTENITORI_MONITORAGGIO',
                'ESCA',
                'LAMPADA_UV',
                'PALETTI_FISSAGGIO',
                'TRAPPOLA_COLLANTE',
            ], BillableItem::query()->orderBy('code')->pluck('code')->all());
            $this->assertTrue(BillableItem::query()
                ->whereIn('name', [
                    'Contenitori esca',
                    'Lampada UV',
                    'Cartelli Posizionamento',
                    'Paletti di fissaggio',
                    'Contenitori per monitoraggio',
                ])
                ->where('status', 'active')
                ->count() === 5);

            $customer = Customer::query()
                ->where('legacy_customer_code', '1858')
                ->firstOrFail();

            $this->assertSame('ANGIPLAST SRL', $customer->name);
            $this->assertSame('ANGIPLAST SRL', $customer->legal_name);
            $this->assertSame('OSTUNI', $customer->city);
            $this->assertSame('IT', $customer->country);
            $this->assertNull($customer->customer_group_id);

            $site = CustomerSite::query()
                ->where('customer_id', $customer->getKey())
                ->where('site_code', '1858')
                ->firstOrFail();

            $this->assertSame('OSTUNI', $site->name);
            $this->assertSame('OSTUNI', $site->city);

            $this->assertSame(1, Customer::query()->count());
            $this->assertSame(1, CustomerSite::query()->count());
            $this->assertSame(5, Contract::query()->count());
            $this->assertSame(5, ContractService::query()->count());

            $this->assertSame(
                ['2569', '2570', '2571', '2572', '2573'],
                Contract::query()->orderBy('contract_number')->pluck('contract_number')->all(),
            );

            $contract = Contract::query()
                ->where('contract_number', '2570')
                ->firstOrFail();

            $this->assertSame($tenant->getKey(), $contract->tenant_id);
            $this->assertSame($customer->getKey(), $contract->customer_id);
            $this->assertSame($site->getKey(), $contract->customer_site_id);
            $this->assertSame('active', $contract->status);
            $this->assertTrue($contract->tacit_renewal);
            $this->assertSame('Rinnovo tacito', $contract->renewal);
            $this->assertSame('4.00', $contract->renewal_price_increase_percentage);
            $this->assertSame(30, $contract->renewal_notice_days);
            $this->assertNull($contract->payment_terms);
            $this->assertNull($contract->billing_frequency);
            $this->assertNull($contract->billing_installments_count);
            $this->assertEqualsWithDelta(1310.40, (float) $contract->total_value, 0.01);

            $service = $contract->service()->firstOrFail();

            $this->assertSame('Monit. Insetti alati', $service->description);
            $this->assertSame($site->getKey(), $service->customer_site_id);
            $this->assertEqualsWithDelta(1.00, (float) $service->quantity, 0.01);
            $this->assertEqualsWithDelta(1310.40, (float) $service->unit_price, 0.01);
            $this->assertEqualsWithDelta(1310.40, (float) $service->total_price, 0.01);
            $this->assertNull($service->billing_frequency);

            $this->assertSame(
                [1, 1, 1, 1, 1],
                Contract::query()->withCount('services')->orderBy('contract_number')->pluck('services_count')->all(),
            );
        });
    }

    public function test_command_fails_when_az_tenant_is_missing(): void
    {
        $this->artisan('az:seed-demo-data')
            ->expectsOutputToContain('Tenant AZ non trovato con slug [azdisinfestazioni].')
            ->assertExitCode(1);
    }

    protected function createAzTenant(): Tenant
    {
        return Tenant::query()->create([
            'name' => 'AZ Disinfestazioni',
            'slug' => 'azdisinfestazioni',
            'db_database' => $this->tenantDatabasePath,
            'status' => 'active',
        ]);
    }

    protected function withinTenant(Tenant $tenant, callable $callback): mixed
    {
        app(TenantConnectionManager::class)->activate($tenant);
        app(CurrentTenant::class)->set($tenant);

        try {
            return $callback($tenant);
        } finally {
            app(CurrentTenant::class)->set(null);
            DB::purge(config('tenancy.database_connection'));
        }
    }
}
