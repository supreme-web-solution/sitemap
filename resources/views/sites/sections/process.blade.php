<section id="process" class="block process-block">
    <div class="container">
        <div class="process-intro">
            <span class="section-label"{!! $e->label('process_label') !!}>{{ $e->copy('process_label', 'How it works') }}</span>
            <h2 class="section-title"{!! $e->label('process_title') !!}>{{ $e->copy('process_title', 'A clear path from the first call') }}</h2>
            <p class="section-intro"{!! $e->label('process_intro') !!}>{{ $e->copy('process_intro', 'A simple working relationship — from the first conversation through to the finished job.') }}</p>
        </div>
        <ol class="process-steps">
            @foreach ($processSteps as $step)
                <li class="process-step">
                    <span class="process-step__num" aria-hidden="true">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                    <h3>{{ $step['title'] }}</h3>
                    <p>{{ $step['description'] }}</p>
                </li>
            @endforeach
        </ol>
    </div>
</section>
