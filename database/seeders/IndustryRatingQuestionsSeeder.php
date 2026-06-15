<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class IndustryRatingQuestionsSeeder extends Seeder
{
    public function run(): void
    {
        $defaultQuestionCodes = [10, 20, 30, 40, 150, 160, 70, 210, 170, 180];

        $questionCodesByIndustry = [
            'Marketing' => [10, 20, 30, 40, 150, 160, 70, 200, 170, 180],
            'Constructor' => [110, 120, 30, 40, 50, 130, 140, 80, 90, 100],
            'Contractor' => [110, 120, 30, 40, 50, 130, 140, 80, 90, 100],
            'Inspection' => [110, 120, 30, 40, 50, 130, 140, 80, 90, 100],
            'Automotive' => [10, 20, 30, 40, 150, 160, 70, 210, 170, 190],
            'Construction' => [10, 20, 30, 40, 150, 160, 70, 210, 170, 190],
            'Manufacturing' => [10, 20, 30, 40, 150, 160, 70, 210, 170, 190],
            'Medical' => [10, 20, 30, 40, 150, 160, 70, 210, 170, 190],
            'Energy' => [10, 20, 30, 40, 150, 160, 70, 210, 170, 190],
            'Chemical' => [10, 20, 30, 40, 150, 160, 70, 210, 170, 190],
        ];

        $industryIdsByName = DB::table('industries')->pluck('id', 'name');
        $questionIdsByCode = DB::table('rating_questions')->pluck('id', 'question_code');

        $mappedRows = 0;

        foreach ($industryIdsByName as $industryName => $industryId) {
            $questionCodes = $questionCodesByIndustry[$industryName] ?? $defaultQuestionCodes;

            foreach ($questionCodes as $index => $questionCode) {
                $questionId = $questionIdsByCode[$questionCode] ?? null;

                if (! $questionId) {
                    continue;
                }

                DB::table('industry_rating_questions')->updateOrInsert(
                    [
                        'industry_id' => $industryId,
                        'question_id' => $questionId,
                    ],
                    [
                        'display_order' => $index + 1,
                        'is_required' => true,
                        'created_by' => null,
                        'updated_by' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );

                $mappedRows++;
            }
        }

        $this->command->info('✓ Industry rating question mappings seeded: '.$mappedRows.' rows.');
    }
}
