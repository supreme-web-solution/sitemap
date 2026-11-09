{{ $brand['name'] ?? config('app.name') }}

{{ $title }}

{{ $intro }}

@foreach ($details ?? [] as $label => $value)
{{ $label }}: {{ $value }}
@endforeach
@if (filled($messageBody ?? null))

{{ __('Message') }}:
{{ $messageBody }}
@endif

{{ $cta }}:
{{ $url }}
