<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = config('tenancy.database_connection');

        if (! Schema::connection($connection)->hasTable('contracts')) {
            return;
        }

        Schema::connection($connection)->table('contracts', function (Blueprint $table) use ($connection): void {
            if (! Schema::connection($connection)->hasColumn('contracts', 'billing_frequency')) {
                $table->string('billing_frequency')->nullable()->after('payment_terms');
            }

            if (! Schema::connection($connection)->hasColumn('contracts', 'billing_installments_count')) {
                $table->unsignedInteger('billing_installments_count')->nullable()->after('billing_frequency');
            }

            if (! Schema::connection($connection)->hasColumn('contracts', 'renewed_from_contract_id')) {
                $table->unsignedBigInteger('renewed_from_contract_id')->nullable()->after('id');
                $table->index('renewed_from_contract_id', 'contracts_renewed_from_contract_id_index');
            }
        });
    }

    public function down(): void
    {
        $connection = config('tenancy.database_connection');

        if (! Schema::connection($connection)->hasTable('contracts')) {
            return;
        }

        Schema::connection($connection)->table('contracts', function (Blueprint $table) use ($connection): void {
            if (Schema::connection($connection)->hasColumn('contracts', 'renewed_from_contract_id')) {
                if (Schema::connection($connection)->hasIndex('contracts', 'contracts_renewed_from_contract_id_index')) {
                    $table->dropIndex('contracts_renewed_from_contract_id_index');
                }

                $table->dropColumn('renewed_from_contract_id');
            }

            if (Schema::connection($connection)->hasColumn('contracts', 'billing_installments_count')) {
                $table->dropColumn('billing_installments_count');
            }

            if (Schema::connection($connection)->hasColumn('contracts', 'billing_frequency')) {
                $table->dropColumn('billing_frequency');
            }
        });
    }
};
