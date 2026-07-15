<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = config('tenancy.database_connection');

        if (Schema::connection($connection)->hasTable('intervention_billable_items')) {
            return;
        }

        Schema::connection($connection)->create('intervention_billable_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('scheduled_intervention_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->foreignId('billable_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contract_billing_schedule_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->decimal('quantity', 14, 2)->default(1);
            $table->decimal('unit_price', 14, 2)->nullable();
            $table->decimal('total_price', 14, 2)->nullable();
            $table->string('status')->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'scheduled_intervention_id'], 'ibi_tenant_intervention_index');
            $table->index(['tenant_id', 'contract_id'], 'ibi_tenant_contract_index');
            $table->index(['tenant_id', 'contract_billing_schedule_id'], 'ibi_tenant_billing_schedule_index');
            $table->index(['tenant_id', 'status'], 'ibi_tenant_status_index');
        });
    }

    public function down(): void
    {
        Schema::connection(config('tenancy.database_connection'))->dropIfExists('intervention_billable_items');
    }
};
