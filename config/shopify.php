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
    // OAuth callback — must be whitelisted under the app's "Allowed redirection URL(s)".
    // Defaults to {APP_URL}/auth/shopify/callback.
    'redirect_uri' => env('SHOPIFY_REDIRECT_URI', null),
    'api_version' => '2026-07',
    'base_url' => env('SHOPIFY_BASE_URL', 'https://{shop}/admin'),
    // Billing is handled by the Shopify Billing API.
    // In production set SHOPIFY_BILLING_TEST=false so real charges are created.
    'billing_test' => env('SHOPIFY_BILLING_TEST', true),
];
