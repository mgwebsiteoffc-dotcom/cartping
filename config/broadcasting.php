<?php

return [
    'default' => env('BROADCAST_CONNECTION', 'reverb'),
    'connections' => [
        'null' => [
            'driver' => 'null',
        ],
        'log' => [
            'driver' => 'log',
        ],
        'reverb' => [
            'driver' => 'reverb',
            'key' => env('REVERB_APP_KEY', 'cartping-key'),
            'secret' => env('REVERB_APP_SECRET', 'cartping-secret'),
            'app_id' => env('REVERB_APP_ID', 'cartping-local'),
            'options' => [
                'host' => env('REVERB_HOST', '0.0.0.0'),
                'port' => env('REVERB_PORT', 8080),
                'scheme' => env('REVERB_SCHEME', 'http'),
                'useTLS' => env('REVERB_SCHEME', 'https') === 'https',
            ],
            'client_options' => [
                // Guzzle client options: see https://docs.php-http.org
            ],
        ],
    ],
];
