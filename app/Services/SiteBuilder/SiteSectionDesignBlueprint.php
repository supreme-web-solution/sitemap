<?php

namespace App\Services\SiteBuilder;

/**
 * HTML/CSS structure hints so the model can write scoped patches without seeing the live DOM.
 */
class SiteSectionDesignBlueprint
{
    public static function keys(): array
    {
        return array_merge(SiteSectionCss::keys(), ['custom']);
    }

    public static function forTarget(string $target): string
    {
        return match ($target) {
            'header' => <<<'HTML'
            <header class="site-header">
              <div class="container">
                <a class="brand">.brand-mark or .brand-logo + business name</a>
                <nav class="nav">page links</nav>
                <div class="header-actions">.header-call .btn-primary, mobile menu</div>
              </div>
            </header>
            HTML,
            'hero' => <<<'HTML'
            <section id="hero" class="hero">
              optional <img class="hero-image"> (full-bleed background on overlay preset)
              <div class="container">
                <span class="hero-eyebrow">category · city</span>
                <h1>headline</h1>
                <p>tagline</p>
                <div class="hero-actions">.btn-primary + .btn-secondary</div>
                optional <div class="rating">.stars + review line</div>
              </div>
            </section>
            Always target .hero .container for column layout — no .hero-inner / .hero__content.
            HTML,
            'services' => <<<'HTML'
            <section id="services" class="block services-block">
              <div class="container">
                <span class="section-label">.section-label</span>
                <h2 class="section-title">.section-title</h2>
                <div class="services">.service cards with .service-photo, h3, p</div>
              </div>
            </section>
            HTML,
            'about' => <<<'HTML'
            <section id="about" class="block about-block">
              <div class="container split">
                labels + .section-title | .about-text (about copy)
              </div>
            </section>
            HTML,
            'process' => <<<'HTML'
            <section id="process" class="block process-block">
              <div class="container">numbered steps from content process[]</div>
            </section>
            HTML,
            'why' => <<<'HTML'
            <section id="why" class="block why-block">
              <div class="container">why points cards from content why[]</div>
            </section>
            HTML,
            'reviews' => <<<'HTML'
            <section id="reviews" class="block reviews-block">
              <div class="container">
                .reviews-summary, .reviews-carousel or .reviews-grid, .review-card
              </div>
            </section>
            HTML,
            'gallery' => <<<'HTML'
            <section id="gallery" class="block gallery-block">
              <div class="container"><div class="gallery">lead photos grid</div></div>
            </section>
            HTML,
            'areas' => <<<'HTML'
            <section id="areas" class="block areas-block">
              <div class="container">service area chips / list</div>
            </section>
            HTML,
            'hours' => <<<'HTML'
            <section id="visit" class="block visit-block">
              <div class="container split">.hours table + .map iframe</div>
            </section>
            HTML,
            'faq' => <<<'HTML'
            <section id="faq" class="block faq-block">
              <div class="container"><div class="faq">details/summary items</div></div>
            </section>
            HTML,
            'contact' => <<<'HTML'
            <section id="contact" class="block contact-block">
              <div class="container split">labels + .contact-list</div>
            </section>
            HTML,
            'footer' => <<<'HTML'
            <footer class="site-footer">
              <div class="footer-cta">tagline + phone CTA</div>
              <div class="footer-main container">.footer-brand, .footer-col ×3</div>
              <div class="footer-bar container">copyright + .footer-nav</div>
            </footer>
            HTML,
            'custom' => <<<'HTML'
            <section id="custom-{id}" class="block custom-section">
              <div class="container">
                <span class="section-label">label</span>
                <h2 class="section-title">headline</h2>
                <div class="custom-section__body">body HTML</div>
              </div>
            </section>
            Scoped CSS: section_css.custom with selectors .custom-section, #custom-{id}, .custom-section__body
            HTML,
            default => '<section id="'.$target.'" class="block '.$target.'-block"><div class="container">…</div></section>',
        };
    }
}
