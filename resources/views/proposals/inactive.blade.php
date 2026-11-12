@extends('public.layout', ['team' => $proposal->team])
@section('title', __('Proposal not active yet'))
@section('content')
<div class="card">
    <div class="row">
        <div>
            <h1>{{ __('This proposal is not active yet') }}</h1>
            <div class="muted" style="margin-top:8px">
                {{ __('The link works, but the agency has not published this proposal. Check back soon, or ask them to send it when it is ready.') }}
            </div>
        </div>
        <span class="badge warn">{{ __('Not active yet') }}</span>
    </div>
</div>
@endsection
