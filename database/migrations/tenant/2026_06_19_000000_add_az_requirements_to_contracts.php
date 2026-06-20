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

        Schema::connection($connection)->table('contracts', function (Blueprint $table): void {
            $table->boolean('tacit_renewal')->default(false)->after('renewal');
            $table->decimal('renewal_price_increase_percentage', 5, 2)->default(4.00)->after('tacit_renewal');
            $table->unsignedInteger('renewal_notice_days')->default(30)->after('renewal_price_increase_percentage');
        });

        Schema::connection($connection)->table('contract_services', function (Blueprint $table): void {
            $table->string('operational_frequency')->nullable()->after('frequency');
            $table->string('billing_frequency')->nullable()->after('operational_frequency');
        });

        DB::connection($connection)
            ->table('contract_services')
            ->whereNull('operational_frequency')
            ->whereNotNull('frequency')
            ->update([
                'operational_frequency' => DB::raw('frequency'),
            ]);

        Schema::connection($connection)->table('contract_services', function (Blueprint $table): void {
            $table->unique(['tenant_id', 'contract_id'], 'contract_services_tenant_contract_unique');
        });
    }

    public function down(): void
    {
        $connection = config('tenancy.database_connection');

        Schema::connection($connection)->table('contract_services', function (Blueprint $table): void {
            $table->dropUnique('contract_services_tenant_contract_unique');
            $table->dropColumn([
                'operational_frequency',
                'billing_frequency',
            ]);
        });

        Schema::connection($connection)->table('contracts', function (Blueprint $table): void {
            $table->dropColumn([
                'tacit_renewal',
                'renewal_price_increase_percentage',
                'renewal_notice_days',
            ]);
        });
    }
};
