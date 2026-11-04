<section id="visit" class="block visit-block">
    <div class="container split">
        <div>
            <span class="section-label"{!! $e->label('hours_label') !!}>{{ $e->copy('hours_label', 'Visit us') }}</span>
            <h2 class="section-title"{!! $e->label('hours_title') !!}>{{ $e->copy('hours_title', 'Opening Hours') }}</h2>
            @if ($lead->address)
                <div class="location-card">
                    <p class="location-card__title">{{ $lead->name }}</p>
                    <p class="location-card__address">{{ $lead->address }}</p>
                    @if ($lead->neighborhood || $lead->located_in)
                        <p class="location-card__meta">
                            @if ($lead->neighborhood)
                                <span>{{ $lead->neighborhood }}</span>
                            @endif
                            @if ($lead->neighborhood && $lead->located_in)
                                <span aria-hidden="true"> · </span>
                            @endif
                            @if ($lead->located_in)
                                <span>{{ $lead->located_in }}</span>
                            @endif
                        </p>
                    @endif
                    @if ($lead->maps_url)
                        <a class="location-card__link" href="{{ $lead->maps_url }}" target="_blank" rel="noopener noreferrer">Open in Google Maps</a>
                    @endif
                </div>
            @endif
            @if ($hours)
                <table class="hours">
                    @foreach ($hours as $day => $time)
                        <tr><td>{{ $day }}</td><td>{{ $time }}</td></tr>
                    @endforeach
                </table>
            @else
                <p>Get in touch to check our current opening hours.</p>
            @endif
        </div>
        @if ($mapEmbedUrl)
            <iframe class="map" src="{{ $mapEmbedUrl }}" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Map showing {{ $lead->name }}"></iframe>
        @endif
    </div>
</section>
