<style>
    :root { --radius: 22px; --ink: #292524; --muted: #57534e; --cream: #fdf8f3; --sand: #f5ebe0; --line: #e7d9c9; }
    body { font-family: 'Nunito Sans', system-ui, sans-serif; color: var(--ink); background: var(--cream); }
    h1, h2, h3, .brand { font-family: 'Fraunces', Georgia, serif; font-weight: 600; letter-spacing: -.01em; }
    .site-header { background: color-mix(in srgb, var(--cream) 92%, transparent); backdrop-filter: blur(10px); border-bottom: 1px solid var(--line); }
    .brand-mark { border-radius: 999px; }
    .btn { border-radius: 999px; }
    .hero { background: radial-gradient(circle at 85% 20%, color-mix(in srgb, var(--accent) 16%, var(--cream)) 0%, var(--cream) 55%); }
    .hero .container { display: grid; align-items: center; }
    .hero-image { position: absolute; top: 3rem; right: 4%; bottom: 3rem; width: 38%; height: calc(100% - 6rem); object-fit: cover; border-radius: 200px 200px var(--radius) var(--radius); box-shadow: 0 30px 60px -20px rgba(41,37,36,.35); }
    .hero-eyebrow { color: var(--accent); text-transform: none; letter-spacing: 0; font-size: 1rem; font-style: italic; font-family: 'Fraunces', Georgia, serif; }
    .hero p { color: var(--muted); }
    .btn-secondary { background: transparent; color: var(--ink); border: 1.5px solid var(--ink); }
    .trust { background: var(--sand); color: var(--ink); }
    .services-block { background: #fff; }
    .service { background: var(--cream); border: 1px solid var(--line); text-align: center; }
    .service-icon { margin-left: auto; margin-right: auto; border-radius: 999px; }
    .section-label { font-family: 'Fraunces', Georgia, serif; font-style: italic; text-transform: none; letter-spacing: 0; font-size: 1.05rem; font-weight: 500; }
    .about-block { background: var(--sand); }
    .about-text { color: var(--muted); }
    .gallery img { border-radius: var(--radius); }
    .hours td { border-bottom: 1px dashed var(--line); }
    .faq details { background: #fff; border: 1px solid var(--line); }
    .contact-block { background: #fff; }
    .site-footer { background: var(--ink); color: #d6d3d1; }
    @media (max-width: 900px) {
        .hero-image { position: relative; top: 0; right: 0; width: calc(100% - 2.5rem); height: 280px; margin: 1.5rem auto 0; border-radius: 160px 160px var(--radius) var(--radius); }
        .hero .container { padding-top: 2.5rem; padding-bottom: 3.5rem; }
    }
    .reviews-block { background: #fff; }
    .review-card { --review-bg: var(--cream); }
    .reviews-controls button { --review-bg: var(--cream); }
</style>
