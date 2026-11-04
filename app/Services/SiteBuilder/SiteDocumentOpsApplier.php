<?php

namespace App\Services\SiteBuilder;

use App\Enums\SiteTemplate;
use App\Models\Site;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Apply validated document ops from AI chat (shared with the visual editor applier).
 */
class SiteDocumentOpsApplier
{
    public function __construct(protected SiteEditApplier $applier) {}

    /**
     * @param  list<array<string, mixed>>  $ops
     * @return array{content: array<string, mixed>, template: SiteTemplate}
     *
     * @throws ValidationException
     */
    public function apply(Site $site, array $ops): array
    {
        $ops = SiteEditOpCatalog::sanitize($ops);

        if ($ops === []) {
            return [
                'content' => $site->content,
                'template' => $site->template,
            ];
        }

        $document = SiteEditApplier::document($site);
        $document = $this->applier->apply($document, $ops);

        $validator = Validator::make($document, SiteContentSchema::rules());
        SiteContentSchema::addCustomPageHooks($validator);
        $validated = $validator->validate();

        $merged = array_merge(
            $site->content,
            SiteContentSchema::toContent($validated),
        );

        return [
            'content' => SiteContentSchema::preserveEditorFields($site->content, $merged),
            'template' => SiteTemplate::from($validated['template']),
        ];
    }
}
