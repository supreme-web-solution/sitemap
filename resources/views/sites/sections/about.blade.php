<section id="about" class="block about-block">
    <div class="container split">
        <div>
            <span class="section-label">About us</span>
            <h2 class="section-title">Why choose {{ $lead->name }}?</h2>
        </div>
        <div>
            <p class="about-text">{{ $content['about'] }}</p>
            @if (collect($nav)->contains('key', 'about'))
                <p class="section-link"><a class="more-link" href="{{ $urls->to('about') }}">More about us &rarr;</a></p>
            @endif
        </div>
    </div>
</section>
