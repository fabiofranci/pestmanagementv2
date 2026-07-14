<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = config('tenancy.database_connection');

        if (! Schema::connection($connection)->hasTable('customers')) {
            return;
        }

        Schema::connection($connection)->table('customers', function (Blueprint $table) use ($connection): void {
            if (! Schema::connection($connection)->hasColumn('customers', 'legacy_customer_code')) {
                $table->string('legacy_customer_code')->nullable();
            }

            if (! Schema::connection($connection)->hasColumn('customers', 'vat_number')) {
                $table->string('vat_number')->nullable();
            }

            if (! Schema::connection($connection)->hasColumn('customers', 'fiscal_code')) {
                $table->string('fiscal_code')->nullable();
            }

            if (! Schema::connection($connection)->hasColumn('customers', 'secondary_phone')) {
                $table->string('secondary_phone')->nullable();
            }

            if (! Schema::connection($connection)->hasColumn('customers', 'mobile')) {
                $table->string('mobile')->nullable();
            }

            if (! Schema::connection($connection)->hasColumn('customers', 'pec')) {
                $table->string('pec')->nullable();
            }

            if (! Schema::connection($connection)->hasColumn('customers', 'sdi_code')) {
                $table->string('sdi_code')->nullable();
            }
        });

        if (! Schema::connection($connection)->hasIndex('customers', 'customers_tenant_legacy_customer_code_unique')) {
            Schema::connection($connection)->table('customers', function (Blueprint $table): void {
                $table->unique(['tenant_id', 'legacy_customer_code'], 'customers_tenant_legacy_customer_code_unique');
            });
        }

        if (
            Schema::connection($connection)->hasColumn('customers', 'tax_id')
            && Schema::connection($connection)->hasColumn('customers', 'vat_number')
        ) {
            DB::connection($connection)
                ->table('customers')
                ->whereNull('vat_number')
                ->whereNotNull('tax_id')
                ->update(['vat_number' => DB::raw('tax_id')]);
        }
    }

    public function down(): void
    {
        $connection = config('tenancy.database_connection');

        if (! Schema::connection($connection)->hasTable('customers')) {
            return;
        }

        if (Schema::connection($connection)->hasIndex('customers', 'customers_tenant_legacy_customer_code_unique')) {
            Schema::connection($connection)->table('customers', function (Blueprint $table): void {
                $table->dropUnique('customers_tenant_legacy_customer_code_unique');
            });
        }

        $columns = collect([
            'legacy_customer_code',
            'vat_number',
            'fiscal_code',
            'secondary_phone',
            'mobile',
            'pec',
            'sdi_code',
        ])->filter(fn (string $column): bool => Schema::connection($connection)->hasColumn('customers', $column))->all();

        if ($columns === []) {
            return;
        }

        Schema::connection($connection)->table('customers', function (Blueprint $table) use ($columns): void {
            $table->dropColumn($columns);
        });
    }
};
