<style>
    /* ===== Hero variants ===== */
    .hero-v-centered .hero .container { display: flex; flex-direction: column; align-items: center; text-align: center; grid-template-columns: none; }
    .hero-v-centered .hero h1, .hero-v-centered .hero p { margin-left: auto; margin-right: auto; }
    .hero-v-centered .hero h1 { max-width: 20ch; }
    .hero-v-centered .hero-actions { justify-content: center; }
    .hero-v-centered .hero .hero-image { position: absolute; inset: 0; width: 100%; height: 100%; clip-path: none; border-radius: 0; box-shadow: none; object-fit: cover; opacity: .16; }
    .hero-v-centered .hero::after { display: none; }

    .hero-v-overlay .hero { min-height: 78vh; display: flex; align-items: center; color: #fff; background: linear-gradient(135deg, color-mix(in srgb, var(--accent) 70%, #0b1220), #0b1220); }
    .hero-v-overlay .hero .hero-image { position: absolute; inset: 0; width: 100%; height: 100%; clip-path: none; border-radius: 0; box-shadow: none; object-fit: cover; opacity: 1; }
    .hero-v-overlay .hero::after { content: ''; position: absolute; inset: 0; background: linear-gradient(90deg, rgba(8,12,24,.84) 0%, rgba(8,12,24,.56) 55%, rgba(8,12,24,.26) 100%); }
    .hero-v-overlay .hero .container { grid-template-columns: none; }
    .hero-v-overlay .hero h1 { color: #fff; }
    .hero-v-overlay .hero p, .hero-v-overlay .hero .rating { color: rgba(255,255,255,.9); }
    .hero-v-overlay .hero-eyebrow { color: #fff; background: rgba(255,255,255,.16); padding: .4rem .85rem; border-radius: 999px; }
    .hero-v-overlay .hero-eyebrow::before { display: none; }
    .hero-v-overlay .btn-secondary { background: transparent; color: #fff; border: 1.5px solid rgba(255,255,255,.7); }

    @media (min-width: 900px) {
        .hero-v-split .hero { min-height: 70vh; display: flex; align-items: center; }
        .hero-v-split .hero .container { display: block; grid-template-columns: none; width: 50%; max-width: 50%; margin: 0; padding-left: max(1.25rem, calc((100vw - 1140px) / 2 + 1.25rem)); padding-right: 2.5rem; }
        .hero-v-split .hero .hero-image { position: absolute; inset: 0 0 0 auto; width: 46%; height: 100%; clip-path: none; border-radius: 0; box-shadow: none; opacity: 1; object-fit: cover; }
    }
    @media (max-width: 899px) {
        .hero-v-split .hero { display: block; min-height: 0; }
        .hero-v-split .hero .hero-image { position: relative; inset: auto; width: 100%; height: 260px; margin: 0; clip-path: none; border-radius: 0; box-shadow: none; opacity: 1; object-fit: cover; }
    }
    .hero-v-split .hero .container { grid-template-columns: none; }
    .hero-v-split .hero::after { display: none; }

    .hero-v-minimal .hero { display: block; min-height: 0; border-bottom: 1px solid color-mix(in srgb, currentColor 12%, transparent); }
    .hero-v-minimal .hero .hero-image, .hero-v-minimal .hero::after { display: none; }
    .hero-v-minimal .hero .container { padding-top: 7rem; padding-bottom: 5.5rem; grid-template-columns: none; display: block; }
    .hero-v-minimal .hero h1 { font-size: clamp(2.75rem, 7vw, 5.25rem); line-height: 1.02; letter-spacing: -.03em; max-width: 18ch; }
    .hero-v-minimal .hero p { max-width: 34rem; }
    .hero-v-minimal .hero-eyebrow { padding: 0; background: transparent; border-radius: 0; }

    .hero-v-photo-top .hero { display: block; min-height: 0; }
    .hero-v-photo-top .hero::after { display: none; }
    .hero-v-photo-top .hero .hero-image { position: relative; inset: auto; display: block; width: min(1140px, calc(100% - 2.5rem)); height: clamp(220px, 34vw, 400px); margin: 1.5rem auto 0; clip-path: none; border-radius: var(--radius); box-shadow: none; opacity: 1; object-fit: cover; }
    .hero-v-photo-top .hero .container { padding-top: 3rem; padding-bottom: 4rem; grid-template-columns: none; display: block; }

    .hero-v-card .hero { min-height: 74vh; display: flex; align-items: center; padding: 3.5rem 0; }
    .hero-v-card .hero .hero-image { position: absolute; inset: 0; width: 100%; height: 100%; clip-path: none; border-radius: 0; box-shadow: none; opacity: 1; object-fit: cover; }
    .hero-v-card .hero::after { content: ''; position: absolute; inset: 0; background: rgba(8,12,24,.3); }
    .hero-v-card .hero .container { display: block; grid-template-columns: none; width: min(640px, calc(100% - 2.5rem)); max-width: none; margin: 0 auto 0 max(1.25rem, calc((100vw - 1140px) / 2 + 1.25rem)); padding: 2.5rem; border-radius: var(--radius); background: color-mix(in srgb, var(--bg, var(--cream, #fff)) 94%, transparent); backdrop-filter: blur(6px); box-shadow: 0 30px 60px -24px rgba(0,0,0,.45); }
    .hero-v-card .hero p { margin-bottom: 1.5rem; }

    /* Inner pages always keep a photo overlay hero, even if the homepage uses split/minimal. */
    .hero.page-hero,
    .hero-v-overlay .hero.page-hero,
    .hero-v-split .hero.page-hero,
    .hero-v-card .hero.page-hero,
    .hero-v-centered .hero.page-hero,
    .hero-v-minimal .hero.page-hero,
    .hero-v-photo-top .hero.page-hero {
        min-height: 56vh;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: stretch;
        color: #fff;
        background: linear-gradient(135deg, color-mix(in srgb, var(--accent) 58%, #0b1220), #0b1220);
        padding: 0;
    }
    .hero.page-hero .hero-image,
    .hero-v-overlay .hero.page-hero .hero-image,
    .hero-v-split .hero.page-hero .hero-image,
    .hero-v-card .hero.page-hero .hero-image,
    .hero-v-centered .hero.page-hero .hero-image,
    .hero-v-minimal .hero.page-hero .hero-image,
    .hero-v-photo-top .hero.page-hero .hero-image {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
        clip-path: none;
        border-radius: 0;
        box-shadow: none;
        opacity: 1;
        display: block;
    }
    .hero.page-hero::after,
    .hero-v-overlay .hero.page-hero::after,
    .hero-v-card .hero.page-hero::after {
        content: '';
        display: block;
        position: absolute;
        inset: 0;
        background: linear-gradient(90deg, rgba(8,12,24,.82) 0%, rgba(8,12,24,.5) 55%, rgba(8,12,24,.28) 100%);
    }
    .hero.page-hero .container,
    .hero-v-overlay .hero.page-hero .container,
    .hero-v-card .hero.page-hero .container,
    .hero-v-split .hero.page-hero .container,
    .hero-v-centered .hero.page-hero .container,
    .hero-v-photo-top .hero.page-hero .container,
    .hero-v-minimal .hero.page-hero .container {
        display: block;
        width: 100%;
        max-width: 1140px;
        margin-left: auto;
        margin-right: auto;
        text-align: left;
        align-items: stretch;
        background: none;
        box-shadow: none;
        backdrop-filter: none;
        padding: 7.5rem 1.25rem 4.25rem;
        color: #fff;
    }
    .hero-v-centered .hero.page-hero h1,
    .hero-v-centered .hero.page-hero p { margin-left: 0; margin-right: 0; }
    .hero-v-centered .hero.page-hero .hero-actions { justify-content: flex-start; }
    .hero.page-hero h1,
    .hero.page-hero p,
    .hero-v-overlay .hero.page-hero h1,
    .hero-v-overlay .hero.page-hero p,
    .hero-v-card .hero.page-hero h1,
    .hero-v-card .hero.page-hero p { color: #fff; }

    /* ===== Header / nav underline variants ===== */
    .header-v-grow .nav a::after { transform-origin: left center; height: 2px; transition: transform .38s cubic-bezier(.22, 1, .36, 1); }
    .header-v-slide .nav a::after { transform-origin: left center; height: 2px; width: 0; right: auto; left: 0; transform: none; transition: width .28s ease; }
    .header-v-slide .nav a:hover::after, .header-v-slide .nav a[aria-current="page"]::after { width: 100%; transform: none; }
    .header-v-bar .nav a::after { height: 3px; border-radius: 999px; }
    .header-v-dot .nav a::after { left: 50%; right: auto; width: .42rem; height: .42rem; border-radius: 999px; bottom: -.55rem; transform: translateX(-50%) scale(0); transform-origin: center; }
    .header-v-dot .nav a:hover::after, .header-v-dot .nav a[aria-current="page"]::after { transform: translateX(-50%) scale(1); }
    .header-v-pill .nav a { padding: .35rem .85rem; border-radius: 999px; }
    .header-v-pill .nav a::after { display: none; }
    .header-v-pill .nav a:hover, .header-v-pill .nav a[aria-current="page"] { background: color-mix(in srgb, var(--accent) 18%, transparent); }
    body:not(.is-scrolled):is(.has-overlay-nav, .hero-v-overlay, .hero-v-card).header-v-pill .nav a:hover,
    body:not(.is-scrolled):is(.has-overlay-nav, .hero-v-overlay, .hero-v-card).header-v-pill .nav a[aria-current="page"] { background: rgba(255,255,255,.16); }
    @media (prefers-reduced-motion: reduce) {
        .nav a::after { transition: none; }
    }

    /* ===== About page / mission variants ===== */
    @media (min-width: 800px) {
        .about_page-v-portrait .about-story { grid-template-columns: minmax(0, .9fr) minmax(0, 1.1fr); }
        .about_page-v-portrait .about-portrait { order: -1; }
    }
    .about_page-v-stacked .about-story { grid-template-columns: 1fr; max-width: 46rem; margin: 0 auto; }
    .about_page-v-stacked .about-portrait { max-width: 28rem; aspect-ratio: 16 / 10; }
    .about_page-v-band .about-story-block { background: var(--accent); color: var(--on-accent); }
    .about_page-v-band .about-story-block .section-label,
    .about_page-v-band .about-story-block .section-title,
    .about_page-v-band .about-story-block .prose,
    .about_page-v-band .about-story-block .btn-secondary { color: inherit; }
    .about_page-v-band .about-story-block .btn-primary { background: #fff; color: #111; }

    .mission-v-centered .mission-grid { grid-template-columns: 1fr; text-align: center; max-width: 42rem; margin: 0 auto; }
    .mission-v-centered .mission-body { margin-left: auto; margin-right: auto; }
    .mission-v-band .mission-block { background: var(--accent); color: var(--on-accent); }
    .mission-v-band .mission-block .section-label,
    .mission-v-band .mission-body { color: inherit; }

    /* ===== Services page variants ===== */
    .services_page-v-reverse .service-feature:not(.is-alt) .service-feature__copy { order: 2; }
    .services_page-v-reverse .service-feature.is-alt .service-feature__copy { order: 0; }
    .services_page-v-stack .service-feature.is-alt .service-feature__copy { order: 0; }
    .services_page-v-featured .service-feature:first-child .service-feature__media .service-photo { height: clamp(280px, 42vw, 460px); }
    @media (min-width: 800px) {
        .services_page-v-featured .service-feature:first-child { grid-template-columns: 1.15fr .85fr; }
    }

    /* ===== Services variants ===== */
    .services-v-list .services { grid-template-columns: 1fr; gap: 0; border-top: 1px solid var(--line, rgba(128,128,128,.3)); }
    .services-v-list .service { display: grid; grid-template-columns: 3rem 1fr; column-gap: 1.25rem; align-items: start; padding: 1.4rem 0; background: transparent; border: 0; border-bottom: 1px solid var(--line, rgba(128,128,128,.3)); border-radius: 0; box-shadow: none; text-align: left; }
    .services-v-list .service-icon { grid-row: 1 / span 3; margin: 0; }
    .services-v-list .service-photo { grid-row: 1 / span 3; width: 5.5rem; height: 5.5rem; margin: 0; border-radius: var(--radius); }
    .services-v-list .service h3, .services-v-list .service p, .services-v-list .service .more-link { grid-column: 2; }

    .services-v-numbered .services { counter-reset: svc; }
    .services-v-numbered .service { counter-increment: svc; position: relative; padding-top: 3.75rem; text-align: left; }
    .services-v-numbered .service-icon { display: none; }
    .services-v-numbered .service::before { content: counter(svc, decimal-leading-zero); position: absolute; top: 1.25rem; left: 1.75rem; font-size: 2rem; font-weight: 800; line-height: 1; color: var(--accent); font-variant-numeric: tabular-nums; }

    .services-v-columns .services { gap: 2.5rem; }
    .services-v-columns .service { background: transparent; border: 0; border-top: 3px solid var(--accent); border-radius: 0; box-shadow: none; padding: 1.25rem 0 0; text-align: left; }
    .services-v-columns .service-icon { margin: 0 0 .75rem; width: auto; height: auto; background: none; border-radius: 0; place-items: start; }

    .service-photo { width: 100%; height: 11rem; object-fit: cover; border-radius: var(--radius); margin-bottom: 1rem; display: block; background: color-mix(in srgb, var(--accent) 18%, #1f2937); }
    .services-v-numbered .service-photo { height: 9rem; }
    .services-v-columns .service-photo { height: 10rem; margin-bottom: .85rem; }
    .services-v-featured .service-photo { height: 13rem; }
    .services-v-tiles .service-photo { height: 10rem; }
    .services-v-outline .service-photo { height: 10rem; }
    .services-v-featured .services { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .services-v-featured .service:first-child { grid-column: span 2; background: var(--accent); color: var(--on-accent); border-color: transparent; }
    .services-v-featured .service:first-child h3 { font-size: 1.55rem; }
    .services-v-featured .service:first-child p { opacity: .92; }
    .services-v-featured .service:first-child .service-icon { background: rgba(255,255,255,.2); color: inherit; }
    .services-v-featured .service:first-child .more-link { color: inherit; }
    @media (max-width: 800px) {
        .services-v-featured .services { grid-template-columns: 1fr; }
        .services-v-featured .service:first-child { grid-column: auto; }
    }

    .services-v-tiles .service { background: color-mix(in srgb, var(--accent) 8%, transparent); border: 1px solid color-mix(in srgb, var(--accent) 18%, transparent); border-radius: calc(var(--radius) * 1.4); box-shadow: none; padding: 2rem 1.75rem; text-align: center; transition: transform .2s ease; }
    .services-v-tiles .service:hover { transform: translateY(-3px); }
    .services-v-tiles .service-icon { margin: 0 auto 1rem; width: 3.25rem; height: 3.25rem; border-radius: 999px; background: var(--accent); color: var(--on-accent); }

    .services-v-outline .service { background: transparent; border: 1.5px solid color-mix(in srgb, var(--accent) 45%, transparent); box-shadow: none; transition: background .2s ease, border-color .2s ease, transform .2s ease; }
    .services-v-outline .service:hover { background: color-mix(in srgb, var(--accent) 10%, transparent); border-color: var(--accent); transform: translateY(-2px); }

    /* ===== About variants ===== */
    .about-v-centered .about-block .split { grid-template-columns: 1fr; gap: 1rem; max-width: 46rem; margin: 0 auto; text-align: center; }

    .about-v-quote .about-block .split { grid-template-columns: 1fr; gap: 1.5rem; }
    .about-v-quote .about-text { font-size: clamp(1.35rem, 2.6vw, 2rem); line-height: 1.45; font-weight: 500; border-left: 4px solid var(--accent); padding-left: 1.5rem; max-width: 52rem; color: var(--ink, inherit); }

    .about-v-card .about-block { background: color-mix(in srgb, var(--accent) 7%, var(--bg, var(--cream, #fff))); }
    .about-v-card .about-block .split { grid-template-columns: 1fr; gap: 1rem; max-width: 56rem; margin: 0 auto; padding: 2.5rem; background: var(--bg, var(--cream, #fff)); border: 1px solid var(--line, rgba(128,128,128,.25)); border-radius: calc(var(--radius) * 1.2); box-shadow: 0 20px 50px -25px rgba(15,23,42,.25); }

    .about-v-columns .about-block .split { grid-template-columns: 1fr; gap: 1.25rem; }
    @media (min-width: 768px) { .about-v-columns .about-text { column-count: 2; column-gap: 3rem; } }

    .about-v-highlight .about-block { background: var(--accent); color: var(--on-accent); }
    .about-v-highlight .about-block .section-label, .about-v-highlight .about-block .more-link, .about-v-highlight .about-block .about-text { color: inherit; }
    .about-v-highlight .about-block .section-label { opacity: .85; }
    .about-v-highlight .about-text { font-size: 1.2rem; }

    /* ===== Gallery variants ===== */
    .gallery-v-masonry .gallery { display: block; column-count: 3; column-gap: 1rem; }
    .gallery-v-masonry .gallery img { margin-bottom: 1rem; break-inside: avoid; }
    .gallery-v-masonry .gallery img:nth-child(3n+1) { aspect-ratio: 3 / 4; }
    .gallery-v-masonry .gallery img:nth-child(3n+2) { aspect-ratio: 4 / 3; }
    .gallery-v-masonry .gallery img:nth-child(3n) { aspect-ratio: 1 / 1; }
    @media (max-width: 700px) { .gallery-v-masonry .gallery { column-count: 2; } }

    .gallery-v-mosaic .gallery { grid-template-columns: repeat(3, 1fr); grid-auto-rows: 190px; grid-auto-flow: dense; }
    .gallery-v-mosaic .gallery img { height: 100%; aspect-ratio: auto; }
    .gallery-v-mosaic .gallery img:first-child { grid-column: span 2; grid-row: span 2; }
    @media (max-width: 700px) {
        .gallery-v-mosaic .gallery { grid-template-columns: repeat(2, 1fr); grid-auto-rows: 150px; }
        .gallery-v-mosaic .gallery img:first-child { grid-row: span 1; }
    }

    .gallery-v-strip .gallery { display: flex; overflow-x: auto; scroll-snap-type: x mandatory; gap: 1rem; padding-bottom: .75rem; scrollbar-width: thin; }
    .gallery-v-strip .gallery img { flex: 0 0 min(80%, 26rem); scroll-snap-align: start; }

    .gallery-v-polaroid .gallery { gap: 1.75rem; padding: .5rem; }
    .gallery-v-polaroid .gallery img { background: #fff; padding: .6rem .6rem 2.4rem; border-radius: 3px; box-shadow: 0 14px 30px -12px rgba(0,0,0,.35); aspect-ratio: 1 / 1; transform: rotate(-1.5deg); transition: transform .25s ease; }
    .gallery-v-polaroid .gallery img:nth-child(even) { transform: rotate(1.6deg); }
    .gallery-v-polaroid .gallery img:hover { transform: rotate(0) scale(1.03); }

    .gallery-v-duo .gallery { grid-template-columns: repeat(2, 1fr); gap: 1.25rem; }
    .gallery-v-duo .gallery img { aspect-ratio: 3 / 2; }
    @media (max-width: 600px) { .gallery-v-duo .gallery { grid-template-columns: 1fr; } }

    /* ===== FAQ variants ===== */
    .faq-v-two-column .faq { grid-template-columns: repeat(2, minmax(0, 1fr)); align-items: start; gap: 1rem 1.5rem; }
    @media (max-width: 760px) { .faq-v-two-column .faq { grid-template-columns: 1fr; } }

    .faq-v-lined .faq { gap: 0; border-top: 1px solid var(--line, rgba(128,128,128,.3)); }
    .faq-v-lined .faq details { background: transparent; border: 0; border-bottom: 1px solid var(--line, rgba(128,128,128,.3)); border-radius: 0; padding: 1.2rem 0; }

    .faq-v-cards .faq details { background: color-mix(in srgb, var(--accent) 8%, transparent); border: 1px solid color-mix(in srgb, var(--accent) 20%, transparent); }
    .faq-v-cards .faq details[open] { background: color-mix(in srgb, var(--accent) 14%, transparent); }

    .faq-v-numbered .faq { counter-reset: faq; }
    .faq-v-numbered .faq details { counter-increment: faq; }
    .faq-v-numbered .faq summary::before { content: counter(faq, decimal-leading-zero); color: var(--accent); font-weight: 800; margin-right: .85rem; font-variant-numeric: tabular-nums; }

    /* ===== Process variants ===== */
    .process-v-timeline .process-steps { grid-template-columns: 1fr; max-width: 40rem; margin-left: auto; margin-right: auto; gap: 0; border-left: 2px solid color-mix(in srgb, var(--accent) 40%, transparent); padding-left: 1.5rem; }
    .process-v-timeline .process-step { position: relative; background: transparent; border: 0; border-radius: 0; padding: 0 0 1.75rem; }
    .process-v-timeline .process-step::before { content: ''; position: absolute; left: calc(-1.5rem - 5px); top: .35rem; width: .7rem; height: .7rem; border-radius: 999px; background: var(--accent); }

    .process-v-stacked .process-steps { grid-template-columns: 1fr; gap: 0; }
    .process-v-stacked .process-step { display: grid; grid-template-columns: 4.5rem 1fr; column-gap: 1rem; background: transparent; border: 0; border-bottom: 1px solid var(--line, rgba(128,128,128,.25)); border-radius: 0; padding: 1.5rem 0; }
    .process-v-stacked .process-step__num { grid-row: 1 / span 2; font-size: 2.25rem; margin: 0; }
    .process-v-stacked .process-step h3, .process-v-stacked .process-step p { grid-column: 2; }

    .process-v-cards .process-step { text-align: center; box-shadow: 0 16px 40px -28px rgba(15,23,42,.45); }
    .process-v-cards .process-step__num { margin-left: auto; margin-right: auto; }

    @media (min-width: 860px) {
        .process-v-split .process-block .container { display: grid; grid-template-columns: minmax(0, .9fr) minmax(0, 1.3fr); gap: 3rem; align-items: start; }
        .process-v-split .process-steps { margin-top: 0; }
    }

    /* ===== Why variants ===== */
    .why-v-numbered .why-points { grid-template-columns: 1fr; gap: 0; }
    .why-v-numbered .why-point { display: grid; grid-template-columns: 3.5rem 1fr; column-gap: 1rem; background: transparent; border: 0; border-bottom: 1px solid var(--line, rgba(128,128,128,.25)); border-radius: 0; padding: 1.4rem 0; }
    .why-v-numbered .why-point__num { margin: 0; }
    .why-v-numbered .why-point h3, .why-v-numbered .why-point p { grid-column: 2; }

    .why-v-list .why-points { grid-template-columns: 1fr; gap: .65rem; }
    .why-v-list .why-point { display: grid; grid-template-columns: 1.5rem 1fr; column-gap: .85rem; background: transparent; border: 0; padding: .35rem 0; }
    .why-v-list .why-point__num { display: none; }
    .why-v-list .why-point::before { content: ''; width: .7rem; height: .7rem; margin-top: .45rem; border-radius: 999px; background: var(--accent); }
    .why-v-list .why-point h3, .why-v-list .why-point p { grid-column: 2; }

    .why-v-band .why-block { background: var(--accent); color: var(--on-accent); }
    .why-v-band .why-block .section-label, .why-v-band .why-block .section-intro, .why-v-band .why-point p { color: inherit; opacity: .9; }
    .why-v-band .why-point { background: color-mix(in srgb, #fff 10%, transparent); border-color: color-mix(in srgb, #fff 22%, transparent); }
    .why-v-band .why-point__num { color: inherit; }

    @media (min-width: 860px) {
        .why-v-split .why-block .container { display: grid; grid-template-columns: minmax(0, .9fr) minmax(0, 1.3fr); gap: 3rem; align-items: start; }
        .why-v-split .why-points { margin-top: 0; }
    }

    /* ===== Areas variants ===== */
    .areas-v-grid .area-chips { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); }
    .areas-v-grid .area-chips li { border-radius: var(--radius); text-align: center; padding: 1rem; }

    .areas-v-list .area-chips { display: grid; gap: 0; max-width: 28rem; }
    .areas-v-list .area-chips li { background: transparent; border: 0; border-bottom: 1px solid var(--line, rgba(128,128,128,.25)); border-radius: 0; padding: .7rem 0; }

    @media (min-width: 860px) {
        .areas-v-split .areas-block .container { display: grid; grid-template-columns: minmax(0, .9fr) minmax(0, 1.3fr); gap: 3rem; align-items: start; }
        .areas-v-split .area-chips { margin-top: .25rem; }
    }

    /* ===== Hours / contact variants ===== */
    .hours-v-stacked .visit-block .split, .contact-v-stacked .contact-block .split { grid-template-columns: 1fr; gap: 1.5rem; max-width: 44rem; margin: 0 auto; text-align: center; }
    .hours-v-stacked .hours td:last-child, .contact-v-stacked .contact-list { text-align: left; }
    .hours-v-cards .visit-block .split > *, .contact-v-cards .contact-block .split > * { padding: 1.75rem; border-radius: var(--radius); border: 1px solid var(--line, rgba(128,128,128,.25)); background: var(--surface, #fff); }

    /* ===== Footer variants ===== */
    .footer-v-mega .footer-main { grid-template-columns: minmax(0, 1.15fr) repeat(3, minmax(0, 1fr)); gap: 2.25rem 2.5rem; padding-top: 3.75rem; padding-bottom: 3rem; }
    .footer-v-mega .footer-col h3, .footer-v-mega .footer-hours-label { margin-bottom: 1rem; }
    .footer-v-stacked .footer-main { grid-template-columns: 1fr; max-width: 40rem; margin: 0 auto; text-align: left; }
    .footer-v-stacked .footer-brand { text-align: center; }
    .footer-v-stacked .footer-brand p { margin-left: auto; margin-right: auto; }
    .footer-v-compact .footer-main, .footer-v-compact .footer-cta { display: none; }
    .footer-v-compact .footer-bar { border-top: 0; }
    .footer-v-cta .footer-cta { display: block; }
</style>
