<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('external_rating_requests');

        Schema::create('external_rating_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('invite_uuid')->unique();
            $table->string('rep_id', 128);
            $table->string('email', 191);
            $table->string('token')->unique();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamps();
            $table->string('created_by', 128);
            $table->string('updated_by', 128);

            $table->index('email');
            $table->index('status');
            $table->index('expires_at');

            $table->foreign('rep_id')->references('firebase_uid')->on('users')->cascadeOnDelete();
            $table->foreign('created_by')->references('firebase_uid')->on('users');
            $table->foreign('updated_by')->references('firebase_uid')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_rating_requests');
    }
};
