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
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('company_name');
            $table->string('slug')->nullable();
            $table->string('vat_number')->nullable();
            $table->string('fiscal_code')->nullable();
            $table->string('website')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('mobile')->nullable();
            $table->string('whatsapp')->nullable();
            $table->string('pec')->nullable();
            $table->string('region')->nullable();
            $table->string('province')->nullable();
            $table->string('city')->nullable();
            $table->string('address')->nullable();
            $table->string('sector')->nullable();
            $table->unsignedTinyInteger('score')->default(0);
            $table->string('status')->default('new');
            $table->string('source_name')->nullable();
            $table->text('source_url')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->boolean('email_marketing_allowed')->default(false);
            $table->boolean('whatsapp_marketing_allowed')->default(false);
            $table->boolean('phone_contact_allowed')->default(false);
            $table->timestamp('opted_out_at')->nullable();
            $table->timestamp('blacklisted_at')->nullable();
            $table->text('notes')->nullable();
            $table->index('status');
            $table->index(['region', 'province', 'city']);
            $table->index('sector');
            $table->index('score');
            $table->index('email');
            $table->index('phone');
            $table->index('mobile');
            $table->index('whatsapp');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
