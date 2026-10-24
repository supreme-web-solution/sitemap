<section id="faq" class="block faq-block">
    <div class="container">
        <span class="section-label"{!! $e->label('faq_label') !!}>{{ $e->copy('faq_label', 'FAQ') }}</span>
        <h2 class="section-title"{!! $e->label('faq_title') !!}>{{ $e->copy('faq_title', 'Frequently Asked Questions') }}</h2>
        <div class="faq">
            @foreach ($content['faqs'] as $faq)
                <details>
                    <summary{!! $e->text('faqs.'.$loop->index.'.question', 'Question', 150) !!}>{{ $faq['question'] }}</summary>
                    <p{!! $e->text('faqs.'.$loop->index.'.answer', 'Answer', 600, true) !!}>{{ $faq['answer'] }}</p>
                </details>
            @endforeach
        </div>
    </div>
</section>
