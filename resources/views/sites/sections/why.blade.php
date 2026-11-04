<section id="why" class="block why-block">
    <div class="container">
        <div class="why-intro">
            <span class="section-label"{!! $e->label('why_label') !!}>{{ $e->copy('why_label', 'Why choose us') }}</span>
            <h2 class="section-title"{!! $e->label('why_title') !!}>{{ $e->copy('why_title', 'Why people pick '.$lead->name) }}</h2>
            <p class="section-intro"{!! $e->label('why_intro') !!}>{{ $e->copy('why_intro', 'Straight reasons, based on what we can show — not slogans.') }}</p>
        </div>
        <ol class="why-points">
            @foreach ($whyPoints as $point)
                <li class="why-point">
                    <span class="why-point__num" aria-hidden="true">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                    <h3>{{ $point['title'] }}</h3>
                    <p>{{ $point['description'] }}</p>
                </li>
            @endforeach
        </ol>
    </div>
</section>
