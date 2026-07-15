<?php

namespace App\Console\Commands;

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
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SeedAzDemoData extends Command
{
    protected $signature = 'az:seed-demo-data';

    protected $description = 'Configura il tenant AZ Disinfestazioni e carica i dati iniziali ANGIPLAST.';

    private const TENANT_SLUG = 'azdisinfestazioni';

    private const SERVICE_TYPES = [
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

    private const BILLABLE_ITEMS = [
        [
            'name' => 'Contenitori esca',
            'code' => 'CONTENITORI_ESCA',
            'legacy_names' => ['Contenitore esca'],
        ],
        [
            'name' => 'Contenitori per monitoraggio',
            'code' => 'CONTENITORI_MONITORAGGIO',
            'legacy_names' => ['Contenitore per monitoraggio'],
        ],
        [
            'name' => 'Lampada UV',
            'code' => 'LAMPADA_UV',
            'legacy_names' => [],
        ],
        [
            'name' => 'Cartelli Posizionamento',
            'code' => 'CARTELLI_POSIZIONAMENTO',
            'legacy_names' => ['Cartello posizionamento'],
        ],
        [
            'name' => 'Paletti di fissaggio',
            'code' => 'PALETTI_FISSAGGIO',
            'legacy_names' => ['Paletto di fissaggio'],
        ],
        [
            'name' => 'Trappola collante',
            'code' => 'TRAPPOLA_COLLANTE',
            'legacy_names' => [],
        ],
        [
            'name' => 'Esca',
            'code' => 'ESCA',
            'legacy_names' => [],
        ],
        [
            'name' => 'Consumabile generico',
            'code' => 'CONSUMABILE_GENERICO',
            'legacy_names' => [],
        ],
    ];

    private const CONTRACTS = [
        [
            'contract_number' => '2569',
            'service_type' => 'Derattizzazione',
            'amount' => '1198.00',
            'start_date' => '2025-12-16',
            'end_date' => '2026-12-31',
        ],
        [
            'contract_number' => '2570',
            'service_type' => 'Monit. Insetti alati',
            'amount' => '1310.40',
            'start_date' => '2025-12-16',
            'end_date' => '2026-12-31',
        ],
        [
            'contract_number' => '2571',
            'service_type' => 'Monit. Insetti Strisc. e Roditori',
            'amount' => '1102.40',
            'start_date' => '2025-12-16',
            'end_date' => '2026-12-31',
        ],
        [
            'contract_number' => '2572',
            'service_type' => 'Disinfezione',
            'amount' => '343.20',
            'start_date' => '2025-12-16',
            'end_date' => '2026-12-31',
        ],
        [
            'contract_number' => '2573',
            'service_type' => 'Disinfestazione Alati',
            'amount' => '644.80',
            'start_date' => '2025-12-16',
            'end_date' => '2026-12-31',
        ],
    ];

    public function handle(TenantConnectionManager $tenantConnectionManager, CurrentTenant $currentTenant): int
    {
        $tenant = Tenant::query()
            ->where('slug', self::TENANT_SLUG)
            ->first();

        if (! $tenant) {
            $this->error('Tenant AZ non trovato con slug ['.self::TENANT_SLUG.'].');

            return self::FAILURE;
        }

        $this->configureTenant($tenant);

        $previousTenant = $currentTenant->get();

        $tenantConnectionManager->activate($tenant);
        $currentTenant->set($tenant);

        try {
            $serviceTypes = $this->seedServiceTypes($tenant);
            $this->seedBillableItems($tenant);
            [$customer, $site] = $this->seedCustomerAndSite($tenant);

            $this->seedContracts($tenant, $customer, $site, $serviceTypes);
        } finally {
            $currentTenant->set($previousTenant);
            DB::purge($this->tenantConnectionName());

            if ($previousTenant) {
                $tenantConnectionManager->activate($previousTenant);
            }
        }

        $this->newLine();
        $this->info('Seed dati AZ completato.');

        return self::SUCCESS;
    }

    private function configureTenant(Tenant $tenant): void
    {
        $modules = $this->azModules();

        $tenant->fill([
            'contract_service_mode' => Tenant::CONTRACT_SERVICE_MODE_SINGLE,
            'enabled_modules' => $modules,
            'module_order' => $modules,
        ])->save();

        $this->info('Tenant AZ configurato: single_service.');
        $this->line('Moduli attivi: '.implode(', ', $modules));
    }

    /**
     * @return array<string, ServiceType>
     */
    private function seedServiceTypes(Tenant $tenant): array
    {
        $records = [];

        foreach (self::SERVICE_TYPES as $name) {
            /** @var ServiceType $serviceType */
            $serviceType = ServiceType::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->getKey(),
                    'name' => $name,
                ],
                [
                    'status' => 'active',
                ],
            );

            $records[$name] = $serviceType;
            $this->report($serviceType, "tipo servizio {$name}");
        }

        return $records;
    }

    private function seedBillableItems(Tenant $tenant): void
    {
        foreach (self::BILLABLE_ITEMS as $itemData) {
            /** @var array{name: string, code: string, legacy_names: array<int, string>} $itemData */
            $billableItem = $this->findExistingBillableItem($tenant, $itemData['name'], $itemData['legacy_names'])
                ?? new BillableItem([
                    'tenant_id' => $tenant->getKey(),
                ]);

            $billableItem->fill($this->tenantModelValues(new BillableItem, 'billable_items', [
                'tenant_id' => $tenant->getKey(),
                'name' => $itemData['name'],
                'code' => $itemData['code'],
                'default_unit_price' => null,
                'vat_rate' => null,
                'status' => 'active',
            ]));

            $billableItem->save();

            $this->report($billableItem, "articolo fatturabile {$itemData['name']}");
        }
    }

    /**
     * @param  array<int, string>  $legacyNames
     */
    private function findExistingBillableItem(Tenant $tenant, string $name, array $legacyNames): ?BillableItem
    {
        return BillableItem::query()
            ->where('tenant_id', $tenant->getKey())
            ->whereIn('name', array_values(array_unique([$name, ...$legacyNames])))
            ->orderByRaw('case when name = ? then 0 else 1 end', [$name])
            ->first();
    }

    /**
     * @return array{0: Customer, 1: CustomerSite}
     */
    private function seedCustomerAndSite(Tenant $tenant): array
    {
        $customerLookup = $this->tenantHasColumn('customers', 'legacy_customer_code')
            ? [
                'tenant_id' => $tenant->getKey(),
                'legacy_customer_code' => '1858',
            ]
            : [
                'tenant_id' => $tenant->getKey(),
                'name' => 'ANGIPLAST SRL',
            ];

        /** @var Customer $customer */
        $customer = Customer::query()->updateOrCreate(
            $customerLookup,
            $this->tenantModelValues(new Customer, 'customers', [
                'legacy_customer_code' => '1858',
                'name' => 'ANGIPLAST SRL',
                'legal_name' => 'ANGIPLAST SRL',
                'city' => 'OSTUNI',
                'country' => 'IT',
                'notes' => 'Codice cliente storico AZ: 1858',
                'status' => 'active',
            ]),
        );

        if ($this->tenantHasColumn('customers', 'customer_group_id')) {
            DB::connection($this->tenantConnectionName())
                ->table('customers')
                ->where('id', $customer->getKey())
                ->update(['customer_group_id' => null]);
        }

        $this->report($customer, 'cliente ANGIPLAST SRL');

        $siteLookup = $this->tenantHasColumn('customer_sites', 'site_code')
            ? [
                'tenant_id' => $tenant->getKey(),
                'customer_id' => $customer->getKey(),
                'site_code' => '1858',
            ]
            : [
                'tenant_id' => $tenant->getKey(),
                'customer_id' => $customer->getKey(),
                'name' => 'OSTUNI',
            ];

        /** @var CustomerSite $site */
        $site = CustomerSite::query()->updateOrCreate(
            $siteLookup,
            $this->tenantModelValues(new CustomerSite, 'customer_sites', [
                'name' => 'OSTUNI',
                'city' => 'OSTUNI',
                'country' => 'IT',
                'site_code' => '1858',
                'notes' => 'Sede importata da elenco contratti AZ.',
                'status' => 'active',
            ]),
        );

        $this->report($site, 'sede ANGIPLAST OSTUNI');

        return [$customer, $site];
    }

    /**
     * @param  array<string, ServiceType>  $serviceTypes
     */
    private function seedContracts(Tenant $tenant, Customer $customer, CustomerSite $site, array $serviceTypes): void
    {
        foreach (self::CONTRACTS as $contractData) {
            /** @var ServiceType $serviceType */
            $serviceType = $serviceTypes[$contractData['service_type']];

            /** @var Contract $contract */
            $contract = Contract::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->getKey(),
                    'contract_number' => $contractData['contract_number'],
                ],
                $this->tenantModelValues(new Contract, 'contracts', [
                    'customer_id' => $customer->getKey(),
                    'customer_site_id' => $site->getKey(),
                    'status' => 'active',
                    'start_date' => $contractData['start_date'],
                    'end_date' => $contractData['end_date'],
                    'renewal' => 'Rinnovo tacito',
                    'tacit_renewal' => true,
                    'renewal_price_increase_percentage' => '4.00',
                    'renewal_notice_days' => 30,
                    'payment_terms' => null,
                    'billing_frequency' => null,
                    'billing_installments_count' => null,
                    'total_value' => $contractData['amount'],
                    'currency' => 'EUR',
                    'notes' => 'Importato da elenco AZ - CONTRATTI IN CORSO.',
                ]),
            );

            $this->report($contract, "contratto {$contractData['contract_number']}");
            $this->seedContractService($tenant, $contract, $serviceType, $site, $contractData);
        }
    }

    /**
     * @param  array{contract_number: string, service_type: string, amount: string, start_date: string, end_date: string}  $contractData
     */
    private function seedContractService(
        Tenant $tenant,
        Contract $contract,
        ServiceType $serviceType,
        CustomerSite $site,
        array $contractData,
    ): void {
        $contractService = ContractService::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('contract_id', $contract->getKey())
            ->first();

        $created = ! $contractService;

        $contractService ??= new ContractService([
            'tenant_id' => $tenant->getKey(),
            'contract_id' => $contract->getKey(),
        ]);

        $contractService->fill($this->tenantModelValues(new ContractService, 'contract_services', [
            'tenant_id' => $tenant->getKey(),
            'contract_id' => $contract->getKey(),
            'service_type_id' => $serviceType->getKey(),
            'customer_site_id' => $site->getKey(),
            'description' => $contractData['service_type'],
            'billing_frequency' => null,
            'quantity' => '1.00',
            'unit_price' => $contractData['amount'],
            'total_price' => $contractData['amount'],
            'currency' => 'EUR',
            'starts_on' => $contractData['start_date'],
            'ends_on' => $contractData['end_date'],
            'status' => 'active',
            'notes' => 'Importato da elenco AZ.',
        ]));

        $contractService->save();

        $this->line(($created ? 'Creato' : 'Aggiornato').": servizio contratto {$contractData['contract_number']}");
    }

    /**
     * @return array<int, string>
     */
    private function azModules(): array
    {
        $requestedModules = [
            TenantModules::DASHBOARD,
            TenantModules::CONTRACTS,
            TenantModules::CUSTOMER_SITES,
            TenantModules::CUSTOMERS,
            TenantModules::SERVICE_TYPES,
            TenantModules::CUSTOMER_GROUPS,
            TenantModules::BILLABLE_ITEMS,
        ];

        return collect($requestedModules)
            ->filter(fn (string $module): bool => array_key_exists($module, TenantModules::options()))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function tenantModelValues(Model $model, string $table, array $values): array
    {
        $fillable = array_flip($model->getFillable());

        return collect($values)
            ->filter(fn (mixed $value, string $column): bool => array_key_exists($column, $fillable))
            ->filter(fn (mixed $value, string $column): bool => $this->tenantHasColumn($table, $column))
            ->all();
    }

    private function tenantHasColumn(string $table, string $column): bool
    {
        return Schema::connection($this->tenantConnectionName())->hasColumn($table, $column);
    }

    private function tenantConnectionName(): string
    {
        return config('tenancy.database_connection');
    }

    private function report(Model $model, string $label): void
    {
        $this->line(($model->wasRecentlyCreated ? 'Creato' : 'Aggiornato').": {$label}");
    }
}
