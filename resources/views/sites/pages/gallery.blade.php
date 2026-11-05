@extends('sites.layout')

@section('main')
    @include('sites.partials.page-hero', [
        'heading' => 'Gallery',
        'headingKey' => 'gallery_page_heading',
        'intro' => $content['tagline'],
        'introKey' => 'gallery_page_intro',
    ])

    <section class="block gallery-block">
        <div class="container">
            <div class="gallery">
                @foreach ($galleryPhotos as $index => $photo)
                    @include('sites.partials.gallery-item', ['photo' => $photo, 'index' => $index])
                @endforeach
            </div>
        </div>
    </section>

@endsection
