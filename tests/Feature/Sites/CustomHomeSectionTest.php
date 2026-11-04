<?php

use App\Models\Lead;
use App\Models\Site;
use App\Services\SiteBuilder\CustomHomeSection;
use App\Services\SiteBuilder\SiteDocumentOpsApplier;
use App\Services\SiteBuilder\SiteEditApplier;

test('custom homepage section can be added via document ops', function () {
    $lead = Lead::factory()->create();
    $site = Site::factory()->create(['lead_id' => $lead->id]);

    $id = CustomHomeSection::newId('promo');
    $key = CustomHomeSection::keyFromId($id);

    $applier = new SiteDocumentOpsApplier(new SiteEditApplier);
    $result = $applier->apply($site, [
        [
            'op' => 'set',
            'path' => 'custom_sections.'.$id,
            'value' => [
                'label' => 'Promo',
                'headline' => 'Spring cleaning offer',
                'body' => 'Book this month and save 15%.',
            ],
        ],
        [
            'op' => 'insert',
            'path' => 'sections',
            'index' => 1,
            'value' => $key,
        ],
    ]);

    expect($result['content']['custom_sections'][$id]['headline'] ?? '')->toBe('Spring cleaning offer')
        ->and($result['content']['sections'])->toContain($key);
});
