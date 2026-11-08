@extends('sites.layout')

@section('main')
    @include('sites.partials.page-hero', [
        'heading' => 'Gallery',
        'headingKey' => 'gallery_page_heading',
        'intro' => $content['tagline'],
        'introKey' => 'gallery_page_intro',
    ])

    @php
        $galleryCount = count($galleryPhotos);
        $galleryMax = \App\Http\Requests\Leads\UpdateLeadProfileRequest::MAX_PHOTOS;
    @endphp

    <section class="block gallery-block">
        <div class="container">
            <div class="gallery">
                @forelse ($galleryPhotos as $index => $photo)
                    @include('sites.partials.gallery-item', ['photo' => $photo, 'index' => $index])
                @empty
                    @unless (! empty($editing))
                        <p class="section-link">Photos for {{ $lead->name }} will show here soon.</p>
                    @endunless
                @endforelse
                @if (! empty($editing) && $galleryCount < $galleryMax)
                    <button
                        type="button"
                        class="edit-add-image is-gallery"
                        {!! $e->image('photo', $galleryCount, 'Photo '.($galleryCount + 1)) !!}
                    >
                        Add a photo
                    </button>
                @endif
            </div>
        </div>
    </section>

@endsection
