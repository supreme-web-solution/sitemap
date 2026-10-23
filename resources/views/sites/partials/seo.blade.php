<title>{{ $seo['title'] }}</title>
<meta name="description" content="{{ $seo['description'] }}">
<meta name="robots" content="{{ $seo['robots'] }}">
@if ($seo['canonical'])
    <link rel="canonical" href="{{ $seo['canonical'] }}">
    <meta property="og:url" content="{{ $seo['canonical'] }}">
@endif
<meta property="og:site_name" content="{{ $lead->name }}">
<meta property="og:type" content="{{ $seo['type'] }}">
<meta property="og:title" content="{{ $seo['title'] }}">
<meta property="og:description" content="{{ $seo['description'] }}">
@if ($seo['image'])
    <meta property="og:image" content="{{ $seo['image'] }}">
@endif
<meta name="twitter:card" content="{{ $seo['image'] ? 'summary_large_image' : 'summary' }}">
@foreach ($seo['schema'] as $schema)
    <script type="application/ld+json">{!! $seoBuilder->encode($schema) !!}</script>
@endforeach