<?php

use App\Services\SiteBuilder\SiteImageFallback;

test('a failed photo uses an inline placeholder', function () {
    $url = SiteImageFallback::photo('#2563eb');

    expect($url)->toStartWith('data:image/svg+xml')
        ->and(rawurldecode(substr($url, strlen('data:image/svg+xml;charset=UTF-8,'))))->toContain('#2563eb');
});

test('a failed logo uses the business initial', function () {
    $url = SiteImageFallback::mark('Barber Shop', '#111111');
    $svg = rawurldecode(substr($url, strlen('data:image/svg+xml;charset=UTF-8,')));

    expect($svg)->toContain('>B<')
        ->and($svg)->toContain('#111111');
});

test('a fallback accent cannot inject markup', function () {
    $url = SiteImageFallback::photo('"><script>');
    $svg = rawurldecode(substr($url, strlen('data:image/svg+xml;charset=UTF-8,')));

    expect($svg)->toContain('#1e3a5f')
        ->and($svg)->not->toContain('<script>');
});
