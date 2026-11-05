<style>
    :root { --radius: 14px; --ink: #0f172a; --muted: #475569; --surface: #f8fafc; --line: #e2e8f0; }
    body { font-family: 'Plus Jakarta Sans', system-ui, sans-serif; color: var(--ink); background: #fff; }
    h1, h2, h3 { font-weight: 800; letter-spacing: -.02em; }
    .site-header { border-bottom: 1px solid transparent; }
    .header-cta { border-radius: 12px; padding: .7rem 1.1rem; }
    .hero { background: linear-gradient(135deg, color-mix(in srgb, var(--accent) 8%, #fff) 0%, #fff 58%); }
    .hero-image { position: absolute; inset: 0 0 0 auto; width: 46%; height: 100%; object-fit: cover; clip-path: polygon(14% 0, 100% 0, 100% 100%, 0 100%); }
    .hero-eyebrow { color: var(--accent); background: color-mix(in srgb, var(--accent) 12%, #fff); padding: .4rem .85rem; border-radius: 999px; }
    .hero p { color: var(--muted); }
    .btn-secondary { background: #fff; color: var(--ink); border: 1px solid var(--line); }
    .trust { background: #fff; color: var(--ink); border-bottom: 1px solid var(--line); }
    .trust .container { justify-content: flex-start; gap: .65rem; }
    .trust span { padding: .4rem .85rem; border-radius: 999px; border: 1px solid var(--line); background: var(--surface); font-size: .82rem; }
    .trust svg { color: var(--accent); }
    .services-block, .process-block, .faq-block, .areas-block { background: var(--surface); }
    .service { background: #fff; border: 1px solid var(--line); box-shadow: 0 1px 2px rgba(15,23,42,.04); }
    .about-text { color: var(--muted); }
    .hours td { border-bottom: 1px solid var(--line); }
    .faq details { background: #fff; border: 1px solid var(--line); }
    .why-block { background: #fff; }
    .site-footer { background: #0f2744; color: #d6e0ea; }
    .footer-bar { border-color: rgba(255,255,255,.12); }
    @media (max-width: 900px) {
        .hero-image { position: relative; width: 100%; height: 260px; clip-path: none; }
        .hero .container { padding-top: 3rem; padding-bottom: 3.5rem; }
    }
    .reviews-block { background: var(--surface); }
    .review-card { box-shadow: 0 1px 2px rgba(15,23,42,.04); }
</style>
