@php($brand = $team->brand())
@if ($brand['poweredBy'])
    <div class="foot muted">Powered by {{ config('app.name') }}</div>
@endif