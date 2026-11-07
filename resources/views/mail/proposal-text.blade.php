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

@if (filled($proposal->intro))
{{ $proposal->intro }}

@endif
View the proposal (no login needed):
{{ $proposal->publicUrl() }}

Questions? Reply to this email and it goes to {{ $senderName }} at {{ $brand['name'] }}.
