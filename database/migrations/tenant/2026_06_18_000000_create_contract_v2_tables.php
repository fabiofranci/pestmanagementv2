<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = config('tenancy.database_connection');

        Schema::connection($connection)->create('contract_services', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_site_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('area_id')->nullable()->constrained()->nullOnDelete();
            $table->text('description');
            $table->string('frequency')->nullable();
            $table->decimal('quantity', 12, 2)->nullable();
            $table->decimal('unit_price', 14, 2)->nullable();
            $table->decimal('total_price', 14, 2)->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'contract_id']);
            $table->index(['tenant_id', 'service_type_id']);
        });

        Schema::connection($connection)->create('scheduled_interventions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contract_service_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_type_id')->constrained()->cascadeOnDelete();
            $table->date('planned_date');
            $table->time('planned_time')->nullable();
            $table->string('status')->default('planned');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'contract_id']);
            $table->index(['tenant_id', 'planned_date']);
        });

        Schema::connection($connection)->create('contract_billing_schedules', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->date('due_date');
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3)->default('EUR');
            $table->decimal('vat_rate', 5, 2)->nullable();
            $table->string('status')->default('planned');
            $table->string('invoice_reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'contract_id']);
            $table->index(['tenant_id', 'due_date']);
        });

        Schema::connection($connection)->create('documents', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('documentable_type');
            $table->unsignedBigInteger('documentable_id');
            $table->string('type');
            $table->string('title');
            $table->string('file_path')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->boolean('visible_to_customer')->default(false);
            $table->timestamp('generated_at')->nullable();
            $table->unsignedBigInteger('uploaded_by_user_id')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'documentable_type', 'documentable_id']);
            $table->index(['tenant_id', 'type']);
        });

        Schema::connection($connection)->create('contract_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->string('event_type');
            $table->string('title');
            $table->json('payload')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'contract_id']);
            $table->index(['tenant_id', 'event_type']);
        });
    }

    public function down(): void
    {
        $connection = config('tenancy.database_connection');

        Schema::connection($connection)->dropIfExists('contract_events');
        Schema::connection($connection)->dropIfExists('documents');
        Schema::connection($connection)->dropIfExists('contract_billing_schedules');
        Schema::connection($connection)->dropIfExists('scheduled_interventions');
        Schema::connection($connection)->dropIfExists('contract_services');
    }
};
