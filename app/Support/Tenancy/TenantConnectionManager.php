<?php

namespace App\Support\Tenancy;

use App\Models\Tenant;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class TenantConnectionManager
{
    public function activate(?Tenant $tenant): void
    {
        $connectionName = config('tenancy.database_connection');

        DB::purge($connectionName);

        if (! $tenant) {
            return;
        }

        $database = $tenant->db_database;

        if (blank($database)) {
            throw new RuntimeException('Il tenant selezionato non ha un database configurato.');
        }

        $baseConfig = config("database.connections.{$connectionName}");

        if (! is_array($baseConfig)) {
            throw new RuntimeException("Connessione tenant [{$connectionName}] non configurata.");
        }

        $connectionConfig = [
            ...$baseConfig,
            'database' => $database,
        ];

        if (array_key_exists('host', $baseConfig)) {
            $connectionConfig['host'] = $tenant->db_host ?: $baseConfig['host'];
        }

        if (array_key_exists('port', $baseConfig)) {
            $connectionConfig['port'] = $tenant->db_port ?: $baseConfig['port'];
        }

        if (array_key_exists('username', $baseConfig)) {
            $connectionConfig['username'] = $tenant->db_username ?: $baseConfig['username'];
        }

        if (array_key_exists('password', $baseConfig)) {
            $connectionConfig['password'] = $tenant->db_password ?: $baseConfig['password'];
        }

        Config::set("database.connections.{$connectionName}", $connectionConfig);

        DB::reconnect($connectionName);

        try {
            DB::connection($connectionName)->getPdo();
        } catch (Throwable $exception) {
            DB::purge($connectionName);

            throw new RuntimeException(
                "Impossibile collegarsi al database del tenant [{$tenant->name}] con le credenziali configurate.",
                previous: $exception,
            );
        }
    }
}
