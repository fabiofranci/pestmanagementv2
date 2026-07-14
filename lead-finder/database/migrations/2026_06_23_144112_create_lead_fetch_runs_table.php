<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('lead_fetch_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_source_id')->nullable()->nullOnDelete()->constrained('lead_sources');
            $table->string('query')->nullable();
            $table->string('region')->nullable();
            $table->string('province')->nullable();
            $table->string('sector')->nullable();
            $table->unsignedInteger('found_count')->default(0);
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->string('status')->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lead_fetch_runs');
    }
};
