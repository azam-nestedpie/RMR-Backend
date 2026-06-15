<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Support\Facades\DB;

class LanguageEndpointTest extends V1TestCase
{
    public function test_authenticated_user_can_update_language(): void
    {
        $user = $this->authAsRole('rater');

        $this->putJson('/api/v1/users/language', ['locale' => 'es'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Language updated successfully.')
            ->assertJsonPath('data.firebase_uid', $user->firebase_uid)
            ->assertJsonPath('data.first_name', $user->first_name)
            ->assertJsonPath('data.last_name', $user->last_name)
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonPath('data.prefered_locale', 'es');
    }

    public function test_user_preference_persists_in_database(): void
    {
        $user = $this->authAsRole('rater');

        $this->putJson('/api/v1/users/language', ['locale' => 'pt']);

        $this->assertDatabaseHas('users', [
            'firebase_uid' => $user->firebase_uid,
            'prefered_locale' => 'pt',
        ]);
    }

    public function test_unauthenticated_request_returns_unauthorized(): void
    {
        $this->putJson('/api/v1/users/language', ['locale' => 'en'])
            ->assertUnauthorized();
    }

    public function test_invalid_locale_returns_validation_error(): void
    {
        $this->authAsRole('rater');

        $this->putJson('/api/v1/users/language', ['locale' => 'fr'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['locale']);
    }

    public function test_missing_locale_returns_validation_error(): void
    {
        $this->authAsRole('rater');

        $this->putJson('/api/v1/users/language', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['locale']);
    }

    public function test_rating_questions_honor_user_locale(): void
    {
        $user = $this->authAsRole('rater');
        $this->assignIndustry($user, 'Marketing');

        $this->putJson('/api/v1/users/language', ['locale' => 'es']);

        $industryId = DB::table('industries')
            ->where('name', 'Marketing')
            ->value('id');

        $response = $this->getJson("/api/v1/industries/{$industryId}/rating-questions");

        $response->assertOk();
        $response->assertJsonPath('success', true);

        $questions = $response->json('data');
        $this->assertNotEmpty($questions);

        foreach ($questions as $question) {
            $this->assertArrayHasKey('title', $question);
            $this->assertArrayNotHasKey('title_en', $question);
            $this->assertArrayNotHasKey('title_es', $question);
            $this->assertArrayNotHasKey('title_pt', $question);
        }
    }
}
