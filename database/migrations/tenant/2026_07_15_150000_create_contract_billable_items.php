<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = config('tenancy.database_connection');

        if (Schema::connection($connection)->hasTable('contract_billable_items')) {
            return;
        }

        Schema::connection($connection)->create('contract_billable_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->foreignId('billable_item_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 14, 2)->default(1);
            $table->decimal('unit_price', 14, 2)->nullable();
            $table->decimal('discount_percentage', 5, 2)->nullable();
            $table->decimal('total_price', 14, 2)->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();

            $table->index(['tenant_id', 'contract_id']);
            $table->index(['tenant_id', 'billable_item_id']);
        });
    }

    public function down(): void
    {
        Schema::connection(config('tenancy.database_connection'))->dropIfExists('contract_billable_items');
    }
};
