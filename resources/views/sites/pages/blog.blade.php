@extends('sites.layout')

@section('main')
    @include('sites.partials.page-hero', [
        'heading' => 'Blog',
        'headingKey' => 'blog_page_heading',
        'intro' => 'News, advice and updates from '.$lead->name.'.',
        'introKey' => 'blog_page_intro',
    ])

    <section class="block">
        <div class="container">
            <div class="services">
                @forelse ($posts as $post)
                    <a class="service" href="{{ $urls->to('blog/'.$post->slug) }}">
                        <p class="post-meta"><time datetime="{{ $post->published_at->toDateString() }}">{{ $post->published_at->format('F j, Y') }}</time></p>
                        <h2 class="service-title">{{ $post->title }}</h2>
                        <p>{{ $seoBuilder->postSummary($post) }}</p>
                        <span class="more-link">Read more &rarr;</span>
                    </a>
                @empty
                    @unless (! empty($editing))
                        <p class="section-link">Posts from {{ $lead->name }} will show here soon.</p>
                    @endunless
                @endforelse
                @if (! empty($editing))
                    <button
                        type="button"
                        class="edit-add-image is-gallery"
                        data-edit-panel="posts"
                        data-test="add-blog-post"
                    >
                        Add a post
                    </button>
                @endif
            </div>
        </div>
    </section>

@endsection