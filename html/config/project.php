<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Authentication & Security Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for certificate-based authentication, device management,
    | and security monitoring features.
    |
    */

    'auth' => [
        'certificates' => [
            'valid_days' => env('CA_VALID_DAY', 30),
            'cert_path' => base_path('certs/ca.cert.pem'),
            'key_path' => base_path('certs/ca.key.pem'),
            'pass_phrase' => env('CA_PASS_PHRASE', ''),
        ],

        'devices' => [
            'max_concurrent_per_user' => env('MAX_CONCURRENT_DEVICES', 3),
            'max_total_per_user' => env('MAX_DEVICES_PER_USER', 5),
        ],

        'security' => [
            'suspicious_activity_threshold' => env('SUSPICIOUS_ACTIVITY_THRESHOLD', 5),
            'suspicious_activity_window_minutes' => env('SUSPICIOUS_ACTIVITY_THRESHOLD_TIME', 5),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Infrastructure Configuration
    |--------------------------------------------------------------------------
    |
    | Server infrastructure, caching, and system-level settings.
    |
    */

    'infrastructure' => [
        'servers' => env('APP_SERVERS', ['Server-A', 'Server-B', 'Server-C']),
        'cache' => [
            'default_ttl' => env('CACHE_TTL', 2592000), // 30 days in seconds
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Synchronization Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for data synchronization, connectivity testing,
    | and high-performance sync job processing.
    |
    */

    'sync' => [
        'connectivity' => [
            'cache_ttl' => env('SYNC_CACHE_TTL', 300), // 5 minutes
            'test_host' => env('SYNC_TEST_HOST', '8.8.8.8'),
            'test_url' => env('SYNC_TEST_URL', 'https://www.google.com/favicon.ico'),
            'test_size_bytes' => env('SYNC_TEST_SIZE_BYTES', 1000),
            'bandwidth_cache_ttl' => env('SYNC_BANDWIDTH_CACHE_TTL', 1800), // 30 minutes
        ],

        'performance' => [
            'target_events_per_second' => env('SYNC_TARGET_EPS', 10000),
            'max_memory_usage_mb' => env('SYNC_MAX_MEMORY_MB', 512),
        ],

        'job' => [
            'queue_name' => env('SYNC_JOB_QUEUE', 'high_performance_sync'),
            'processing' => [
                'chunk_size' => env('SYNC_JOB_CHUNK_SIZE', 100),
                'max_chunks_per_job' => env('SYNC_JOB_MAX_CHUNKS', 100),
                'max_events_per_job' => env('SYNC_JOB_MAX_EVENTS', 10000),
            ],
            'execution' => [
                'timeout_seconds' => env('SYNC_JOB_TIMEOUT', 3600), // 1 hour
                'max_tries' => env('SYNC_JOB_MAX_TRIES', 2),
                'max_exceptions' => env('SYNC_JOB_MAX_EXCEPTIONS', 3),
            ],
            'monitoring' => [
                'metrics_enabled' => env('SYNC_JOB_METRICS_ENABLED', true),
                'redis_prefix' => env('SYNC_JOB_REDIS_PREFIX', 'sync_metrics'),
            ],
        ],
    ],
];
