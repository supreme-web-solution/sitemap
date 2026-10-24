@php
    $shown = match ($reviewsStyle) {
        'spotlight' => $reviews->take(6),
        'grid' => $reviews->take(9),
        default => $reviews,
    };
@endphp
<section id="reviews" class="block reviews-block">
    <div class="container">
        <span class="section-label"{!! $e->label('reviews_label') !!}>{{ $e->copy('reviews_label', 'Reviews') }}</span>
        <h2 class="section-title"{!! $e->label('reviews_title') !!}>{{ $e->copy('reviews_title', 'What our customers say') }}</h2>
        @if ($lead->rating && $lead->reviews_count > 0)
            <p class="reviews-summary">
                <span class="stars" aria-hidden="true">@for ($i = 0; $i < 5; $i++) @include('sites.icons.star') @endfor</span>
                <strong>{{ number_format($lead->rating, 1) }}</strong> from {{ number_format($lead->reviews_count) }} Google reviews
            </p>
        @endif

        @if ($reviewsStyle === 'carousel')
            <div class="reviews-carousel" data-reviews-carousel>
                <div class="reviews-track" tabindex="0" role="region" aria-label="Customer reviews">
                    @foreach ($shown as $review)
                        @include('sites.partials.review-card', ['review' => $review])
                    @endforeach
                </div>
                <div class="reviews-controls">
                    <button type="button" data-dir="-1" aria-label="Previous reviews">&#8249;</button>
                    <button type="button" data-dir="1" aria-label="Next reviews">&#8250;</button>
                </div>
            </div>
        @elseif ($reviewsStyle === 'grid')
            <div class="reviews-grid">
                @foreach ($shown as $review)
                    @include('sites.partials.review-card', ['review' => $review])
                @endforeach
            </div>
        @elseif ($reviewsStyle === 'spotlight')
            <div class="reviews-spotlight" data-reviews-spotlight>
                @foreach ($shown as $review)
                    <figure class="spotlight-slide {{ $loop->first ? 'is-active' : '' }}">
                        <div class="stars" role="img" aria-label="{{ $review->rating }} out of 5 stars">@for ($i = 0; $i < $review->rating; $i++) @include('sites.icons.star') @endfor</div>
                        <blockquote>&ldquo;{{ $review->body }}&rdquo;</blockquote>
                        <figcaption><strong>{{ $review->author_name }}</strong> <span>&middot; Google review</span></figcaption>
                    </figure>
                @endforeach
                <div class="spotlight-dots" role="tablist" aria-label="Choose a review">
                    @foreach ($shown as $review)
                        <button type="button" role="tab" aria-label="Review {{ $loop->iteration }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}" class="{{ $loop->first ? 'is-active' : '' }}"></button>
                    @endforeach
                </div>
            </div>
        @else
            <div class="reviews-marquee" role="region" aria-label="Customer reviews">
                <div class="marquee-track">
                    @foreach ($shown as $review)
                        @include('sites.partials.review-card', ['review' => $review])
                    @endforeach
                    @foreach ($shown as $review)
                        <div class="marquee-copy" aria-hidden="true">
                            @include('sites.partials.review-card', ['review' => $review])
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <p class="reviews-source">
            Reviews from Google &middot;
            <a href="{{ $reviewsUrl }}" target="_blank" rel="noopener noreferrer">Read all reviews on Google</a>
        </p>
    </div>
</section>
<script>
(function () {
    var carousel = document.querySelector('[data-reviews-carousel]');
    if (carousel) {
        var track = carousel.querySelector('.reviews-track');
        carousel.querySelectorAll('button[data-dir]').forEach(function (button) {
            button.addEventListener('click', function () {
                track.scrollBy({ left: Number(button.dataset.dir) * track.clientWidth * 0.85, behavior: 'smooth' });
            });
        });
    }

    var spotlight = document.querySelector('[data-reviews-spotlight]');
    if (spotlight) {
        var slides = spotlight.querySelectorAll('.spotlight-slide');
        var dots = spotlight.querySelectorAll('.spotlight-dots button');
        var current = 0;
        var timer = null;
        var show = function (index) {
            current = (index + slides.length) % slides.length;
            slides.forEach(function (slide, i) { slide.classList.toggle('is-active', i === current); });
            dots.forEach(function (dot, i) {
                dot.classList.toggle('is-active', i === current);
                dot.setAttribute('aria-selected', i === current ? 'true' : 'false');
            });
        };
        var stop = function () { if (timer) { clearInterval(timer); timer = null; } };
        var start = function () {
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) { return; }
            stop();
            timer = setInterval(function () { show(current + 1); }, 7000);
        };
        spotlight.classList.add('is-enhanced');
        dots.forEach(function (dot, i) { dot.addEventListener('click', function () { show(i); start(); }); });
        spotlight.addEventListener('mouseenter', stop);
        spotlight.addEventListener('mouseleave', start);
        spotlight.addEventListener('focusin', stop);
        spotlight.addEventListener('focusout', start);
        start();
    }
})();
</script>
