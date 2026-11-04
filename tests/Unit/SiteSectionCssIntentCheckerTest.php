<?php

use App\Services\SiteBuilder\SiteSectionCssIntentChecker;

test('center request fails for any section when css lacks centering rules', function () {
    expect(SiteSectionCssIntentChecker::issues(
        'centralize the hero section text over the image',
        '#hero { padding: 2rem; }',
        'hero',
    ))->not->toBeEmpty();

    expect(SiteSectionCssIntentChecker::issues(
        'center the footer content',
        '.site-footer { padding: 2rem; }',
        'footer',
    ))->not->toBeEmpty();
});

test('center request passes when css centers content', function () {
    $css = ".hero .container { text-align: center; align-items: center; }\n.hero-actions { justify-content: center; }";

    expect(SiteSectionCssIntentChecker::issues(
        'center the hero headline and buttons',
        $css,
        'hero',
    ))->toBeEmpty();

    expect(SiteSectionCssIntentChecker::issues(
        'center everything in the footer',
        str_replace('hero', 'site-footer', $css),
        'footer',
    ))->toBeEmpty();
});

test('footer column balance requires flex or grid alignment rules', function () {
    expect(SiteSectionCssIntentChecker::issues(
        'balance the footer columns evenly',
        '.site-footer { color: #fff; }',
        'footer',
    ))->not->toBeEmpty();

    expect(SiteSectionCssIntentChecker::issues(
        'balance the footer columns evenly',
        '.footer-main { display: grid; grid-template-columns: repeat(4, 1fr); gap: 2rem; }',
        'footer',
    ))->toBeEmpty();
});
