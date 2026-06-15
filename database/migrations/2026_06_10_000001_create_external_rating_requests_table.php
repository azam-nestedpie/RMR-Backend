<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('external_rating_requests')) {
            return;
        }

        Schema::create('external_rating_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('sales_rep_id', 128);
            $table->string('email', 191);
            $table->string('status', 20)->default('pending');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index('sales_rep_id');
            $table->index('email');
            $table->index('status');
            $table->index('expires_at');

            $table->foreign('sales_rep_id')->references('firebase_uid')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_rating_requests');
    }
};
