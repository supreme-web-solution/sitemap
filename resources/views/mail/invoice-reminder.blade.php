Hi {{ $invoice->lead->name }},

This is a friendly reminder that invoice {{ $invoice->number }} for {{ \App\Models\Invoice::formatCents($invoice->total_cents) }} was due on {{ $invoice->due_on->toFormattedDateString() }} and has not been paid yet.

You can view and pay it securely online here:
{{ $invoice->payUrl() }}

If you have already paid, please ignore this message and thank you. If you have any questions, just reply to this email.

{{ $senderName }}
{{ $invoice->team->name }}