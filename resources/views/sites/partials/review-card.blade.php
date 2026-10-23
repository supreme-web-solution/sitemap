<figure class="review-card">
    <div class="stars" role="img" aria-label="{{ $review->rating }} out of 5 stars">@for ($i = 0; $i < $review->rating; $i++) @include('sites.icons.star') @endfor</div>
    <blockquote>&ldquo;{{ $review->body }}&rdquo;</blockquote>
    <figcaption>
        <span class="review-avatar" aria-hidden="true">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($review->author_name, 0, 1)) }}</span>
        <span class="review-meta"><strong>{{ $review->author_name }}</strong><small>Google review</small></span>
    </figcaption>
</figure>
