@php
    $ctaVariant = $layoutVariants['cta'] ?? 'solid';
    $ctaImage = $ctaBandImage ?? null;
    $showCtaPhoto = filled($ctaImage) || (! empty($editing) && in_array($ctaVariant, ['photo', 'split'], true));
@endphp
<section
    class="cta-band"
    data-section="cta"
    data-section-label="Call-to-action banner"
    @if (filled($ctaImage) && in_array($ctaVariant, ['photo', 'split', 'solid'], true))
        style="--cta-image: url('{{ $ctaImage }}')"
    @endif
>
    @if ($showCtaPhoto)
        @if (filled($ctaImage))
            <img
                class="cta-band__media"
                src="{{ $ctaImage }}"
                alt=""
                loading="lazy"
                decoding="async"
                {!! $e->image('cta_band', null, 'CTA background photo') !!}
            >
        @elseif (! empty($editing))
            <button
                type="button"
                class="edit-add-image is-gallery cta-band__add"
                {!! $e->image('cta_band', null, 'CTA background photo') !!}
            >
                Add a background photo
            </button>
        @endif
    @endif

    <div class="container">
        <h2{!! $e->label('cta_heading') !!}>{{ $e->copy('cta_heading', $ctaHeading ?? 'Ready to get started?') }}</h2>
        <p{!! $e->text('tagline', 'Tagline', 200, true) !!}>{{ $content['tagline'] }}</p>
        <a class="btn" href="{{ collect($nav)->contains('key', 'contact') ? $urls->to('contact') : ($phoneHref ?? '#') }}"><span{!! $e->text('cta_label', 'Button text', 40) !!}>{{ $content['cta_label'] }}</span></a>
    </div>
</section>
