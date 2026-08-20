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
            // External / server API (API-key based). See https://whatify.in/api-docs
            'base_url' => env('WHATIFY_BASE_URL', 'https://whatify.in/api/v1/external'),
            'api_key' => env('WHATIFY_API_KEY', ''),
            'api_secret' => env('WHATIFY_API_SECRET', ''),
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
