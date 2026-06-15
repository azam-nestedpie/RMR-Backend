<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class IndustriesSeeder extends Seeder
{
    public function run(): void
    {
        $industries = [
            'Automotive', 'Chemical', 'Construction', 'Constructor', 'Contractor', 'Education',
            'Energy', 'Finance', 'HomeImprovement', 'Hospitality',
            'InformationTechnology', 'Inspection', 'Marketing', 'Manufacturing',
            'Medical', 'Other', 'Pharmaceutical', 'Retail', 'Services', 'Telecommunications',
        ];

        foreach ($industries as $name) {
            DB::table('industries')->updateOrInsert(
                ['name' => $name],
                [
                    'name' => $name,
                    'created_by' => null,
                    'updated_by' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $this->command->info('✓ Industries seeded: '.count($industries).' rows.');
    }
}
