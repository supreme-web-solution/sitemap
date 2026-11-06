<!DOCTYPE html>
<html lang="{{ $locale ?? 'en' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('sites.partials.seo')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    @include('sites.themes.'.$template.'-fonts')
    <style>
        :root { --accent: {{ $content['accent'] }}; --on-accent: {{ $onAccent }}; }
        *, *::before, *::after { box-sizing: border-box; }
        html { scroll-behavior: smooth; overflow-x: clip; }
        body { margin: 0; max-width: 100%; overflow-x: clip; overflow-wrap: break-word; -webkit-font-smoothing: antialiased; line-height: 1.6; }
        img, iframe, video { max-width: 100%; }
        img { display: block; }
        a { color: inherit; }
        .container { width: 100%; max-width: 1140px; margin: 0 auto; padding: 0 1.25rem; }
        .btn { display: inline-flex; align-items: center; gap: .5rem; padding: .85rem 1.5rem; border-radius: var(--radius); font-weight: 600; text-decoration: none; transition: transform .15s ease, opacity .15s ease; }
        .btn:hover { transform: translateY(-1px); opacity: .92; }
        .btn svg { width: 1.1rem; height: 1.1rem; }
        .btn-primary { background: var(--accent); color: var(--on-accent); }
        .btn-secondary { background: transparent; color: inherit; border: 1.5px solid color-mix(in srgb, currentColor 22%, transparent); }
        .section-actions { display: flex; flex-wrap: wrap; align-items: center; gap: .75rem; margin-top: 2rem; }
        .section-actions .btn { margin: 0; }
        .site-header { position: fixed; top: 0; left: 0; right: 0; z-index: 40; width: 100%; background: transparent; border-bottom: 1px solid transparent; transition: background .28s ease, border-color .28s ease, box-shadow .28s ease, color .28s ease; }
        .site-header .container { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding-top: 1.15rem; padding-bottom: 1.15rem; transition: padding .28s ease; }
        .brand { display: flex; align-items: center; gap: .75rem; min-width: 0; font-weight: 700; font-size: 1.1rem; text-decoration: none; }
        .brand > span:last-child { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .brand-mark { width: 2.25rem; height: 2.25rem; border-radius: var(--radius); background: var(--accent); color: var(--on-accent); display: grid; place-items: center; font-weight: 800; transition: width .28s ease, height .28s ease; }
        .brand-logo { height: 2.5rem; width: auto; max-width: 9rem; object-fit: contain; transition: height .28s ease, max-width .28s ease; }
        .gallery { display: grid; gap: 1rem; margin-top: 2.5rem; min-width: 0; max-width: 100%; grid-template-columns: repeat(auto-fill, minmax(min(100%, 240px), 1fr)); }
        .gallery-item { position: relative; margin: 0; min-width: 0; overflow: hidden; border-radius: var(--radius); cursor: zoom-in; }
        .gallery-item img { width: 100%; aspect-ratio: 4 / 3; object-fit: cover; border-radius: var(--radius); display: block; }
        .gallery-zoom {
            position: absolute; right: .7rem; bottom: .7rem; z-index: 2;
            display: inline-grid; place-items: center; width: 2.35rem; height: 2.35rem; padding: 0;
            border: 0; border-radius: 999px; cursor: pointer;
            background: rgba(15, 23, 42, .72); color: #fff;
            box-shadow: 0 8px 20px rgba(15, 23, 42, .28);
            opacity: 0; transform: translateY(.2rem); transition: opacity .18s ease, transform .18s ease, background .18s ease;
        }
        .gallery-zoom svg { width: 1.05rem; height: 1.05rem; }
        .gallery-item:hover .gallery-zoom, .gallery-item:focus-within .gallery-zoom, .gallery-zoom:focus-visible { opacity: 1; transform: none; }
        .gallery-zoom:hover, .gallery-zoom:focus-visible { background: rgba(15, 23, 42, .92); }
        @media (hover: none) { .gallery-zoom { opacity: 1; transform: none; } }
        .gallery-lightbox { border: 0; padding: 0; background: transparent; max-width: none; max-height: none; width: 100vw; height: 100vh; }
        .gallery-lightbox::backdrop { background: rgba(8, 12, 24, .82); backdrop-filter: blur(4px); }
        .gallery-lightbox[open] { display: grid; place-items: center; }
        .gallery-lightbox__panel { position: relative; width: min(1100px, calc(100vw - 2rem)); max-height: calc(100vh - 2rem); }
        .gallery-lightbox__img { display: block; width: 100%; max-height: calc(100vh - 2rem); object-fit: contain; border-radius: calc(var(--radius) + 2px); background: #0b1220; box-shadow: 0 30px 80px rgba(0,0,0,.45); }
        .gallery-lightbox__close {
            position: absolute; top: .6rem; right: .6rem; z-index: 2;
            display: inline-grid; place-items: center; width: 2.5rem; height: 2.5rem;
            border: 0; border-radius: 999px; cursor: pointer;
            background: rgba(255,255,255,.92); color: #0f172a; font: 700 1.25rem/1 system-ui, sans-serif;
        }
        .gallery-lightbox__close:hover { background: #fff; }
        .sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }
        .locale-switcher { display: none; gap: .65rem; font-size: .85rem; margin-right: .5rem; }
        .locale-switcher a { text-decoration: none; opacity: .75; }
        .locale-switcher a[aria-current="page"] { opacity: 1; font-weight: 700; }
        .nav { display: none; gap: 1.75rem; font-size: .95rem; }
        .nav a { position: relative; text-decoration: none; opacity: .82; padding-bottom: .15rem; }
        .nav a:hover { opacity: 1; }
        .nav a::after { content: ''; position: absolute; left: 0; right: 0; bottom: -.28rem; height: 2px; background: var(--accent); transform: scaleX(0); transform-origin: left center; transition: transform .22s ease; }
        .nav a:hover::after, .nav a[aria-current="page"]::after { transform: scaleX(1); }
        .hero { position: relative; overflow: hidden; }
        .hero::before { content: ''; position: absolute; left: 0; right: 0; top: 0; height: 8.5rem; z-index: 1; pointer-events: none; background: linear-gradient(180deg, rgba(8,12,24,.58) 0%, transparent 100%); }
        .hero .container { position: relative; z-index: 1; padding-top: 6rem; padding-bottom: 6rem; }
        .hero-eyebrow { display: inline-flex; align-items: center; gap: .5rem; font-size: .85rem; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; }
        .keeps-lines, .hero h1, .page-hero h1, .section-title, .section-intro, .process-step h3, .process-step p, .why-point h3, .why-point p { white-space: pre-line; }
        .hero h1 { font-size: clamp(2.25rem, 5vw, 3.75rem); line-height: 1.08; margin: 1rem 0; max-width: min(16ch, 100%); }
        .hero p { font-size: 1.2rem; max-width: min(36rem, 100%); margin: 0 0 2rem; }
        .hero-actions { display: flex; flex-wrap: wrap; gap: .75rem; }
        .rating { display: inline-flex; align-items: center; gap: .4rem; margin-top: 2rem; font-weight: 600; }
        .stars { display: inline-flex; color: #fbbf24; }
        .stars svg { width: 1.1rem; height: 1.1rem; }
        .trust { padding: 1.5rem 0; }
        .trust .container { display: flex; flex-wrap: wrap; justify-content: center; gap: 1rem 2.5rem; font-weight: 600; font-size: .95rem; }
        .trust span { display: inline-flex; align-items: center; gap: .5rem; }
        .trust svg { width: 1.1rem; height: 1.1rem; color: var(--accent); }
        section.block { padding: 5rem 0; scroll-margin-top: 3.5rem; }
        .section-label { color: var(--accent); font-weight: 700; font-size: .85rem; letter-spacing: .08em; text-transform: uppercase; }
        .section-title { font-size: clamp(1.75rem, 3.5vw, 2.5rem); line-height: 1.15; margin: .5rem 0 1rem; }
        .services { display: grid; gap: 1.25rem; margin-top: 2.5rem; min-width: 0; grid-template-columns: repeat(auto-fit, minmax(min(100%, 230px), 1fr)); }
        .service { padding: 1.75rem; border-radius: var(--radius); }
        .service-icon { width: 2.75rem; height: 2.75rem; border-radius: var(--radius); display: grid; place-items: center; background: color-mix(in srgb, var(--accent) 14%, transparent); color: var(--accent); margin-bottom: 1rem; }
        .service-icon svg { width: 1.3rem; height: 1.3rem; }
        .service h3 { margin: 0 0 .5rem; font-size: 1.15rem; }
        .service p { margin: 0; opacity: .8; }
        .split { display: grid; gap: 3rem; align-items: start; }
        .about-text { font-size: 1.1rem; }
        .hours { width: 100%; border-collapse: collapse; }
        .hours td { padding: .65rem 0; }
        .hours td:last-child { text-align: right; font-weight: 600; }
        .map { width: 100%; height: 340px; border: 0; border-radius: var(--radius); }
        .faq { margin-top: 2rem; display: grid; gap: .75rem; }
        .faq details { padding: 1.1rem 1.35rem; border-radius: var(--radius); }
        .faq summary { cursor: pointer; font-weight: 600; }
        .faq p { margin: .75rem 0 0; opacity: .85; }
        .reviews-summary { display: flex; align-items: center; gap: .5rem; margin: 0 0 2rem; opacity: .85; }
        .review-card { display: flex; flex-direction: column; justify-content: space-between; gap: 1.1rem; margin: 0; padding: 1.5rem; border-radius: var(--radius); background: var(--review-bg, #fff); border: 1px solid var(--line, #e2e8f0); }
        .review-card blockquote { margin: 0; font-size: 1rem; line-height: 1.65; }
        .review-card figcaption { display: flex; align-items: center; gap: .75rem; }
        .review-avatar { width: 2.5rem; height: 2.5rem; flex: none; border-radius: 999px; display: grid; place-items: center; font-weight: 700; background: color-mix(in srgb, var(--accent) 16%, transparent); color: var(--accent); }
        .review-meta { display: flex; flex-direction: column; line-height: 1.3; min-width: 0; }
        .review-meta strong { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .review-meta small { opacity: .65; font-size: .8rem; }
        .reviews-source { margin: 2rem 0 0; font-size: .9rem; opacity: .75; }
        .reviews-carousel { position: relative; min-width: 0; max-width: 100%; }
        .reviews-track { display: flex; gap: 1.25rem; max-width: 100%; overflow-x: auto; scroll-snap-type: x mandatory; scrollbar-width: none; padding: .25rem .1rem 1rem; }
        .reviews-track::-webkit-scrollbar { display: none; }
        .reviews-track .review-card { flex: 0 0 min(88%, 24rem); scroll-snap-align: start; }
        .reviews-controls { display: flex; gap: .5rem; margin-top: .5rem; }
        .reviews-controls button { width: 2.75rem; height: 2.75rem; border-radius: 999px; border: 1px solid var(--line, #e2e8f0); background: var(--review-bg, #fff); color: inherit; font-size: 1.4rem; line-height: 1; cursor: pointer; transition: transform .15s ease, border-color .15s ease; }
        .reviews-controls button:hover { transform: translateY(-1px); border-color: var(--accent); }
        .reviews-grid { columns: 1; column-gap: 1.25rem; }
        .reviews-grid .review-card { break-inside: avoid; margin-bottom: 1.25rem; }
        .reviews-spotlight { position: relative; max-width: 48rem; margin: 0 auto; text-align: center; }
        .spotlight-slide { margin: 0 0 2rem; }
        .spotlight-slide .stars { justify-content: center; }
        .spotlight-slide .stars svg { width: 1.4rem; height: 1.4rem; }
        .spotlight-slide blockquote { margin: 1.25rem 0; font-size: clamp(1.25rem, 2.6vw, 1.75rem); line-height: 1.5; font-weight: 500; }
        .spotlight-slide figcaption span { opacity: .65; }
        .reviews-spotlight.is-enhanced .spotlight-slide { display: none; margin: 0; animation: reviewFade .5s ease; }
        .reviews-spotlight.is-enhanced .spotlight-slide.is-active { display: block; }
        .spotlight-dots { display: none; justify-content: center; gap: .5rem; margin-top: 1.5rem; }
        .reviews-spotlight.is-enhanced .spotlight-dots { display: flex; }
        .spotlight-dots button { color: inherit; width: .65rem; height: .65rem; padding: 0; border-radius: 999px; border: 0; background: currentColor; opacity: .25; cursor: pointer; transition: opacity .2s ease, width .2s ease; }
        .spotlight-dots button.is-active { opacity: 1; width: 1.6rem; background: var(--accent); }
        .reviews-marquee { max-width: 100%; min-width: 0; overflow: hidden; -webkit-mask-image: linear-gradient(90deg, transparent, #000 6%, #000 94%, transparent); mask-image: linear-gradient(90deg, transparent, #000 6%, #000 94%, transparent); }
        .marquee-track { display: flex; gap: 1.25rem; width: max-content; padding: .25rem 0 1rem; animation: reviewMarquee 70s linear infinite; }
        .marquee-track:hover { animation-play-state: paused; }
        .marquee-track .review-card { width: 22rem; flex: none; }
        .marquee-copy { display: contents; }
        @keyframes reviewMarquee { to { transform: translateX(calc(-50% - .625rem)); } }
        @keyframes reviewFade { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }
        @media (prefers-reduced-motion: reduce) {
            .marquee-track { animation: none; width: auto; overflow-x: auto; }
            .marquee-copy { display: none; }
            .reviews-spotlight.is-enhanced .spotlight-slide { animation: none; }
        }
        .cta-band { padding: 4.5rem 0; text-align: center; background: var(--accent); color: var(--on-accent); }
        .cta-band h2 { font-size: clamp(1.75rem, 3.5vw, 2.5rem); margin: 0 0 .75rem; }
        .cta-band p { margin: 0 auto 2rem; max-width: 36rem; opacity: .9; }
        .cta-band .btn { background: #fff; color: #111; }
        .location-card { margin: 1rem 0 1.25rem; padding: 1rem 1.1rem; border-radius: .85rem; border: 1px solid var(--line, #e7e5e4); background: color-mix(in srgb, var(--accent) 12%, var(--surface, #fff)); color: var(--ink, inherit); }
        .location-card__title { margin: 0; font-weight: 700; font-size: .95rem; color: var(--ink, inherit); }
        .location-card__address { margin: .35rem 0 0; font-size: .9rem; line-height: 1.45; color: var(--muted, inherit); }
        .location-card__meta { margin: .35rem 0 0; font-size: .82rem; color: var(--muted, inherit); }
        .location-card__link { display: inline-block; margin-top: .65rem; font-size: .82rem; font-weight: 600; color: var(--accent); text-decoration: none; }
        .location-card__link:hover { text-decoration: underline; }
        .contact-grid { display: grid; gap: 2.5rem; align-items: start; }
        .contact-form { display: grid; gap: .85rem; min-width: 0; }
        .contact-form label { font-weight: 650; font-size: .92rem; color: var(--ink, inherit); }
        .contact-form input, .contact-form textarea { width: 100%; max-width: 100%; padding: .8rem .9rem; border: 1px solid var(--line, #e7e5e4); border-radius: var(--radius, 12px); background: var(--surface, #fff); color: var(--ink, inherit); font: inherit; }
        .contact-form textarea { min-height: 9rem; resize: vertical; }
        .contact-form .form-error { margin: 0; color: #b91c1c; font-size: .92rem; }
        .contact-form .contact-note { margin: 0; color: var(--muted, inherit); }
        .contact-note { margin: 0 0 .5rem; }
        .contact-honeypot { position: absolute; left: -9999px; }
        .contact-aside { min-width: 0; padding: 1.5rem; border: 1px solid var(--line, #e7e5e4); border-radius: calc(var(--radius, 12px) * 1.2); background: var(--surface, #fff); color: var(--ink, inherit); }
        .contact-aside .section-title { margin: 0 0 1rem; color: var(--ink, inherit); }
        .contact-aside .contact-list { color: var(--ink, inherit); }
        .contact-aside .contact-list a { color: inherit; }
        .additional-services { margin-top: 3.5rem; }
        .additional-services ul { list-style: none; display: flex; flex-wrap: wrap; gap: .6rem; padding: 0; margin: 1rem 0 0; }
        .additional-services li { padding: .45rem .8rem; border: 1px solid var(--line, #e7e5e4); border-radius: 999px; }
        .contact-list { list-style: none; padding: 0; margin: 1.5rem 0 0; display: grid; gap: .9rem; }
        .contact-list li { display: flex; gap: .75rem; align-items: flex-start; }
        .contact-list svg { width: 1.2rem; height: 1.2rem; flex: none; margin-top: .2rem; color: var(--accent); }
        .section-intro { margin: 0 0 2rem; max-width: 38rem; opacity: .8; }
        .process-steps, .why-points { list-style: none; margin: 0; padding: 0; display: grid; gap: 1.25rem; min-width: 0; grid-template-columns: repeat(auto-fit, minmax(min(100%, 200px), 1fr)); }
        .process-step, .why-point { margin: 0; padding: 1.5rem 1.35rem 1.6rem; border-radius: var(--radius); border: 1px solid var(--line, #e2e8f0); background: var(--surface, #fff); }
        .process-step__num, .why-point__num { display: block; font-size: 1.35rem; font-weight: 800; color: var(--accent); letter-spacing: .04em; margin-bottom: .75rem; font-variant-numeric: tabular-nums; }
        .process-step h3, .why-point h3 { margin: 0 0 .45rem; font-size: 1.1rem; }
        .process-step p, .why-point p { margin: 0; opacity: .8; }
        .area-chips { list-style: none; margin: 0; padding: 0; display: flex; flex-wrap: wrap; gap: .6rem; }
        .area-chips li { padding: .45rem 1rem; border-radius: 999px; border: 1px solid var(--line, #e2e8f0); background: color-mix(in srgb, var(--accent) 14%, var(--surface, #fff)); color: var(--ink, inherit); font-weight: 600; font-size: .92rem; }
        .site-footer { padding: 0; font-size: .9rem; }
        .footer-cta { display: none; padding: 1.75rem 0; border-bottom: 1px solid color-mix(in srgb, currentColor 16%, transparent); }
        .footer-cta .container { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1rem; }
        .footer-cta__copy { margin: 0; max-width: 36rem; }
        .footer-main { display: grid; gap: 2rem 2rem; padding: 3rem 1.25rem 2.25rem; grid-template-columns: minmax(0, 1.25fr) repeat(3, minmax(0, 1fr)); align-items: start; }
        .footer-brand .brand { margin-bottom: .75rem; }
        .footer-brand p { margin: 0 0 1rem; opacity: .8; max-width: 22rem; line-height: 1.55; }
        .footer-col h3, .footer-hours-label { margin: 0 0 .85rem; font-size: .72rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; opacity: .7; }
        .footer-hours-label { margin-top: 0; }
        .footer-col { min-width: 0; }
        .footer-col ul { list-style: none; margin: 0; padding: 0; display: grid; gap: .45rem; }
        .footer-col a { text-decoration: none; opacity: .88; }
        .footer-col a:hover { opacity: 1; }
        .footer-hours li { display: flex; justify-content: space-between; gap: .75rem; opacity: .88; }
        .footer-bar { border-top: 1px solid color-mix(in srgb, currentColor 16%, transparent); }
        .footer-bar .container { display: flex; flex-wrap: wrap; justify-content: space-between; gap: .75rem 1.25rem; padding: 1.1rem 1.25rem; }
        @media (max-width: 900px) {
            .footer-main { grid-template-columns: 1fr 1fr; }
            .footer-brand { grid-column: 1 / -1; }
        }
        @media (max-width: 640px) {
            .footer-main { grid-template-columns: 1fr; padding-top: 2.25rem; }
            .footer-brand { grid-column: auto; }
        }
        .mobile-cta { display: none; }
        @media (min-width: 768px) {
            .mobile-cta {
                display: inline-flex;
                position: fixed;
                right: 1.25rem;
                bottom: calc(1.25rem + var(--preview-bar-height, 0px));
                z-index: 35;
                width: auto;
                border-radius: 999px;
                padding: .8rem 1.15rem;
                box-shadow: 0 12px 28px rgba(0, 0, 0, .22);
                transform: translateY(140%);
                opacity: 0;
                pointer-events: none;
                transition: transform .32s ease, opacity .32s ease;
            }
            .mobile-cta.is-visible { transform: translateY(0); opacity: 1; pointer-events: auto; }
        }
        .preview-bar {
            --preview-primary: hsl(265 72% 51%);
            --preview-primary-hover: hsl(265 72% 45%);
            position: fixed; left: 0; right: 0; bottom: 0; z-index: 40;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            background: rgba(255, 255, 255, .94);
            backdrop-filter: blur(12px);
            border-top: 1px solid #e4e4e7;
            box-shadow: 0 -4px 24px rgba(88, 28, 135, .08);
            padding: .65rem 1rem;
            display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: .5rem .75rem;
            font-size: .875rem; color: #3f3f46;
        }
        .preview-bar__note { margin: 0; flex: 1 1 12rem; min-width: 0; text-align: center; }
        .preview-bar__actions { display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: .4rem; }
        .preview-bar__packages { display: flex; flex-wrap: wrap; gap: .35rem; }
        .preview-bar__btn {
            font: inherit; cursor: pointer; border-radius: 999px; font-weight: 600; font-size: .8125rem;
            padding: .5rem 1rem; border: 1px solid #e4e4e7; background: #fff; color: #18181b;
            text-decoration: none; display: inline-flex; align-items: center; justify-content: center;
            transition: transform .15s ease, box-shadow .15s ease, border-color .15s ease, background .15s ease;
        }
        .preview-bar__btn:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(88, 28, 135, .12); border-color: #d4d4d8; }
        .preview-bar__btn--primary { background: var(--preview-primary); border-color: transparent; color: #fff; }
        .preview-bar__btn--primary:hover { background: var(--preview-primary-hover); }
        .preview-bar__btn--pulse {
            position: relative;
            animation: preview-cta-glow 2.2s ease-in-out infinite;
        }
        .preview-bar__btn--pulse::after {
            content: "";
            position: absolute; inset: -3px; border-radius: 999px; pointer-events: none;
            border: 2px solid hsl(265 72% 51% / .55);
            animation: preview-cta-ring 2.2s ease-out infinite;
        }
        @keyframes preview-cta-glow {
            0%, 100% { box-shadow: 0 2px 8px hsl(265 72% 51% / .35); }
            50% { box-shadow: 0 4px 20px hsl(265 72% 51% / .55), 0 0 0 1px hsl(265 72% 65% / .4); }
        }
        @keyframes preview-cta-ring {
            0% { transform: scale(1); opacity: .85; }
            70%, 100% { transform: scale(1.12); opacity: 0; }
        }
        @media (prefers-reduced-motion: reduce) {
            .preview-bar__btn--pulse { animation: none; }
            .preview-bar__btn--pulse::after { animation: none; opacity: 0; }
        }
        .preview-bar__btn--package { background: #faf5ff; border-color: #e9d5ff; color: #581c87; }
        .preview-bar__btn--block { width: 100%; margin-top: .25rem; padding: .65rem 1rem; font-size: .9375rem; }
        .preview-modal {
            --preview-primary: hsl(265 72% 51%);
            --preview-primary-hover: hsl(265 72% 45%);
            position: fixed; inset: 0; z-index: 50; display: grid; place-items: center; padding: 1rem;
        }
        .preview-modal .preview-bar__btn--primary {
            background: var(--preview-primary); border-color: transparent; color: #fff;
        }
        .preview-modal .preview-bar__btn--primary:hover { background: var(--preview-primary-hover); color: #fff; }
        .preview-modal[hidden] { display: none !important; }
        .preview-modal__backdrop {
            position: absolute; inset: 0; background: rgba(24, 24, 27, .45);
            animation: preview-fade-in .2s ease;
        }
        .preview-modal__panel {
            position: relative; width: min(100%, 26rem); max-height: min(90vh, 640px); overflow: auto;
            background: #fff; border-radius: 1rem; padding: 1.25rem 1.25rem 1.5rem;
            box-shadow: 0 24px 48px rgba(88, 28, 135, .18);
            border: 1px solid #f4f4f5;
            animation: preview-slide-up .25s cubic-bezier(.22, 1, .36, 1);
        }
        .preview-modal__close {
            position: absolute; top: .75rem; right: .75rem; width: 2rem; height: 2rem;
            border: 0; border-radius: .5rem; background: #f4f4f5; color: #52525b; font-size: 1.25rem; line-height: 1; cursor: pointer;
        }
        .preview-modal__close:hover { background: #e4e4e7; }
        .preview-modal__title { margin: 0 0 .35rem; font-size: 1.125rem; color: #18181b; padding-right: 2rem; }
        .preview-modal__lead { margin: 0 0 1rem; font-size: .875rem; color: #71717a; line-height: 1.45; }
        .preview-modal__form { display: grid; gap: .75rem; }
        .preview-modal__field { display: grid; gap: .35rem; font-size: .8125rem; font-weight: 600; color: #3f3f46; }
        .preview-modal__field input, .preview-modal__field textarea {
            font: inherit; font-weight: 400; padding: .55rem .75rem; border-radius: .5rem;
            border: 1px solid #d4d4d8; background: #fff; color: #18181b;
            transition: border-color .15s ease, box-shadow .15s ease;
        }
        .preview-modal__field input::placeholder, .preview-modal__field textarea::placeholder {
            color: #a1a1aa; font-weight: 400;
        }
        .preview-modal__field input:focus, .preview-modal__field textarea:focus {
            outline: none; border-color: var(--preview-primary); box-shadow: 0 0 0 3px hsl(265 72% 51% / .15);
        }
        .preview-modal__optional { font-weight: 400; color: #a1a1aa; }
        .claim-hp { position: absolute; left: -9999px; }
        body.has-claim-bar { padding-bottom: 4.75rem; }
        @media (max-width: 767px) {
            .mobile-cta {
                display: flex;
                position: fixed;
                left: 0;
                right: 0;
                bottom: var(--preview-bar-height, 0px);
                z-index: 35;
                width: 100%;
                justify-content: center;
                border-radius: 0;
                padding: 1rem 1.25rem;
                box-shadow: 0 -10px 28px rgba(0, 0, 0, .2);
                transform: translateY(110%);
                opacity: 0;
                pointer-events: none;
                transition: transform .32s ease, opacity .32s ease;
            }
            .mobile-cta.is-visible { transform: translateY(0); opacity: 1; pointer-events: auto; }
            body.has-mobile-cta { padding-bottom: calc(3.75rem + var(--preview-bar-height, 0px)); }
            .brand { max-width: calc(100% - 3.5rem); }
        }
        @media (max-width: 767px) and (prefers-reduced-motion: reduce) {
            .mobile-cta { transition: none; }
        }
        body.preview-modal-open { overflow: hidden; }
        @keyframes preview-fade-in { from { opacity: 0; } to { opacity: 1; } }
        @keyframes preview-slide-up { from { opacity: 0; transform: translateY(12px) scale(.98); } to { opacity: 1; transform: translateY(0) scale(1); } }
        @media (min-width: 768px) {
            .preview-bar { padding: .75rem 1.5rem; justify-content: space-between; }
            .preview-bar__note { text-align: left; }
        }
        .header-actions { display: flex; align-items: center; flex: none; gap: .75rem; }
        .header-cta { display: none; max-width: 16rem; padding: .7rem 1.05rem; white-space: nowrap; }
        .header-cta span { overflow: hidden; text-overflow: ellipsis; }
        .nav a[aria-current="page"] { opacity: 1; font-weight: 700; color: var(--accent); }
        .mobile-menu { position: relative; }
        .mobile-menu summary { list-style: none; cursor: pointer; width: 2.6rem; height: 2.6rem; display: grid; place-content: center; gap: .3rem; border-radius: var(--radius); border: 1px solid color-mix(in srgb, currentColor 25%, transparent); }
        .mobile-menu summary::-webkit-details-marker { display: none; }
        .mobile-menu summary span { display: block; width: 1.2rem; height: 2px; background: currentColor; }
        .mobile-menu nav { position: absolute; right: 0; top: calc(100% + .75rem); min-width: 13rem; display: grid; padding: .5rem; border-radius: var(--radius); background: var(--surface, #fff); color: var(--ink, #0f172a); border: 1px solid var(--line, #e2e8f0); box-shadow: 0 18px 40px rgba(0,0,0,.18); }
        .mobile-menu nav a { padding: .75rem .9rem; text-decoration: none; border-radius: var(--radius); font-weight: 500; }
        .mobile-menu nav a[aria-current="page"] { color: var(--accent); font-weight: 700; }
        .hero.page-hero { min-height: 56vh; display: flex; flex-direction: column; justify-content: center; align-items: stretch; color: #fff; background: linear-gradient(135deg, color-mix(in srgb, var(--accent) 58%, #0b1220), #0b1220); }
        .hero.page-hero .hero-image { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; clip-path: none; border-radius: 0; box-shadow: none; opacity: 1; }
        .hero.page-hero::after { content: ''; position: absolute; inset: 0; background: linear-gradient(90deg, rgba(8,12,24,.82) 0%, rgba(8,12,24,.5) 55%, rgba(8,12,24,.28) 100%); }
        .page-hero .container { position: relative; z-index: 1; display: block; width: 100%; max-width: 1140px; margin: 0 auto; padding: 7.5rem 1.25rem 4.25rem; text-align: left; }
        .page-hero h1 { max-width: none; font-size: clamp(2rem, 4vw, 3.15rem); margin: .65rem 0 .75rem; color: #fff; }
        .page-hero p { margin: 0 0 1.5rem; font-size: 1.12rem; max-width: 38rem; color: rgba(255,255,255,.9); }
        .page-hero .breadcrumbs, .page-hero .breadcrumbs a, .page-hero .breadcrumbs [aria-current="page"] { color: #fff; }
        .page-hero .btn-secondary { background: transparent; color: #fff; border: 1.5px solid rgba(255,255,255,.7); }
        .about-story { display: grid; gap: 2.5rem; align-items: center; }
        .about-story__copy .prose { max-width: 40rem; }
        .about-portrait { position: relative; margin: 0; aspect-ratio: 4 / 5; overflow: hidden; border-radius: calc(var(--radius) * 1.2); background: color-mix(in srgb, var(--accent) 18%, #1f2937); box-shadow: 0 24px 50px -28px rgba(15, 23, 42, .45); }
        .about-portrait img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .about-portrait__fallback { width: 100%; height: 100%; display: grid; place-items: center; font-size: 4.5rem; font-weight: 700; color: #fff; }
        .about-portrait .edit-add-image.is-block { position: absolute; inset: auto auto 1rem 1rem; }
        .mission-grid { display: grid; gap: 1.5rem; align-items: start; }
        .mission-body { margin: 0; font-size: 1.15rem; line-height: 1.7; max-width: 40rem; }
        .service-features { display: grid; gap: 4rem; }
        .service-feature { display: grid; gap: 2rem; align-items: center; }
        .service-feature__copy .service-title { font-size: clamp(1.45rem, 2.5vw, 2.05rem); margin: 0 0 .75rem; }
        .service-feature__copy p { margin: 0; font-size: 1.08rem; line-height: 1.7; max-width: 38rem; opacity: .88; }
        .service-feature .section-actions { margin-top: 1.5rem; }
        .service-feature__media { margin: 0; }
        .service-feature__media .service-photo { width: 100%; height: clamp(220px, 34vw, 380px); margin: 0; border-radius: calc(var(--radius) * 1.15); box-shadow: 0 22px 48px -26px rgba(15, 23, 42, .4); }
        .breadcrumbs ol { list-style: none; display: flex; flex-wrap: wrap; align-items: center; gap: .4rem; padding: 0; margin: 0; font-size: .82rem; font-weight: 600; letter-spacing: .05em; text-transform: uppercase; }
        .breadcrumbs li + li::before { content: '/'; margin-right: .4rem; opacity: .45; }
        .breadcrumbs a { text-decoration: none; opacity: .75; }
        .breadcrumbs a:hover { opacity: 1; }
        .breadcrumbs [aria-current="page"] { color: var(--accent); }
        .prose { max-width: 46rem; font-size: 1.08rem; }
        .prose p { margin: 0 0 1.25rem; }
        a.service { display: block; text-decoration: none; color: inherit; transition: transform .15s ease, border-color .15s ease; }
        a.service:hover { transform: translateY(-2px); border-color: var(--accent); }
        .more-link { display: inline-block; margin-top: 1rem; color: var(--accent); font-weight: 600; text-decoration: none; }
        .section-link { margin-top: 2rem; }
        .post-meta { margin: 0 0 .5rem; font-size: .85rem; opacity: .65; }
        .service-layout { display: grid; gap: 3rem; align-items: start; }
        .service-title { font-family: inherit; font-size: 1.15rem; font-weight: 600; letter-spacing: 0; margin: 0 0 .5rem; }
        .service-aside { display: grid; gap: 1rem; }
        .service-aside h2 { margin: 0; font-size: 1.25rem; }
        .service-aside ul { list-style: none; margin: 0; padding: 0; display: grid; gap: .5rem; }
        .service-aside li a { text-decoration: none; opacity: .85; }
        .service-aside li a:hover, .service-aside li a[aria-current="page"] { opacity: 1; color: var(--accent); }
        .footer-nav { display: flex; flex-wrap: wrap; gap: .5rem 1.25rem; }
        .footer-nav a { text-decoration: none; opacity: .8; }
        .footer-nav a:hover { opacity: 1; }
        .footer-social { display: flex; flex-wrap: wrap; gap: .55rem; }
        .footer-social__link {
            display: inline-grid; place-items: center; width: 2.4rem; height: 2.4rem;
            border-radius: 999px; text-decoration: none; opacity: .9;
            background: color-mix(in srgb, currentColor 12%, transparent); color: inherit;
            transition: opacity .15s ease, background .15s ease, transform .15s ease;
        }
        .footer-social__link:hover { opacity: 1; background: color-mix(in srgb, var(--accent) 28%, transparent); transform: translateY(-1px); }
        .footer-social__icon { display: grid; place-items: center; width: 1.15rem; height: 1.15rem; }
        .footer-social__icon svg { width: 100%; height: 100%; display: block; }
        @media (min-width: 768px) {
            .nav { display: flex; min-width: 0; }
            .header-cta { display: inline-flex; }
            .mobile-menu { display: none; }
            .split { grid-template-columns: 1fr 1fr; }
            .contact-grid { grid-template-columns: 1.15fr .85fr; }
            .service-layout { grid-template-columns: minmax(0, 1fr) 20rem; }
            .about-story { grid-template-columns: minmax(0, 1.05fr) minmax(0, .95fr); gap: 4rem; }
            .mission-grid { grid-template-columns: minmax(0, .8fr) minmax(0, 1.2fr); gap: 3rem; }
            .service-layout { grid-template-columns: minmax(0, 1fr) 20rem; }
        }
        @media (min-width: 800px) {
            .service-feature { grid-template-columns: 1fr 1fr; gap: 3.5rem; }
            .service-feature.is-alt .service-feature__copy { order: 2; }
        }
        @media (min-width: 700px) { .reviews-grid { columns: 2; } }
        @media (min-width: 1000px) { .reviews-grid { columns: 3; } }
        @media (min-width: 900px) { .locale-switcher { display: inline-flex; } }
        .layout-split-hero .hero .container { display: grid; gap: 2rem; align-items: center; }
        @media (min-width: 900px) { .layout-split-hero .hero .container { grid-template-columns: 1fr 1fr; } }
        .layout-split-hero .hero.page-hero .container { display: block; grid-template-columns: none; }
        .layout-card-services .services { grid-template-columns: repeat(auto-fit, minmax(min(100%, 280px), 1fr)); }
        .layout-card-services .service { box-shadow: 0 12px 30px rgba(15,23,42,.08); }
        .layout-editorial-hero .hero h1 { max-width: min(22ch, 100%); font-style: italic; }
    </style>
    @include('sites.themes.'.$template)
    @include('sites.partials.section-variants')
    @if ($designFontsUrl)
        <link rel="stylesheet" href="{{ $designFontsUrl }}" media="print" onload="this.media='all'">
    @endif
    @if ($designCss)
        <style>{!! $designCss !!}</style>
    @endif
    @if (! empty($sectionCss))
        <style>{!! $sectionCss !!}</style>
    @endif
    <style>
        [data-sec-style="tint"] > section:not(.cta-band) { background: color-mix(in srgb, var(--accent) 9%, transparent); }
        [data-sec-style="accent"] > section:not(.cta-band) { background: var(--accent); color: var(--on-accent); }
        [data-sec-style="dark"] > section:not(.cta-band) { background: #0f172a; color: #f8fafc; }
        [data-sec-style="accent"] .section-label, [data-sec-style="accent"] .more-link { color: inherit; }
        [data-sec-style="dark"] .section-label, [data-sec-style="dark"] .more-link { color: color-mix(in srgb, var(--accent) 65%, #fff); }
        [data-sec-style="accent"] .service, [data-sec-style="accent"] .faq details, [data-sec-style="accent"] .review-card,
        [data-sec-style="dark"] .service, [data-sec-style="dark"] .faq details, [data-sec-style="dark"] .review-card {
            background: color-mix(in srgb, currentColor 8%, transparent); border-color: color-mix(in srgb, currentColor 20%, transparent); color: inherit;
        }
        body:not(.is-scrolled):not(.has-overlay-nav):not(.hero-v-overlay):not(.hero-v-card) .site-header {
            background: color-mix(in srgb, var(--bg, var(--cream, #fff)) 88%, transparent);
            backdrop-filter: blur(12px);
            color: var(--ink, #0f172a);
        }
        body:not(.is-scrolled):is(.has-overlay-nav, .hero-v-overlay, .hero-v-card) .site-header { background: transparent; border-bottom-color: transparent; color: #fff; box-shadow: none; }
        body:not(.is-scrolled):is(.has-overlay-nav, .hero-v-overlay, .hero-v-card) .site-header .brand,
        body:not(.is-scrolled):is(.has-overlay-nav, .hero-v-overlay, .hero-v-card) .site-header .nav a,
        body:not(.is-scrolled):is(.has-overlay-nav, .hero-v-overlay, .hero-v-card) .site-header .locale-switcher a { color: #fff; }
        body:not(.is-scrolled):is(.has-overlay-nav, .hero-v-overlay, .hero-v-card) .brand-logo { height: 3.4rem; max-width: 13rem; }
        body:not(.is-scrolled):is(.has-overlay-nav, .hero-v-overlay, .hero-v-card) .brand-mark { width: 2.9rem; height: 2.9rem; }
        body.is-scrolled .site-header { background: color-mix(in srgb, var(--bg, var(--cream, #fff)) 92%, transparent); backdrop-filter: blur(16px); border-bottom-color: var(--line, rgba(128,128,128,.22)); box-shadow: 0 10px 28px -18px rgba(15,23,42,.45); color: var(--ink, #0f172a); }
        body.is-scrolled .site-header .container { padding-top: .7rem; padding-bottom: .7rem; }
        body.is-scrolled .nav a { color: inherit; }
        body.is-scrolled .nav a[aria-current="page"] { color: var(--accent); }
        @media (max-width: 900px) {
            body:is(.has-overlay-nav, .hero-v-overlay, .hero-v-card) .hero .container { padding-top: 6.5rem; }
            body.has-overlay-nav .page-hero .container { padding-top: 7.5rem; }
        }
    </style>
    @if ($editing)
        @include('sites.partials.editor-styles')
    @endif
</head>
<body class="{{ trim(($layoutClass ?? '').' '.($asPreview ? 'has-claim-bar' : '').' '.(($page['type'] ?? 'home') !== 'home' ? 'has-overlay-nav' : '')) }}">
    <header class="site-header"{!! $e->section('header', 'Header') !!}>
        <div class="container">
            <a href="{{ $urls->to() }}" class="brand">
                @if ($logoUrl)
                    <img class="brand-logo" src="{{ $logoUrl }}" alt="{{ $lead->name }} logo"{!! $e->image('logo', null, 'Logo') !!}>
                @else
                    <span class="brand-mark"{!! $e->image('logo', null, 'Logo') !!}>{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($lead->name, 0, 1)) }}</span>
                @endif
                <span>{{ $lead->name }}</span>
            </a>
            <nav class="nav" aria-label="Main">
                @foreach ($nav as $item)
                    <a href="{{ $item['url'] }}" @if ($item['key'] === $page['key']) aria-current="page" @endif>{{ $item['label'] }}</a>
                @endforeach
            </nav>
            <div class="header-actions">
                @if (! empty($localeSwitcher))
                    <nav class="locale-switcher" aria-label="Languages">
                        @foreach ($localeSwitcher as $item)
                            <a href="{{ $item['url'] }}" @if (($locale ?? 'en') === $item['code']) aria-current="page" @endif>{{ $item['label'] }}</a>
                        @endforeach
                    </nav>
                @endif
                <a class="btn btn-primary header-cta" href="{{ collect($nav)->contains('key', 'contact') ? $urls->to('contact') : ($phoneHref ?? '#contact') }}"><span>{{ $content['cta_label'] }}</span></a>
                <details class="mobile-menu">
                    <summary aria-label="Menu"><span></span><span></span><span></span></summary>
                    <nav aria-label="Mobile">
                        @foreach ($nav as $item)
                            <a href="{{ $item['url'] }}" @if ($item['key'] === $page['key']) aria-current="page" @endif>{{ $item['label'] }}</a>
                        @endforeach
                    </nav>
                </details>
            </div>
        </div>
    </header>

    <main id="top">
        @yield('main')
    </main>

    @if (($page['key'] ?? '') !== 'contact')
        @include('sites.partials.cta-band')
    @endif

    @include('sites.partials.footer')

    <a class="btn btn-primary mobile-cta" href="{{ collect($nav)->contains('key', 'contact') ? $urls->to('contact') : ($phoneHref ?? '#contact') }}">{{ $content['cta_label'] }}</a>

    @if (! empty($sectionBeacons))
        <script>
            (() => {
                if (new URLSearchParams(location.search).get('internal') === '1') {
                    return;
                }

                const token = @json(csrf_token());
                const beacons = @json($sectionBeacons);
                const seen = {};

                const ping = (key) => {
                    if (seen[key] || ! beacons[key]) {
                        return;
                    }

                    seen[key] = true;
                    fetch(beacons[key], {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': token,
                            'X-Requested-With': 'XMLHttpRequest',
                            Accept: 'application/json',
                        },
                        credentials: 'same-origin',
                    });
                };

                const observer = new IntersectionObserver((entries) => {
                    entries.forEach((entry) => {
                        if (entry.isIntersecting) {
                            ping(entry.target.id);
                        }
                    });
                }, { threshold: 0.35 });

                Object.keys(beacons).forEach((key) => {
                    const node = document.getElementById(key);

                    if (node) {
                        observer.observe(node);
                    }
                });
            })();
        </script>
    @endif

    <script>
        (() => {
            const update = () => document.body.classList.toggle('is-scrolled', window.scrollY > 24);
            update();
            window.addEventListener('scroll', update, { passive: true });
        })();
        (() => {
            const bar = document.querySelector('.mobile-cta');

            if (!bar) {
                return;
            }

            const hero = document.querySelector('main .hero');
            const desktop = window.matchMedia('(min-width: 768px)');
            const preview = document.querySelector('.preview-bar');

            const syncPreview = () => {
                document.documentElement.style.setProperty('--preview-bar-height', (preview ? preview.offsetHeight : 0) + 'px');
            };

            const show = (visible) => {
                bar.classList.toggle('is-visible', visible);
                document.body.classList.toggle('has-mobile-cta', visible && !desktop.matches);
            };

            const update = () => {
                syncPreview();

                if (!hero) {
                    show(true);

                    return;
                }

                show(hero.getBoundingClientRect().bottom <= window.innerHeight * 0.55);
            };

            update();
            window.addEventListener('scroll', update, { passive: true });
            window.addEventListener('resize', update);
        })();
    </script>

    <dialog class="gallery-lightbox" id="gallery-lightbox" aria-label="Gallery photo">
        <div class="gallery-lightbox__panel">
            <button type="button" class="gallery-lightbox__close" data-gallery-close aria-label="Close">&times;</button>
            <img class="gallery-lightbox__img" alt="">
        </div>
    </dialog>
    <script>
        (() => {
            const dialog = document.getElementById('gallery-lightbox');
            const image = dialog?.querySelector('.gallery-lightbox__img');

            if (! dialog || ! image) {
                return;
            }

            const open = (src, alt) => {
                image.src = src;
                image.alt = alt || '';
                if (typeof dialog.showModal === 'function') {
                    dialog.showModal();
                } else {
                    dialog.setAttribute('open', '');
                }
            };

            const close = () => {
                if (typeof dialog.close === 'function') {
                    dialog.close();
                } else {
                    dialog.removeAttribute('open');
                }
                image.removeAttribute('src');
            };

            document.addEventListener('click', (event) => {
                const zoom = event.target.closest?.('[data-gallery-open]');

                if (zoom) {
                    const editing = document.documentElement.classList.contains('sm-editing');

                    if (editing && event.target.closest?.('[data-edit-image]')) {
                        return;
                    }

                    event.preventDefault();
                    event.stopPropagation();
                    open(zoom.dataset.gallerySrc || '', zoom.dataset.galleryAlt || '');

                    return;
                }

                if (event.target === dialog || event.target.closest?.('[data-gallery-close]')) {
                    close();
                }
            });

            dialog.addEventListener('cancel', (event) => {
                event.preventDefault();
                close();
            });
        })();
    </script>

    @if ($editing)
        @include('sites.partials.editor-bridge')
    @endif

    @if ($asPreview)
        @if (request()->boolean('thanks'))
            <div class="preview-bar" role="status">
                <p class="preview-bar__note">Thank you! We have your details and will be in touch shortly.</p>
            </div>
        @else
            @include('sites.partials.preview-claim-bar')
        @endif
    @endif
</body>
</html>
