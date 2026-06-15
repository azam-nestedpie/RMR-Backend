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
        Schema::create('external_users', function (Blueprint $table) {
            $table->id();
            $table->string('external_uuid', 128)->unique();
            $table->string('first_name', 100);
            $table->string('last_name', 100)->nullable();
            $table->string('email', 191)->nullable();
            $table->string('company_name', 255)->nullable();
            $table->string('position', 255)->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();

            $table->index('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('external_users');
    }
};
