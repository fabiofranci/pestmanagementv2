<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = config('tenancy.database_connection');

        if (! Schema::connection($connection)->hasTable('customer_groups')) {
            Schema::connection($connection)->create('customer_groups', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->string('name');
                $table->string('code')->nullable();
                $table->text('description')->nullable();
                $table->string('status')->default('active');
                $table->timestamps();

                $table->unique(['tenant_id', 'name']);
            });
        }

        if (
            Schema::connection($connection)->hasTable('customers')
            && ! Schema::connection($connection)->hasColumn('customers', 'customer_group_id')
        ) {
            Schema::connection($connection)->table('customers', function (Blueprint $table): void {
                $table->foreignId('customer_group_id')
                    ->nullable()
                    ->after('tenant_id')
                    ->constrained('customer_groups')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        $connection = config('tenancy.database_connection');

        if (
            Schema::connection($connection)->hasTable('customers')
            && Schema::connection($connection)->hasColumn('customers', 'customer_group_id')
        ) {
            Schema::connection($connection)->table('customers', function (Blueprint $table): void {
                $table->dropForeign(['customer_group_id']);
                $table->dropColumn('customer_group_id');
            });
        }

        Schema::connection($connection)->dropIfExists('customer_groups');
    }
};
