<?php

namespace Tests\Feature;

use App\Filament\Resources\Areas\AreaResource;
use App\Filament\Resources\Contracts\ContractResource;
use App\Filament\Resources\Tenants\TenantResource;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\CurrentTenant;
use App\Support\Tenancy\TenantModules;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    protected function createTenant(?array $enabledModules = null): Tenant
    {
        return Tenant::query()->create([
            'name' => 'Tenant Demo',
            'slug' => 'tenant-demo',
            'db_database' => 'tenant_demo',
            'enabled_modules' => $enabledModules,
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
