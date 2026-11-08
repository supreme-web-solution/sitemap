<title>{{ $seo['title'] }}</title>
<meta name="description" content="{{ $seo['description'] }}">
<meta name="robots" content="{{ $seo['robots'] }}">
<meta name="theme-color" content="{{ $content['accent'] ?? '#2563eb' }}">
@if (! empty($brand['favicon_svg']))
    <link rel="icon" href="{{ $brand['favicon_svg'] }}" type="image/svg+xml">
@endif
@if (! empty($brand['favicon_32']))
    <link rel="icon" href="{{ $brand['favicon_32'] }}" type="image/png" sizes="32x32">
@endif
@if (! empty($brand['favicon_16']))
    <link rel="icon" href="{{ $brand['favicon_16'] }}" type="image/png" sizes="16x16">
@endif
@if (! empty($brand['apple_touch_icon']))
    <link rel="apple-touch-icon" href="{{ $brand['apple_touch_icon'] }}" sizes="180x180">
@endif
@if ($seo['canonical'])
    <link rel="canonical" href="{{ $seo['canonical'] }}">
    <meta property="og:url" content="{{ $seo['canonical'] }}">
@endif
@foreach ($seo['alternates'] ?? [] as $alternate)
    <link rel="alternate" hreflang="{{ $alternate['hreflang'] }}" href="{{ $alternate['href'] }}">
@endforeach
<meta property="og:site_name" content="{{ $lead->name }}">
<meta property="og:type" content="{{ $seo['type'] }}">
<meta property="og:title" content="{{ $seo['title'] }}">
<meta property="og:description" content="{{ $seo['description'] }}">
@if ($seo['image'])
    <meta property="og:image" content="{{ $seo['image'] }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta name="twitter:image" content="{{ $seo['image'] }}">
@endif
<meta name="twitter:card" content="{{ $seo['image'] ? 'summary_large_image' : 'summary' }}">
@foreach ($seo['schema'] as $schema)
    <script type="application/ld+json">{!! $seoBuilder->encode($schema) !!}</script>
@endforeach