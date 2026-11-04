<?php

use App\Services\Ai\AiProviderErrors;

test('detects anthropic quota messages', function () {
    expect(AiProviderErrors::isQuotaOrAuth('AI provider [anthropic] has insufficient credits or quota.'))->toBeTrue();
});

test('ignores unrelated errors', function () {
    expect(AiProviderErrors::isQuotaOrAuth('Validation failed for template field'))->toBeFalse();
});
