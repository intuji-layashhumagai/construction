<?php

return [
    'auth' => [
        'certification_valid_day' => env('CA_VALID_DAY', 30),
        'cert_path' => base_path('certs/ca.cert.pem'),
        'key_path' => base_path('certs/ca.key.pem'),
        'pass_phrase' => env('CA_PASS_PHRASE', ''),
    ],
];
