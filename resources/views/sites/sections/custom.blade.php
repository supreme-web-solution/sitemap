@php
    $block = $content['custom_sections'][$customId] ?? null;
@endphp
@if (is_array($block))
    <section id="custom-{{ $customId }}" class="block custom-section">
        <div class="container">
            <span class="section-label"{!! $e->text('custom_sections.'.$customId.'.label', 'Section label', 40) !!}>{{ $block['label'] ?? 'Custom section' }}</span>
            <h2 class="section-title keeps-lines"{!! $e->text('custom_sections.'.$customId.'.headline', 'Heading', 120, true) !!}>{{ $block['headline'] ?? '' }}</h2>
            <div class="custom-section__body keeps-lines"{!! $e->text('custom_sections.'.$customId.'.body', 'Section text', 4000, true) !!}>{{ $block['body'] ?? '' }}</div>
        </div>
    </section>
@endif
