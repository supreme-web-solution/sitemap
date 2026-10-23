@extends('sites.layout')

@section('main')
    @include('sites.partials.page-hero', [
        'heading' => $page['service']['title'].($lead->city ? ' in '.$lead->city : ''),
        'intro' => $page['service']['description'],
    ])

    <section class="block">
        <div class="container service-layout">
            <div>
                @if (filled($page['service']['body']))
                    @include('sites.partials.prose', ['text' => $page['service']['body']])
                @else
                    @include('sites.partials.prose', ['text' => 'Want to know more about '.$page['service']['title'].'? Get in touch with '.$lead->name.' and we will talk you through the options.'])
                @endif
            </div>
            <aside class="service">
                <div class="service-aside">
                    <h2>Our services</h2>
                    <ul>
                        @foreach ($services as $other)
                            <li>
                                <a href="{{ $urls->to('services/'.$other['slug']) }}" @if ($other['slug'] === $page['service']['slug']) aria-current="page" @endif>{{ $other['title'] }}</a>
                            </li>
                        @endforeach
                    </ul>
                    <a class="btn btn-primary" href="{{ $phoneHref ?? $urls->to('contact') }}">{{ $content['cta_label'] }}</a>
                </div>
            </aside>
        </div>
    </section>

    @if ($reviews->count() >= \App\Models\Lead::MIN_TESTIMONIALS)
        @include('sites.sections.reviews')
    @endif

    @include('sites.partials.cta-band')
@endsection