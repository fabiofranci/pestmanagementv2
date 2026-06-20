<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class TenantMigrationsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected array $tenantDatabasePaths = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureTenantSqliteConnection();
    }

    protected function tearDown(): void
    {
        DB::purge('tenant');

        foreach (array_unique($this->tenantDatabasePaths) as $databasePath) {
            if (is_file($databasePath)) {
                @unlink($databasePath);
            }
        }

        parent::tearDown();
    }

    public function test_it_migrates_all_active_tenants(): void
    {
        $activeDatabase = $this->createTenantDatabasePath();
        $inactiveDatabase = $this->createTenantDatabasePath();

        $this->createTenant('Active Tenant', 'active-tenant', $activeDatabase);
        $this->createTenant('Inactive Tenant', 'inactive-tenant', $inactiveDatabase, 'inactive');

        $this->artisan('tenants:migrate')
            ->expectsOutputToContain('Tenant: Active Tenant (active-tenant)')
            ->expectsOutputToContain("Database: {$activeDatabase}")
            ->expectsOutputToContain('Esito: completato')
            ->assertExitCode(0);

        $this->assertTrue($this->tenantHasTable($activeDatabase, 'customers'));
        $this->assertFalse($this->tenantHasTable($inactiveDatabase, 'customers'));
    }

    public function test_it_migrates_a_single_tenant_by_slug(): void
    {
        $targetDatabase = $this->createTenantDatabasePath();
        $otherDatabase = $this->createTenantDatabasePath();

        $this->createTenant('Target Tenant', 'target-tenant', $targetDatabase);
        $this->createTenant('Other Tenant', 'other-tenant', $otherDatabase);

        $this->artisan('tenants:migrate', ['--tenant' => 'target-tenant'])
            ->expectsOutputToContain('Tenant: Target Tenant (target-tenant)')
            ->doesntExpectOutputToContain('Tenant: Other Tenant (other-tenant)')
            ->assertExitCode(0);

        $this->assertTrue($this->tenantHasTable($targetDatabase, 'customers'));
        $this->assertFalse($this->tenantHasTable($otherDatabase, 'customers'));
    }

    public function test_fresh_is_blocked_outside_local_and_testing(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('tenants:migrate', ['--fresh' => true])
            ->expectsOutputToContain('L opzione --fresh e consentita solo negli ambienti local/testing.')
            ->assertExitCode(1);
    }

    public function test_continue_on_error_migrates_remaining_tenants(): void
    {
        $validDatabase = $this->createTenantDatabasePath();
        $invalidDatabase = sys_get_temp_dir().'/missing-tenant-dir-'.uniqid().'/tenant.sqlite';

        $this->createTenant('A Broken Tenant', 'broken-tenant', $invalidDatabase);
        $this->createTenant('B Valid Tenant', 'valid-tenant', $validDatabase);

        $this->artisan('tenants:migrate', ['--continue-on-error' => true])
            ->expectsOutputToContain('Tenant: A Broken Tenant (broken-tenant)')
            ->expectsOutputToContain('Esito: errore')
            ->expectsOutputToContain('Tenant: B Valid Tenant (valid-tenant)')
            ->expectsOutputToContain('Esito: completato')
            ->assertExitCode(1);

        $this->assertTrue($this->tenantHasTable($validDatabase, 'customers'));
    }

    protected function configureTenantSqliteConnection(): void
    {
        config([
            'database.connections.tenant' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);

        DB::purge('tenant');
    }

    protected function createTenantDatabasePath(): string
    {
        $databasePath = tempnam(sys_get_temp_dir(), 'tenant-db-');

        if ($databasePath === false) {
            throw new RuntimeException('Impossibile creare il database temporaneo tenant per il test.');
        }

        $this->tenantDatabasePaths[] = $databasePath;

        return $databasePath;
    }

    protected function createTenant(string $name, string $slug, string $databasePath, string $status = 'active'): Tenant
    {
        return Tenant::query()->create([
            'name' => $name,
            'slug' => $slug,
            'db_database' => $databasePath,
            'status' => $status,
        ]);
    }

    protected function tenantHasTable(string $databasePath, string $table): bool
    {
        config(['database.connections.tenant.database' => $databasePath]);

        DB::purge('tenant');

        try {
            return Schema::connection('tenant')->hasTable($table);
        } finally {
            DB::purge('tenant');
            $this->configureTenantSqliteConnection();
        }
    }
}
