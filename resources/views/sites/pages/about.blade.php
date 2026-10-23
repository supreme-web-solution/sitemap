@extends('sites.layout')

@section('main')
    @include('sites.partials.page-hero', ['heading' => 'About '.$lead->name, 'intro' => $content['tagline']])

    <section class="block about-block">
        <div class="container">
            @include('sites.partials.prose', ['text' => $content['about']])
            @if (collect($nav)->contains('key', 'services'))
                <p class="section-link"><a class="more-link" href="{{ $urls->to('services') }}">See what we offer &rarr;</a></p>
            @endif
        </div>
    </section>

    @if ($reviews->count() >= \App\Models\Lead::MIN_TESTIMONIALS)
        @include('sites.sections.reviews')
    @endif

    @include('sites.partials.cta-band')
@endsection