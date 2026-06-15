<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->index(['to_user_firebase_uid', 'is_read', 'sent_at'], 'notifications_recipient_read_sent_idx');
        });

        Schema::table('connection_requests', function (Blueprint $table) {
            $table->index(['target_user_firebase_uid', 'status_id', 'created_at'], 'connection_requests_target_status_created_idx');
        });

        Schema::table('connections', function (Blueprint $table) {
            $table->index(['user_a_firebase_uid', 'is_active'], 'connections_user_a_active_idx');
            $table->index(['user_b_firebase_uid', 'is_active'], 'connections_user_b_active_idx');
        });

        Schema::table('ratings', function (Blueprint $table) {
            $table->index(['rep_firebase_uid', 'rated_at'], 'ratings_rep_rated_at_idx');
            $table->index(['rater_firebase_uid', 'rated_at'], 'ratings_rater_rated_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('ratings', function (Blueprint $table) {
            $table->dropIndex('ratings_rep_rated_at_idx');
            $table->dropIndex('ratings_rater_rated_at_idx');
        });

        Schema::table('connections', function (Blueprint $table) {
            $table->dropIndex('connections_user_a_active_idx');
            $table->dropIndex('connections_user_b_active_idx');
        });

        Schema::table('connection_requests', function (Blueprint $table) {
            $table->dropIndex('connection_requests_target_status_created_idx');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('notifications_recipient_read_sent_idx');
        });
    }
};
