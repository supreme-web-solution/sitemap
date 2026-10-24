<section class="hero page-hero">
    <div class="container">
        @include('sites.partials.breadcrumbs')
        @php
            $headingAttributes = ! empty($headingKey) ? $e->label($headingKey) : (! empty($headingPath) ? $e->text($headingPath, $headingLabel ?? 'Heading', 60) : '');
            $introAttributes = ! empty($introKey) ? $e->label($introKey) : (! empty($introPath) ? $e->text($introPath, $introLabel ?? 'Introduction', 250, true) : '');
        @endphp
        @if (! empty($headingPath) && ! empty($headingSuffix))
            <h1><span{!! $headingAttributes !!}>{{ $heading }}</span>{{ $headingSuffix }}</h1>
        @else
            <h1{!! $headingAttributes !!}>{{ ! empty($headingKey) ? $e->copy($headingKey, $heading) : $heading }}</h1>
        @endif
        @if (! empty($intro))
            <p{!! $introAttributes !!}>{{ ! empty($introKey) ? $e->copy($introKey, $intro) : $intro }}</p>
        @endif
    </div>
</section>
