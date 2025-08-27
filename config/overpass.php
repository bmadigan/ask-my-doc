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

    'timeout' => env('OVERPASS_TIMEOUT', 60),

    'max_output' => env('OVERPASS_MAX_OUTPUT', 1048576), // 1MB

    'embedding_model' => env('OVERPASS_EMBEDDING_MODEL', 'text-embedding-3-small'),

    'chat_model' => env('OVERPASS_CHAT_MODEL', 'gpt-4o-mini'),
];
