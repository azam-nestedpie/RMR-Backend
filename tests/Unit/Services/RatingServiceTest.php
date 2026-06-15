<?php

namespace Tests\Unit\Services;

use App\Models\Rating;
use App\Models\RatingItem;
use App\Models\SalesRepUser;
use App\Models\User;
use App\Repositories\RatingRepository;
use App\Services\Migration\BaseMigrationService;
use App\Services\Migration\MigrationLogger;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RatingServiceTest extends TestCase
{
    use RefreshDatabase;

    private RatingRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->repo = new RatingRepository;
    }

    public function test_average_by_question_returns_correct_averages(): void
    {
        $rater = User::factory()->create();
        $rep = User::factory()->create();
        $questionThirtyId = (int) \DB::table('rating_questions')->where('question_code', 30)->value('id');
        $questionFortyId = (int) \DB::table('rating_questions')->where('question_code', 40)->value('id');

        $firstRating = Rating::create([
            'firebase_uuid' => (string) \Str::uuid(),
            'rater_firebase_uid' => $rater->firebase_uid,
            'rep_firebase_uid' => $rep->firebase_uid,
            'average_score' => 4.5,
            'rated_at' => now(),
            'created_by' => $rater->firebase_uid,
            'updated_by' => $rater->firebase_uid,
        ]);

        $secondRating = Rating::create([
            'firebase_uuid' => (string) \Str::uuid(),
            'rater_firebase_uid' => $rater->firebase_uid,
            'rep_firebase_uid' => $rep->firebase_uid,
            'average_score' => 3.0,
            'rated_at' => now(),
            'created_by' => $rater->firebase_uid,
            'updated_by' => $rater->firebase_uid,
        ]);

        RatingItem::create([
            'rating_id' => $firstRating->id,
            'question_id' => $questionThirtyId,
            'score' => 4,
            'created_by' => $rater->firebase_uid,
            'updated_by' => $rater->firebase_uid,
        ]);
        RatingItem::create([
            'rating_id' => $firstRating->id,
            'question_id' => $questionFortyId,
            'score' => 5,
            'created_by' => $rater->firebase_uid,
            'updated_by' => $rater->firebase_uid,
        ]);
        RatingItem::create([
            'rating_id' => $secondRating->id,
            'question_id' => $questionThirtyId,
            'score' => 3,
            'created_by' => $rater->firebase_uid,
            'updated_by' => $rater->firebase_uid,
        ]);
        RatingItem::create([
            'rating_id' => $secondRating->id,
            'question_id' => $questionFortyId,
            'score' => 3,
            'created_by' => $rater->firebase_uid,
            'updated_by' => $rater->firebase_uid,
        ]);

        $averages = $this->repo->averageByQuestion($rep->firebase_uid)->keyBy('question_code');

        $this->assertEquals(3.5, round((float) $averages[30]->avg_score, 2));
        $this->assertEquals(4.0, round((float) $averages[40]->avg_score, 2));
    }

    public function test_recalculate_rep_stats_updates_sales_rep_profile(): void
    {
        $rater = User::factory()->create();
        $rep = User::factory()->create();

        Rating::create([
            'firebase_uuid' => (string) \Str::uuid(),
            'rater_firebase_uid' => $rater->firebase_uid,
            'rep_firebase_uid' => $rep->firebase_uid,
            'average_score' => 4.0,
            'rated_at' => now(),
            'created_by' => $rater->firebase_uid,
            'updated_by' => $rater->firebase_uid,
        ]);

        Rating::create([
            'firebase_uuid' => (string) \Str::uuid(),
            'rater_firebase_uid' => $rater->firebase_uid,
            'rep_firebase_uid' => $rep->firebase_uid,
            'average_score' => 2.0,
            'rated_at' => now(),
            'created_by' => $rater->firebase_uid,
            'updated_by' => $rater->firebase_uid,
        ]);

        $this->repo->recalculateRepStats($rep->firebase_uid);

        $profile = SalesRepUser::findOrFail($rep->firebase_uid);

        $this->assertEquals(3.0, $profile->avg_rating);
        $this->assertSame(2, $profile->ratings_count);
    }

    public function test_parse_firestore_date_handles_microseconds(): void
    {
        $service = new class(new MigrationLogger) extends BaseMigrationService
        {
            protected string $collection = 'test';

            public function migrate(array $documents): array
            {
                return [];
            }

            protected function insertDocument(array $doc): void {}
        };

        $method = new \ReflectionMethod($service, 'parseFirestoreDate');
        $method->setAccessible(true);

        $result = $method->invoke($service, '2025-02-02 13:59:31.849159');

        $this->assertSame('2025-02-02 13:59:31', $result);
    }
}
