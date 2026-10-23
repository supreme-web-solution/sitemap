<!DOCTYPE html>
<html lang="en">
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
        html { scroll-behavior: smooth; }
        body { margin: 0; -webkit-font-smoothing: antialiased; line-height: 1.6; }
        img { max-width: 100%; display: block; }
        a { color: inherit; }
        .container { width: 100%; max-width: 1140px; margin: 0 auto; padding: 0 1.25rem; }
        .btn { display: inline-flex; align-items: center; gap: .5rem; padding: .85rem 1.5rem; border-radius: var(--radius); font-weight: 600; text-decoration: none; transition: transform .15s ease, opacity .15s ease; }
        .btn:hover { transform: translateY(-1px); opacity: .92; }
        .btn svg { width: 1.1rem; height: 1.1rem; }
        .btn-primary { background: var(--accent); color: var(--on-accent); }
        .site-header { position: sticky; top: 0; z-index: 20; }
        .site-header .container { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding-top: 1rem; padding-bottom: 1rem; }
        .brand { display: flex; align-items: center; gap: .75rem; font-weight: 700; font-size: 1.1rem; text-decoration: none; }
        .brand-mark { width: 2.25rem; height: 2.25rem; border-radius: var(--radius); background: var(--accent); color: var(--on-accent); display: grid; place-items: center; font-weight: 800; }
        .brand-logo { height: 2.5rem; width: auto; max-width: 9rem; object-fit: contain; }
        .gallery { display: grid; gap: 1rem; margin-top: 2.5rem; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); }
        .gallery img { width: 100%; aspect-ratio: 4 / 3; object-fit: cover; border-radius: var(--radius); }
        .nav { display: none; gap: 1.75rem; font-size: .95rem; }
        .nav a { text-decoration: none; opacity: .8; }
        .nav a:hover { opacity: 1; }
        .hero { position: relative; overflow: hidden; }
        .hero .container { position: relative; z-index: 1; padding-top: 6rem; padding-bottom: 6rem; }
        .hero-eyebrow { display: inline-flex; align-items: center; gap: .5rem; font-size: .85rem; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; }
        .hero h1 { font-size: clamp(2.25rem, 5vw, 3.75rem); line-height: 1.08; margin: 1rem 0; max-width: 16ch; }
        .hero p { font-size: 1.2rem; max-width: 36rem; margin: 0 0 2rem; }
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
        .services { display: grid; gap: 1.25rem; margin-top: 2.5rem; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); }
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
        .reviews-carousel { position: relative; }
        .reviews-track { display: flex; gap: 1.25rem; overflow-x: auto; scroll-snap-type: x mandatory; scrollbar-width: none; padding: .25rem .1rem 1rem; }
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
        .reviews-marquee { overflow: hidden; -webkit-mask-image: linear-gradient(90deg, transparent, #000 6%, #000 94%, transparent); mask-image: linear-gradient(90deg, transparent, #000 6%, #000 94%, transparent); }
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
        .contact-list { list-style: none; padding: 0; margin: 1.5rem 0 0; display: grid; gap: .9rem; }
        .contact-list li { display: flex; gap: .75rem; align-items: flex-start; }
        .contact-list svg { width: 1.2rem; height: 1.2rem; flex: none; margin-top: .2rem; color: var(--accent); }
        .site-footer { padding: 2.5rem 0; font-size: .9rem; }
        .site-footer .container { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 1rem; }
        .mobile-call { position: fixed; left: 1rem; right: 1rem; bottom: 1rem; z-index: 30; justify-content: center; box-shadow: 0 10px 30px rgba(0,0,0,.25); }
        .claim-bar { position: fixed; left: 0; right: 0; bottom: 0; z-index: 40; background: #0f172a; color: #fff; padding: .85rem 1.25rem; display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: .75rem 1.25rem; font-family: system-ui, sans-serif; font-size: .95rem; box-shadow: 0 -8px 24px rgba(0,0,0,.2); }
        .claim-bar a { background: #22c55e; color: #052e16; padding: .55rem 1.1rem; border-radius: 999px; font-weight: 700; text-decoration: none; }
        .claim-discuss summary { cursor: pointer; background: #fff; color: #0f172a; padding: .55rem 1.1rem; border-radius: 999px; font-weight: 700; list-style: none; }
        .claim-discuss summary::-webkit-details-marker { display: none; }
        .claim-discuss form { display: grid; gap: .5rem; margin-top: .75rem; width: min(22rem, 85vw); }
        .claim-discuss input, .claim-discuss textarea { font: inherit; padding: .55rem .75rem; border-radius: .6rem; border: 1px solid #334155; background: #1e293b; color: #fff; }
        .claim-discuss button { font: inherit; background: #22c55e; color: #052e16; border: 0; padding: .6rem 1rem; border-radius: 999px; font-weight: 700; cursor: pointer; }
        .claim-hp { position: absolute; left: -9999px; }
        body.has-claim-bar { padding-bottom: 7rem; }
        body.has-claim-bar .mobile-call { bottom: 8rem; }
        .claim-packages { display: flex; flex-wrap: wrap; gap: .4rem; justify-content: center; }
        .claim-packages button { font: inherit; background: #fff; color: #0f172a; border: 0; padding: .45rem .9rem; border-radius: 999px; font-weight: 700; cursor: pointer; }
        .header-actions { display: flex; align-items: center; gap: .75rem; }
        .header-call { display: none; }
        .nav a[aria-current="page"] { opacity: 1; font-weight: 700; color: var(--accent); }
        .mobile-menu { position: relative; }
        .mobile-menu summary { list-style: none; cursor: pointer; width: 2.6rem; height: 2.6rem; display: grid; place-content: center; gap: .3rem; border-radius: var(--radius); border: 1px solid color-mix(in srgb, currentColor 25%, transparent); }
        .mobile-menu summary::-webkit-details-marker { display: none; }
        .mobile-menu summary span { display: block; width: 1.2rem; height: 2px; background: currentColor; }
        .mobile-menu nav { position: absolute; right: 0; top: calc(100% + .75rem); min-width: 13rem; display: grid; padding: .5rem; border-radius: var(--radius); background: var(--surface, #fff); color: var(--ink, #0f172a); border: 1px solid var(--line, #e2e8f0); box-shadow: 0 18px 40px rgba(0,0,0,.18); }
        .mobile-menu nav a { padding: .75rem .9rem; text-decoration: none; border-radius: var(--radius); font-weight: 500; }
        .mobile-menu nav a[aria-current="page"] { color: var(--accent); font-weight: 700; }
        .hero.page-hero { min-height: 0; display: block; }
        .page-hero .container { padding-top: 4.5rem; padding-bottom: 4rem; }
        .page-hero h1 { max-width: 24ch; font-size: clamp(2rem, 4.5vw, 3.1rem); margin: 1rem 0 .75rem; }
        .page-hero p { margin: 0; font-size: 1.15rem; max-width: 40rem; }
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
        @media (min-width: 768px) {
            .nav { display: flex; }
            .header-call { display: inline-flex; }
            .mobile-menu { display: none; }
            .split { grid-template-columns: 1fr 1fr; }
            .service-layout { grid-template-columns: minmax(0, 1fr) 20rem; }
            .mobile-call { display: none; }
        }
        @media (min-width: 700px) { .reviews-grid { columns: 2; } }
        @media (min-width: 1000px) { .reviews-grid { columns: 3; } }
    </style>
    @include('sites.themes.'.$template)
</head>
<body class="{{ $asPreview ? 'has-claim-bar' : '' }}">
    <header class="site-header">
        <div class="container">
            <a href="{{ $urls->to() }}" class="brand">
                @if ($logoUrl)
                    <img class="brand-logo" src="{{ $logoUrl }}" alt="{{ $lead->name }} logo">
                @else
                    <span class="brand-mark">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($lead->name, 0, 1)) }}</span>
                @endif
                <span>{{ $lead->name }}</span>
            </a>
            <nav class="nav" aria-label="Main">
                @foreach ($nav as $item)
                    <a href="{{ $item['url'] }}" @if ($item['key'] === $page['key']) aria-current="page" @endif>{{ $item['label'] }}</a>
                @endforeach
            </nav>
            <div class="header-actions">
                @if ($phoneHref)
                    <a class="btn btn-primary header-call" href="{{ $phoneHref }}">@include('sites.icons.phone') <span>{{ $lead->phone }}</span></a>
                @endif
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

    <footer class="site-footer">
        <div class="container">
            <span>&copy; {{ $year }} {{ $lead->name }}. All rights reserved.</span>
            <nav class="footer-nav" aria-label="Footer">
                @foreach ($nav as $item)
                    <a href="{{ $item['url'] }}">{{ $item['label'] }}</a>
                @endforeach
            </nav>
            @if ($lead->address)
                <span>{{ $lead->address }}</span>
            @endif
        </div>
    </footer>

    @if ($phoneHref)
        <a class="btn btn-primary mobile-call" href="{{ $phoneHref }}">@include('sites.icons.phone') Call {{ $lead->name }}</a>
    @endif

    @if ($asPreview)
        <div class="claim-bar" role="region" aria-label="Website preview">
            @if (request()->boolean('thanks'))
                <span>Thank you! We have your details and will be in touch shortly.</span>
            @else
                <span>This is a free preview website created for <strong>{{ $lead->name }}</strong>.</span>
                <details class="claim-discuss">
                    <summary>Discuss this website</summary>
                    <form method="POST" action="{{ route('sites.preview.interest', $site->slug) }}">
                        @csrf
                        <input type="text" name="name" placeholder="Your name" maxlength="100" autocomplete="name">
                        <input type="text" name="contact" placeholder="Phone or email" maxlength="150" required>
                        <textarea name="message" placeholder="Anything you would like changed? (optional)" maxlength="1000" rows="2"></textarea>
                        <input type="text" name="company_url" class="claim-hp" tabindex="-1" autocomplete="off" aria-hidden="true">
                        <button type="submit">Send</button>
                    </form>
                </details>
                @if ($claimEmail)
                    <a href="mailto:{{ $claimEmail }}?subject={{ rawurlencode('Claim website: '.$lead->name) }}">Claim this website</a>
                @endif
                @if (($packages ?? collect())->isNotEmpty())
                    <div class="claim-packages" role="group" aria-label="Choose a package">
                        @foreach ($packages as $package)
                            <form method="POST" action="{{ $packageAction }}">
                                @csrf
                                <input type="hidden" name="package_id" value="{{ $package->id }}">
                                <input type="text" name="company_url" class="claim-hp" tabindex="-1" autocomplete="off" aria-hidden="true">
                                <button type="submit">{{ $package->name }} · {{ \App\Models\Invoice::formatCents($package->unit_cents) }}</button>
                            </form>
                        @endforeach
                    </div>
                @endif
                @if ($commentAction ?? null)
                    <details class="claim-discuss">
                        <summary>Comment on a section</summary>
                        <form method="POST" action="{{ $commentAction }}">
                            @csrf
                            <input type="text" name="author_name" placeholder="Your name" maxlength="80" required>
                            <input type="text" name="author_contact" placeholder="Phone or email" maxlength="120">
                            <input type="text" name="section" placeholder="Section, e.g. About" maxlength="40">
                            <textarea name="body" placeholder="What should change?" maxlength="1000" rows="2" required></textarea>
                            <input type="text" name="company_url" class="claim-hp" tabindex="-1" autocomplete="off" aria-hidden="true">
                            <button type="submit">Send comment</button>
                        </form>
                    </details>
                @endif
            @endif
        </div>
    @endif
</body>
</html>
