<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Custom domains
    |--------------------------------------------------------------------------
    |
    | Customers point their own domain at the "edge" in front of this app. The
    | edge terminates HTTPS and forwards the original Host header. The app then
    | serves the matching website. Hosts listed here (plus the APP_URL host)
    | always belong to the dashboard and are never treated as customer sites.
    |
    */

    'app_hosts' => array_filter(array_map('trim', explode(',', (string) env('SITES_APP_HOSTS', '')))),

    // Suffix for per-site staging hosts: {site-slug}.{staging_base_host} (defaults to staging.{APP_URL host}).
    'staging_base_host' => env('SITES_STAGING_BASE_HOST'),

    // The hostname customers create a CNAME record for (e.g. sites.yourapp.com).
    'cname_target' => env('SITES_CNAME_TARGET'),

    // The IP address customers create an A record for when using a root domain.
    'server_ip' => env('SITES_SERVER_IP'),

    // Shared secret the edge sends when asking whether it may issue a certificate.
    'tls_ask_token' => env('SITES_TLS_ASK_TOKEN'),

    // Redirect plain HTTP requests on customer domains to HTTPS.
    'force_https' => (bool) env('SITES_FORCE_HTTPS', true),

    // How many domains one website may have.
    'max_domains_per_site' => 5,

    // Seconds browsers and CDNs may reuse a page, and how long stale pages may be served while refreshing.
    'cache_seconds' => 300,
    'stale_seconds' => 86400,

    // Seconds a rendered page is kept server-side. Edits clear it sooner.
    'html_cache_seconds' => 3600,

    // Supported visitor locales (ISO 639-1). English is always the default unless overridden per site.
    'locales' => [
        'en' => 'English',
        'es' => 'Español',
        'fr' => 'Français',
        'de' => 'Deutsch',
        'pt' => 'Português',
    ],

    // Extra locales an agency may add beyond the default.
    'max_extra_locales' => (int) env('SITES_MAX_EXTRA_LOCALES', 3),

    // OpenAI image model for editor/generation assists.
    'image_model' => env('OPENAI_IMAGE_MODEL', 'gpt-image-1'),

];
