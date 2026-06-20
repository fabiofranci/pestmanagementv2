<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = config('tenancy.database_connection');
        $schema = Schema::connection($connection);

        if (! $schema->hasIndex('contract_services', 'contract_services_tenant_contract_unique', 'unique')) {
            return;
        }

        $schema->table('contract_services', function (Blueprint $table): void {
            $table->dropUnique('contract_services_tenant_contract_unique');
        });
    }

    public function down(): void
    {
        //
    }
};
