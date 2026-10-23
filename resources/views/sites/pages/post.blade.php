@extends('sites.layout')

@section('main')
    @include('sites.partials.page-hero', [
        'heading' => $page['post']->title,
        'intro' => $page['post']->published_at->format('F j, Y'),
    ])

    <section class="block">
        <div class="container">
            <article>
                @include('sites.partials.prose', ['text' => $page['post']->body])
            </article>
            <p class="section-link"><a class="more-link" href="{{ $urls->to('blog') }}">&larr; Back to the blog</a></p>
        </div>
    </section>

    @include('sites.partials.cta-band')
@endsection