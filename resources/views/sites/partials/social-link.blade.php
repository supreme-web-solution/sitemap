@php
    $platformKey = \Illuminate\Support\Str::lower(trim((string) $platform));
    $label = match ($platformKey) {
        'x', 'twitter' => 'X (Twitter)',
        'youtube', 'yt' => 'YouTube',
        'linkedin', 'linked-in' => 'LinkedIn',
        'facebook', 'fb' => 'Facebook',
        'instagram', 'ig' => 'Instagram',
        'tiktok' => 'TikTok',
        'pinterest' => 'Pinterest',
        'yelp' => 'Yelp',
        'nextdoor' => 'Nextdoor',
        'threads' => 'Threads',
        'whatsapp' => 'WhatsApp',
        'google', 'google_business', 'google-business' => 'Google',
        'vimeo' => 'Vimeo',
        default => \Illuminate\Support\Str::headline($platformKey !== '' ? $platformKey : 'Social'),
    };
@endphp
<a
    class="footer-social__link"
    href="{{ $url }}"
    rel="noopener noreferrer"
    target="_blank"
    title="{{ $label }}"
>
    <span class="footer-social__icon" aria-hidden="true">@include('sites.icons.social', ['platform' => $platformKey])</span>
    <span class="sr-only">{{ $label }}</span>
</a>
