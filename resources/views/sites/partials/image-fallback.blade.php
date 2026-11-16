@php
    $imageFallbackPhoto = \App\Services\SiteBuilder\SiteImageFallback::photo((string) ($content['accent'] ?? ''));
    $imageFallbackMark = \App\Services\SiteBuilder\SiteImageFallback::mark((string) ($lead->name ?? ''), (string) ($content['accent'] ?? ''));
@endphp
<script data-image-fallback>
    (() => {
        const photo = @json($imageFallbackPhoto);
        const mark = @json($imageFallbackMark);

        const apply = (img) => {
            const src = img.currentSrc || img.src;

            if (!src || src.startsWith('data:')) {
                return;
            }

            const next = img.classList.contains('brand-logo') ? mark : photo;
            img.classList.add('is-placeholder', 'is-image-fallback');
            img.src = next;

            const frame = img.closest('[data-gallery-src]');

            if (frame) {
                frame.querySelectorAll('[data-gallery-src]').forEach((node) => {
                    node.dataset.gallerySrc = next;
                });
                frame.dataset.gallerySrc = next;
            }

            const band = img.closest('.cta-band');

            if (band) {
                band.style.removeProperty('--cta-image');
            }
        };

        document.addEventListener('error', (event) => {
            if (event.target instanceof HTMLImageElement) {
                apply(event.target);
            }
        }, true);

        const sweep = () => {
            document.querySelectorAll('img').forEach((img) => {
                if (img.complete && img.naturalWidth === 0 && img.getAttribute('src')) {
                    apply(img);
                }
            });
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', sweep);
        } else {
            sweep();
        }
    })();
</script>
