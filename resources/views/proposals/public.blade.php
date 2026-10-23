@extends('public.layout', ['team' => $proposal->team])
@section('title', $proposal->title)
@section('content')
@php
    use App\Enums\BillingInterval;
    use App\Enums\ProposalStatus;
    use App\Models\Invoice;
    $accepted = $proposal->status === ProposalStatus::Accepted;
@endphp
<div class="card">
    <div class="row">
        <div>
            <h1>{{ $proposal->title }}</h1>
            <div class="muted">Prepared for {{ $proposal->lead->name }} · {{ $proposal->number }}</div>
        </div>
        <span class="badge {{ $accepted ? 'ok' : ($proposal->status === ProposalStatus::Declined ? 'bad' : ($proposal->isExpired() ? 'warn' : '')) }}">
            {{ $proposal->isExpired() && $proposal->status === ProposalStatus::Sent ? 'Expired' : $proposal->status->label() }}
        </span>
    </div>
    @if ($proposal->valid_until && $proposal->status === ProposalStatus::Sent)
        <div class="muted" style="margin-top:6px">Valid until {{ $proposal->valid_until->toFormattedDateString() }}</div>
    @endif
    @if ($proposal->intro)
        <p class="pre" style="margin-top:16px">{{ $proposal->intro }}</p>
    @endif
</div>

<div class="card">
    <h2>What is included</h2>
    <table>
        <thead><tr><th>Item</th><th class="num">Qty</th><th class="num">Price</th><th class="num">Total</th></tr></thead>
        <tbody>
        @foreach ($proposal->items as $item)
            <tr>
                <td>{{ $item->description }}@if ($item->interval)<div class="muted" style="font-size:12px">{{ $item->interval->label() }}</div>@endif</td>
                <td class="num">{{ $item->quantity }}</td>
                <td class="num">{{ Invoice::formatCents($item->unit_cents) }}</td>
                <td class="num">{{ Invoice::formatCents($item->totalCents()) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    @if ($proposal->oneTimeCents() > 0)
        <div class="total"><span>Due on acceptance</span><span>{{ Invoice::formatCents($proposal->oneTimeCents()) }}</span></div>
    @endif
    @foreach ($proposal->recurringCents() as $interval => $cents)
        <div class="total"><span>{{ BillingInterval::from($interval)->label() }}</span><span>{{ Invoice::formatCents($cents) }}</span></div>
    @endforeach
    <div class="muted" style="font-size:12px;margin-top:8px">All prices in US dollars.</div>
</div>

@if ($proposal->terms)
    <div class="card">
        <h2>Terms</h2>
        <div class="pre muted">{{ $proposal->terms }}</div>
    </div>
@endif

@if ($proposal->isOpen())
    <div class="card">
        <h2>Accept this proposal</h2>
        <form method="POST" action="{{ route('proposals.public.accept', $proposal->public_token) }}">
            @csrf
            <label for="name">Type your full name to accept</label>
            <input id="name" type="text" name="name" value="{{ old('name') }}" maxlength="120" required>
            @error('name')<div class="err">{{ $message }}</div>@enderror
            <label style="display:flex;gap:8px;align-items:flex-start;margin-top:12px">
                <input type="checkbox" name="agree" value="1" required style="margin-top:4px">
                <span>I agree to the items, prices and terms above.</span>
            </label>
            @error('agree')<div class="err">{{ $message }}</div>@enderror
            <div style="display:flex;gap:10px;margin-top:16px;flex-wrap:wrap">
                <button type="submit">Accept and continue</button>
            </div>
        </form>
        <form method="POST" action="{{ route('proposals.public.decline', $proposal->public_token) }}" style="margin-top:10px">
            @csrf
            <button type="submit" class="alt">Decline</button>
        </form>
    </div>
@elseif ($accepted)
    <div class="notice ok">Accepted by {{ $proposal->accepted_name }} on {{ $proposal->accepted_at->toFormattedDateString() }}.
        @if ($proposal->invoice)<a href="{{ $proposal->invoice->payUrl() }}">View your invoice</a>@endif
    </div>
@endif
@endsection