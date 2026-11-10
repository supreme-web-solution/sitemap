@php
    $brand = $proposal->team->brand();
@endphp
{{ $brand['name'] }}

{{ ($isPreview ?? false) ? 'Preview: your proposal email' : 'Your proposal is ready' }}

Hi {{ $proposal->lead->name }},

{{ $senderName }} at {{ $brand['name'] }} put together {{ $proposal->title }} for you.
@if ($proposal->valid_until)
It stays open until {{ $proposal->valid_until->toFormattedDateString() }}.
@endif
@if ($pitchVideo ?? null)
We also recorded a short video that walks through the website we built for you.
@endif

@if (filled($proposal->intro))
{{ $proposal->intro }}

@endif
@if ($pitchVideo ?? null)
Watch the video about your website:
{{ $pitchVideo->watchUrl() }}

@endif
View the proposal:
{{ $proposal->publicUrl() }}

Questions? Reply to this email and it goes to {{ $senderName }} at {{ $brand['name'] }}.
