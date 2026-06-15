<?php

namespace Tests\Feature\Api\V1;

class FavoriteEndpointsTest extends V1TestCase
{
    public function test_user_can_add_list_and_remove_favorite_users(): void
    {
        $user = $this->authAsRole('rater');
        $favorite = $this->createUserWithRole('rep', [
            'first_name' => 'Jordan',
            'last_name' => 'Miles',
        ]);

        $this->postJson('/api/v1/users/favorite', [
            'firebase_uid' => $favorite->firebase_uid,
        ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'User added to favorites.');

        $this->assertDatabaseHas('user_favorites', [
            'user_firebase_uid' => $user->firebase_uid,
            'favorite_user_firebase_uid' => $favorite->firebase_uid,
        ]);

        $this->getJson('/api/v1/favorites')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.firebase_uid', $favorite->firebase_uid);

        $this->deleteJson("/api/v1/users/{$favorite->firebase_uid}/favorite")
            ->assertOk()
            ->assertJsonPath('message', 'User removed from favorites.');

        $this->assertDatabaseMissing('user_favorites', [
            'user_firebase_uid' => $user->firebase_uid,
            'favorite_user_firebase_uid' => $favorite->firebase_uid,
        ]);
    }

    public function test_user_cannot_favorite_themselves(): void
    {
        $user = $this->authAsRole('rater');

        $this->postJson('/api/v1/users/favorite', [
            'firebase_uid' => $user->firebase_uid,
        ])
            ->assertStatus(400)
            ->assertJsonPath('message', 'You cannot favorite yourself.');
    }
}
