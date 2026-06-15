<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('connections', function (Blueprint $table) {
            $table->id();
            $table->uuid('firebase_uuid')->unique();
            $table->string('user_a_firebase_uid', 128);
            $table->string('user_b_firebase_uid', 128);
            $table->string('connected_by_uid', 128)->nullable();
            $table->unsignedBigInteger('source_request_id')->nullable();
            $table->timestamp('connected_at')->useCurrent();
            $table->timestamp('disconnected_at')->nullable();
            $table->text('disconnect_reason')->nullable();
            $table->boolean('is_active')->default(true);

            $table->string('created_by', 128)->nullable();
            $table->string('updated_by', 128)->nullable();
            $table->timestamps();

            // ⚡ Indexes
            $table->index('connected_by_uid');
            $table->index('is_active');
            $table->index(['user_a_firebase_uid', 'user_b_firebase_uid', 'is_active'], 'connections_pair_active_idx');

            $table->foreign('user_a_firebase_uid')->references('firebase_uid')->on('users')->onDelete('cascade');
            $table->foreign('user_b_firebase_uid')->references('firebase_uid')->on('users')->onDelete('cascade');
            $table->foreign('connected_by_uid')->references('firebase_uid')->on('users')->onDelete('set null');
            $table->foreign('source_request_id')->references('id')->on('connection_requests')->onDelete('set null');
            $table->foreign('created_by')->references('firebase_uid')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('firebase_uid')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('connections');
    }
};
