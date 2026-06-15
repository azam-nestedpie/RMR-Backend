<?php

use App\Services\Migration\ExternalUsersMigrationService;
use App\Services\Migration\FirestoreDocumentSource;
use App\Services\Migration\MigrationLogger;
use App\Services\Migration\RatingsMigrationService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('firestore importer uses the configured new ratings collection by default', function () {
    config()->set('migration.firestore_collections.ratings', 'New Ratings');

    $source = Mockery::mock(FirestoreDocumentSource::class);
    $this->instance(FirestoreDocumentSource::class, $source);

    foreach (['Users', 'External Users', 'Requests', 'Connections', 'New Ratings', 'Notifications'] as $collection) {
        $source->shouldReceive('documents')
            ->once()
            ->ordered()
            ->with($collection, 200)
            ->andReturn([]);
    }

    $this->artisan('app:migrate-firestore-to-mysql', ['--dry-run' => true])
        ->assertExitCode(0);
});

test('migration logger writes required legacy columns', function () {
    (new MigrationLogger)->logSuccess('ratings', 'rating-doc-1');

    $this->assertDatabaseHas('migration_logs', [
        'entity_type' => 'ratings',
        'collection' => 'ratings',
        'firestore_doc_id' => 'rating-doc-1',
        'old_id' => 'rating-doc-1',
        'status' => 'success',
    ]);
});

test('new ratings documents migrate from firestore shape', function () {
    $this->seed(DatabaseSeeder::class);

    DB::table('users')->insert([
        [
            'firebase_uid' => 'rater-uid',
            'first_name' => 'Rater',
            'email' => 'rater@example.test',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'firebase_uid' => 'rep-uid',
            'first_name' => 'Rep',
            'email' => 'rep@example.test',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $result = app(RatingsMigrationService::class)->migrate([
        [
            '_id' => 'new-rating-doc',
            'from' => 'rater-uid',
            'to' => 'rep-uid',
            'avgRating' => 10,
            'dateTime' => '2026-02-20 11:58:39.286955',
            'ratings' => [
                ['id' => 10, 'rating' => 5],
                ['id' => 30, 'rating' => 4],
            ],
        ],
    ]);

    expect($result)->toBe(['success' => 1, 'failed' => 0, 'skipped' => 0]);

    $this->assertDatabaseHas('ratings', [
        'firebase_uuid' => 'new-rating-doc',
        'rater_firebase_uid' => 'rater-uid',
        'rep_firebase_uid' => 'rep-uid',
        'average_score' => 5,
    ]);

    $ratingId = DB::table('ratings')->where('firebase_uuid', 'new-rating-doc')->value('id');
    $questionTenId = DB::table('rating_questions')->where('question_code', '10')->value('id');

    $this->assertDatabaseHas('rating_items', [
        'rating_id' => $ratingId,
        'question_id' => $questionTenId,
        'score' => 5,
    ]);
});

test('external users are stored separately and can submit ratings', function () {
    $this->seed(DatabaseSeeder::class);

    DB::table('users')->insert([
        'firebase_uid' => 'rep-uid',
        'first_name' => 'Rep',
        'email' => 'rep@example.test',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    app(ExternalUsersMigrationService::class)->migrate([
        [
            '_id' => 'external-rater-uuid',
            'name' => 'External',
            'lastName' => 'Rater',
            'email' => 'external@example.test',
            'companyName' => 'External Co',
            'position' => 'Buyer',
        ],
    ]);

    app(RatingsMigrationService::class)->migrate([
        [
            '_id' => 'external-rating-doc',
            'from' => 'external-rater-uuid',
            'to' => 'rep-uid',
            'avgRating' => 8,
            'dateTime' => '2026-02-20 11:58:39.286955',
            'ratings' => [
                ['id' => 30, 'rating' => 4],
            ],
        ],
    ]);

    $externalUserId = DB::table('external_users')->where('external_uuid', 'external-rater-uuid')->value('id');

    $this->assertDatabaseHas('external_users', [
        'external_uuid' => 'external-rater-uuid',
        'email' => 'external@example.test',
    ]);

    $this->assertDatabaseMissing('users', [
        'firebase_uid' => 'external-rater-uuid',
    ]);

    $this->assertDatabaseHas('ratings', [
        'firebase_uuid' => 'external-rating-doc',
        'rater_firebase_uid' => null,
        'external_user_id' => $externalUserId,
        'rep_firebase_uid' => 'rep-uid',
        'from_external_link' => true,
        'average_score' => 4,
    ]);
});
