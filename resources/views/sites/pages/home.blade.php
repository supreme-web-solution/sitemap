@extends('sites.layout')

@section('main')
    <section id="hero" class="hero"{!! $e->section('hero', 'Hero') !!}>
        @if ($heroImage)
            <img class="hero-image" src="{{ $heroImage }}" alt="{{ $lead->name }}" loading="eager" fetchpriority="high" decoding="async"{!! $e->image('photo', 0, 'Hero photo') !!}>
            @if ($editing)
                <button type="button" class="edit-add-image edit-change-hero"{!! $e->image('photo', 0, 'Hero photo') !!}>Change photo</button>
            @endif
        @elseif ($editing)
            <button type="button" class="edit-add-image"{!! $e->image('photo', 0, 'Hero photo') !!}>Add a hero photo</button>
        @endif
        <div class="container">
            <span class="hero-eyebrow"{!! $e->label('hero_kicker') !!}>{{ $e->copy('hero_kicker', trim(($lead->category ?? '').($lead->city ? ' · '.$lead->city : ''))) }}</span>
            <h1 class="keeps-lines"{!! $e->text('headline', 'Headline', \App\Services\SiteBuilder\SiteContentSchema::HEADLINE_MAX, true) !!}>{{ $content['headline'] }}</h1>
            <p class="keeps-lines"{!! $e->text('tagline', 'Tagline', 200, true) !!}>{{ $content['tagline'] }}</p>
            <div class="hero-actions">
                <a class="btn btn-primary" href="{{ collect($nav)->contains('key', 'contact') ? $urls->to('contact') : ($phoneHref ?? '#contact') }}"><span{!! $e->text('cta_label', 'Button text', 40) !!}>{{ $content['cta_label'] }}</span></a>
                @if ($phoneHref)
                    <a class="btn btn-secondary" href="{{ $phoneHref }}">@include('sites.icons.phone') <span{!! $e->label('call_label') !!}>{{ $e->copy('call_label', 'Call Now') }}</span></a>
                @endif
            </div>
            @if ($lead->rating && $lead->reviews_count > 0)
                <div class="rating">
                    <span class="stars" aria-hidden="true">
                        @for ($i = 0; $i < 5; $i++) @include('sites.icons.star') @endfor
                    </span>
                    <span>{{ number_format($lead->rating, 1) }} rating from {{ number_format($lead->reviews_count) }} Google reviews</span>
                </div>
            @endif
        </div>
    </section>

    @php
        $trustPoints = array_filter([
            $lead->city ? 'Serving '.$lead->city : null,
            $lead->rating && $lead->rating >= 4.5 && $lead->reviews_count >= 10 ? 'Highly rated on Google' : null,
            count(array_filter($hours, fn ($time) => ! \Illuminate\Support\Str::contains($time, 'closed', true))) === 7 ? 'Open 7 days a week' : null,
            $phoneHref ? 'Call us directly' : null,
        ]);
    @endphp
    @if (count($trustPoints) >= 2)
        <div class="trust">
            <div class="container">
                @foreach ($trustPoints as $point)
                    <span>@include('sites.icons.check') {{ $point }}</span>
                @endforeach
            </div>
        </div>
    @endif

    @foreach ($sections as $section)
        @continue($section === 'contact')
        @if (\App\Services\SiteBuilder\CustomHomeSection::isKey($section))
            @php $customId = \App\Services\SiteBuilder\CustomHomeSection::idFromKey($section); @endphp
            <div{!! $e->section($section, $content['custom_sections'][$customId]['label'] ?? 'Custom section') !!}>
                @include('sites.sections.custom', ['customId' => $customId])
            </div>
        @else
            <div{!! $e->section($section, \App\Enums\SiteSection::from($section)->label()) !!}>
                @include('sites.sections.'.$section)
            </div>
        @endif
    @endforeach
@endsection
