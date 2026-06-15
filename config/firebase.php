<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Firebase Configuration
    |--------------------------------------------------------------------------
    | Set these in your .env. Never commit credentials.
    |
    | The service account already contains the Firebase project ID, so
    | FIREBASE_PROJECT_ID is only needed when you want to override it.
    |
    | FIREBASE_CREDENTIALS=storage/serviceAccountKeyFile.json
    | FIREBASE_CREDENTIALS_JSON={"type":"service_account",...}
    |
    | FIREBASE_API_KEY is only needed if you call Firebase's public REST
    | endpoints directly. The Admin SDK does not require it.
    */

    'project_id' => env('FIREBASE_PROJECT_ID'),
    'api_key' => env('FIREBASE_API_KEY'),
    'credentials' => env('FIREBASE_CREDENTIALS', 'storage/serviceAccountKeyFile.json'),

    // Raw JSON credentials for Docker/CI environments (base64 or raw JSON string)
    'credentials_json' => env('FIREBASE_CREDENTIALS_JSON'),

    // Legacy Firebase REST Auth endpoint (not required by the Admin SDK flow)
    'auth_url' => 'https://identitytoolkit.googleapis.com/v1/accounts:signInWithPassword',

    // Firestore REST base URL — set FIREBASE_FIRESTORE_URL directly if you do
    // not want to provide FIREBASE_PROJECT_ID separately for migration scripts.
    'firestore_url' => env(
        'FIREBASE_FIRESTORE_URL',
        'https://firestore.googleapis.com/v1/projects/'.env('FIREBASE_PROJECT_ID').'/databases/(default)/documents'
    ),
];
