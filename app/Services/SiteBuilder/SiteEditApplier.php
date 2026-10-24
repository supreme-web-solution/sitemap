<?php

namespace App\Services\SiteBuilder;

use App\Enums\SiteSection;
use App\Models\Site;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

/**
 * Applies a batch of small, named edits to a website document.
 *
 * The visual editor never posts the whole site. It sends operations such as "set the
 * headline" or "move section 2 up". Only paths listed here can be touched, and the
 * resulting document is validated as a whole by the caller before anything is stored.
 *
 * Operations:
 *  - set:    {op: 'set', path, value}
 *  - insert: {op: 'insert', path, index, value}
 *  - remove: {op: 'remove', path, index}
 *  - move:   {op: 'move', path, from, to}
 */
class SiteEditApplier
{
    public const MAX_OPERATIONS = 60;

    /**
     * Paths a `set` operation may write.
     *
     * @var list<string>
     */
    protected const SETTABLE = [
        '#^(template|accent|headline|tagline|cta_label|about|reviews_style|default_locale)$#',
        '#^pages$#',
        '#^services\.\d+\.(title|description|slug|body)$#',
        '#^faqs\.\d+\.(question|answer)$#',
        '#^copy\.[a-z_]+$#',
        '#^design\.(font|radius|spacing)$#',
        '#^section_styles\.[a-z]+$#',
        '#^page_labels\.[a-z_]+$#',
        '#^custom_pages\.\d+\.(title|headline|intro|body|cta_label|city|slug)$#',
        '#^translations\.[a-z]{2}\.(headline|tagline|cta_label|about)$#',
        '#^translations\.[a-z]{2}\.services\.\d+\.(title|description|body)$#',
        '#^translations\.[a-z]{2}\.faqs\.\d+\.(question|answer)$#',
        '#^translations\.[a-z]{2}\.copy\.[a-z_]+$#',
    ];

    /**
     * Lists that support insert, remove and move.
     *
     * @var list<string>
     */
    protected const LISTS = ['sections', 'services', 'faqs', 'pages', 'locales', 'custom_pages'];

    /**
     * Paths where an empty value means "back to the default" rather than an empty string.
     */
    protected const RESETTABLE = '#^(copy|design|section_styles)\.#';

    /**
     * Build the editable document for a site: template plus content, with defaults filled in.
     *
     * @return array<string, mixed>
     */
    public static function document(Site $site): array
    {
        return [
            'template' => $site->template->value,
            ...$site->content,
            'sections' => $site->content['sections'] ?? SiteSection::defaultOrder(),
            'pages' => $site->content['pages'] ?? SitePages::OPTIONAL,
            'custom_pages' => array_values($site->content['custom_pages'] ?? []),
        ];
    }

    /**
     * Apply the operations in order and return the new document.
     *
     * @param  array<string, mixed>  $document
     * @param  list<array<string, mixed>>  $operations
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function apply(array $document, array $operations): array
    {
        if (count($operations) > self::MAX_OPERATIONS) {
            throw ValidationException::withMessages(['ops' => __('Too many changes in one request.')]);
        }

        foreach (array_values($operations) as $position => $operation) {
            $document = match ($operation['op'] ?? null) {
                'set' => $this->set($document, $operation, $position),
                'insert' => $this->insert($document, $operation, $position),
                'remove' => $this->remove($document, $operation, $position),
                'move' => $this->move($document, $operation, $position),
                default => $this->fail($position, __('Unknown edit.')),
            };
        }

        return $document;
    }

    /**
     * @param  array<string, mixed>  $document
     * @param  array<string, mixed>  $operation
     * @return array<string, mixed>
     */
    protected function set(array $document, array $operation, int $position): array
    {
        $path = (string) ($operation['path'] ?? '');

        if (! self::isEditablePath($path)) {
            return $this->fail($position, __('That part of the website cannot be edited.'));
        }

        $value = $operation['value'] ?? null;

        if (preg_match(self::RESETTABLE, $path) && ($value === null || $value === '')) {
            Arr::forget($document, $path);

            return $document;
        }

        Arr::set($document, $path, $value);

        return $document;
    }

    /**
     * @param  array<string, mixed>  $document
     * @param  array<string, mixed>  $operation
     * @return array<string, mixed>
     */
    protected function insert(array $document, array $operation, int $position): array
    {
        $path = $this->listPath($operation, $position);
        $items = array_values((array) Arr::get($document, $path, []));
        $index = $this->index($operation['index'] ?? null, count($items), $position);

        array_splice($items, $index, 0, [$operation['value'] ?? null]);
        Arr::set($document, $path, $items);

        return $document;
    }

    /**
     * @param  array<string, mixed>  $document
     * @param  array<string, mixed>  $operation
     * @return array<string, mixed>
     */
    protected function remove(array $document, array $operation, int $position): array
    {
        $path = $this->listPath($operation, $position);
        $items = array_values((array) Arr::get($document, $path, []));
        $index = $this->index($operation['index'] ?? null, count($items) - 1, $position);

        array_splice($items, $index, 1);
        Arr::set($document, $path, $items);

        return $document;
    }

    /**
     * @param  array<string, mixed>  $document
     * @param  array<string, mixed>  $operation
     * @return array<string, mixed>
     */
    protected function move(array $document, array $operation, int $position): array
    {
        $path = $this->listPath($operation, $position);
        $items = array_values((array) Arr::get($document, $path, []));
        $from = $this->index($operation['from'] ?? null, count($items) - 1, $position);
        $to = $this->index($operation['to'] ?? null, count($items) - 1, $position);

        [$item] = array_splice($items, $from, 1);
        array_splice($items, $to, 0, [$item]);
        Arr::set($document, $path, $items);

        return $document;
    }

    /**
     * Whether a `set` operation may write this content path.
     */
    public static function isEditablePath(string $path): bool
    {
        foreach (self::SETTABLE as $pattern) {
            if (preg_match($pattern, $path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $operation
     */
    protected function listPath(array $operation, int $position): string
    {
        $path = (string) ($operation['path'] ?? '');

        if (! in_array($path, self::LISTS, true)) {
            $this->fail($position, __('That list cannot be edited.'));
        }

        return $path;
    }

    protected function index(mixed $value, int $max, int $position): int
    {
        if (! is_int($value) || $value < 0 || $value > max($max, 0)) {
            $this->fail($position, __('That item is out of range.'));
        }

        return $value;
    }

    protected function fail(int $position, string $message): never
    {
        throw ValidationException::withMessages(["ops.{$position}" => $message]);
    }
}
