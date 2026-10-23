@extends('sites.layout')

@section('main')
    <section class="hero">
        @if ($heroImage)
            <img class="hero-image" src="{{ $heroImage }}" alt="" loading="eager" fetchpriority="high" decoding="async">
        @endif
        <div class="container">
            <span class="hero-eyebrow">{{ $lead->category }}{{ $lead->city ? ' · '.$lead->city : '' }}</span>
            <h1>{{ $content['headline'] }}</h1>
            <p>{{ $content['tagline'] }}</p>
            <div class="hero-actions">
                <a class="btn btn-primary" href="#contact">{{ $content['cta_label'] }}</a>
                @if ($phoneHref)
                    <a class="btn btn-secondary" href="{{ $phoneHref }}">@include('sites.icons.phone') Call Now</a>
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
        @include('sites.sections.'.$section)
    @endforeach
@endsection
