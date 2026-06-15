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
        Schema::create('team_requests', function (Blueprint $table) {
            $table->id();
            $table->string('firebase_uuid', 64)->unique();
            $table->string('manager_firebase_uid', 128);
            $table->string('target_user_firebase_uid', 128);
            $table->unsignedBigInteger('manager_type_role_id');
            $table->unsignedBigInteger('status_id');
            $table->timestamp('responded_at')->nullable();
            $table->string('created_by', 128)->nullable();
            $table->string('updated_by', 128)->nullable();
            $table->timestamps();

            $table->index('manager_firebase_uid');
            $table->index('target_user_firebase_uid');
            $table->index('manager_type_role_id');
            $table->index('status_id');
            $table->index(['manager_firebase_uid', 'target_user_firebase_uid', 'status_id'], 'team_requests_manager_target_status_idx');

            $table->foreign('manager_firebase_uid')->references('firebase_uid')->on('users')->onDelete('cascade');
            $table->foreign('target_user_firebase_uid')->references('firebase_uid')->on('users')->onDelete('cascade');
            $table->foreign('manager_type_role_id')->references('id')->on('roles');
            $table->foreign('status_id')->references('id')->on('statuses');
            $table->foreign('created_by')->references('firebase_uid')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('firebase_uid')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('team_requests');
    }
};
