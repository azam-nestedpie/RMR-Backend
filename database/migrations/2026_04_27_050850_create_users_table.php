<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->string('firebase_uid', 128)->primary();
            $table->string('first_name', 100);
            $table->string('last_name', 100)->nullable();
            $table->string('email', 191)->unique();
            $table->string('password', 255)->nullable();
            $table->text('bio')->nullable();
            $table->text('image_url')->nullable();
            $table->string('company_name', 255)->nullable();
            $table->string('position', 255)->nullable();
            $table->boolean('is_blocked')->default(false);
            $table->boolean('is_deleted')->default(false);
            $table->string('fcm_token', 255)->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('created_by', 128)->nullable();
            $table->string('updated_by', 128)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('email');
            $table->index('is_deleted');
            $table->index('created_by');
            $table->index('updated_by');

            // 🔗 Self-referencing foreign keys
            $table->foreign('created_by')
                ->references('firebase_uid')
                ->on('users')
                ->nullOnDelete();

            $table->foreign('updated_by')
                ->references('firebase_uid')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
