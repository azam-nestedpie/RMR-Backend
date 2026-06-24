<?php

namespace Tests\Feature\Api\V1;

use App\Models\Rating;
use App\Models\RatingRequest;
use App\Models\Role;
use App\Models\Status;
use App\Models\User;
use App\Services\V1\RatingService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Mockery;

class RatingEndpointsTest extends V1TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_store_returns_created_payload(): void
    {
        $authUser = $this->authAsRole(Role::RATER);
        $rep = $this->createUserWithRole(Role::REPRESENTATIVE);

        $service = Mockery::mock(RatingService::class);
        $service->shouldReceive('submit')
            ->once()
            ->with(Mockery::on(fn (User $user) => $user->firebase_uid === $authUser->firebase_uid), Mockery::subset([
                'rep_uid' => $rep->firebase_uid,
            ]))
            ->andReturn(['rating_uuid' => 'rating-1', 'average_score' => 4.5, 'rated_at' => now()->toDateTimeString()]);
        $this->instance(RatingService::class, $service);

        $questionId = (int) DB::table('rating_questions')->value('id');

        $this->assignIndustry($authUser, 'Marketing');

        $this->postJson('/api/v1/ratings', [
            'rep_uid' => $rep->firebase_uid,
            'items' => [
                ['question_id' => $questionId, 'score' => 5],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Rating submitted successfully.')
            ->assertJsonPath('data.rating_uuid', 'rating-1');
    }

    public function test_index_returns_received_collection_for_rep(): void
    {
        $authUser = $this->authAsRole(Role::REPRESENTATIVE);
        $rating = $this->ratingModel('rating-2', 4.0);

        $service = Mockery::mock(RatingService::class);
        $service->shouldReceive('forAuthenticatedUser')
            ->once()
            ->with(Mockery::on(fn (User $user) => $user->firebase_uid === $authUser->firebase_uid), [
                'date_from' => '2026-01-01',
                'date_to' => '2026-01-31',
            ])
            ->andReturn(new LengthAwarePaginator([$rating], 1, 20, 1));
        $this->instance(RatingService::class, $service);

        $this->getJson('/api/v1/ratings?date_from=2026-01-01&date_to=2026-01-31')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.firebase_uid', 'rater-1');
    }

    public function test_index_returns_given_collection_for_rater(): void
    {
        $authUser = $this->authAsRole(Role::RATER);
        $rating = $this->ratingModel('rating-3', 3.5);

        $service = Mockery::mock(RatingService::class);
        $service->shouldReceive('forAuthenticatedUser')
            ->once()
            ->with(Mockery::on(fn (User $user) => $user->firebase_uid === $authUser->firebase_uid), [])
            ->andReturn(new LengthAwarePaginator([$rating], 1, 20, 1));
        $this->instance(RatingService::class, $service);

        $this->getJson('/api/v1/ratings')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.firebase_uid', 'rep-1');
    }

    public function test_for_user_returns_collection(): void
    {
        $this->authAsRole(Role::RATER);
        $rating = $this->ratingModel('rating-4', 4.2);

        $service = Mockery::mock(RatingService::class);
        $service->shouldReceive('forUser')->once()->with('user-123')->andReturn(
            new LengthAwarePaginator([$rating], 1, 20, 1)
        );
        $this->instance(RatingService::class, $service);

        $this->getJson('/api/v1/ratings/user/user-123')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.firebase_uid', 'rep-1');
    }

    public function test_average_by_question_returns_stats_payload(): void
    {
        $this->authAsRole(Role::RATER);

        $service = Mockery::mock(RatingService::class);
        $service->shouldReceive('averageByQuestion')->once()->with('user-123', Mockery::any())->andReturn(collect([
            ['question_id' => 1, 'average_score' => 4.5],
        ]));
        $this->instance(RatingService::class, $service);

        $this->getJson('/api/v1/ratings/user/user-123/average-by-question')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.firebase_uid', 'user-123')
            ->assertJsonPath('data.avg_by_question.0.question_id', 1);
    }

    public function test_requests_returns_received_collection_for_rater(): void
    {
        $authUser = $this->authAsRole(Role::RATER);
        $received = new RatingRequest([
            'firebase_uuid' => 'received-rating-request-1',
            'requested_at' => now(),
        ]);
        $received->setRelation('requester', $this->makeUser(['firebase_uid' => 'rep-1']));
        $received->setRelation('target', $this->makeUser(['firebase_uid' => $authUser->firebase_uid]));
        $received->setRelation('status', new Status(['name' => 'pending']));
        $received->setRelation('rater', $this->makeUser(['firebase_uid' => $authUser->firebase_uid]));
        $received->setRelation('subjectRep', $this->makeUser(['firebase_uid' => 'rep-1']));

        $sent = new RatingRequest([
            'firebase_uuid' => 'sent-rating-request-1',
            'requested_at' => now(),
        ]);
        $sent->setRelation('requester', $this->makeUser(['firebase_uid' => $authUser->firebase_uid]));
        $sent->setRelation('target', $this->makeUser(['firebase_uid' => 'rep-2']));
        $sent->setRelation('status', new Status(['name' => 'accepted']));
        $sent->setRelation('rater', $this->makeUser(['firebase_uid' => $authUser->firebase_uid]));
        $sent->setRelation('subjectRep', $this->makeUser(['firebase_uid' => 'rep-2']));

        $service = Mockery::mock(RatingService::class);
        $service->shouldReceive('requests')
            ->once()
            ->with(Mockery::on(fn (User $user) => $user->firebase_uid === $authUser->firebase_uid))
            ->andReturn([
                'received' => collect([$received]),
            ]);
        $this->instance(RatingService::class, $service);

        $this->getJson('/api/v1/ratings/requests')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.firebase_uid', 'rep-1')
            ->assertJsonPath('data.0.request_uuid', 'received-rating-request-1')
            ->assertJsonPath('data.0.status', 'pending')
            ->assertJsonPath('data.0.direction', 'received');
    }

    private function ratingModel(string $uuid, float $score): Rating
    {
        $rating = new Rating([
            'firebase_uuid' => $uuid,
            'average_score' => $score,
            'rated_at' => now(),
        ]);

        $rater = $this->makeUser(['firebase_uid' => 'rater-1']);
        $rep = $this->makeUser(['firebase_uid' => 'rep-1']);
        $rating->setRelation('rater', $rater);
        $rating->setRelation('rep', $rep);
        $rating->setRelation('items', collect([]));

        return $rating;
    }
}
