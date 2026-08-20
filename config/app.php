<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    */
    'name' => env('APP_NAME', 'CartPing'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    */
    'env' => env('APP_ENV', 'production'),

    'debug' => (bool) env('APP_DEBUG', false),

    'url' => env('APP_URL', 'http://localhost'),

    'asset_url' => env('ASSET_URL'),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    */
    'timezone' => env('APP_TIMEZONE', 'UTC'),

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    */
    'locale' => env('APP_LOCALE', 'en'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    */
    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(explode(',', env('APP_PREVIOUS_KEYS', ''))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Maintenance Mode Driver
    |--------------------------------------------------------------------------
    */
    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Autoloaded Service Providers
    |--------------------------------------------------------------------------
    | Laravel auto-registers the framework service providers. Any application
    | service providers are registered in bootstrap/providers.php instead.
    | Keep this array empty.
    */
    'providers' => [],

    /*
    |--------------------------------------------------------------------------
    | Class Aliases
    |--------------------------------------------------------------------------
    | Laravel 11+ resolves facades automatically; no manual aliases are needed.
    */
    'aliases' => [],

];
