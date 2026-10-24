@extends('sites.layout')

@php
    $heading = $lead->category && $lead->city ? $lead->category.' Services in '.$lead->city : 'Our Services';
@endphp

@section('main')
    @include('sites.partials.page-hero', [
        'heading' => $heading,
        'headingKey' => 'services_page_heading',
        'intro' => 'Everything '.$lead->name.' can do for you.',
        'introKey' => 'services_page_intro',
    ])

    <section class="block services-block">
        <div class="container">
            <div class="services">
                @foreach ($services as $service)
                    <a class="service" href="{{ $urls->to('services/'.$service['slug']) }}">
                        <div class="service-icon">@include('sites.icons.check')</div>
                        <h2 class="service-title"{!! $e->text('services.'.$loop->index.'.title', 'Service name', 60) !!}>{{ $service['title'] }}</h2>
                        <p{!! $e->text('services.'.$loop->index.'.description', 'Service description', 250, true) !!}>{{ $service['description'] }}</p>
                        <span class="more-link">Learn more &rarr;</span>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    @include('sites.partials.cta-band')
@endsection
