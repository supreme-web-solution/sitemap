@extends('sites.layout')

@section('main')
    @include('sites.partials.page-hero', [
        'heading' => 'Contact',
        'headingKey' => 'contact_page_heading',
        'intro' => $content['tagline'],
        'introKey' => 'contact_page_intro',
    ])

    @php
        $contactVariant = $content['variants']['contact'] ?? 'form-left';
        $contactLayout = match ($contactVariant) {
            'form-right' => 'details-form',
            'stacked', 'card' => 'stacked',
            default => 'form-details',
        };
    @endphp

    <section id="contact" class="block contact-block"{!! $e->section('contact', 'Contact') !!}>
        <div class="container contact-grid" data-contact-layout="{{ $contactLayout }}">
            <form class="contact-form" method="post" action="{{ $contactFormAction }}" novalidate>
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
                    <p class="form-error">
                        @if ($errors->has('name') || $errors->has('contact') || $errors->has('message'))
                            {{ $errors->first('name') ?: $errors->first('contact') ?: $errors->first('message') }}
                        @else
                            Add your name, phone or email, and a short message, then try again.
                        @endif
                    </p>
                @else
                    <p class="contact-note"{!! $e->label('contact_intro') !!}>{{ $e->copy('contact_intro', 'Tell us what you need and the best way to reach you.') }}</p>
                @endif
                <label for="contact-name">Your name <span class="required-mark" aria-hidden="true">*</span></label>
                <input
                    id="contact-name"
                    name="name"
                    type="text"
                    maxlength="100"
                    required
                    autocomplete="name"
                    placeholder="Jane Smith"
                    value="{{ old('name') }}"
                    aria-required="true"
                >
                <label for="contact-detail">Phone or email <span class="required-mark" aria-hidden="true">*</span></label>
                <input
                    id="contact-detail"
                    name="contact"
                    type="text"
                    maxlength="150"
                    required
                    autocomplete="email"
                    placeholder="(555) 123-4567 or you@email.com"
                    value="{{ old('contact') }}"
                    aria-required="true"
                >
                <label for="contact-message">Message <span class="required-mark" aria-hidden="true">*</span></label>
                <textarea
                    id="contact-message"
                    name="message"
                    maxlength="2000"
                    required
                    placeholder="What do you need help with?"
                    aria-required="true"
                >{{ old('message') }}</textarea>
                <div class="contact-honeypot" aria-hidden="true">
                    <label for="company-url">Company website</label>
                    <input id="company-url" name="company_url" type="text" tabindex="-1" autocomplete="off">
                    <label for="website">Website</label>
                    <input id="website" name="website" type="url" tabindex="-1" autocomplete="off">
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
