<?php

return [

    'shopify' => [
        'api_key' => env('SHOPIFY_API_KEY'),
        'api_secret' => env('SHOPIFY_API_SECRET'),
        'scopes' => explode(',', env('SHOPIFY_API_SCOPES', '')),
        'api_version' => env('SHOPIFY_API_VERSION', '2024-10'),
        'embedded' => env('SHOPIFY_EMBEDDED', true),
    ],

    'openrouter' => [
        'api_key' => env('OPENROUTER_API_KEY'),
        'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
        'http_referer' => env('OPENROUTER_HTTP_REFERER'),
        'site_url' => env('OPENROUTER_SITE_URL'),
    ],

    'meta' => [
        'whatsapp_token' => env('META_WHATSAPP_TOKEN'),
        'phone_number_id' => env('META_WHATSAPP_PHONE_ID'),
        'webhook_verify_token' => env('META_WEBHOOK_VERIFY_TOKEN'),
        'webhook_secret' => env('META_WEBHOOK_SECRET'),
        'access_token' => env('META_ACCESS_TOKEN'),
        'pixel_id' => env('META_PIXEL_ID'),
        'graph_version' => env('META_GRAPH_VERSION', 'v22.0'),
    ],
];
