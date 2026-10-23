<section id="faq" class="block faq-block">
    <div class="container">
        <span class="section-label">FAQ</span>
        <h2 class="section-title">Frequently Asked Questions</h2>
        <div class="faq">
            @foreach ($content['faqs'] as $faq)
                <details>
                    <summary>{{ $faq['question'] }}</summary>
                    <p>{{ $faq['answer'] }}</p>
                </details>
            @endforeach
        </div>
    </div>
</section>
