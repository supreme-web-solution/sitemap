@extends('sites.layout')

@section('main')
    @include('sites.partials.page-hero', [
        'heading' => 'About '.$lead->name,
        'headingKey' => 'about_page_heading',
        'intro' => $content['tagline'],
        'introKey' => 'about_page_intro',
    ])

    <section class="block about-block">
        <div class="container">
            @include('sites.partials.prose', ['text' => $content['about'], 'editAttributes' => $e->text('about', 'About text', 2000, true, reload: true)])
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
