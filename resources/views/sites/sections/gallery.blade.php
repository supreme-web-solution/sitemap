@php
    $galleryCount = count($galleryPhotos);
    $galleryMax = \App\Http\Requests\Leads\UpdateLeadProfileRequest::MAX_PHOTOS;
@endphp
<section id="gallery" class="block gallery-block">
    <div class="container">
        <span class="section-label"{!! $e->label('gallery_label') !!}>{{ $e->copy('gallery_label', 'Gallery') }}</span>
        <h2 class="section-title"{!! $e->label('gallery_title') !!}>{{ $e->copy('gallery_title', 'Take a Look') }}</h2>
        <div class="gallery">
            @foreach (array_slice($galleryPhotos, 0, 6) as $index => $photo)
                @include('sites.partials.gallery-item', ['photo' => $photo, 'index' => $index])
            @endforeach
            @if (! empty($editing) && $galleryCount < $galleryMax)
                <button
                    type="button"
                    class="edit-add-image is-gallery"
                    data-edit-generate="1"
                    {!! $e->image('photo', $galleryCount, 'Photo '.($galleryCount + 1)) !!}
                >
                    Add a photo
                </button>
            @endif
        </div>
        @if (collect($nav)->contains('key', 'gallery') && $galleryCount > 6)
            <p class="section-link"><a class="more-link" href="{{ $urls->to('gallery') }}">See the full gallery &rarr;</a></p>
        @endif
    </div>
</section>
