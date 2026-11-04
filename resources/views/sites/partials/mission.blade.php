@php
    $missionDefault = 'Reliable '.($lead->category ?: 'work we are proud to put our name on')
        .($lead->city ? ' for people in '.$lead->city : '')
        .', with a clear next step and a team you can reach.';
@endphp
<section id="mission" class="block mission-block"{!! $e->section('mission', 'Our mission') !!}>
    <div class="container mission-grid">
        <div class="mission-intro">
            <span class="section-label"{!! $e->label('mission_label') !!}>{{ $e->copy('mission_label', 'Our mission') }}</span>
            <h2 class="section-title"{!! $e->label('mission_title') !!}>{{ $e->copy('mission_title', 'What we stand for') }}</h2>
        </div>
        <p class="mission-body"{!! $e->label('mission_body') !!}>{{ $e->copy('mission_body', $missionDefault) }}</p>
    </div>
</section>
