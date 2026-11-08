<?php

return [
    'enabled' => (bool) env('OPERATIONAL_NOTIFICATIONS_ENABLED', true),
    'connection' => env('NOTIFICATION_QUEUE_CONNECTION', 'database'),
    'in_app_queue' => 'notifications',
    'whatsapp_queue' => 'whatsapp',
    'fonnte' => [
        // Explicit opt-in: never send WhatsApp on a fresh installation.
        'enabled' => (bool) env('FONNTE_ENABLED', false),
        'token' => env('FONNTE_TOKEN'),
    ],
];
