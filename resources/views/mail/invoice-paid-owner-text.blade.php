@php
    $brand = $invoice->team->brand();
    $lead = $invoice->lead;
    $amount = \App\Models\Invoice::formatCents($invoice->total_cents);
    $via = $invoice->paid_via ? ucfirst($invoice->paid_via) : __('card');
@endphp
{{ $brand['name'] }}

Payment received

{{ $lead->name }} paid {{ $amount }} on invoice {{ $invoice->number }} via {{ $via }}.

@if (filled($invoice->notes))
{{ $invoice->notes }}

@endif
The client has been emailed a receipt@if ($lead->site && $lead->site->transferred_at) and their website download link@endif.
