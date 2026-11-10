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

];
