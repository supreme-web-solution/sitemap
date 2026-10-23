Hi {{ $invoice->lead->name }},

{{ $senderName }} at {{ $invoice->team->name }} has sent you invoice {{ $invoice->number }} for {{ \App\Models\Invoice::formatCents($invoice->total_cents) }}.
@if ($invoice->due_on)

It is due on {{ $invoice->due_on->toFormattedDateString() }}.
@endif

You can view and pay it securely online here:
{{ $invoice->payUrl() }}

If you have any questions, just reply to this email.

{{ $senderName }}
{{ $invoice->team->name }}