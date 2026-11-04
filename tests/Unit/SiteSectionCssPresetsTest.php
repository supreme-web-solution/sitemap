<?php

use App\Services\SiteBuilder\SiteSectionCss;
use App\Services\SiteBuilder\SiteSectionCssPresets;

test('hero centre preset validates and targets the container wrapper', function () {
    $css = SiteSectionCssPresets::heroCenteredOverlay();

    expect(SiteSectionCss::validate('hero', $css))->toBeEmpty()
        ->and($css)->toContain('.hero .container')
        ->and($css)->toContain('align-items: center');
});

test('hero centre preset does not match unrelated copy edits', function () {
    expect(SiteSectionCssPresets::match('hero', 'change the headline to Local Experts'))->toBeNull();
});
