<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ratings', function (Blueprint $table) {
            $table->unsignedBigInteger('external_user_id')->nullable()->after('rater_firebase_uid');
            $table->index('external_user_id');
            $table->foreign('external_user_id')->references('id')->on('external_users')->nullOnDelete();
        });

        $this->makeRaterNullable();
        $this->moveImportedExternalUsers();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ratings', function (Blueprint $table) {
            $table->dropForeign(['external_user_id']);
            $table->dropIndex(['external_user_id']);
            $table->dropColumn('external_user_id');
        });
    }

    private function makeRaterNullable(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            Schema::table('ratings', function (Blueprint $table) {
                $table->string('rater_firebase_uid', 128)->nullable()->change();
            });

            return;
        }

        DB::statement('ALTER TABLE ratings DROP FOREIGN KEY ratings_rater_firebase_uid_foreign');
        DB::statement('ALTER TABLE ratings MODIFY rater_firebase_uid VARCHAR(128) NULL');
        DB::statement('ALTER TABLE ratings ADD CONSTRAINT ratings_rater_firebase_uid_foreign FOREIGN KEY (rater_firebase_uid) REFERENCES users(firebase_uid) ON DELETE SET NULL');
    }

    private function moveImportedExternalUsers(): void
    {
        $externalUserIds = DB::table('migration_logs')
            ->where('collection', 'external_users')
            ->where('status', 'success')
            ->pluck('firestore_doc_id');

        if ($externalUserIds->isEmpty()) {
            return;
        }

        DB::table('users')
            ->whereIn('firebase_uid', $externalUserIds)
            ->orderBy('firebase_uid')
            ->get()
            ->each(function (object $user): void {
                DB::table('external_users')->updateOrInsert(
                    ['external_uuid' => $user->firebase_uid],
                    [
                        'first_name' => $user->first_name,
                        'last_name' => $user->last_name,
                        'email' => $user->email,
                        'company_name' => $user->company_name,
                        'position' => $user->position,
                        'updated_at' => now(),
                        'created_at' => $user->created_at ?? now(),
                    ]
                );
            });

        DB::table('external_users')
            ->whereIn('external_uuid', $externalUserIds)
            ->get()
            ->each(function (object $externalUser): void {
                DB::table('ratings')
                    ->where('rater_firebase_uid', $externalUser->external_uuid)
                    ->update([
                        'external_user_id' => $externalUser->id,
                        'rater_firebase_uid' => null,
                        'from_external_link' => true,
                        'created_by' => null,
                        'updated_by' => null,
                    ]);
            });

        DB::table('rating_items')
            ->whereIn('created_by', $externalUserIds)
            ->update(['created_by' => null]);

        DB::table('rating_items')
            ->whereIn('updated_by', $externalUserIds)
            ->update(['updated_by' => null]);

        DB::table('notifications')
            ->whereIn('from_user_firebase_uid', $externalUserIds)
            ->update(['from_user_firebase_uid' => null]);

        DB::table('notifications')
            ->whereIn('created_by', $externalUserIds)
            ->update(['created_by' => null]);

        DB::table('notifications')
            ->whereIn('updated_by', $externalUserIds)
            ->update(['updated_by' => null]);

        DB::table('user_roles')->whereIn('user_firebase_uid', $externalUserIds)->delete();
        DB::table('users')->whereIn('firebase_uid', $externalUserIds)->delete();
    }
};
