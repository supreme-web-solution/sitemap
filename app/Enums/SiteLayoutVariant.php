<?php

namespace App\Enums;

enum SiteLayoutVariant: string
{
    case Standard = 'standard';
    case SplitHero = 'split-hero';
    case CardServices = 'card-services';
    case EditorialHero = 'editorial-hero';

    /**
     * CSS class applied to the document body for layout tweaks.
     */
    public function bodyClass(): string
    {
        return 'layout-'.$this->value;
    }
}
