<section class="hero page-hero">
    <div class="container">
        @include('sites.partials.breadcrumbs')
        <h1>{{ $heading }}</h1>
        @if (! empty($intro))
            <p>{{ $intro }}</p>
        @endif
    </div>
</section>