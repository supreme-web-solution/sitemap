@extends('sites.layout')

@php
    $serviceIndex = collect($services)->search(fn (array $item) => $item['slug'] === $page['service']['slug']);
    $serviceIndex = $serviceIndex === false ? null : $serviceIndex;
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

    @if ($reviews->count() >= \App\Models\Lead::MIN_TESTIMONIALS)
        @include('sites.sections.reviews')
    @endif

@endsection
