<?php

use App\Support\AdminEmails;

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

    'emails' => AdminEmails::parse(env('ADMIN_EMAILS')),

];
