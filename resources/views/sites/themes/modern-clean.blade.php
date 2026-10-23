<style>
    :root { --radius: 14px; --ink: #0f172a; --muted: #475569; --surface: #f8fafc; --line: #e2e8f0; }
    body { font-family: 'Plus Jakarta Sans', system-ui, sans-serif; color: var(--ink); background: #fff; }
    h1, h2, h3 { font-weight: 800; letter-spacing: -.02em; }
    .site-header { background: rgba(255,255,255,.92); backdrop-filter: blur(10px); border-bottom: 1px solid var(--line); }
    .hero { background: linear-gradient(135deg, color-mix(in srgb, var(--accent) 10%, #fff) 0%, #fff 60%); }
    .hero-image { position: absolute; inset: 0 0 0 auto; width: 46%; height: 100%; object-fit: cover; clip-path: polygon(14% 0, 100% 0, 100% 100%, 0 100%); }
    .hero-eyebrow { color: var(--accent); background: color-mix(in srgb, var(--accent) 12%, #fff); padding: .4rem .85rem; border-radius: 999px; }
    .hero p { color: var(--muted); }
    .btn-secondary { background: #fff; color: var(--ink); border: 1px solid var(--line); }
    .trust { background: var(--ink); color: #fff; }
    .trust svg { color: #4ade80; }
    .services-block { background: var(--surface); }
    .service { background: #fff; border: 1px solid var(--line); box-shadow: 0 1px 2px rgba(15,23,42,.04); }
    .about-text { color: var(--muted); }
    .hours td { border-bottom: 1px solid var(--line); }
    .faq-block { background: var(--surface); }
    .faq details { background: #fff; border: 1px solid var(--line); }
    .site-footer { background: var(--ink); color: #cbd5e1; }
    @media (max-width: 900px) {
        .hero-image { position: relative; width: 100%; height: 260px; clip-path: none; }
        .hero .container { padding-top: 3rem; padding-bottom: 3.5rem; }
    }
    .reviews-block { background: var(--surface); }
    .review-card { box-shadow: 0 1px 2px rgba(15,23,42,.04); }
</style>
