<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Overpass Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the Overpass AI Bridge service that handles embeddings,
    | chat operations, and Python script execution.
    |
    */

    'script_path' => env('OVERPASS_SCRIPT_PATH', base_path('overpass-ai/main.py')),

    'timeout' => (int) env('OVERPASS_TIMEOUT', 90),

    'max_output_length' => (int) env('OVERPASS_MAX_OUTPUT', 10000),

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'organization' => env('OPENAI_ORGANIZATION'),
    ],

    'logging' => [
        'enabled' => env('OVERPASS_LOGGING', true),
        'log_channel' => env('OVERPASS_LOG_CHANNEL', 'default'),
    ],

    'error_handling' => [
        'fallback_enabled' => env('OVERPASS_FALLBACK_ENABLED', true),
        'retry_attempts' => env('OVERPASS_RETRY_ATTEMPTS', 3),
        'retry_delay' => env('OVERPASS_RETRY_DELAY', 1000),
    ],
];
