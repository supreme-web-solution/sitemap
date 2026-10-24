<section class="cta-band">
    <div class="container">
        <h2{!! $e->label('cta_heading') !!}>{{ $e->copy('cta_heading', 'Ready to get started?') }}</h2>
        <p{!! $e->text('tagline', 'Tagline', 200, true) !!}>{{ $content['tagline'] }}</p>
        <a class="btn" href="{{ $phoneHref ?? '#contact' }}"><span{!! $e->text('cta_label', 'Button text', 40) !!}>{{ $content['cta_label'] }}</span></a>
    </div>
</section>

<section id="contact" class="block contact-block">
    <div class="container split">
        <div>
            <span class="section-label"{!! $e->label('contact_label') !!}>{{ $e->copy('contact_label', 'Contact') }}</span>
            <h2 class="section-title"{!! $e->label('contact_title') !!}>{{ $e->copy('contact_title', 'Get in Touch') }}</h2>
            <p{!! $e->label('contact_intro') !!}>{{ $e->copy('contact_intro', 'Reach out using the details below.') }}</p>
        </div>
        <ul class="contact-list">
            @if ($lead->phone)
                <li>@include('sites.icons.phone') <a href="{{ $phoneHref }}">{{ $lead->phone }}</a></li>
            @endif
            @if ($lead->email)
                <li>@include('sites.icons.mail') <a href="mailto:{{ $lead->email }}">{{ $lead->email }}</a></li>
            @endif
            @if ($lead->address)
                <li>@include('sites.icons.pin') <span>{{ $lead->address }}</span></li>
            @endif
        </ul>
    </div>
</section>
