@extends('public.layout', ['team' => $lead->team])
@section('title', 'Your project')
@section('content')
@php
    use App\Enums\InvoiceStatus;
    use App\Enums\ProposalStatus;
    use App\Models\Invoice;
@endphp
<div class="card">
    <h1>Hello, {{ $lead->name }}</h1>
    <div class="muted">Follow your project, review your website and pay invoices here.</div>
</div>

@if ($site)
    <div class="card">
        <div class="row">
            <h2 style="margin:0">Your website</h2>
            @if ($site->approved_at)
                <span class="badge ok">Approved {{ $site->approved_at->toFormattedDateString() }}</span>
            @else
                <span class="badge warn">Waiting for your review</span>
            @endif
        </div>
        <p class="muted">Take a look and tell us what you think. You can send feedback as many times as you like.</p>
        <p><a class="btn" href="{{ $previewUrl }}" target="_blank" rel="noopener">Open your website</a></p>

        @unless ($site->approved_at)
            <form method="POST" action="{{ route('portal.approve', $lead->portal_token) }}" style="margin-top:12px">
                @csrf
                <button type="submit">Approve the website</button>
            </form>
        @endunless

        <form method="POST" action="{{ route('portal.feedback', $lead->portal_token) }}" style="margin-top:16px">
            @csrf
            <label for="message">Send feedback or request a change</label>
            <textarea id="message" name="message" rows="3" maxlength="2000" required>{{ old('message') }}</textarea>
            @error('message')<div class="err">{{ $message }}</div>@enderror
            <div style="margin-top:10px"><button type="submit" class="alt">Send feedback</button></div>
        </form>
    </div>
@endif

@if ($proposals->isNotEmpty())
    <div class="card">
        <h2>Proposals</h2>
        <table>
            @foreach ($proposals as $proposal)
                <tr>
                    <td><a href="{{ $proposal->publicUrl() }}">{{ $proposal->title }}</a><div class="muted" style="font-size:12px">{{ $proposal->number }}</div></td>
                    <td class="num"><span class="badge {{ $proposal->status === ProposalStatus::Accepted ? 'ok' : '' }}">{{ $proposal->status->label() }}</span></td>
                </tr>
            @endforeach
        </table>
    </div>
@endif

@if ($invoices->isNotEmpty())
    <div class="card">
        <h2>Invoices</h2>
        <table>
            @foreach ($invoices as $invoice)
                <tr>
                    <td><a href="{{ $invoice->payUrl() }}">{{ $invoice->number }}</a>
                        @if ($invoice->due_on && $invoice->status === InvoiceStatus::Open)<div class="muted" style="font-size:12px">Due {{ $invoice->due_on->toFormattedDateString() }}</div>@endif
                    </td>
                    <td class="num">{{ Invoice::formatCents($invoice->total_cents) }}</td>
                    <td class="num"><span class="badge {{ $invoice->status === InvoiceStatus::Paid ? 'ok' : ($invoice->isOverdue() ? 'bad' : '') }}">{{ $invoice->isOverdue() ? 'Overdue' : $invoice->status->label() }}</span></td>
                </tr>
            @endforeach
        </table>
    </div>
@endif
@endsection