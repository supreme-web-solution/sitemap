<section id="areas" class="block areas-block">
    <div class="container">
        <div class="areas-intro">
            <span class="section-label"{!! $e->label('areas_label') !!}>{{ $e->copy('areas_label', 'Service area') }}</span>
            <h2 class="section-title"{!! $e->label('areas_title') !!}>{{ $e->copy('areas_title', $lead->city ? 'Where we work in '.$lead->city : 'Where we work') }}</h2>
            <p class="section-intro"{!! $e->label('areas_intro') !!}>{{ $e->copy('areas_intro', 'Places we already serve. Ask if you are nearby and not listed.') }}</p>
        </div>
        <ul class="area-chips">
            @foreach ($areaNames as $area)
                <li>{{ $area }}</li>
            @endforeach
        </ul>
    </div>
</section>
