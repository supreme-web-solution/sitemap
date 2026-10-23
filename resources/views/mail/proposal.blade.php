Hi {{ $proposal->lead->name }},

{{ $senderName }} at {{ $proposal->team->brand()['name'] }} has put together a proposal for you: {{ $proposal->title }}.
@if ($proposal->valid_until)

It is valid until {{ $proposal->valid_until->toFormattedDateString() }}.
@endif

You can read it, and accept it online, here:
{{ $proposal->publicUrl() }}

If you have any questions, just reply to this email.

{{ $senderName }}
{{ $proposal->team->brand()['name'] }}