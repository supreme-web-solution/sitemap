<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Administrator emails
    |--------------------------------------------------------------------------
    |
    | People who sign in with one of these addresses can open the admin area to
    | manage users, teams and plans. Separate several addresses with commas.
    |
    */

    'emails' => array_values(array_filter(array_map(
        fn (string $email) => strtolower(trim($email)),
        explode(',', (string) env('ADMIN_EMAILS', '')),
    ))),

];