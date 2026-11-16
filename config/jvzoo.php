<?php

return [

    /*
    |--------------------------------------------------------------------------
    | JVZoo IPN Secret Key
    |--------------------------------------------------------------------------
    |
    | Used to verify incoming IPN requests via the cverify hash.
    |
    */

    'secret_key' => env('JVZOO_SECRET_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Welcome Email Notification
    |--------------------------------------------------------------------------
    |
    | Optional address that receives a copy of welcome emails sent to new buyers.
    |
    */

    'welcome_notify_email' => env('JVZOO_WELCOME_NOTIFY_EMAIL'),

];
