<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Firestore → MySQL Migration Configuration
    |--------------------------------------------------------------------------
    */

    // Documents per API call batch
    'batch_size' => env('MIGRATION_BATCH_SIZE', 200),

    // Milliseconds between batches to avoid rate limiting
    'batch_delay_ms' => env('MIGRATION_BATCH_DELAY_MS', 200),

    // Max retries per failed document before marking as failed
    'max_retries' => env('MIGRATION_MAX_RETRIES', 3),

    // Run order — DO NOT change. FK dependencies require this exact sequence.
    'collections' => [
        'users',
        'external_users',
        'requests',
        'connections',
        'ratings',
        'notifications',
    ],

    // Firestore collection IDs used by the CLI importer. Keep the target keys
    // above stable because they map to MySQL services and tables.
    'firestore_collections' => [
        'users' => env('MIGRATION_FIRESTORE_USERS_COLLECTION', 'Users'),
        'external_users' => env('MIGRATION_FIRESTORE_EXTERNAL_USERS_COLLECTION', 'External Users'),
        'requests' => env('MIGRATION_FIRESTORE_REQUESTS_COLLECTION', 'Requests'),
        'connections' => env('MIGRATION_FIRESTORE_CONNECTIONS_COLLECTION', 'Connections'),
        'ratings' => env('MIGRATION_FIRESTORE_RATINGS_COLLECTION', 'New Ratings'),
        'notifications' => env('MIGRATION_FIRESTORE_NOTIFICATIONS_COLLECTION', 'Notifications'),
    ],

    // true = halt entire migration on first error (debug mode)
    // false = log and continue (production mode)
    'stop_on_error' => env('MIGRATION_STOP_ON_ERROR', false),

    // Log channel for migration output (separate from app logs)
    'log_channel' => env('MIGRATION_LOG_CHANNEL', 'migration'),

    // Verify table row counts after each collection migration
    'verify_counts' => env('MIGRATION_VERIFY_COUNTS', true),

    // Secret key to protect the migration API endpoint
    // Set a strong random string: php artisan key:generate --show
    'secret' => env('MIGRATION_SECRET'),
];
