<?php

namespace App\Models;

use App\Enums\SiteDesignStyle;
use App\Enums\SiteLayoutVariant;
use App\Enums\SiteTemplate;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property SiteTemplate $base_template
 * @property SiteDesignStyle $style
 * @property SiteLayoutVariant $layout_variant
 * @property list<string> $industries
 * @property string $accent_color
 * @property string|null $description
 * @property int $sort_order
 * @property bool $is_active
 */
#[Fillable(['slug', 'name', 'base_template', 'style', 'layout_variant', 'industries', 'accent_color', 'description', 'sort_order', 'is_active'])]
class SiteDesignTemplate extends Model
{
    /**
     * @param  Builder<SiteDesignTemplate>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<SiteDesignTemplate>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * @param  Builder<SiteDesignTemplate>  $query
     */
    public function scopeFiltered(Builder $query, ?string $industry = null, ?string $style = null, ?string $palette = null): void
    {
        if (filled($industry)) {
            $needle = mb_strtolower(trim($industry));
            $query->where('industries', 'like', '%'.$needle.'%');
        }

        if (filled($style)) {
            $query->where('style', $style);
        }

        if (filled($palette)) {
            $query->where('accent_color', 'like', '%'.ltrim($palette, '#').'%');
        }
    }

    /**
     * The underlying Blade theme this catalog entry renders with.
     */
    public function siteTemplate(): SiteTemplate
    {
        return $this->base_template;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'base_template' => SiteTemplate::class,
            'style' => SiteDesignStyle::class,
            'layout_variant' => SiteLayoutVariant::class,
            'industries' => 'array',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
