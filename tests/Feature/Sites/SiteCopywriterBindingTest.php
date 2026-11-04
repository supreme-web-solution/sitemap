<?php

use App\Services\SiteBuilder\ClaudeSitePlanner;
use App\Services\SiteBuilder\RuleBasedCopywriter;
use App\Services\SiteBuilder\SiteCopywriter;

test('site copywriter uses Claude when Anthropic is configured', function () {
    config([
        'services.anthropic.key' => 'ak-test',
        'services.openai.key' => 'sk-test',
    ]);

    expect(app(SiteCopywriter::class))->toBeInstanceOf(ClaudeSitePlanner::class);
});

test('site copywriter ignores OpenAI and falls back without Anthropic', function () {
    config([
        'services.anthropic.key' => null,
        'services.openai.key' => 'sk-test',
    ]);

    expect(app(SiteCopywriter::class))->toBeInstanceOf(RuleBasedCopywriter::class);
});

test('site copywriter is rule based when no AI keys are set', function () {
    config([
        'services.anthropic.key' => null,
        'services.openai.key' => null,
    ]);

    expect(app(SiteCopywriter::class))->toBeInstanceOf(RuleBasedCopywriter::class);
});
