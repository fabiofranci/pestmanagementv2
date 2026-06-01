<?php

namespace App\Support\Tenancy;

use App\Models\Tenant;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class TenantDatabaseProvisioner
{
    public function __construct(
        protected TenantConnectionManager $connectionManager,
    ) {}

    public function makeDefaultDatabaseName(string $slug): string
    {
        $sanitizedSlug = Str::of($slug)
            ->lower()
            ->replace('-', '_')
            ->replaceMatches('/[^a-z0-9_]/', '')
            ->value();

        return config('tenancy.database_prefix') . $sanitizedSlug;
    }

    public function provision(Tenant $tenant): void
    {
        if (blank($tenant->db_database)) {
            throw new RuntimeException('Specifica un nome database per il tenant prima del provisioning.');
        }

        $this->createDatabase($tenant);
        $this->connectionManager->activate($tenant);

        Artisan::call('migrate', [
            '--database' => config('tenancy.database_connection'),
            '--path' => config('tenancy.migration_path'),
            '--realpath' => true,
            '--force' => true,
        ]);

        DB::purge(config('tenancy.database_connection'));
    }

    protected function createDatabase(Tenant $tenant): void
    {
        $driver = config('database.connections.' . config('tenancy.admin_connection') . '.driver');

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            throw new RuntimeException("Il provisioning automatico supporta solo MySQL/MariaDB. Driver attuale: {$driver}.");
        }

        $databaseName = $tenant->db_database;

        if (! preg_match('/^[A-Za-z0-9_]+$/', $databaseName)) {
            throw new RuntimeException('Il nome del database tenant contiene caratteri non supportati.');
        }

        DB::connection(config('tenancy.admin_connection'))
            ->unprepared("CREATE DATABASE IF NOT EXISTS `{$databaseName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    }
}
