<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Command Center AI employee (OpenAI — separate from Anthropic site builder)
    |--------------------------------------------------------------------------
    */

    'employee_name' => env('COMMAND_CENTER_EMPLOYEE_NAME', 'Mira'),

    'employee_title' => env('COMMAND_CENTER_EMPLOYEE_TITLE', 'Agency operator'),

    /** Public URL for avatar image. Defaults to the bundled Mira portrait. */
    'employee_avatar_url' => env('COMMAND_CENTER_EMPLOYEE_AVATAR_URL', '/images/mira-avatar.jpg'),

    'openai_model' => env('COMMAND_CENTER_OPENAI_MODEL', env('OPENAI_MODEL', 'gpt-4o-mini')),

    'whatsapp' => [
        'number' => env('ZERNIO_WHATSAPP_NUMBER'),
        'account_id' => env('ZERNIO_WHATSAPP_ACCOUNT_ID'),
        'webhook_secret' => env('ZERNIO_WEBHOOK_SECRET'),
    ],

];
