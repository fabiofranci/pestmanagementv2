<?php

return [
    'session_key' => env('TENANCY_SESSION_KEY', 'current_tenant_id'),

    'database_connection' => env('TENANT_DB_CONNECTION_NAME', 'tenant'),

    'admin_connection' => env('TENANT_DB_ADMIN_CONNECTION_NAME', 'tenant_admin'),

    'database_prefix' => env('TENANT_DB_DATABASE_PREFIX', 'tenant_'),

    'migration_path' => database_path('migrations/tenant'),
];
