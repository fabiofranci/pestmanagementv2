<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection(config('tenancy.database_connection'))->create('customers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('tax_id')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('postcode')->nullable();
            $table->string('province')->nullable();
            $table->string('country')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::connection(config('tenancy.database_connection'))->create('customer_sites', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('postcode')->nullable();
            $table->string('province')->nullable();
            $table->string('country')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('site_code')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::connection(config('tenancy.database_connection'))->create('service_types', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('name');
            $table->string('code')->nullable();
            $table->text('description')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->unique(['tenant_id', 'name']);
        });

        Schema::connection(config('tenancy.database_connection'))->create('pest_types', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('name');
            $table->string('code')->nullable();
            $table->text('description')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::connection(config('tenancy.database_connection'))->create('areas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('customer_site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_type_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('thresholds')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::connection(config('tenancy.database_connection'))->create('contracts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_site_id')->constrained()->cascadeOnDelete();
            $table->string('contract_number');
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

        Schema::connection(config('tenancy.database_connection'))->create('monitoring_points', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('area_id')->constrained()->cascadeOnDelete();
            $table->string('code')->unique();
            $table->string('name')->nullable();
            $table->foreignId('service_type_id')->constrained()->cascadeOnDelete();
            $table->string('type')->nullable();
            $table->string('model')->nullable();
            $table->string('product')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->json('map_position')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $connection = config('tenancy.database_connection');

        Schema::connection($connection)->dropIfExists('monitoring_points');
        Schema::connection($connection)->dropIfExists('contracts');
        Schema::connection($connection)->dropIfExists('areas');
        Schema::connection($connection)->dropIfExists('pest_types');
        Schema::connection($connection)->dropIfExists('service_types');
        Schema::connection($connection)->dropIfExists('customer_sites');
        Schema::connection($connection)->dropIfExists('customers');
    }
};
