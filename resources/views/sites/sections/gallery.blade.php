<section id="gallery" class="block gallery-block">
    <div class="container">
        <span class="section-label">Gallery</span>
        <h2 class="section-title">Take a Look</h2>
        <div class="gallery">
            @foreach (array_slice($galleryPhotos, 0, 6) as $photo)
                <img src="{{ $photo }}" alt="{{ $lead->name }} photo {{ $loop->iteration }}" loading="lazy" decoding="async">
            @endforeach
        </div>
        @if (collect($nav)->contains('key', 'gallery') && count($galleryPhotos) > 6)
            <p class="section-link"><a class="more-link" href="{{ $urls->to('gallery') }}">See the full gallery &rarr;</a></p>
        @endif
    </div>
</section>
