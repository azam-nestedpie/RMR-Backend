<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_rep_users', function (Blueprint $table) {
            $table->string('user_firebase_uid', 128)->primary();
            $table->decimal('avg_rating', 3, 2)->default(0);
            $table->integer('ratings_count')->default(0);
            $table->boolean('is_subscribed')->default(false);
            $table->timestamp('subscription_started_at')->nullable();
            $table->timestamp('subscription_expires_at')->nullable();

            $table->string('created_by', 128)->nullable();
            $table->string('updated_by', 128)->nullable();
            $table->timestamps();

            // ⚡ Indexes
            $table->index('is_subscribed');

            $table->foreign('user_firebase_uid')->references('firebase_uid')->on('users')->onDelete('cascade');
            $table->foreign('created_by')->references('firebase_uid')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('firebase_uid')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_rep_users');
    }
};
