<?php

namespace Tests\Feature\Api\V1;

use App\Models\Notification;
use App\Models\Role;
use App\Services\V1\NotificationService;
use Illuminate\Pagination\LengthAwarePaginator;
use Mockery;

class NotificationEndpointsTest extends V1TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_index_returns_notifications_and_meta(): void
    {
        $authUser = $this->authAsRole(Role::RATER);
        $notification = new Notification([
            'firebase_uuid' => 'note-1',
            'message' => 'You received a rating',
            'screen' => 'ratings',
            'tab_index' => 0,
            'is_read' => false,
            'sent_at' => now(),
        ]);

        $service = Mockery::mock(NotificationService::class);
        $service->shouldReceive('unreadCount')->once()->with(Mockery::on(fn ($user) => $user->firebase_uid === $authUser->firebase_uid))->andReturn(1);
        $service->shouldReceive('markAllRead')->once()->with(Mockery::on(fn ($user) => $user->firebase_uid === $authUser->firebase_uid))->andReturn(1);
        $service->shouldReceive('list')->once()->with(Mockery::on(fn ($user) => $user->firebase_uid === $authUser->firebase_uid))->andReturn(
            new LengthAwarePaginator([$notification], 1, 15, 1)
        );
        $this->instance(NotificationService::class, $service);

        $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.unread_count', 0)
            ->assertJsonPath('data.0.firebase_uuid', 'note-1');
    }
}
