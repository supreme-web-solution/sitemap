<?php

namespace App\Services\SiteBuilder;

use Illuminate\Support\HtmlString;

/**
 * The bridge between site templates and the visual editor.
 *
 * Templates ask this helper for reworded labels (`copy`) and for the data attributes the editor
 * script uses to find editable text, images and sections. Outside the editor every attribute
 * helper returns an empty string, so published sites and exports carry no editor markup.
 */
class SiteEditing
{
    /**
     * @param  array<string, mixed>  $content
     */
    public function __construct(protected array $content, public readonly bool $enabled = false) {}

    /**
     * Get a heading or label, using the agency's wording when they changed it.
     */
    public function copy(string $key, string $default): string
    {
        $value = $this->content['copy'][$key] ?? null;

        return is_string($value) && trim($value) !== '' ? $value : $default;
    }

    /**
     * Mark an element as editable text stored at a content path such as `headline` or `services.0.title`.
     */
    public function text(string $path, string $label, int $max = 200, bool $multiline = false, bool $reload = false): HtmlString
    {
        if (! $this->enabled) {
            return new HtmlString('');
        }

        return new HtmlString(sprintf(
            ' data-edit="%s" data-edit-label="%s" data-edit-max="%d"%s%s',
            e($path),
            e($label),
            $max,
            $multiline ? ' data-edit-multiline="1"' : '',
            $reload ? ' data-edit-reload="1"' : '',
        ));
    }

    /**
     * Mark an element as one of the site's reworded labels (see SiteCopy).
     */
    public function label(string $key): HtmlString
    {
        $field = SiteCopy::FIELDS[$key];

        return $this->text('copy.'.$key, $field['label'], $field['max'], $field['multiline']);
    }

    /**
     * Mark an image the agency can replace: the logo, or a numbered photo.
     */
    public function image(string $slot, ?int $index, string $label): HtmlString
    {
        if (! $this->enabled) {
            return new HtmlString('');
        }

        return new HtmlString(sprintf(
            ' data-edit-image="%s" data-edit-index="%s" data-edit-label="%s"',
            e($slot),
            $index === null ? '' : $index,
            e($label),
        ));
    }

    /**
     * Attributes for the wrapper around a home page section: its background style and editor handle.
     */
    public function section(string $key, string $label): HtmlString
    {
        $attributes = [];
        $style = $this->content['section_styles'][$key] ?? null;

        if (is_string($style) && in_array($style, SiteDesign::SECTION_STYLES, true)) {
            $attributes[] = 'data-sec-style="'.e($style).'"';
        }

        if ($this->enabled) {
            $attributes[] = 'data-section="'.e($key).'" data-section-label="'.e($label).'"';
        }

        return new HtmlString($attributes === [] ? '' : ' '.implode(' ', $attributes));
    }
}
