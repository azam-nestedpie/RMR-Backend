<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('migration_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('migration_logs', 'collection')) {
                $table->string('collection', 100)->nullable()->after('id');
            }

            if (! Schema::hasColumn('migration_logs', 'firestore_doc_id')) {
                $table->string('firestore_doc_id', 191)->nullable()->after('collection');
            }

            if (! Schema::hasColumn('migration_logs', 'raw_data')) {
                $table->json('raw_data')->nullable()->after('error_message');
            }

            if (! Schema::hasColumn('migration_logs', 'migrated_by')) {
                $table->string('migrated_by', 128)->nullable()->after('raw_data');
            }

            if (! Schema::hasColumn('migration_logs', 'migrated_at')) {
                $table->timestamp('migrated_at')->nullable()->after('migrated_by');
            }

            $table->index(['collection', 'firestore_doc_id', 'status'], 'migration_logs_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::table('migration_logs', function (Blueprint $table) {
            $table->dropIndex('migration_logs_lookup_idx');

            if (Schema::hasColumn('migration_logs', 'migrated_at')) {
                $table->dropColumn('migrated_at');
            }

            if (Schema::hasColumn('migration_logs', 'migrated_by')) {
                $table->dropColumn('migrated_by');
            }

            if (Schema::hasColumn('migration_logs', 'raw_data')) {
                $table->dropColumn('raw_data');
            }

            if (Schema::hasColumn('migration_logs', 'firestore_doc_id')) {
                $table->dropColumn('firestore_doc_id');
            }

            if (Schema::hasColumn('migration_logs', 'collection')) {
                $table->dropColumn('collection');
            }
        });
    }
};
