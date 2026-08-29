<?php

return [
    'max_body_bytes' => (int) env('HOOKSCOPE_MAX_BODY_BYTES', 1_048_576),
    'throttle_per_minute' => (int) env('HOOKSCOPE_THROTTLE_PER_MINUTE', 120),
    'throttle_global_per_minute' => (int) env('HOOKSCOPE_THROTTLE_GLOBAL_PER_MINUTE', 600),
];
