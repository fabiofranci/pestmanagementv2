<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        return;

        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_site_id')->constrained()->cascadeOnDelete();
            $table->string('contract_number')->unique();
            $table->string('status')->default('active');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('renewal')->nullable();
            $table->string('term')->nullable();
            $table->string('payment_terms')->nullable();
            $table->decimal('total_value', 14, 2)->nullable();
            $table->string('currency', 3)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'contract_number']);
        });
    }

    public function down(): void
    {
        return;

        Schema::dropIfExists('contracts');
    }
};
