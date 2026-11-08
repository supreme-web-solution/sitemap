@extends('sites.layout')

@php
    $serviceIndex = collect($services)->search(fn (array $item) => $item['slug'] === $page['service']['slug']);
    $serviceIndex = $serviceIndex === false ? null : $serviceIndex;
    $otherServices = collect($services)->filter(
        fn (array $item) => $item['slug'] !== $page['service']['slug'],
    );
    $showReviews = $reviews->count() >= \App\Models\Lead::MIN_TESTIMONIALS;
@endphp

@section('main')
    @include('sites.partials.page-hero', [
        'heading' => $page['service']['title'],
        'headingPath' => $serviceIndex !== null ? 'services.'.$serviceIndex.'.title' : null,
        'headingLabel' => 'Service name',
        'intro' => $page['service']['description'],
        'introPath' => $serviceIndex !== null ? 'services.'.$serviceIndex.'.description' : null,
        'introLabel' => 'Service description',
    ])

    <section class="block">
        <div class="container service-layout">
            <div>
                @php
                    $bodyAttributes = $serviceIndex !== null ? $e->text('services.'.$serviceIndex.'.body', 'Service page text', 5000, true, reload: true) : '';
                @endphp
                @include('sites.partials.prose', [
                    'text' => filled($page['service']['body'])
                        ? $page['service']['body']
                        : $page['service']['description'],
                    'editAttributes' => $bodyAttributes,
                ])
            </div>
            <aside class="service">
                <div class="service-aside">
                    <h2>Our services</h2>
                    <ul>
                        @foreach ($services as $other)
                            <li>
                                <a href="{{ $urls->to('services/'.$other['slug']) }}" @if ($other['slug'] === $page['service']['slug']) aria-current="page" @endif>{{ $other['title'] }}</a>
                            </li>
                        @endforeach
                    </ul>
                    <a class="btn btn-primary" href="{{ $phoneHref ?? $urls->to('contact') }}"><span{!! $e->text('cta_label', 'Button text', 40) !!}>{{ $content['cta_label'] }}</span></a>
                </div>
            </aside>
        </div>
    </section>

    @if ($otherServices->isNotEmpty())
        <section class="block service-related-block">
            <div class="container">
                <span class="section-label">More from {{ $lead->name }}</span>
                <h2 class="section-title">Other services</h2>
                <div class="services">
                    @foreach ($services as $service)
                        @continue($service['slug'] === $page['service']['slug'])
                        <a class="service" href="{{ $urls->to('services/'.$service['slug']) }}">
                            @include('sites.partials.service-card-media')
                            <h3 class="service-title">{{ $service['title'] }}</h3>
                            <p class="keeps-lines">{{ $service['description'] }}</p>
                            <span class="more-link">Learn more &rarr;</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if ($showReviews)
        @include('sites.sections.reviews')
    @endif

    @if ($mapEmbedUrl)
        <section class="block service-map-block" id="service-map">
            <div class="container">
                <span class="section-label">Visit</span>
                <h2 class="section-title">Find us{{ $lead->city ? ' in '.$lead->city : '' }}</h2>
                @if ($lead->address)
                    <p class="service-map__address">{{ $lead->address }}</p>
                @endif
            </div>
            <div class="service-map">
                <iframe
                    class="map map--bleed"
                    src="{{ $mapEmbedUrl }}"
                    loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade"
                    title="Map showing {{ $lead->name }}"
                ></iframe>
            </div>
        </section>
    @endif

@endsection
