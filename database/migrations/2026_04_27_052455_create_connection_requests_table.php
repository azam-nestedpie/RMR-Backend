<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('connection_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('firebase_uuid')->unique();
            $table->string('requester_firebase_uid', 128);
            $table->string('target_user_firebase_uid', 128);
            $table->string('manager_firebase_uid', 128)->nullable();
            $table->string('behalf_firebase_uid', 128)->nullable();
            $table->unsignedBigInteger('status_id');

            $table->string('created_by', 128)->nullable();
            $table->string('updated_by', 128)->nullable();
            $table->timestamps();

            // ⚡ Indexes
            $table->index('requester_firebase_uid');
            $table->index('target_user_firebase_uid');
            $table->index('manager_firebase_uid');
            $table->index('behalf_firebase_uid');
            $table->index('status_id');
            $table->index(['requester_firebase_uid', 'target_user_firebase_uid', 'status_id'], 'connection_requests_pair_status_idx');

            $table->foreign('requester_firebase_uid')->references('firebase_uid')->on('users')->onDelete('cascade');
            $table->foreign('target_user_firebase_uid')->references('firebase_uid')->on('users')->onDelete('cascade');
            $table->foreign('manager_firebase_uid')->references('firebase_uid')->on('users')->nullOnDelete();
            $table->foreign('behalf_firebase_uid')->references('firebase_uid')->on('users')->nullOnDelete();
            $table->foreign('status_id')->references('id')->on('statuses');
            $table->foreign('created_by')->references('firebase_uid')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('firebase_uid')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('connection_requests');
    }
};
