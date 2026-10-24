@extends('sites.layout')

@section('main')
    @include('sites.partials.page-hero', [
        'heading' => 'Contact '.$lead->name,
        'headingKey' => 'contact_page_heading',
        'intro' => $content['tagline'],
        'introKey' => 'contact_page_intro',
    ])

    <section id="contact" class="block contact-block">
        <div class="container split">
            <div>
                <span class="section-label"{!! $e->label('contact_label') !!}>{{ $e->copy('contact_label', 'Contact') }}</span>
                <h2 class="section-title"{!! $e->label('contact_title') !!}>{{ $e->copy('contact_title', 'Get in Touch') }}</h2>
                <p{!! $e->label('contact_intro') !!}>{{ $e->copy('contact_intro', 'Reach out using the details below.') }}</p>
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
            @if ($phoneHref || $lead->email)
                <div>
                    <a class="btn btn-primary" href="{{ $phoneHref ?? 'mailto:'.$lead->email }}"><span{!! $e->text('cta_label', 'Button text', 40) !!}>{{ $content['cta_label'] }}</span></a>
                </div>
            @endif
        </div>
    </section>

    @if ($hours || $mapEmbedUrl)
        @include('sites.sections.hours')
    @endif
@endsection