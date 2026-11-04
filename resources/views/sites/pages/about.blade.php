@extends('sites.layout')

@php
    $aboutPhotoIndex = isset($galleryPhotos[1]) ? 1 : 0;
    $aboutPortrait = $galleryPhotos[$aboutPhotoIndex] ?? $heroImage ?? null;
    $quoteHref = collect($nav)->contains('key', 'contact') ? $urls->to('contact') : ($phoneHref ?? '#contact');
@endphp

@section('main')
    @include('sites.partials.page-hero', [
        'heading' => 'About us',
        'headingKey' => 'about_page_heading',
        'intro' => $content['tagline'],
        'introKey' => 'about_page_intro',
    ])

    <section class="block about-story-block"{!! $e->section('about_page', 'About story') !!}>
        <div class="container about-story">
            <div class="about-story__copy">
                <span class="section-label"{!! $e->label('about_label') !!}>{{ $e->copy('about_label', 'About us') }}</span>
                <h2 class="section-title"{!! $e->label('about_title') !!}>{{ $e->copy('about_title', 'Why choose '.$lead->name.'?') }}</h2>
                @include('sites.partials.prose', ['text' => $content['about'], 'editAttributes' => $e->text('about', 'About text', 2000, true, reload: true)])
                <div class="section-actions">
                    <a class="btn btn-primary" href="{{ $quoteHref }}">
                        <span{!! $e->text('cta_label', 'Button text', 40) !!}>{{ $content['cta_label'] }}</span>
                    </a>
                    @if (collect($nav)->contains('key', 'services'))
                        <a class="btn btn-secondary" href="{{ $urls->to('services') }}">See what we offer</a>
                    @endif
                </div>
            </div>
            <figure class="about-portrait">
                @if ($aboutPortrait)
                    <img src="{{ $aboutPortrait }}" alt="{{ $lead->name }}" loading="lazy" decoding="async"{!! $e->image('photo', $aboutPhotoIndex, 'About photo') !!}>
                @elseif ($editing)
                    <button type="button" class="edit-add-image is-block"{!! $e->image('photo', $aboutPhotoIndex, 'About photo') !!}>Add a photo</button>
                @else
                    <div class="about-portrait__fallback" aria-hidden="true">{{ mb_strtoupper(mb_substr($lead->name, 0, 1)) }}</div>
                @endif
            </figure>
        </div>
    </section>

    <div{!! $e->section('why', 'Why choose us') !!}>
        @include('sites.sections.why')
    </div>

    @include('sites.partials.mission')

    @if ($reviews->count() >= \App\Models\Lead::MIN_TESTIMONIALS)
        @include('sites.sections.reviews')
    @endif

@endsection
