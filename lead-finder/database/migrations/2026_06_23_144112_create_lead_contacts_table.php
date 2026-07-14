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
        Schema::create('lead_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->string('type');
            $table->string('value');
            $table->string('label')->nullable();
            $table->text('source_url')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_valid')->nullable();
            $table->text('notes')->nullable();
            $table->unique(['lead_id', 'type', 'value']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lead_contacts');
    }
};
