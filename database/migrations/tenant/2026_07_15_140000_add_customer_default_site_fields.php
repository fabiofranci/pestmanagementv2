<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = config('tenancy.database_connection');

        if (Schema::connection($connection)->hasTable('customers')) {
            Schema::connection($connection)->table('customers', function (Blueprint $table) use ($connection): void {
                if (! Schema::connection($connection)->hasColumn('customers', 'default_site_same_as_customer')) {
                    $table->boolean('default_site_same_as_customer')
                        ->default(false)
                        ->after('customer_group_id');
                }
            });
        }

        if (Schema::connection($connection)->hasTable('customer_sites')) {
            Schema::connection($connection)->table('customer_sites', function (Blueprint $table) use ($connection): void {
                if (! Schema::connection($connection)->hasColumn('customer_sites', 'auto_created_from_customer')) {
                    $table->boolean('auto_created_from_customer')
                        ->default(false)
                        ->after('customer_id');
                }
            });
        }
    }

    public function down(): void
    {
        $connection = config('tenancy.database_connection');

        if (
            Schema::connection($connection)->hasTable('customer_sites')
            && Schema::connection($connection)->hasColumn('customer_sites', 'auto_created_from_customer')
        ) {
            Schema::connection($connection)->table('customer_sites', function (Blueprint $table): void {
                $table->dropColumn('auto_created_from_customer');
            });
        }

        if (
            Schema::connection($connection)->hasTable('customers')
            && Schema::connection($connection)->hasColumn('customers', 'default_site_same_as_customer')
        ) {
            Schema::connection($connection)->table('customers', function (Blueprint $table): void {
                $table->dropColumn('default_site_same_as_customer');
            });
        }
    }
};
