<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Firebase Cloud Messaging Configuration
    |--------------------------------------------------------------------------
    */
    'project_id' => env('FCM_PROJECT_ID', env('FIREBASE_PROJECT_ID', '')),
    'credentials_path' => env('FIREBASE_CREDENTIALS', ''),
    'server_key' => env('FCM_SERVER_KEY', ''),
];
