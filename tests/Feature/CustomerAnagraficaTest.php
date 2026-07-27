<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Tenant;
use App\Support\Tenancy\CurrentTenant;
use App\Support\Tenancy\TenantConnectionManager;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class CustomerAnagraficaTest extends TestCase
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

    public function test_customer_anagrafica_keeps_az_legacy_code_and_new_fields(): void
    {
        $tenant = $this->createTenant();

        $this->withinTenant($tenant, function (Tenant $tenant): void {
            foreach ([
                'legacy_customer_code',
                'vat_number',
                'fiscal_code',
                'secondary_phone',
                'mobile',
                'pec',
                'sdi_code',
            ] as $column) {
                $this->assertTrue(Schema::connection('tenant')->hasColumn('customers', $column), "Missing customers.{$column}");
            }

            $customer = Customer::query()->create([
                'tenant_id' => $tenant->getKey(),
                'legacy_customer_code' => 'AZ-001',
                'name' => 'Mario Rossi',
                'legal_name' => 'Rossi Disinfestazioni Srl',
                'vat_number' => '01234567890',
                'fiscal_code' => 'RSSMRA80A01H501U',
                'phone' => '011000000',
                'secondary_phone' => '011000001',
                'mobile' => '3330000000',
                'email' => 'cliente@example.com',
                'pec' => 'cliente@pec.example.com',
                'sdi_code' => 'ABC1234',
                'address' => 'Via Roma 1',
                'city' => 'Torino',
                'postcode' => '10100',
                'province' => 'TO',
                'status' => 'active',
            ])->refresh();

            $this->assertSame('AZ-001', $customer->legacy_customer_code);
            $this->assertSame('01234567890', $customer->vat_number);
            $this->assertSame('RSSMRA80A01H501U', $customer->fiscal_code);
            $this->assertSame('3330000000', $customer->mobile);
            $this->assertSame('cliente@pec.example.com', $customer->pec);
            $this->assertSame('ABC1234', $customer->sdi_code);
            $this->assertSame('01234567890', $customer->tax_id);
            $this->assertSame('Rossi Disinfestazioni Srl', $customer->display_name);

            $privateCustomer = Customer::query()->create([
                'tenant_id' => $tenant->getKey(),
                'name' => 'Giuseppe Verdi',
                'status' => 'active',
            ]);

            $this->assertSame('Giuseppe Verdi', $privateCustomer->display_name);
        });
    }

    public function test_az_legacy_customer_code_is_unique_inside_the_tenant(): void
    {
        $tenant = $this->createTenant();

        $this->withinTenant($tenant, function (Tenant $tenant): void {
            Customer::query()->create([
                'tenant_id' => $tenant->getKey(),
                'legacy_customer_code' => 'AZ-001',
                'name' => 'Cliente Uno',
                'status' => 'active',
            ]);

            $this->expectException(QueryException::class);

            Customer::query()->create([
                'tenant_id' => $tenant->getKey(),
                'legacy_customer_code' => 'AZ-001',
                'name' => 'Cliente Due',
                'status' => 'active',
            ]);
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
