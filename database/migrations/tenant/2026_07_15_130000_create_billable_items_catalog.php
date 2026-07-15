<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = config('tenancy.database_connection');

        if (! Schema::connection($connection)->hasTable('billable_items')) {
            Schema::connection($connection)->create('billable_items', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->string('name');
                $table->string('code')->nullable();
                $table->text('description')->nullable();
                $table->decimal('default_unit_price', 14, 2)->nullable();
                $table->decimal('vat_rate', 5, 2)->nullable();
                $table->string('status')->default('active');
                $table->timestamps();

                $table->unique(['tenant_id', 'name']);
            });
        }

        if (! Schema::connection($connection)->hasTable('customer_billable_item_prices')) {
            Schema::connection($connection)->create('customer_billable_item_prices', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
                $table->foreignId('billable_item_id')->constrained()->cascadeOnDelete();
                $table->decimal('discount_percentage', 5, 2)->nullable();
                $table->decimal('custom_unit_price', 14, 2)->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'customer_id', 'billable_item_id'], 'customer_billable_item_prices_unique');
            });
        }
    }

    public function down(): void
    {
        $connection = config('tenancy.database_connection');

        Schema::connection($connection)->dropIfExists('customer_billable_item_prices');
        Schema::connection($connection)->dropIfExists('billable_items');
    }
};
