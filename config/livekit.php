<?php

return [
    /*
    |--------------------------------------------------------------------------
    | LiveKit Cloud Credentials
    |--------------------------------------------------------------------------
    |
    | LiveKit Cloud URL, API Key, and API Secret for real-time audio rooms.
    |
    */
    'url' => env('LIVEKIT_URL', 'https://your-project.livekit.cloud'),

    // Where this server reaches LiveKit's API (create/delete rooms).
    // For LiveKit Cloud, leave empty so it automatically hits LIVEKIT_URL.
    'api_url' => env('LIVEKIT_API_URL', ''),
    'api_key' => env('LIVEKIT_API_KEY', ''),
    'api_secret' => env('LIVEKIT_API_SECRET', ''),

    /*
    |--------------------------------------------------------------------------
    | Token and Room Lifetimes (in seconds)
    |--------------------------------------------------------------------------
    */
    'token_ttl' => (int) env('LIVEKIT_TOKEN_TTL', 3600),
    'empty_timeout' => (int) env('LIVEKIT_EMPTY_TIMEOUT', 300),
    'max_duration' => (int) env('LIVEKIT_MAX_DURATION', 7200),
    'ringing_timeout' => (int) env('CALL_RINGING_TIMEOUT', 30),
];
