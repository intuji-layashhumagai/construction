<?php

return [
    'auth' => [
        'certification_valid_day' => env('CA_VALID_DAY', 30),
        'cert_path' => base_path('certs/ca.cert.pem'),
        'key_path' => base_path('certs/ca.key.pem'),
        'pass_phrase' => env('CA_PASS_PHRASE', ''),
        'max_concurrent_device' => env('MAX_CONCURRENT_DEVICES', 3),
        'max_device_per_user' => env('MAX_DEVICES_PER_USER', 5),
        'suspicious_activity_threshold' => env('SUSPICIOUS_ACTIVITY_THRESHOLD', 5), // count
        'suspicious_activity_threshold_time' => env('SUSPICIOUS_ACTIVITY_THRESHOLD_TIME', 5), // minutes
    ],
    'servers' => ['Server-A', 'Server-B', 'Server-C'],
    'cache_ttl' => env('CACHE_TTL', 2592000), // seconds
];
