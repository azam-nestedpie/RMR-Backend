<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('migration_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('entity_type');
            $table->string('old_id')->nullable();
            $table->string('new_id')->nullable();
            $table->string('status');
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('migration_logs');
    }
};
