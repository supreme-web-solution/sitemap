<section class="cta-band">
    <div class="container">
        <h2>{{ $ctaHeading ?? 'Ready to get started?' }}</h2>
        <p>{{ $content['tagline'] }}</p>
        <a class="btn" href="{{ $phoneHref ?? $urls->to('contact') }}">{{ $content['cta_label'] }}</a>
    </div>
</section>