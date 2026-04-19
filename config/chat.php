<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Chat Feature Toggle
    |--------------------------------------------------------------------------
    |
    | Set to true to enable the user-to-user chat system. When disabled, the
    | chat icon is hidden, and all /chat routes return 404.
    |
    */
    'enabled' => (bool) env('CHAT_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Message Encryption
    |--------------------------------------------------------------------------
    |
    | When enabled, chat message bodies are encrypted at rest using Laravel's
    | Crypt facade (AES-256-CBC with your APP_KEY). Note: rotating APP_KEY
    | will make previously encrypted messages unreadable, and full-text search
    | across encrypted messages is not supported.
    |
    */
    'encryption_enabled' => (bool) env('CHAT_ENCRYPTION_ENABLED', false),
];
