@php
    $quoteHref = collect($nav)->contains('key', 'contact') ? $urls->to('contact') : ($phoneHref ?? '#contact');
    $headingAttributes = ! empty($headingKey) ? $e->label($headingKey) : (! empty($headingPath) ? $e->text($headingPath, $headingLabel ?? 'Heading', 60) : '');
    $introAttributes = ! empty($introKey) ? $e->label($introKey) : (! empty($introPath) ? $e->text($introPath, $introLabel ?? 'Introduction', 250, true) : '');
    $heroSlot = $heroSlot ?? 'home_hero';
    $heroIndex = $heroIndex ?? null;
    $heroLabel = $heroSlot === 'service' ? 'Service photo' : 'Page hero photo';
@endphp
<section class="hero page-hero">
    @if ($heroImage)
        <img class="hero-image" src="{{ $heroImage }}" alt="{{ $lead->name }}" loading="eager" decoding="async"{!! $e->image($heroSlot, $heroIndex, $heroLabel) !!}>
        @if ($editing)
            <button type="button" class="edit-change-hero"{!! $e->image($heroSlot, $heroIndex, $heroLabel) !!}>Change photo</button>
        @endif
    @elseif ($editing)
        <button type="button" class="edit-add-image"{!! $e->image($heroSlot, $heroIndex, $heroLabel) !!}>Add a hero photo</button>
    @endif
    <div class="container">
        @include('sites.partials.breadcrumbs')
        @if (! empty($headingPath) && ! empty($headingSuffix))
            <h1><span{!! $headingAttributes !!}>{{ $heading }}</span>{{ $headingSuffix }}</h1>
        @else
            <h1{!! $headingAttributes !!}>{{ ! empty($headingKey) ? $e->copy($headingKey, $heading) : $heading }}</h1>
        @endif
        @if (! empty($intro))
            <p{!! $introAttributes !!}>{{ ! empty($introKey) ? $e->copy($introKey, $intro) : $intro }}</p>
        @endif
        <div class="hero-actions">
            <a class="btn btn-primary" href="{{ $quoteHref }}">
                <span{!! $e->text('cta_label', 'Button text', 40) !!}>{{ $content['cta_label'] }}</span>
            </a>
            @if ($phoneHref)
                <a class="btn btn-secondary" href="{{ $phoneHref }}">@include('sites.icons.phone') <span{!! $e->label('call_label') !!}>{{ $e->copy('call_label', 'Call Now') }}</span></a>
            @endif
        </div>
    </div>
</section>
