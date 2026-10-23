<?php

namespace App\Data;

use App\Enums\SiteTemplate;

readonly class SiteDraft
{
    /**
     * @param  array{
     *     accent: string,
     *     headline: string,
     *     tagline: string,
     *     cta_label: string,
     *     about: string,
     *     sections: list<string>,
     *     reviews_style?: string,
     *     services: list<array{title: string, description: string}>,
     *     faqs: list<array{question: string, answer: string}>,
     * }  $content
     */
    public function __construct(
        public SiteTemplate $template,
        public array $content,
    ) {
        //
    }
}
