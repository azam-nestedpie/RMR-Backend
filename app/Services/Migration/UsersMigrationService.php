<?php

namespace App\Services\Migration;

use App\Models\Role;
use Illuminate\Support\Facades\DB;

class UsersMigrationService extends BaseMigrationService
{
    protected string $collection = 'users';

    public function migrate(array $documents): array
    {
        $results = ['success' => 0, 'failed' => 0, 'skipped' => 0];
        $this->logger->info('[users] Starting. Total: '.count($documents));

        foreach ($documents as $doc) {
            $results[$this->migrateDocument($doc)]++;
            if (($results['success'] + $results['failed']) % $this->batchSize === 0) {
                usleep($this->batchDelayMs * 1000);
            }
        }

        $this->logger->info('[users] Done: '.json_encode($results));

        return $results;
    }

    protected function insertDocument(array $doc): void
    {
        $firebaseUid = $doc['_id'] ?? $doc['userId'] ?? null;
        if (empty($firebaseUid)) {
            throw new \InvalidArgumentException('User doc missing Firebase UID.');
        }
        if (empty($doc['email'])) {
            throw new \InvalidArgumentException("User [{$firebaseUid}] missing email.");
        }

        $roleId = ($doc['isRepresentative'] ?? false) === true ? Role::REPRESENTATIVE : Role::RATER;
        $address = $doc['address'] ?? [];
        $now = now();

        DB::table('users')->insertOrIgnore([
            'firebase_uid' => $firebaseUid,
            'first_name' => $doc['name'] ?? '',
            'last_name' => $doc['lastName'] ?? null,
            'email' => strtolower(trim($doc['email'])),
            'password' => null,
            'bio' => null,
            'image_url' => $doc['imageUrl'] ?? null,
            'company_name' => $doc['companyName'] ?? null,
            'position' => $doc['position'] ?? null,
            'is_blocked' => ($doc['isBlocked'] ?? false) ? 1 : 0,
            'is_deleted' => ($doc['isDeleted'] ?? false) ? 1 : 0,
            'fcm_token' => $doc['fcmToken'] ?? null,
            'created_by' => $firebaseUid, // Self-referencing audit
            'updated_by' => $firebaseUid,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Assign role in user_roles table
        if ($roleId) {
            DB::table('user_roles')->insertOrIgnore([
                'user_firebase_uid' => $firebaseUid,
                'role_id' => $roleId,
                'created_by' => $firebaseUid,
                'updated_by' => $firebaseUid,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Create address record if present
        $city = $address['city'] ?? $doc['city'] ?? null;
        $state = $address['state'] ?? $doc['state'] ?? null;
        $country = $address['country'] ?? $doc['country'] ?? null;

        if ($city || $state || $country) {
            DB::table('addresses')->insertOrIgnore([
                'user_firebase_uid' => $firebaseUid,
                'city' => $city,
                'state' => $state,
                'country' => $country,
                'created_by' => $firebaseUid,
                'updated_by' => $firebaseUid,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Migrate myFavourites JSON array → user_favorites rows
        $favourites = $doc['myFavourites'] ?? [];
        foreach ($favourites as $favUid) {
            if (! empty($favUid)) {
                DB::table('user_favorites')->insertOrIgnore([
                    'user_firebase_uid' => $firebaseUid,
                    'favorite_user_firebase_uid' => $favUid,
                    'created_by' => $firebaseUid,
                    'updated_by' => $firebaseUid,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        // Create sales_rep_users record for reps with stats
        if ($roleId === Role::REPRESENTATIVE) {
            DB::table('sales_rep_users')->insertOrIgnore([
                'user_firebase_uid' => $firebaseUid,
                'avg_rating' => isset($doc['avgRating']) ? (float) $doc['avgRating'] : 0.00,
                'ratings_count' => isset($doc['noOfRatings']) ? (int) $doc['noOfRatings'] : 0,
                'is_subscribed' => false,
                'created_by' => $firebaseUid,
                'updated_by' => $firebaseUid,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Migrate industry string → user_industries
        if (! empty($doc['industry'])) {
            $industryId = DB::table('industries')
                ->where('name', $doc['industry'])
                ->value('id');

            if (! $industryId) {
                $industryId = DB::table('industries')->insertGetId([
                    'name' => $doc['industry'],
                    'created_by' => $firebaseUid,
                    'updated_by' => $firebaseUid,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('user_industries')->insertOrIgnore([
                'user_firebase_uid' => $firebaseUid,
                'industry_id' => $industryId,
                'is_primary' => true,
                'created_by' => $firebaseUid,
                'updated_by' => $firebaseUid,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    protected function getDocId(array $doc): string
    {
        return $doc['_id'] ?? $doc['userId'] ?? 'unknown_'.uniqid();
    }
}
