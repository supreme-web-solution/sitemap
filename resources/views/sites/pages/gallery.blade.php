@extends('sites.layout')

@section('main')
    @include('sites.partials.page-hero', ['heading' => 'Photo Gallery', 'intro' => 'A look at '.$lead->name.($lead->city ? ' in '.$lead->city : '').'.'])

    <section class="block gallery-block">
        <div class="container">
            <div class="gallery">
                @foreach ($galleryPhotos as $photo)
                    <img src="{{ $photo }}" alt="{{ $lead->name }} photo {{ $loop->iteration }}" loading="lazy" decoding="async">
                @endforeach
            </div>
        </div>
    </section>

    @include('sites.partials.cta-band')
@endsection