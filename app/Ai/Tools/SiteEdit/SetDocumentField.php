<?php

namespace App\Ai\Tools\SiteEdit;

use App\Ai\Tools\SiteEdit\Concerns\UsesSiteEditExecutorState;
use App\Services\SiteBuilder\SiteEditApplier;
use App\Services\SiteBuilder\SiteEditOpCatalog;
use App\Services\SiteBuilder\SiteSectionCss;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class SetDocumentField implements Tool
{
    use UsesSiteEditExecutorState;

    public function description(): Stringable|string
    {
        return 'Set one editable site field (headline, variants.hero, section_css.footer, accent, custom_sections.*, …). Value is JSON.';
    }

    public function handle(Request $request): Stringable|string
    {
        $path = trim((string) ($request['path'] ?? ''));
        $rawValue = (string) ($request['value_json'] ?? '');

        if (! SiteEditApplier::isEditablePath($path)) {
            return 'Rejected: path is not editable.';
        }

        $decoded = json_decode($rawValue, true);

        if ($rawValue !== '' && json_last_error() !== JSON_ERROR_NONE) {
            $decoded = $rawValue;
        }

        if (preg_match('/^section_css\.([a-z_]+)$/', $path, $match) === 1) {
            $target = $match[1];
            $css = is_string($decoded) ? SiteSectionCss::sanitize($decoded) : '';

            if ($css === '' || SiteSectionCss::validate($target, $css) !== []) {
                return 'Rejected: invalid scoped CSS for '.$target.'. Call get_section_blueprint first.';
            }

            $decoded = $css;
        }

        $op = ['op' => 'set', 'path' => $path, 'value' => $decoded];
        $sanitized = SiteEditOpCatalog::sanitize([$op]);

        if ($sanitized === []) {
            return 'Rejected: value failed validation for this path.';
        }

        $this->state->documentOps[] = $sanitized[0];

        return 'Accepted set '.$path;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'path' => $schema->string()->required(),
            'value_json' => $schema->string()->required()->description('JSON-encoded value (strings must be quoted)'),
        ];
    }
}
