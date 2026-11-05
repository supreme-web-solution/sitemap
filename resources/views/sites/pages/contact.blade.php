@extends('sites.layout')

@section('main')
    @include('sites.partials.page-hero', [
        'heading' => 'Contact',
        'headingKey' => 'contact_page_heading',
        'intro' => $content['tagline'],
        'introKey' => 'contact_page_intro',
    ])

    <section id="contact" class="block contact-block"{!! $e->section('contact', 'Contact') !!}>
        <div class="container contact-grid" data-contact-layout="form-details">
            <form class="contact-form" method="post" action="{{ $contactFormAction }}">
                @if ($contactFormUsesCsrf)
                    @csrf
                @endif
                @if (request()->boolean('internal'))
                    <input type="hidden" name="internal" value="1">
                @endif
                <span class="section-label"{!! $e->label('contact_label') !!}>{{ $e->copy('contact_label', 'Contact') }}</span>
                <h2 class="section-title"{!! $e->label('contact_title') !!}>{{ $e->copy('contact_title', 'Send a message') }}</h2>
                @if (request()->boolean('thanks'))
                    <p class="contact-note">Thanks. We have your message and will be in touch shortly.</p>
                @elseif (request()->boolean('error') || $errors->any())
                    <p class="form-error">Add your phone or email, and a short message, then try again.</p>
                @else
                    <p class="contact-note"{!! $e->label('contact_intro') !!}>{{ $e->copy('contact_intro', 'Tell us what you need and the best way to reach you.') }}</p>
                @endif
                <label for="contact-name">Your name</label>
                <input id="contact-name" name="name" type="text" maxlength="100" autocomplete="name" value="{{ old('name') }}">
                <label for="contact-detail">Phone or email</label>
                <input id="contact-detail" name="contact" type="text" maxlength="150" required autocomplete="email" value="{{ old('contact') }}">
                <label for="contact-message">Message</label>
                <textarea id="contact-message" name="message" maxlength="2000" required>{{ old('message') }}</textarea>
                <div class="contact-honeypot" aria-hidden="true">
                    <label for="company-url">Company website</label>
                    <input id="company-url" name="company_url" type="text" tabindex="-1" autocomplete="off">
                </div>
                <button class="btn btn-primary" type="submit">{{ $content['cta_label'] }}</button>
            </form>
            <aside class="contact-aside">
                <h2 class="section-title">Contact details</h2>
                <ul class="contact-list">
                    @if ($lead->phone)
                        <li>@include('sites.icons.phone') <a href="{{ $phoneHref }}">{{ $lead->phone }}</a></li>
                    @endif
                    @foreach ($additionalPhones as $extraPhone)
                        <li>@include('sites.icons.phone') <a href="tel:{{ preg_replace('/[^\d+]/', '', $extraPhone) }}">{{ $extraPhone }}</a></li>
                    @endforeach
                    @if ($lead->email)
                        <li>@include('sites.icons.mail') <a href="mailto:{{ $lead->email }}">{{ $lead->email }}</a></li>
                    @endif
                    @if ($lead->address)
                        <li>@include('sites.icons.pin') <span>{{ $lead->address }}</span></li>
                    @endif
                </ul>
            </aside>
        </div>
    </section>

    @if ($hours || $mapEmbedUrl)
        @include('sites.sections.hours')
    @endif
@endsection
