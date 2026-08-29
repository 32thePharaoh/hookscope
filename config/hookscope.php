<?php

return [
    'max_body_bytes' => (int) env('HOOKSCOPE_MAX_BODY_BYTES', 1_048_576),
    'throttle_per_minute' => (int) env('HOOKSCOPE_THROTTLE_PER_MINUTE', 120),
    'throttle_global_per_minute' => (int) env('HOOKSCOPE_THROTTLE_GLOBAL_PER_MINUTE', 600),
    'demo' => [
        'email' => 'demo@hookscope.test',
        'password' => 'password',
        'name' => 'Demo User',
        'endpoint' => 'Demo',
        'base_url' => env('HOOKSCOPE_DEMO_BASE_URL', 'http://nginx'),
    ],
];
