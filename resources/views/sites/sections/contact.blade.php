<section class="cta-band">
    <div class="container">
        <h2>Ready to get started?</h2>
        <p>{{ $content['tagline'] }}</p>
        <a class="btn" href="{{ $phoneHref ?? '#contact' }}">{{ $content['cta_label'] }}</a>
    </div>
</section>

<section id="contact" class="block contact-block">
    <div class="container split">
        <div>
            <span class="section-label">Contact</span>
            <h2 class="section-title">Get in Touch</h2>
            <p>Reach out using the details below.</p>
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
