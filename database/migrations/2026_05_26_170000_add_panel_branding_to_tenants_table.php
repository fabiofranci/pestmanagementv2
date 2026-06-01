<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('panel_palette')->default('salvia')->after('db_password');
            $table->string('panel_theme_mode')->default('light')->after('panel_palette');
            $table->string('panel_font_family')->default('manrope')->after('panel_theme_mode');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'panel_palette',
                'panel_theme_mode',
                'panel_font_family',
            ]);
        });
    }
};
