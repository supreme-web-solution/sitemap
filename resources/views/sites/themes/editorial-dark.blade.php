<style>
    :root { --radius: 6px; --ink: #f5f5f4; --muted: #a8a29e; --bg: #0c0a09; --surface: #1c1917; --line: #292524; }
    body { font-family: 'Inter', system-ui, sans-serif; color: var(--ink); background: var(--bg); }
    h1, h2 { font-family: 'Fraunces', Georgia, serif; font-weight: 700; letter-spacing: -.01em; }
    h3 { font-weight: 600; }
    .site-header { background: rgba(12,10,9,.85); backdrop-filter: blur(10px); border-bottom: 1px solid var(--line); }
    .hero { min-height: 82vh; display: flex; align-items: center; background: radial-gradient(circle at 20% 20%, color-mix(in srgb, var(--accent) 22%, transparent), transparent 55%), var(--bg); }
    .hero-image { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; opacity: .38; }
    .hero::after { content: ''; position: absolute; inset: 0; background: linear-gradient(90deg, var(--bg) 10%, rgba(12,10,9,.55) 60%, rgba(12,10,9,.2)); }
    .hero-eyebrow { color: var(--accent); }
    .hero-eyebrow::before { content: ''; width: 2.5rem; height: 2px; background: var(--accent); }
    .hero p { color: #d6d3d1; }
    .btn-secondary { background: transparent; color: var(--ink); border: 1px solid rgba(245,245,244,.3); }
    .trust { border-top: 1px solid var(--line); border-bottom: 1px solid var(--line); color: var(--muted); }
    .service { background: var(--surface); border: 1px solid var(--line); }
    .service p, .about-text { color: var(--muted); }
    .about-block { background: var(--surface); }
    .hours td { border-bottom: 1px solid var(--line); }
    .hours td:first-child { color: var(--muted); }
    .map { filter: grayscale(.6) invert(.9) hue-rotate(180deg); }
    .faq details { background: var(--surface); border: 1px solid var(--line); }
    .cta-band .btn { background: #0c0a09; color: #fff; }
    .contact-block { background: var(--surface); }
    .site-footer { border-top: 1px solid var(--line); color: var(--muted); }
    .reviews-block { background: var(--bg); }
    .review-card { --review-bg: var(--surface); }
    .reviews-controls button { --review-bg: var(--surface); }
    .review-meta small, .reviews-source, .reviews-summary { color: var(--muted); }
</style>
