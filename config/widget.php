<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Smart WhatsApp widget defaults
    |--------------------------------------------------------------------------
    */
    'default_type' => 'chat_widget', // simple_button | tooltip | chat_widget | smart_contextual
    'session_cookie' => env('WIDGET_SESSION_COOKIE', 'cp_widget'),

    // Entry popup: shown after N ms offering a discount for WhatsApp number capture.
    'entry_popup_delay_ms' => (int) env('WIDGET_ENTRY_POPUP_DELAY_MS', 4000),
    'entry_popup_max_per_session' => 1,

    // Exit popup: exit-intent or inactivity, shows QR code or capture form.
    'exit_popup_delay_ms' => (int) env('WIDGET_EXIT_POPUP_DELAY_MS', 2000),
    'inactivity_timeout_ms' => (int) env('WIDGET_INACTIVITY_TIMEOUT_MS', 30000),

    'available_assets' => [
        'widget' => '/js/widget.js',
        'styles' => '/css/widget.css',
    ],
];
