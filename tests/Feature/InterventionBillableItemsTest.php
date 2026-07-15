<?php

namespace Tests\Feature;

use App\Filament\Resources\Contracts\ContractResource;
use App\Filament\Resources\Contracts\RelationManagers\InterventionBillableItemsRelationManager;
use App\Models\BillableItem;
use App\Models\Contract;
use App\Models\ContractBillingSchedule;
use App\Models\Customer;
use App\Models\CustomerBillableItemPrice;
use App\Models\CustomerSite;
use App\Models\InterventionBillableItem;
use App\Models\ScheduledIntervention;
use App\Models\ServiceType;
use App\Models\Tenant;
use App\Support\Billing\InterventionBillableItemService;
use App\Support\Tenancy\CurrentTenant;
use App\Support\Tenancy\TenantConnectionManager;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class InterventionBillableItemsTest extends TestCase
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

    public function test_intervention_extra_sets_contract_from_intervention_and_relations_work(): void
    {
        $tenant = $this->createTenant();

        $this->withinTenant($tenant, function (Tenant $tenant): void {
            [$contract, , , , $intervention] = $this->createInterventionFixture($tenant, 'CTR-EXTRA-REL');
            $item = $this->createBillableItem($tenant, 'Contenitore esca', 10);

            $extra = InterventionBillableItem::query()->create([
                'tenant_id' => $tenant->getKey(),
                'scheduled_intervention_id' => $intervention->getKey(),
                'billable_item_id' => $item->getKey(),
                'description' => 'Sostituito contenitore esca',
                'quantity' => 2,
                'unit_price' => 10,
                'status' => 'pending',
            ]);

            $this->assertSame($contract->getKey(), $extra->refresh()->contract_id);
            $this->assertSame('20.00', $extra->total_price);
            $this->assertSame(1, $contract->interventionBillableItems()->count());
            $this->assertSame(1, $intervention->interventionBillableItems()->count());
            $this->assertSame(1, $item->interventionBillableItems()->count());
            $this->assertSame($intervention->getKey(), $extra->scheduledIntervention?->getKey());
            $this->assertSame($item->getKey(), $extra->billableItem?->getKey());
        });
    }

    public function test_pricing_service_proposes_customer_price_description_and_total(): void
    {
        $tenant = $this->createTenant();

        $this->withinTenant($tenant, function (Tenant $tenant): void {
            [, $customer, , , $intervention] = $this->createInterventionFixture($tenant, 'CTR-EXTRA-PRICE');
            $item = $this->createBillableItem($tenant, 'Trappola collante', 20);

            CustomerBillableItemPrice::query()->create([
                'tenant_id' => $tenant->getKey(),
                'customer_id' => $customer->getKey(),
                'billable_item_id' => $item->getKey(),
                'custom_unit_price' => 7.25,
            ]);

            $state = app(InterventionBillableItemService::class)
                ->suggestedStateForIntervention($intervention, $item, 3);

            $this->assertSame('Trappola collante', $state['description']);
            $this->assertSame(7.25, $state['unit_price']);
            $this->assertSame(21.75, $state['total_price']);
        });
    }

    public function test_contract_registers_intervention_extras_relation_manager_and_exposes_pending_totals(): void
    {
        $this->assertContains(
            InterventionBillableItemsRelationManager::class,
            ContractResource::getRelations(),
        );

        $tenant = $this->createTenant();

        $this->withinTenant($tenant, function (Tenant $tenant): void {
            [$contract, , , , $intervention] = $this->createInterventionFixture($tenant, 'CTR-EXTRA-VIEW');

            InterventionBillableItem::query()->create([
                'tenant_id' => $tenant->getKey(),
                'scheduled_intervention_id' => $intervention->getKey(),
                'contract_id' => $contract->getKey(),
                'description' => 'Extra pending',
                'quantity' => 1,
                'unit_price' => 15,
                'status' => 'pending',
            ]);

            InterventionBillableItem::query()->create([
                'tenant_id' => $tenant->getKey(),
                'scheduled_intervention_id' => $intervention->getKey(),
                'contract_id' => $contract->getKey(),
                'description' => 'Extra annullato',
                'quantity' => 1,
                'unit_price' => 20,
                'status' => 'cancelled',
            ]);

            $this->assertSame(2, $contract->interventionBillableItems()->count());
            $this->assertSame(1, $contract->interventionBillableItems()->where('status', 'pending')->count());
            $this->assertSame('15', (string) $contract->interventionBillableItems()->where('status', 'pending')->sum('total_price'));
        });
    }

    public function test_pending_extras_are_linked_to_billing_schedule_once(): void
    {
        $tenant = $this->createTenant();

        $this->withinTenant($tenant, function (Tenant $tenant): void {
            [$contract, , , , $intervention] = $this->createInterventionFixture($tenant, 'CTR-EXTRA-BILLING');
            $schedule = $this->createBillingSchedule($tenant, $contract, 'Scadenza extra');
            $previousSchedule = $this->createBillingSchedule($tenant, $contract, 'Scadenza precedente');

            $pendingA = $this->createExtra($tenant, $contract, $intervention, 'Contenitore sostituito', 10, 'pending');
            $pendingB = $this->createExtra($tenant, $contract, $intervention, 'Cartelli aggiunti', 6, 'pending');
            $cancelled = $this->createExtra($tenant, $contract, $intervention, 'Extra annullato', 8, 'cancelled');
            $alreadyAdded = $this->createExtra($tenant, $contract, $intervention, 'Extra gia collegato', 12, 'added_to_invoice', $previousSchedule);

            $result = app(InterventionBillableItemService::class)
                ->addPendingToBillingSchedule($schedule);

            $this->assertSame(2, $result['count']);
            $this->assertSame(16.0, $result['total']);

            $this->assertSame('added_to_invoice', $pendingA->refresh()->status);
            $this->assertSame($schedule->getKey(), $pendingA->contract_billing_schedule_id);
            $this->assertSame('added_to_invoice', $pendingB->refresh()->status);
            $this->assertSame($schedule->getKey(), $pendingB->contract_billing_schedule_id);
            $this->assertSame('cancelled', $cancelled->refresh()->status);
            $this->assertNull($cancelled->contract_billing_schedule_id);
            $this->assertSame('added_to_invoice', $alreadyAdded->refresh()->status);
            $this->assertSame($previousSchedule->getKey(), $alreadyAdded->contract_billing_schedule_id);
            $this->assertSame(2, $schedule->interventionBillableItems()->count());

            $secondResult = app(InterventionBillableItemService::class)
                ->addPendingToBillingSchedule($schedule);

            $this->assertSame(['count' => 0, 'total' => 0.0], $secondResult);
            $this->assertSame(2, $schedule->interventionBillableItems()->count());
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
     * @return array{0: Contract, 1: Customer, 2: CustomerSite, 3: ServiceType, 4: ScheduledIntervention}
     */
    protected function createInterventionFixture(Tenant $tenant, string $contractNumber): array
    {
        $customer = Customer::query()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Cliente extra '.$contractNumber,
            'status' => 'active',
        ]);

        $site = CustomerSite::query()->create([
            'tenant_id' => $tenant->getKey(),
            'customer_id' => $customer->getKey(),
            'name' => 'Sede extra '.$contractNumber,
            'status' => 'active',
        ]);

        $serviceType = ServiceType::query()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Derattizzazione '.$contractNumber,
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

        $intervention = ScheduledIntervention::query()->create([
            'tenant_id' => $tenant->getKey(),
            'contract_id' => $contract->getKey(),
            'customer_site_id' => $site->getKey(),
            'service_type_id' => $serviceType->getKey(),
            'planned_date' => '2026-07-15',
            'status' => 'planned',
        ]);

        return [$contract, $customer, $site, $serviceType, $intervention];
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

    protected function createBillingSchedule(Tenant $tenant, Contract $contract, string $description): ContractBillingSchedule
    {
        return ContractBillingSchedule::query()->create([
            'tenant_id' => $tenant->getKey(),
            'contract_id' => $contract->getKey(),
            'description' => $description,
            'due_date' => '2026-07-31',
            'amount' => 100,
            'currency' => 'EUR',
            'status' => 'planned',
        ]);
    }

    protected function createExtra(
        Tenant $tenant,
        Contract $contract,
        ScheduledIntervention $intervention,
        string $description,
        float $totalPrice,
        string $status,
        ?ContractBillingSchedule $schedule = null,
    ): InterventionBillableItem {
        return InterventionBillableItem::query()->create([
            'tenant_id' => $tenant->getKey(),
            'scheduled_intervention_id' => $intervention->getKey(),
            'contract_id' => $contract->getKey(),
            'contract_billing_schedule_id' => $schedule?->getKey(),
            'description' => $description,
            'quantity' => 1,
            'unit_price' => $totalPrice,
            'status' => $status,
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
