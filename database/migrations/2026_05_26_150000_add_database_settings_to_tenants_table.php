<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('db_host')->nullable()->after('domain');
            $table->unsignedInteger('db_port')->nullable()->after('db_host');
            $table->string('db_database')->nullable()->unique()->after('db_port');
            $table->string('db_username')->nullable()->after('db_database');
            $table->text('db_password')->nullable()->after('db_username');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropUnique(['db_database']);
            $table->dropColumn([
                'db_host',
                'db_port',
                'db_database',
                'db_username',
                'db_password',
            ]);
        });
    }
};
