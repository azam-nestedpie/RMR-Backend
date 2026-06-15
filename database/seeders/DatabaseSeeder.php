<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * SEED ORDER IS CRITICAL — do not change.
     * RolesPermissionsSeeder must run before UsersMigrationService.
     * RatingQuestionsSeeder must run before RatingsMigrationService.
     * IndustryRatingQuestionsSeeder must run after IndustriesSeeder and RatingQuestionsSeeder.
     */
    public function run(): void
    {
        $this->call([
            StatusesSeeder::class,
            IndustriesSeeder::class,
            RolesPermissionsSeeder::class,
            RatingQuestionsSeeder::class,
            IndustryRatingQuestionsSeeder::class,
        ]);
    }
}
