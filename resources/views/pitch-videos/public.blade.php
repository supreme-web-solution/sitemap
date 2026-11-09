@extends('public.layout', ['team' => $pitchVideo->team])
@section('title', 'A video for '.$pitchVideo->site->lead->name)
@section('content')
<div class="card">
    <h1>A quick video for {{ $pitchVideo->site->lead->name }}</h1>
    <div class="muted">About the website we built for your business</div>

    <video
        controls
        playsinline
        preload="metadata"
        @if ($pitchVideo->presenter_thumbnail_url) poster="{{ $pitchVideo->presenter_thumbnail_url }}" @endif
        style="width:100%;margin-top:16px;border-radius:12px;background:#000"
    >
        <source src="{{ $pitchVideo->videoUrl() }}" type="video/mp4">
        Your browser cannot play this video.
    </video>

    <div style="margin-top:20px">
        <a class="btn" href="{{ $previewUrl }}">See your website</a>
    </div>
</div>
@endsection
