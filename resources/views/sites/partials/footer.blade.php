<footer class="site-footer"{!! $e->section('footer', 'Footer') !!}>
    <div class="footer-cta">
        <div class="container">
            <p class="footer-cta__copy">{{ $content['tagline'] }}</p>
            @if ($phoneHref)
                <a class="btn btn-primary" href="{{ $phoneHref }}">@include('sites.icons.phone') {{ $lead->phone }}</a>
            @endif
        </div>
    </div>
    <div class="footer-main container">
        <div class="footer-brand">
            <a href="{{ $urls->to() }}" class="brand">
                @if ($logoUrl)
                    <img class="brand-logo" src="{{ $logoUrl }}" alt="{{ $lead->name }} logo">
                @else
                    <span class="brand-mark">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($lead->name, 0, 1)) }}</span>
                @endif
                <span>{{ $lead->name }}</span>
            </a>
            <p>{{ $content['tagline'] }}</p>
            @if (! empty($socialLinks))
                <nav class="footer-social" aria-label="Social">
                    @foreach ($socialLinks as $platform => $url)
                        <a href="{{ $url }}" rel="noopener noreferrer">{{ ucfirst($platform) }}</a>
                    @endforeach
                </nav>
            @endif
        </div>
        <nav class="footer-col" aria-label="Explore">
            <h3>{{ $e->copy('footer_explore_label', 'Explore') }}</h3>
            <ul>
                @foreach ($nav as $item)
                    <li><a href="{{ $item['url'] }}">{{ $item['label'] }}</a></li>
                @endforeach
            </ul>
        </nav>
        @if (! empty($services))
            <nav class="footer-col" aria-label="Services">
                <h3>{{ $e->copy('footer_services_label', 'Services') }}</h3>
                <ul>
                    @foreach ($services as $service)
                        <li>
                            @if (collect($nav)->contains('key', 'services') && ! empty($service['slug']))
                                <a href="{{ $urls->to('services/'.$service['slug']) }}">{{ $service['title'] }}</a>
                            @else
                                <a href="{{ $urls->to() }}#services">{{ $service['title'] }}</a>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </nav>
        @endif
        <div class="footer-col">
            <h3>{{ $e->copy('footer_contact_label', 'Visit') }}</h3>
            <ul class="footer-contact">
                @if ($lead->address)
                    <li>{{ $lead->address }}</li>
                @endif
                @if ($lead->phone)
                    <li><a href="{{ $phoneHref }}">{{ $lead->phone }}</a></li>
                @endif
                @if ($lead->email)
                    <li><a href="mailto:{{ $lead->email }}">{{ $lead->email }}</a></li>
                @endif
            </ul>
            @if (! empty($hours))
                <h3 class="footer-hours-label">{{ $e->copy('footer_hours_label', 'Hours') }}</h3>
                <ul class="footer-hours">
                    @foreach ($hours as $day => $time)
                        <li><span>{{ $day }}</span><span>{{ $time }}</span></li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
    <div class="footer-bar">
        <div class="container">
            <span>&copy; {{ $year }} {{ $lead->name }}. All rights reserved.</span>
            <nav class="footer-nav" aria-label="Footer">
                @foreach ($nav as $item)
                    <a href="{{ $item['url'] }}">{{ $item['label'] }}</a>
                @endforeach
            </nav>
        </div>
    </div>
</footer>
