<?php

return [
    'max_body_bytes' => (int) env('HOOKSCOPE_MAX_BODY_BYTES', 1_048_576),
    'throttle_per_minute' => (int) env('HOOKSCOPE_THROTTLE_PER_MINUTE', 120),
    'throttle_global_per_minute' => (int) env('HOOKSCOPE_THROTTLE_GLOBAL_PER_MINUTE', 600),
    'allow_private_targets' => filter_var(env('HOOKSCOPE_ALLOW_PRIVATE_TARGETS', false), FILTER_VALIDATE_BOOLEAN),
    'replay_connect_timeout' => (int) env('HOOKSCOPE_REPLAY_CONNECT_TIMEOUT', 2),
    'replay_timeout' => (int) env('HOOKSCOPE_REPLAY_TIMEOUT', 8),
    'replay_snippet_bytes' => (int) env('HOOKSCOPE_REPLAY_SNIPPET_BYTES', 8192),
    'demo' => [
        'email' => 'demo@hookscope.test',
        'password' => 'password',
        'name' => 'Demo User',
        'endpoint' => 'Demo',
        'base_url' => env('HOOKSCOPE_DEMO_BASE_URL', 'http://nginx'),
    ],
];
