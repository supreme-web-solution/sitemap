@extends('sites.layout')

@php
    $servicesPageLayout = $layoutVariants['services_page'] ?? 'zigzag';
    $useServiceCards = $servicesPageLayout === 'cards';
    $quoteHref = collect($nav)->contains('key', 'contact') ? $urls->to('contact') : ($phoneHref ?? '#contact');
@endphp

@section('main')
    @include('sites.partials.page-hero', [
        'heading' => 'Our Services',
        'headingKey' => 'services_page_heading',
        'intro' => $content['tagline'],
        'introKey' => 'services_page_intro',
    ])

    <section class="block services-page-block"{!! $e->section('services_page', 'Services list') !!}>
        <div class="container">
            @if ($useServiceCards)
                <div class="services">
                    @foreach ($services as $service)
                        <a class="service" href="{{ $urls->to('services/'.$service['slug']) }}">
                            @include('sites.partials.service-card-media')
                            <h2 class="service-title"{!! $e->text('services.'.$loop->index.'.title', 'Service name', 60) !!}>{{ $service['title'] }}</h2>
                            <p class="keeps-lines"{!! $e->text('services.'.$loop->index.'.description', 'Service description', \App\Services\SiteBuilder\SiteContentSchema::SERVICE_DESCRIPTION_MAX, true) !!}>{{ $service['description'] }}</p>
                            <span class="more-link">Learn more &rarr;</span>
                        </a>
                    @endforeach
                </div>
            @else
                <div class="service-features">
                    @foreach ($services as $service)
                        <article class="service-feature{{ $loop->even ? ' is-alt' : '' }}">
                            <div class="service-feature__copy">
                                <h2 class="service-title"{!! $e->text('services.'.$loop->index.'.title', 'Service name', 60) !!}>{{ $service['title'] }}</h2>
                                <p class="keeps-lines"{!! $e->text('services.'.$loop->index.'.description', 'Service description', \App\Services\SiteBuilder\SiteContentSchema::SERVICE_DESCRIPTION_MAX, true) !!}>{{ $service['description'] }}</p>
                                <div class="section-actions">
                                    <a class="btn btn-primary" href="{{ $quoteHref }}">
                                        <span{!! $e->text('cta_label', 'Button text', 40) !!}>{{ $content['cta_label'] }}</span>
                                    </a>
                                    <a class="btn btn-secondary" href="{{ $urls->to('services/'.$service['slug']) }}">Learn more</a>
                                </div>
                            </div>
                            <div class="service-feature__media">
                                @include('sites.partials.service-card-media')
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif

            @if (! empty($content['additional_services']))
                <div class="additional-services">
                    <h2 class="section-title">Also available</h2>
                    <ul>
                        @foreach ($content['additional_services'] as $extra)
                            <li>{{ $extra }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </section>

@endsection
