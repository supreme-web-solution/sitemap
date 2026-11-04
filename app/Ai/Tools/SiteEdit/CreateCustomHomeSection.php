<?php

namespace App\Ai\Tools\SiteEdit;

use App\Ai\Tools\SiteEdit\Concerns\UsesSiteEditExecutorState;
use App\Services\SiteBuilder\CustomHomeSection;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class CreateCustomHomeSection implements Tool
{
    use UsesSiteEditExecutorState;

    public function description(): Stringable|string
    {
        return 'Create a new custom homepage block with headline and body, and add it to the homepage order.';
    }

    public function handle(Request $request): Stringable|string
    {
        $label = Str::limit(trim((string) ($request['label'] ?? 'Custom section')), 40, '');
        $headline = Str::limit(trim((string) ($request['headline'] ?? '')), 120, '');
        $body = Str::limit(trim((string) ($request['body'] ?? '')), 4000, '');

        if ($headline === '' || $body === '') {
            return 'Rejected: headline and body are required.';
        }

        if ($label === '') {
            $label = 'Custom section';
        }

        $id = CustomHomeSection::newId($headline);
        $key = CustomHomeSection::keyFromId($id);

        $this->state->documentOps[] = [
            'op' => 'set',
            'path' => 'custom_sections.'.$id,
            'value' => [
                'label' => $label,
                'headline' => $headline,
                'body' => $body,
            ],
        ];

        $this->state->sectionsAdd[] = $key;

        return 'Accepted custom block '.$key.'. Style with section_css.custom or scoped rules on .custom-section#'.$id;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'label' => $schema->string()->description('Short editor label'),
            'headline' => $schema->string()->required(),
            'body' => $schema->string()->required()->description('Plain text or simple HTML paragraphs'),
        ];
    }
}
