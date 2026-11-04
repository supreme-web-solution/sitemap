<?php

use App\Data\SiteEditPlan;
use App\Services\SiteBuilder\SiteEditPlanIntentFilter;

test('service image requests drop services from design css targets', function () {
    $plan = new SiteEditPlan(
        reasoning: 'test',
        copyInstruction: 'generate images for services',
        designCssTargets: ['hero', 'services'],
        designInstruction: 'make cards pretty',
        sectionsAdd: [],
        sectionsRemove: [],
        layoutVariants: [],
        documentOps: [],
        fromLlm: true,
    );

    $instruction = 'hero background image and generate unique images for each service';

    $filtered = SiteEditPlanIntentFilter::apply($plan, $instruction);

    expect($filtered->designCssTargets)->toBe([])
        ->and($filtered->copyInstruction)->toBeNull();
});
