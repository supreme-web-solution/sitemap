@extends('sites.layout')

@section('main')
    @include('sites.partials.page-hero', [
        'heading' => $page['custom']['headline'],
        'intro' => $page['custom']['intro'] ?? ($page['custom']['city'] ?? $lead->city),
    ])

    <section class="block about-block">
        <div class="container">
            @include('sites.partials.prose', ['text' => $page['custom']['body']])
            @if ($mapEmbedUrl)
                <div class="map-embed" style="margin-top:2rem;border-radius:var(--radius);overflow:hidden;">
                    <iframe src="{{ $mapEmbedUrl }}" width="100%" height="360" style="border:0" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Map"></iframe>
                </div>
            @endif
        </div>
    </section>

    @include('sites.partials.cta-band')
@endsection
