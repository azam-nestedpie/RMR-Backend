<?php

namespace Tests\Feature\Api\V1;

use App\Services\Migration\ConnectionsMigrationService;
use App\Services\Migration\ExternalUsersMigrationService;
use App\Services\Migration\NotificationsMigrationService;
use App\Services\Migration\RatingsMigrationService;
use App\Services\Migration\RequestsMigrationService;
use App\Services\Migration\UsersMigrationService;
use Mockery;

class MigrationEndpointsTest extends V1TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_run_all_dispatches_each_collection(): void
    {
        $this->authAsRole('manager_of_reps');

        $this->instance(UsersMigrationService::class, $this->migrationMock(UsersMigrationService::class));
        $this->instance(ExternalUsersMigrationService::class, $this->migrationMock(ExternalUsersMigrationService::class));
        $this->instance(RequestsMigrationService::class, $this->migrationMock(RequestsMigrationService::class));
        $this->instance(ConnectionsMigrationService::class, $this->migrationMock(ConnectionsMigrationService::class));
        $this->instance(RatingsMigrationService::class, $this->migrationMock(RatingsMigrationService::class));
        $this->instance(NotificationsMigrationService::class, $this->migrationMock(NotificationsMigrationService::class));

        $this->postJson('/api/v1/migration/run-all', [
            'documents' => [
                'users' => [['id' => 1]],
                'external_users' => [['id' => 2]],
                'requests' => [['id' => 3]],
                'connections' => [['id' => 4]],
                'ratings' => [['id' => 5]],
                'notifications' => [['id' => 6]],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'All migrations completed.');
    }

    public function test_run_users_is_versioned_and_validation_safe(): void
    {
        $this->authAsRole('manager_of_reps');
        $mock = $this->migrationMock(UsersMigrationService::class);
        $this->instance(UsersMigrationService::class, $mock);

        $this->postJson('/api/v1/migration/users', [
            'documents' => [['id' => 1]],
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Users migration complete.');
    }

    public function test_run_external_users_returns_success(): void
    {
        $this->authAsRole('manager_of_reps');
        $this->instance(ExternalUsersMigrationService::class, $this->migrationMock(ExternalUsersMigrationService::class));

        $this->postJson('/api/v1/migration/external-users', [
            'documents' => [['id' => 2]],
        ])
            ->assertOk()
            ->assertJsonPath('message', 'External users migration complete.');
    }

    public function test_run_requests_returns_success(): void
    {
        $this->authAsRole('manager_of_reps');
        $this->instance(RequestsMigrationService::class, $this->migrationMock(RequestsMigrationService::class));

        $this->postJson('/api/v1/migration/requests', [
            'documents' => [['id' => 3]],
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Requests migration complete.');
    }

    public function test_run_connections_returns_success(): void
    {
        $this->authAsRole('manager_of_reps');
        $this->instance(ConnectionsMigrationService::class, $this->migrationMock(ConnectionsMigrationService::class));

        $this->postJson('/api/v1/migration/connections', [
            'documents' => [['id' => 4]],
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Connections migration complete.');
    }

    public function test_run_ratings_returns_success(): void
    {
        $this->authAsRole('manager_of_reps');
        $this->instance(RatingsMigrationService::class, $this->migrationMock(RatingsMigrationService::class));

        $this->postJson('/api/v1/migration/ratings', [
            'documents' => [['id' => 5]],
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Ratings migration complete.');
    }

    public function test_run_notifications_returns_success(): void
    {
        $this->authAsRole('manager_of_reps');
        $this->instance(NotificationsMigrationService::class, $this->migrationMock(NotificationsMigrationService::class));

        $this->postJson('/api/v1/migration/notifications', [
            'documents' => [['id' => 6]],
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Notifications migration complete.');
    }

    private function migrationMock(string $className): object
    {
        $mock = Mockery::mock($className);
        $mock->shouldReceive('migrate')
            ->once()
            ->with(Mockery::on(fn (array $documents) => is_array($documents)))
            ->andReturn([
                'success' => 1,
                'failed' => 0,
                'skipped' => 0,
            ]);

        return $mock;
    }
}
