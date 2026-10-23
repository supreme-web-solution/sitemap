<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Opportunity score
    |--------------------------------------------------------------------------
    |
    | The score is a transparent prioritisation aid, not a prediction. Each
    | component produces a ratio between 0 and 1 that is multiplied by its
    | weight. Weights should add up to 100.
    |
    */

    'score_weights' => [
        'website_need' => 30,
        'website_quality' => 25,
        'business_activity' => 20,
        'contactability' => 15,
        'profile_fit' => 10,
    ],

    'score_tiers' => [
        'hot' => 70,
        'warm' => 45,
    ],

    /*
    |--------------------------------------------------------------------------
    | Website audit
    |--------------------------------------------------------------------------
    */

    'audit' => [
        'timeout_seconds' => (int) env('AUDIT_TIMEOUT', 8),
        'max_bytes' => (int) env('AUDIT_MAX_BYTES', 1_500_000),
        'max_redirects' => 3,
        'user_agent' => env('AUDIT_USER_AGENT', 'SitemapAuditBot/1.0'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Google reviews (testimonials)
    |--------------------------------------------------------------------------
    |
    | Only reviews at or above the minimum rating, with real written text of at
    | least the minimum length, are kept for the website. Fetching costs about
    | $0.25 per 1,000 reviews, so the number requested per business is capped.
    |
    */

    'reviews' => [
        'fetch_limit' => (int) env('REVIEWS_FETCH_LIMIT', 60),
        'minimum_rating' => 4,
        'minimum_length' => 25,
        'maximum_length' => 400,
        'stored_per_lead' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Follow-ups
    |--------------------------------------------------------------------------
    */

    'follow_up_days_after_contact' => 3,
    'follow_up_days_after_preview_interest' => 0,

    /*
    |--------------------------------------------------------------------------
    | Outreach
    |--------------------------------------------------------------------------
    */

    'outreach_emails_per_minute' => 20,

    /*
    | Text and WhatsApp messages one team may send per minute.
    */
    'messages_per_minute' => 30,

];
