<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Public Shopify App (OAuth)
    |--------------------------------------------------------------------------
    */
    'api_key' => env('SHOPIFY_API_KEY', ''),
    'api_secret' => env('SHOPIFY_API_SECRET', ''),
    'scopes' => explode(',', env('SHOPIFY_API_SCOPES', 'read_products,write_products,read_orders,write_orders,read_customers,read_script_tags,write_script_tags,read_checkouts,write_checkouts')),
    'hmac_secret' => env('SHOPIFY_APP_HMAC_SECRET', ''),
    'embedded' => env('SHOPIFY_EMBEDDED', true),
    'api_version' => '2026-07',
    'base_url' => env('SHOPIFY_BASE_URL', 'https://{shop}/admin'),
];
