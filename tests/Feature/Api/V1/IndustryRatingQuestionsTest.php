<?php

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

test('industry rating questions are returned in display order', function () {
    $industryId = (int) DB::table('industries')->where('name', 'Marketing')->value('id');

    $this->getJson("/api/v1/industries/{$industryId}/rating-questions")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.0.question_code', '10')
        ->assertJsonPath('data.0.display_order', 1)
        ->assertJsonPath('data.0.is_required', true)
        ->assertJsonPath('data.7.question_code', '200')
        ->assertJsonPath('data.9.question_code', '180');
});

test('contractor style industries use technical questions', function () {
    $industryId = (int) DB::table('industries')->where('name', 'Inspection')->value('id');

    $this->getJson("/api/v1/industries/{$industryId}/rating-questions")
        ->assertOk()
        ->assertJsonPath('data.0.question_code', '110')
        ->assertJsonPath('data.1.question_code', '120')
        ->assertJsonPath('data.9.question_code', '100');
});
