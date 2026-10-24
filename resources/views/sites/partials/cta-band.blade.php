<section class="cta-band">
    <div class="container">
        <h2{!! $e->label('cta_heading') !!}>{{ $e->copy('cta_heading', $ctaHeading ?? 'Ready to get started?') }}</h2>
        <p{!! $e->text('tagline', 'Tagline', 200, true) !!}>{{ $content['tagline'] }}</p>
        <a class="btn" href="{{ $phoneHref ?? $urls->to('contact') }}"><span{!! $e->text('cta_label', 'Button text', 40) !!}>{{ $content['cta_label'] }}</span></a>
    </div>
</section>
