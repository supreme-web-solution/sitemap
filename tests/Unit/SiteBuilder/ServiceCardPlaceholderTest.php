<?php

use App\Services\SiteBuilder\ServiceCardPlaceholder;
use App\Services\SiteBuilder\SitePages;

test('service card placeholder is a data uri image', function () {
    $url = ServiceCardPlaceholder::url('Roof inspection', 1);

    expect($url)->toStartWith('data:image/svg+xml')
        ->and($url)->toContain('Roof');
});

test('site pages always expose a service image url', function () {
    $pages = app(SitePages::class);
    $services = $pages->services([
        'services' => [
            ['title' => 'Roof repair', 'description' => 'Fix leaks.'],
        ],
    ]);

    expect($services[0]['image_url'])->toStartWith('data:image/svg+xml');
});
