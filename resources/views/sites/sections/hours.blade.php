<section id="visit" class="block visit-block">
    <div class="container split">
        <div>
            <span class="section-label"{!! $e->label('hours_label') !!}>{{ $e->copy('hours_label', 'Visit us') }}</span>
            <h2 class="section-title"{!! $e->label('hours_title') !!}>{{ $e->copy('hours_title', 'Opening Hours') }}</h2>
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
