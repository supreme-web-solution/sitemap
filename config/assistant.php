<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Website Assistant Limits
    |--------------------------------------------------------------------------
    |
    | The AI assistant embedded in generated websites answers visitors with a
    | paid model, so each website has a daily message allowance. Once it is
    | used up the widget keeps working as a "leave your details" form.
    |
    */

    'daily_message_cap' => (int) env('ASSISTANT_DAILY_MESSAGE_CAP', 500),

    'history_turns' => 8,

    'max_message_length' => 500,

    /*
    | Platform kill-switch for live lip-synced talking avatars (Simli).
    | When false, the editor hides the toggle and every widget stays on still mode.
    | Also requires SIMLI_API_KEY in services.simli.
    */
    'talking_avatar_available' => (bool) env('ASSISTANT_TALKING_AVATAR_AVAILABLE', false),

    'talking_avatar_max_session_seconds' => (int) env('ASSISTANT_TALKING_AVATAR_MAX_SESSION', 600),

    'talking_avatar_max_idle_seconds' => (int) env('ASSISTANT_TALKING_AVATAR_MAX_IDLE', 180),

];
