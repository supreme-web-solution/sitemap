<?php

use App\Services\SiteBuilder\PreviewEditPrompt;

test('preview edit prompt lists targets for the model', function () {
    $text = PreviewEditPrompt::format([
        [
            'region' => 'footer',
            'selector' => '.footer-main nav',
            'tag' => 'nav',
            'text' => 'Explore',
        ],
    ], 'balance columns');

    expect($text)
        ->toContain('Preview targets')
        ->toContain('region=footer')
        ->toContain('.footer-main nav')
        ->toContain('balance columns');
});
