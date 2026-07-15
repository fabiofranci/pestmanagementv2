<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = config('tenancy.database_connection');

        if (! Schema::connection($connection)->hasTable('contract_services')) {
            return;
        }

        Schema::connection($connection)->table('contract_services', function (Blueprint $table) use ($connection): void {
            if (! Schema::connection($connection)->hasColumn('contract_services', 'operational_schedule_mode')) {
                $table->string('operational_schedule_mode')->default('recurring')->after('operational_frequency');
            }

            if (! Schema::connection($connection)->hasColumn('contract_services', 'scheduled_months')) {
                $table->json('scheduled_months')->nullable()->after('operational_schedule_mode');
            }

            if (! Schema::connection($connection)->hasColumn('contract_services', 'interventions_per_year')) {
                $table->unsignedInteger('interventions_per_year')->nullable()->after('scheduled_months');
            }
        });
    }

    public function down(): void
    {
        $connection = config('tenancy.database_connection');

        if (! Schema::connection($connection)->hasTable('contract_services')) {
            return;
        }

        $columns = collect([
            'interventions_per_year',
            'scheduled_months',
            'operational_schedule_mode',
        ])->filter(fn (string $column): bool => Schema::connection($connection)->hasColumn('contract_services', $column))->all();

        if ($columns === []) {
            return;
        }

        Schema::connection($connection)->table('contract_services', function (Blueprint $table) use ($columns): void {
            $table->dropColumn($columns);
        });
    }
};
