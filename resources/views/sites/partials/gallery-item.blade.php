@php
    /** @var string $photo */
    /** @var int $index */
    $alt = ($lead->name ?? 'Business').' photo '.($index + 1);
@endphp
<figure class="gallery-item">
    <img
        src="{{ $photo }}"
        alt="{{ $alt }}"
        loading="lazy"
        decoding="async"
        {!! $e->image('photo', $index, 'Photo '.($index + 1)) !!}
    >
    <button
        type="button"
        class="gallery-zoom"
        data-gallery-open
        data-gallery-src="{{ $photo }}"
        data-gallery-alt="{{ $alt }}"
        aria-label="Zoom photo"
    >
        @include('sites.icons.zoom')
    </button>
</figure>
