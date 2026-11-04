@php
    $block = $content['custom_sections'][$customId] ?? null;
@endphp
@if (is_array($block))
    <section id="custom-{{ $customId }}" class="block custom-section">
        <div class="container">
            <span class="section-label">{{ $block['label'] ?? 'Custom section' }}</span>
            <h2 class="section-title">{{ $block['headline'] ?? '' }}</h2>
            <div class="custom-section__body">{!! nl2br(e($block['body'] ?? '')) !!}</div>
        </div>
    </section>
@endif
