<section id="services" class="block services-block">
    <div class="container">
        <span class="section-label"{!! $e->label('services_label') !!}>{{ $e->copy('services_label', 'What we do') }}</span>
        <h2 class="section-title"{!! $e->label('services_title') !!}>{{ $e->copy('services_title', 'Our Services') }}</h2>
        @php $hasServicePages = collect($nav)->contains('key', 'services'); @endphp
        <div class="services">
            @foreach ($services as $service)
                @if ($hasServicePages)
                    <a class="service" href="{{ $urls->to('services/'.$service['slug']) }}">
                        @include('sites.partials.service-card-media')
                        <h3{!! $e->text('services.'.$loop->index.'.title', 'Service name', 60) !!}>{{ $service['title'] }}</h3>
                        <p{!! $e->text('services.'.$loop->index.'.description', 'Service description', 250, true) !!}>{{ $service['description'] }}</p>
                        <span class="more-link">Learn more &rarr;</span>
                    </a>
                @else
                    <article class="service">
                        @include('sites.partials.service-card-media')
                        <h3{!! $e->text('services.'.$loop->index.'.title', 'Service name', 60) !!}>{{ $service['title'] }}</h3>
                        <p{!! $e->text('services.'.$loop->index.'.description', 'Service description', 250, true) !!}>{{ $service['description'] }}</p>
                    </article>
                @endif
            @endforeach
        </div>
        @php $quoteHref = collect($nav)->contains('key', 'contact') ? $urls->to('contact') : ($phoneHref ?? '#contact'); @endphp
        <div class="section-actions">
            <a class="btn btn-primary" href="{{ $quoteHref }}">
                <span{!! $e->text('cta_label', 'Button text', 40) !!}>{{ $content['cta_label'] }}</span>
            </a>
            @if ($hasServicePages)
                <a class="btn btn-secondary" href="{{ $urls->to('services') }}">
                    <span{!! $e->label('services_view_all') !!}>{{ $e->copy('services_view_all', 'View all services') }}</span>
                </a>
            @else
                <a class="btn btn-secondary" href="{{ $quoteHref }}">
                    <span{!! $e->label('services_view_all') !!}>{{ $e->copy('services_view_all', 'View all services') }}</span>
                </a>
            @endif
        </div>
    </div>
</section>
