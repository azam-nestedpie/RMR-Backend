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
        if (Schema::hasColumn('external_users', 'raw_data')) {
            Schema::table('external_users', function (Blueprint $table) {
                $table->dropColumn('raw_data');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('external_users', 'raw_data')) {
            Schema::table('external_users', function (Blueprint $table) {
                $table->json('raw_data')->nullable()->after('position');
            });
        }
    }
};
