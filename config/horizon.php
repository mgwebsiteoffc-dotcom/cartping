<?php

use Illuminate\Support\Str;

return [
    'domain' => env('HORIZON_DOMAIN'),
    'path' => env('HORIZON_PATH', 'horizon'),
    'use' => 'default',

    'prefix' => env('HORIZON_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_'),

    'middleware' => ['web', 'auth.store'],

    /*
    |--------------------------------------------------------------------------
    | Queue levels
    |--------------------------------------------------------------------------
    | high      : inbound WhatsApp messages, live agent takeovers (latency critical)
    | default   : notifications, template sends, RAG indexing
    | low       : analytics rollups, enrichment, retention jobs
    | webhooks  : Shopify / WhatsApp webhook dispatch
    */
    'waits' => [
        'redis:high' => 60,
        'redis:default' => 30,
        'redis:low' => 10,
        'redis:webhooks' => 60,
    ],

    'trim' => [
        'recent' => 60,
        'pending' => 120,
        'completed' => 60,
        'recent_failed' => 900,
        'failed' => 14400,
        'monitored' => 900,
    ],

    'silenced' => [],

    'metrics' => [
        'trim_snapshots' => [
            'job' => 24,
            'queue' => 24,
        ],
    ],

    'terminate_timeout' => 60,

    'fast_termination' => false,

    'environments' => [
        'production' => [
            'supervisor-1' => [
                'connection' => 'redis',
                'queue' => ['high', 'default', 'webhooks'],
                'balance' => 'auto',
                'autoScalingStrategy' => 'time',
                'maxProcesses' => 12,
                'maxTime' => 0,
                'maxJobs' => 0,
                'memory' => 128,
                'tries' => 3,
                'timeout' => 120,
                'nice' => 0,
            ],
            'supervisor-2' => [
                'connection' => 'redis',
                'queue' => ['low'],
                'balance' => 'simple',
                'maxProcesses' => 4,
                'maxTime' => 0,
                'maxJobs' => 0,
                'memory' => 128,
                'tries' => 2,
                'timeout' => 300,
                'nice' => 10,
            ],
        ],
        'local' => [
            'supervisor-1' => [
                'connection' => 'redis',
                'queue' => ['high', 'default', 'webhooks', 'low'],
                'balance' => 'auto',
                'maxProcesses' => 8,
                'maxTime' => 0,
                'maxJobs' => 0,
                'memory' => 128,
                'tries' => 3,
                'timeout' => 120,
                'nice' => 0,
            ],
        ],
    ],
];
