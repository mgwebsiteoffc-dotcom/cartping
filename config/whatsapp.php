<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default provider
    |--------------------------------------------------------------------------
    | meta = Meta Cloud API, whatify = Whatify. Merchants can override this
    | per-store via their whatsapp_connections record.
    */
    'default' => env('WHATSAPP_PROVIDER', 'meta'),

    'providers' => [
        'meta' => [
            'driver' => \App\Services\Whatsapp\Providers\MetaCloudProvider::class,
            'graph_version' => env('META_GRAPH_VERSION', 'v22.0'),
            'base_url' => env('META_BASE_URL', 'https://graph.facebook.com'),
            'token' => env('META_WHATSAPP_TOKEN', ''),
            'phone_number_id' => env('META_WHATSAPP_PHONE_ID', ''),
            'webhook_verify_token' => env('META_WEBHOOK_VERIFY_TOKEN', ''),
            'webhook_secret' => env('META_WEBHOOK_SECRET', ''),
        ],
        'whatify' => [
            'driver' => \App\Services\Whatsapp\Providers\WhatifyProvider::class,
            'base_url' => env('WHATIFY_BASE_URL', 'https://api.whatify.app/v1'),
            'api_token' => env('WHATIFY_API_TOKEN', ''),
            'webhook_secret' => env('WHATIFY_WEBHOOK_SECRET', ''),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook routing
    |--------------------------------------------------------------------------
    | Providers POST inbound events to /webhooks/whatsapp/{provider} and we
    | hand the raw payload to the resolved provider for normalization.
    */
    'webhook_path' => 'webhooks/whatsapp',
];
