<section id="services" class="block services-block">
    <div class="container">
        <span class="section-label"{!! $e->label('services_label') !!}>{{ $e->copy('services_label', 'What we do') }}</span>
        <h2 class="section-title"{!! $e->label('services_title') !!}>{{ $e->copy('services_title', 'Our Services') }}</h2>
        @php $hasServicePages = collect($nav)->contains('key', 'services'); @endphp
        <div class="services">
            @foreach ($services as $service)
                @if ($hasServicePages)
                    <a class="service" href="{{ $urls->to('services/'.$service['slug']) }}">
                        <div class="service-icon">@include('sites.icons.check')</div>
                        <h3{!! $e->text('services.'.$loop->index.'.title', 'Service name', 60) !!}>{{ $service['title'] }}</h3>
                        <p{!! $e->text('services.'.$loop->index.'.description', 'Service description', 250, true) !!}>{{ $service['description'] }}</p>
                        <span class="more-link">Learn more &rarr;</span>
                    </a>
                @else
                    <article class="service">
                        <div class="service-icon">@include('sites.icons.check')</div>
                        <h3{!! $e->text('services.'.$loop->index.'.title', 'Service name', 60) !!}>{{ $service['title'] }}</h3>
                        <p{!! $e->text('services.'.$loop->index.'.description', 'Service description', 250, true) !!}>{{ $service['description'] }}</p>
                    </article>
                @endif
            @endforeach
        </div>
        @if ($hasServicePages)
            <p class="section-link"><a class="more-link" href="{{ $urls->to('services') }}">View all services &rarr;</a></p>
        @endif
    </div>
</section>
