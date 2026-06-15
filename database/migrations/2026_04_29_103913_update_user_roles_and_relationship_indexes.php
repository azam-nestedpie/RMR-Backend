<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_roles', function (Blueprint $table) {
            $table->dropForeign(['user_firebase_uid']);
            $table->dropPrimary(['user_firebase_uid']);
        });

        Schema::table('user_roles', function (Blueprint $table) {
            $table->primary(['user_firebase_uid', 'role_id']);
            $table->foreign('user_firebase_uid')->references('firebase_uid')->on('users')->onDelete('cascade');
            $table->index('role_id', 'user_roles_role_id_idx');
        });

        Schema::table('rating_requests', function (Blueprint $table) {
            $table->foreign('requested_by_role_id')
                ->references('id')
                ->on('roles')
                ->onDelete('restrict');

            $table->index(['subject_rep_firebase_uid', 'created_at'], 'rating_requests_subject_created_idx');
            $table->index(['rater_firebase_uid', 'subject_rep_firebase_uid', 'status_id'], 'rating_requests_rater_subject_status_idx');
        });

        Schema::table('connection_requests', function (Blueprint $table) {
            $table->index(['requester_firebase_uid', 'target_user_firebase_uid', 'status_id'], 'connection_requests_requester_target_status_idx');
        });

        Schema::table('manager_team_members', function (Blueprint $table) {
            $table->index(['manager_firebase_uid', 'active', 'left_at'], 'manager_team_members_manager_active_idx');
            $table->index(['member_firebase_uid', 'active', 'left_at'], 'manager_team_members_member_active_idx');
        });
    }

    public function down(): void
    {
        Schema::table('rating_requests', function (Blueprint $table) {
            $table->dropForeign(['requested_by_role_id']);
        });

        Schema::table('manager_team_members', function (Blueprint $table) {
            $table->dropIndex('manager_team_members_manager_active_idx');
            $table->dropIndex('manager_team_members_member_active_idx');
        });

        Schema::table('connection_requests', function (Blueprint $table) {
            $table->dropIndex('connection_requests_requester_target_status_idx');
        });

        Schema::table('rating_requests', function (Blueprint $table) {
            $table->dropIndex('rating_requests_subject_created_idx');
            $table->dropIndex('rating_requests_rater_subject_status_idx');
        });

        Schema::table('user_roles', function (Blueprint $table) {
            $table->dropIndex('user_roles_role_id_idx');
            $table->dropForeign(['user_firebase_uid']);
            $table->dropPrimary(['user_firebase_uid', 'role_id']);
        });

        Schema::table('user_roles', function (Blueprint $table) {
            $table->primary('user_firebase_uid');
            $table->foreign('user_firebase_uid')->references('firebase_uid')->on('users')->onDelete('cascade');
        });
    }
};
