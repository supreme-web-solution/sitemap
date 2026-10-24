@extends('sites.layout')

@section('main')
    @include('sites.partials.page-hero', [
        'heading' => 'Photo Gallery',
        'headingKey' => 'gallery_page_heading',
        'intro' => 'A look at '.$lead->name.($lead->city ? ' in '.$lead->city : '').'.',
        'introKey' => 'gallery_page_intro',
    ])

    <section class="block gallery-block">
        <div class="container">
            <div class="gallery">
                @foreach ($galleryPhotos as $photo)
                    <img src="{{ $photo }}" alt="{{ $lead->name }} photo {{ $loop->iteration }}" loading="lazy" decoding="async"{!! $e->image('photo', $loop->index, 'Photo '.$loop->iteration) !!}>
                @endforeach
            </div>
        </div>
    </section>

    @include('sites.partials.cta-band')
@endsection