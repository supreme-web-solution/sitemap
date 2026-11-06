<?php

use App\Services\Ai\AiProviderErrors;
use Tests\TestCase;

uses(TestCase::class);

test('detects anthropic quota messages', function () {
    expect(AiProviderErrors::isQuotaOrAuth('AI provider [anthropic] has insufficient credits or quota.'))->toBeTrue();
});

test('ignores unrelated errors', function () {
    expect(AiProviderErrors::isQuotaOrAuth('Validation failed for template field'))->toBeFalse();
});

test('quota failures tell the user the service is unavailable', function () {
    expect(AiProviderErrors::siteEditUnavailableMessage())->toBe('This service is not available right now.')
        ->and(AiProviderErrors::siteEditPartialUnavailableMessage())->toBe('This service is not available right now.')
        ->and(AiProviderErrors::serviceImagesNeedImageApiMessage())->toBe('This service is not available right now.')
        ->and(AiProviderErrors::siteEditUnavailableMessage())->not->toContain('Anthropic');
});
