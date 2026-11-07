@php
    $brand = $invoice->team->brand();
    $client = $invoice->lead->name;
    $amount = \App\Models\Invoice::formatCents($invoice->total_cents);
@endphp
{{ $brand['name'] }}

@if ($downloadUrl)
Your website is ready
@else
Payment received
@endif

Hi {{ $client }},

Thanks — we received your payment of {{ $amount }} for invoice {{ $invoice->number }}.

@if (filled($invoice->notes))
{{ $invoice->notes }}

@endif
@if ($downloadUrl)
The website for {{ $client }} is ready for you. Download the ZIP below — one folder per page, plus robots.txt. No login needed.

Download your website (works until {{ $expiresOn }}):
{{ $downloadUrl }}

@endif
Questions? Reply to this email and it goes to {{ $brand['name'] }}.
