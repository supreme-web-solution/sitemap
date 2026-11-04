@extends('public.layout', ['team' => $lead->team])
@section('title', 'Website report')
@section('content')
@php
    $score = $audit['error'] ?? null ? null : ($audit['score'] ?? null);
    $checkedAt = isset($audit['fetched_at']) ? \Illuminate\Support\Carbon::parse($audit['fetched_at']) : null;
    $url = $audit['final_url'] ?? $audit['url'] ?? $lead->website;
@endphp

<div class="card">
    <h1>{{ $lead->name }}</h1>
    <p class="muted" style="word-break:break-all;margin:0 0 8px">{{ $url }}</p>
    @if ($checkedAt)
        <p class="muted" style="margin:0;font-size:13px">Checked {{ $checkedAt->timezone(config('app.timezone'))->format('j M Y, g:i A') }}</p>
    @endif
</div>

@if ($audit['error'] ?? null)
    <div class="notice warn">
        We could not fully scan this website (often due to security software). Your agency can still help you improve your online presence.
    </div>
@else
    <div class="card" style="text-align:center">
        <div style="font-size:13px;text-transform:uppercase;letter-spacing:.06em;color:#71717a;font-weight:600">Website health score</div>
        <div style="font-size:56px;font-weight:700;line-height:1.1;margin:8px 0;color:var(--brand)">{{ $score }}<span style="font-size:22px;color:#71717a;font-weight:600"> / 100</span></div>
        <p class="muted" style="margin:0">
            @if ($issues === 0)
                Looking good — a few tweaks could make it even stronger.
            @else
                {{ $issues }} {{ str('area')->plural($issues) }} worth improving for customers and Google.
            @endif
        </p>
    </div>

    <div class="card">
        <h2>What we looked at</h2>
        <p class="muted" style="margin-top:0">Plain-language checks on how your site works for real customers.</p>
        <ul style="list-style:none;padding:0;margin:16px 0 0;display:grid;gap:14px">
            @foreach ($checks as $item)
                <li style="border:1px solid #f4f4f5;border-radius:12px;padding:14px 16px">
                    <div class="row" style="align-items:flex-start">
                        <div style="flex:1;min-width:0">
                            <strong style="display:block;font-size:15px">{{ $item['title'] }}</strong>
                            <span class="muted" style="display:block;margin-top:4px;font-size:14px">{{ $item['summary'] }}</span>
                            @if ($item['recommendation'])
                                <span style="display:block;margin-top:8px;font-size:14px"><strong>Suggestion:</strong> {{ $item['recommendation'] }}</span>
                            @endif
                        </div>
                        <span class="badge {{ $item['status'] === 'pass' ? 'ok' : ($item['status'] === 'warn' ? 'warn' : 'bad') }}" style="flex-shrink:0">
                            {{ $item['status'] === 'pass' ? 'Good' : ($item['status'] === 'warn' ? 'Improve' : 'Fix') }}
                        </span>
                    </div>
                </li>
            @endforeach
        </ul>
    </div>
@endif

<div class="card muted" style="font-size:14px">
    This report is a snapshot for conversation with {{ $lead->team->brand()['name'] }}. It is not a guarantee of search rankings or sales.
</div>
@endsection
