@php
    $interestAction = route('sites.preview.interest', $site->slug);
@endphp

<div class="preview-bar" role="region" aria-label="Website preview">
    <p class="preview-bar__note">
        Free preview for <strong>{{ $lead->name }}</strong>
    </p>
    <div class="preview-bar__actions">
        <button type="button" class="preview-bar__btn" data-preview-modal-open="discuss">
            Discuss this website
        </button>
        <button
            type="button"
            class="preview-bar__btn preview-bar__btn--primary preview-bar__btn--pulse"
            data-preview-modal-open="claim"
        >
            Claim this website
        </button>
        @if ($proposalUrl ?? null)
            <a
                class="preview-bar__btn preview-bar__btn--pricing"
                href="{{ $proposalUrl }}"
            >
                See pricing
            </a>
        @endif
        @if ($commentAction ?? null)
            <button type="button" class="preview-bar__btn" data-preview-modal-open="comment">
                Comment on a section
            </button>
        @endif
    </div>
</div>

<div class="preview-modal" id="preview-modal-discuss" hidden aria-hidden="true">
    <div class="preview-modal__backdrop" data-preview-modal-close tabindex="-1"></div>
    <div class="preview-modal__panel" role="dialog" aria-modal="true" aria-labelledby="preview-modal-discuss-title">
        <button type="button" class="preview-modal__close" data-preview-modal-close aria-label="Close">&times;</button>
        <h2 id="preview-modal-discuss-title" class="preview-modal__title">Discuss this website</h2>
        <p class="preview-modal__lead">Tell us what you think or what you would like changed.</p>
        <form method="POST" action="{{ $interestAction }}" class="preview-modal__form">
            @csrf
            <input type="hidden" name="intent" value="discuss">
            <label class="preview-modal__field">
                <span>Your name</span>
                <input type="text" name="name" maxlength="100" autocomplete="name" placeholder="Your full name">
            </label>
            <label class="preview-modal__field">
                <span>Phone or email</span>
                <input type="text" name="contact" maxlength="150" required autocomplete="email" placeholder="you@email.com or (555) 123-4567">
            </label>
            <label class="preview-modal__field">
                <span>Message <span class="preview-modal__optional">(optional)</span></span>
                <textarea name="message" maxlength="1000" rows="3" placeholder="Tell us what you love, or what you would like changed…"></textarea>
            </label>
            <input type="text" name="company_url" class="claim-hp" tabindex="-1" autocomplete="off" aria-hidden="true">
            <button type="submit" class="preview-bar__btn preview-bar__btn--primary preview-bar__btn--block">Send message</button>
        </form>
    </div>
</div>

<div class="preview-modal" id="preview-modal-claim" hidden aria-hidden="true">
    <div class="preview-modal__backdrop" data-preview-modal-close tabindex="-1"></div>
    <div class="preview-modal__panel" role="dialog" aria-modal="true" aria-labelledby="preview-modal-claim-title">
        <button type="button" class="preview-modal__close" data-preview-modal-close aria-label="Close">&times;</button>
        <h2 id="preview-modal-claim-title" class="preview-modal__title">Claim this website</h2>
        <p class="preview-modal__lead">
            Leave your details and we will follow up to transfer ownership, connect your domain, and finish setup.
        </p>
        <form method="POST" action="{{ $interestAction }}" class="preview-modal__form">
            @csrf
            <input type="hidden" name="intent" value="claim">
            <label class="preview-modal__field">
                <span>Your name</span>
                <input type="text" name="name" maxlength="100" autocomplete="name" placeholder="Your full name" required>
            </label>
            <label class="preview-modal__field">
                <span>Phone or email</span>
                <input type="text" name="contact" maxlength="150" required autocomplete="email" placeholder="Best way to reach you">
            </label>
            <label class="preview-modal__field">
                <span>Anything we should know? <span class="preview-modal__optional">(optional)</span></span>
                <textarea name="message" maxlength="1000" rows="3" placeholder="Preferred domain, launch timing, or questions…"></textarea>
            </label>
            <input type="text" name="company_url" class="claim-hp" tabindex="-1" autocomplete="off" aria-hidden="true">
            <button type="submit" class="preview-bar__btn preview-bar__btn--primary preview-bar__btn--block">Request claim</button>
        </form>
    </div>
</div>

@if ($commentAction ?? null)
    <div class="preview-modal" id="preview-modal-comment" hidden aria-hidden="true">
        <div class="preview-modal__backdrop" data-preview-modal-close tabindex="-1"></div>
        <div class="preview-modal__panel" role="dialog" aria-modal="true" aria-labelledby="preview-modal-comment-title">
            <button type="button" class="preview-modal__close" data-preview-modal-close aria-label="Close">&times;</button>
            <h2 id="preview-modal-comment-title" class="preview-modal__title">Comment on a section</h2>
            <p class="preview-modal__lead">Point to a part of the page and describe the change you want.</p>
            <form method="POST" action="{{ $commentAction }}" class="preview-modal__form">
                @csrf
                <label class="preview-modal__field">
                    <span>Your name</span>
                    <input type="text" name="author_name" maxlength="80" required autocomplete="name" placeholder="Your full name">
                </label>
                <label class="preview-modal__field">
                    <span>Phone or email</span>
                    <input type="text" name="author_contact" maxlength="120" autocomplete="email" placeholder="So we can follow up (optional)">
                </label>
                <label class="preview-modal__field">
                    <span>Section</span>
                    <input type="text" name="section" maxlength="40" placeholder="e.g. Hero, About, Services">
                </label>
                <label class="preview-modal__field">
                    <span>What should change?</span>
                    <textarea name="body" maxlength="1000" rows="3" required placeholder="Describe the change you want on that part of the page…"></textarea>
                </label>
                <input type="text" name="company_url" class="claim-hp" tabindex="-1" autocomplete="off" aria-hidden="true">
                <button type="submit" class="preview-bar__btn preview-bar__btn--primary preview-bar__btn--block">Send comment</button>
            </form>
        </div>
    </div>
@endif

<script>
    (() => {
        const modals = document.querySelectorAll('.preview-modal');
        let lastFocus = null;

        const closeAll = () => {
            modals.forEach((modal) => {
                modal.hidden = true;
                modal.setAttribute('aria-hidden', 'true');
            });
            document.body.classList.remove('preview-modal-open');
            lastFocus?.focus?.();
            lastFocus = null;
        };

        const open = (id) => {
            const modal = document.getElementById(`preview-modal-${id}`);
            if (!modal) {
                return;
            }
            lastFocus = document.activeElement;
            closeAll();
            modal.hidden = false;
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('preview-modal-open');
            modal.querySelector('.preview-modal__panel input, .preview-modal__panel textarea, .preview-modal__panel button')?.focus();
        };

        document.querySelectorAll('[data-preview-modal-open]').forEach((trigger) => {
            trigger.addEventListener('click', () => open(trigger.getAttribute('data-preview-modal-open')));
        });

        document.querySelectorAll('[data-preview-modal-close]').forEach((el) => {
            el.addEventListener('click', closeAll);
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeAll();
            }
        });
    })();
</script>
