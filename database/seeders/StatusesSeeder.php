<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StatusesSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            'active',
            'inactive',
            'pending',
            'accepted',
            'rejected',
            'cancelled',
            'disconnected',
            'blocked',
            'deleted',
            'completed',
            'expired',
            'used',
            'failed',
            'connected',
            'not_connected',
            'connect',
            'request_sent',
            'rating_request',
            'send_rating_request',
            'send_connection_request',
        ];

        foreach ($statuses as $name) {
            DB::table('statuses')->updateOrInsert(
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

        $this->command->info('✓ Statuses seeded.');
    }
}
