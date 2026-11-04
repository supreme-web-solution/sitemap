<?php

use App\Data\SiteEditPlan;
use App\Models\Lead;
use App\Models\Site;
use App\Services\SiteBuilder\SiteEditIntent;

test('detects service image generation requests', function () {
    $text = 'in the services section, generate unique images for each of those services';

    expect(SiteEditIntent::wantsServiceImages($text))->toBeTrue();
});

test('detects hero background photo intent', function () {
    expect(SiteEditIntent::wantsHeroPhotoBackground('background image in the hero section, not plain background'))->toBeTrue();
});

test('multi-part hero and services request is site work beyond a hero photo', function () {
    $text = "there should be background image in the hero section, not plain background\n\n2. generate unique images for each of those services";

    expect(SiteEditIntent::wantsSiteWorkBeyondHeroPhoto($text))->toBeTrue();
});

test('simple hero regenerate is not extra site work', function () {
    expect(SiteEditIntent::wantsSiteWorkBeyondHeroPhoto('regenerate the image in the hero section, I need a better image'))->toBeFalse();
});

test('offline plan succeeds when heuristic document ops exist', function () {
    $site = new Site(['id' => 1, 'content' => []]);
    $site->setRelation('lead', new Lead(['id' => 1]));

    $plan = new SiteEditPlan(
        reasoning: 'test',
        copyInstruction: null,
        designCssTargets: ['hero', 'services'],
        designInstruction: 'hero background',
        sectionsAdd: [],
        sectionsRemove: [],
        layoutVariants: [],
        documentOps: [
            ['op' => 'set', 'path' => 'section_css.hero', 'value' => '.hero{}'],
        ],
        fromLlm: false,
    );

    $instruction = 'background image in the hero section';

    expect(SiteEditIntent::canCompleteOffline($plan, $instruction, $site))->toBeTrue();
});
