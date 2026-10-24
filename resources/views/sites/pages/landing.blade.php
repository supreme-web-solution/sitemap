@extends('sites.layout')

@section('main')
    @include('sites.partials.page-hero', [
        'heading' => $page['custom']['headline'],
        'intro' => $page['custom']['intro'] ?? $content['tagline'],
    ])

    <section class="block about-block">
        <div class="container">
            @include('sites.partials.prose', ['text' => $page['custom']['body']])
            @if (filled($page['custom']['cta_label'] ?? null) && $phoneHref)
                <p class="section-link"><a class="btn btn-primary" href="{{ $phoneHref }}">{{ $page['custom']['cta_label'] }}</a></p>
            @endif
        </div>
    </section>

    @include('sites.partials.cta-band')
@endsection
