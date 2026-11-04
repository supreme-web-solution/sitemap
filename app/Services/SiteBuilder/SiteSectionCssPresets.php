<?php

namespace App\Services\SiteBuilder;

/**
 * Deterministic scoped CSS for common section layout intents (no LLM).
 */
class SiteSectionCssPresets
{
    /**
     * @return array{css: string, summary: string}|null
     */
    public static function match(string $target, string $instruction): ?array
    {
        if (! isset(SiteSectionCss::TARGETS[$target])) {
            return null;
        }

        return match ($target) {
            'hero' => self::matchHero($instruction),
            'footer' => self::matchFooter($instruction),
            'header' => self::matchHeader($instruction),
            default => null,
        };
    }

    /**
     * @return array{css: string, summary: string}|null
     */
    protected static function matchHero(string $instruction): ?array
    {
        if (SiteSectionCssIntentChecker::instructionWantsHorizontalCenter($instruction)) {
            return [
                'css' => self::heroCenteredOverlay(),
                'summary' => __('Hero content is centred over the photo — headline, tagline, buttons, and rating stack in the middle.'),
            ];
        }

        if (SiteSectionCssIntentChecker::instructionWantsHorizontalLeft($instruction)) {
            return [
                'css' => self::heroLeftAligned(),
                'summary' => __('Hero content is aligned to the left over the background.'),
            ];
        }

        if (SiteSectionCssIntentChecker::instructionWantsHeroPhotoBackground($instruction)
            || SiteSectionCssIntentChecker::instructionWantsFullWidthHero($instruction)) {
            return [
                'css' => self::heroPhotoBackground(),
                'summary' => __('Hero uses a full-width background photo with readable text overlay.'),
            ];
        }

        return null;
    }

    /**
     * @return array{css: string, summary: string}|null
     */
    protected static function matchFooter(string $instruction): ?array
    {
        if (! SiteSectionCssIntentChecker::instructionWantsHorizontalCenter($instruction)) {
            return null;
        }

        return [
            'css' => <<<'CSS'
.site-footer { text-align: center; }
.footer-main { display: grid; gap: 2rem; justify-items: center; text-align: center; }
.footer-brand, .footer-col, .footer-bar { text-align: center; }
.footer-nav { justify-content: center; }
CSS,
            'summary' => __('Footer content is centred.'),
        ];
    }

    /**
     * @return array{css: string, summary: string}|null
     */
    protected static function matchHeader(string $instruction): ?array
    {
        if (SiteSectionCssIntentChecker::instructionWantsHorizontalCenter($instruction)) {
            return [
                'css' => <<<'CSS'
.site-header .container { justify-content: center; flex-wrap: wrap; }
.brand { margin-left: auto; margin-right: auto; }
.nav { margin-left: auto; margin-right: auto; }
CSS,
                'summary' => __('Header branding and navigation are centred.'),
            ];
        }

        return null;
    }

    public static function heroCenteredOverlay(): string
    {
        return <<<'CSS'
#hero.hero {
  min-height: 88vh;
  display: flex;
  align-items: center;
  justify-content: center;
  text-align: center;
}

#hero.hero::after {
  background: radial-gradient(ellipse 85% 70% at 50% 45%, rgba(8, 12, 24, 0.35) 0%, rgba(8, 12, 24, 0.78) 100%);
}

.hero .container {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  max-width: 720px;
  margin: 0 auto;
  padding-top: 2.5rem;
  padding-bottom: 2.5rem;
  position: relative;
  z-index: 1;
}

.hero h1 {
  text-align: center;
  margin-left: auto;
  margin-right: auto;
  max-width: 22ch;
  text-shadow: 0 2px 14px rgba(0, 0, 0, 0.45);
}

.hero p {
  text-align: center;
  margin-left: auto;
  margin-right: auto;
  max-width: 36rem;
}

.hero-eyebrow {
  margin-left: auto;
  margin-right: auto;
}

.hero-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.85rem;
  justify-content: center;
}

.hero .rating {
  display: inline-flex;
  justify-content: center;
  margin-left: auto;
  margin-right: auto;
}
CSS;
    }

    public static function heroPhotoBackground(): string
    {
        return <<<'CSS'
#hero.hero {
  position: relative;
  min-height: 92vh;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}

#hero.hero .hero-image,
#hero.hero img.hero-image {
  display: block !important;
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  z-index: 0;
  opacity: 1;
  clip-path: none;
  border-radius: 0;
  box-shadow: none;
}

#hero.hero::after {
  content: "";
  display: block;
  position: absolute;
  inset: 0;
  z-index: 0;
  background: linear-gradient(180deg, rgba(8, 12, 24, 0.35) 0%, rgba(8, 12, 24, 0.72) 100%);
  pointer-events: none;
}

#hero.hero .container {
  position: relative;
  z-index: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  width: 100%;
  max-width: 960px;
  margin: 0 auto;
  padding-top: 3rem;
  padding-bottom: 3rem;
}
CSS;
    }

    public static function heroLeftAligned(): string
    {
        return <<<'CSS'
#hero.hero {
  min-height: 78vh;
  display: flex;
  align-items: center;
  justify-content: flex-start;
  text-align: left;
}

.hero .container {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  text-align: left;
  max-width: 640px;
  margin: 0;
  padding-top: 3rem;
  padding-bottom: 3rem;
  position: relative;
  z-index: 1;
}

.hero h1,
.hero p {
  text-align: left;
  margin-left: 0;
  margin-right: auto;
}

.hero-actions {
  justify-content: flex-start;
}

.hero .rating {
  justify-content: flex-start;
}
CSS;
    }
}
