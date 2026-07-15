<?php

namespace Tests\Feature;

use App\Filament\Pages\Dashboard;
use App\Filament\Resources\Areas\AreaResource;
use App\Filament\Resources\BillableItems\BillableItemResource;
use App\Filament\Resources\Contracts\ContractResource;
use App\Filament\Resources\CustomerGroups\CustomerGroupResource;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\CustomerSites\CustomerSiteResource;
use App\Filament\Resources\Tenants\Pages\EditTenant;
use App\Filament\Resources\Tenants\TenantResource;
use App\Filament\Widgets\BillingSchedulesDueWidget;
use App\Filament\Widgets\ContractsExpiringWidget;
use App\Filament\Widgets\ScheduledInterventionsDueWidget;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\CurrentTenant;
use App\Support\Tenancy\TenantModules;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TenantModuleNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        app(CurrentTenant::class)->set(null);

        parent::tearDown();
    }

    public function test_tenant_with_null_enabled_modules_sees_contracts_module(): void
    {
        $tenant = $this->createTenant();
        $user = $this->createTenantAdmin($tenant);

        $this->actingAs($user);
        app(CurrentTenant::class)->set($tenant);

        $this->assertTrue($tenant->hasModuleEnabled(TenantModules::CONTRACTS));
        $this->assertTrue(ContractResource::shouldRegisterNavigation());
        $this->assertTrue(ContractResource::canAccess());
    }

    public function test_tenant_with_contracts_module_only_sees_contracts_but_not_areas(): void
    {
        $tenant = $this->createTenant([
            TenantModules::CONTRACTS,
        ]);
        $user = $this->createTenantAdmin($tenant);

        $this->actingAs($user);
        app(CurrentTenant::class)->set($tenant);

        $this->assertTrue($tenant->hasModuleEnabled(TenantModules::CONTRACTS));
        $this->assertFalse($tenant->hasModuleEnabled(TenantModules::AREAS));
        $this->assertTrue(ContractResource::shouldRegisterNavigation());
        $this->assertFalse(AreaResource::shouldRegisterNavigation());
        $this->assertFalse(AreaResource::canAccess());
        $this->assertFalse(CustomerGroupResource::shouldRegisterNavigation());
        $this->assertFalse(BillableItemResource::shouldRegisterNavigation());
    }

    public function test_customer_groups_module_respects_enabled_modules(): void
    {
        $tenant = $this->createTenant([
            TenantModules::CUSTOMERS,
        ]);
        $user = $this->createTenantAdmin($tenant);

        $this->actingAs($user);
        app(CurrentTenant::class)->set($tenant);

        $this->assertFalse(CustomerGroupResource::shouldRegisterNavigation());
        $this->assertFalse(CustomerGroupResource::canAccess());

        $tenant->update([
            'enabled_modules' => [
                TenantModules::CUSTOMERS,
                TenantModules::CUSTOMER_GROUPS,
            ],
        ]);
        app(CurrentTenant::class)->set($tenant->refresh());

        $this->assertTrue(CustomerGroupResource::shouldRegisterNavigation());
        $this->assertTrue(CustomerGroupResource::canAccess());
    }

    public function test_custom_module_order_changes_navigation_sort(): void
    {
        $tenant = $this->createTenant(
            enabledModules: null,
            moduleOrder: [
                TenantModules::CONTRACTS,
                TenantModules::CUSTOMER_SITES,
                TenantModules::CUSTOMERS,
            ],
        );
        $user = $this->createTenantAdmin($tenant);

        $this->actingAs($user);
        app(CurrentTenant::class)->set($tenant);

        $this->assertSame(10, ContractResource::getNavigationSort());
        $this->assertSame(20, CustomerSiteResource::getNavigationSort());
        $this->assertSame(30, CustomerResource::getNavigationSort());
    }

    public function test_module_order_can_sort_customer_groups(): void
    {
        $tenant = $this->createTenant(
            enabledModules: null,
            moduleOrder: [
                TenantModules::CUSTOMER_GROUPS,
                TenantModules::CUSTOMERS,
            ],
        );
        $user = $this->createTenantAdmin($tenant);

        $this->actingAs($user);
        app(CurrentTenant::class)->set($tenant);

        $this->assertSame(10, CustomerGroupResource::getNavigationSort());
        $this->assertSame(20, CustomerResource::getNavigationSort());
    }

    public function test_billable_items_module_respects_enabled_modules_and_order(): void
    {
        $tenant = $this->createTenant(
            enabledModules: [
                TenantModules::CUSTOMERS,
            ],
            moduleOrder: [
                TenantModules::BILLABLE_ITEMS,
                TenantModules::CUSTOMERS,
            ],
        );
        $user = $this->createTenantAdmin($tenant);

        $this->actingAs($user);
        app(CurrentTenant::class)->set($tenant);

        $this->assertFalse(BillableItemResource::shouldRegisterNavigation());
        $this->assertFalse(BillableItemResource::canAccess());

        $tenant->update([
            'enabled_modules' => [
                TenantModules::BILLABLE_ITEMS,
                TenantModules::CUSTOMERS,
            ],
        ]);
        app(CurrentTenant::class)->set($tenant->refresh());

        $this->assertTrue(BillableItemResource::shouldRegisterNavigation());
        $this->assertTrue(BillableItemResource::canAccess());
        $this->assertSame(10, BillableItemResource::getNavigationSort());
        $this->assertSame(20, CustomerResource::getNavigationSort());
    }

    public function test_module_missing_from_custom_order_is_sorted_after_configured_modules(): void
    {
        $tenant = $this->createTenant(
            enabledModules: null,
            moduleOrder: [
                TenantModules::CONTRACTS,
            ],
        );
        $user = $this->createTenantAdmin($tenant);

        $this->actingAs($user);
        app(CurrentTenant::class)->set($tenant);

        $this->assertSame(10, ContractResource::getNavigationSort());
        $this->assertGreaterThan(ContractResource::getNavigationSort(), AreaResource::getNavigationSort());
    }

    public function test_dashboard_uses_operational_widgets_without_filament_standard_widgets(): void
    {
        $widgets = app(Dashboard::class)->getWidgets();

        $this->assertContains(ContractsExpiringWidget::class, $widgets);
        $this->assertContains(BillingSchedulesDueWidget::class, $widgets);
        $this->assertContains(ScheduledInterventionsDueWidget::class, $widgets);
        $this->assertNotContains(AccountWidget::class, $widgets);
        $this->assertNotContains(FilamentInfoWidget::class, $widgets);
    }

    public function test_tenant_form_saves_module_order_as_simple_json_array(): void
    {
        $tenant = $this->createTenant();
        $user = User::query()->create([
            'name' => 'Super Admin',
            'email' => 'super-admin-module-order@example.com',
            'password' => 'password123',
            'is_superuser' => true,
        ]);

        $this->actingAs($user);

        Livewire::test(EditTenant::class, ['record' => $tenant->getKey()])
            ->fillForm([
                'module_order' => [
                    ['module' => TenantModules::DASHBOARD],
                    ['module' => TenantModules::CONTRACTS],
                    ['module' => TenantModules::CUSTOMERS],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $tenant->refresh();

        $this->assertSame([
            TenantModules::DASHBOARD,
            TenantModules::CONTRACTS,
            TenantModules::CUSTOMERS,
        ], $tenant->module_order);
        $this->assertIsString($tenant->module_order[0]);
    }

    public function test_superuser_without_current_tenant_does_not_error(): void
    {
        $user = User::query()->create([
            'name' => 'Super Admin',
            'email' => 'super-admin-modules@example.com',
            'password' => 'password123',
            'is_superuser' => true,
        ]);

        $this->actingAs($user);
        app(CurrentTenant::class)->set(null);

        $this->assertFalse(ContractResource::shouldRegisterNavigation());
        $this->assertTrue(TenantResource::shouldRegisterNavigation());
        $this->assertTrue(TenantResource::canAccess());
    }

    protected function createTenant(?array $enabledModules = null, ?array $moduleOrder = null): Tenant
    {
        return Tenant::query()->create([
            'name' => 'Tenant Demo',
            'slug' => 'tenant-demo',
            'db_database' => 'tenant_demo',
            'enabled_modules' => $enabledModules,
            'module_order' => $moduleOrder,
            'status' => 'active',
        ]);
    }

    protected function createTenantAdmin(Tenant $tenant): User
    {
        return User::query()->create([
            'name' => 'Tenant Admin',
            'email' => 'tenant-admin-modules@example.com',
            'password' => 'password123',
            'tenant_id' => $tenant->getKey(),
            'is_superuser' => false,
        ]);
    }
}
