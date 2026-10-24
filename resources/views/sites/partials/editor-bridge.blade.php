<script>
    (() => {
        const SOURCE = 'sitemap-editor';
        const HOST = 'sitemap-editor-host';
        const root = document.documentElement;
        const origin = window.location.origin;
        const SCROLL_KEY = 'sm-editor-scroll:' + window.location.pathname;

        let mode = 'edit';
        let active = null;
        let selected = null;
        let overlay = null;
        let selectedSection = null;
        let hoveredSection = null;

        root.classList.add('sm-editing');

        const send = (type, payload = {}) => {
            window.parent.postMessage({ source: SOURCE, type, ...payload }, origin);
        };

        const isEditing = () => mode === 'edit';
        const textEls = (path) => Array.from(document.querySelectorAll('[data-edit]')).filter((el) => el.dataset.edit === path);
        const meta = (el) => ({
            path: el.dataset.edit,
            label: el.dataset.editLabel || el.dataset.edit,
            max: Number(el.dataset.editMax || 200),
            multiline: el.dataset.editMultiline === '1',
            reload: el.dataset.editReload === '1',
        });

        const readMultiline = (el) => {
            const paragraphs = el.querySelectorAll(':scope > p');

            if (paragraphs.length > 0) {
                return Array.from(paragraphs).map((p) => {
                    const clone = p.cloneNode(true);
                    clone.querySelectorAll('br').forEach((br) => br.replaceWith('\n'));

                    return clone.textContent.trim();
                }).join('\n\n');
            }

            return el.textContent.trim();
        };

        const readValue = (el) => (el.dataset.editMultiline === '1' ? readMultiline(el) : el.textContent.trim());

        const writeValue = (el, value) => {
            if (el.dataset.editReload === '1') {
                return;
            }

            el.textContent = value;
        };

        const mirror = (path, value, except = null) => {
            textEls(path).forEach((el) => {
                if (el !== except) {
                    writeValue(el, value);
                }
            });
        };

        const clearSelection = () => {
            document.querySelectorAll('.sm-selected').forEach((el) => el.classList.remove('sm-selected'));
            selected = null;
        };

        const markSelected = (el) => {
            clearSelection();
            selected = el;
            el.classList.add('sm-selected');
        };

        const clearSectionSelection = () => {
            document.querySelectorAll('.sm-section-selected').forEach((el) => el.classList.remove('sm-section-selected'));
            selectedSection = null;
        };

        const selectSection = (section) => {
            clearSectionSelection();
            selectedSection = section;
            section.classList.add('sm-section-selected');
        };

        // ---- Floating section toolbar -------------------------------------------------

        const chrome = document.createElement('div');
        chrome.className = 'sm-chrome';
        chrome.setAttribute('role', 'toolbar');
        chrome.setAttribute('aria-label', 'Section tools');
        chrome.innerHTML = '<span class="sm-chrome-label"></span>'
            + '<button type="button" data-act="up" title="Move section up" aria-label="Move section up">&uarr;</button>'
            + '<button type="button" data-act="down" title="Move section down" aria-label="Move section down">&darr;</button>'
            + '<button type="button" data-act="improve" title="Improve this section with AI">Improve with AI</button>'
            + '<button type="button" data-act="hide" title="Hide this section" aria-label="Hide this section">Hide</button>';
        document.body.appendChild(chrome);

        const sections = () => Array.from(document.querySelectorAll('[data-section]'));

        const placeChrome = (section) => {
            if (! section || ! isEditing() || active || overlay) {
                chrome.classList.remove('is-visible');

                return;
            }

            const rect = section.getBoundingClientRect();

            if (rect.bottom < 40 || rect.top > window.innerHeight) {
                chrome.classList.remove('is-visible');

                return;
            }

            const list = sections();
            const index = list.indexOf(section);

            chrome.querySelector('.sm-chrome-label').textContent = section.dataset.sectionLabel || section.dataset.section;
            chrome.querySelector('[data-act="up"]').disabled = index <= 0;
            chrome.querySelector('[data-act="down"]').disabled = index === list.length - 1;
            chrome.dataset.section = section.dataset.section;
            chrome.classList.add('is-visible');
            chrome.style.top = Math.max(8, rect.top + 8) + 'px';
            chrome.style.left = Math.max(8, Math.min(window.innerWidth - chrome.offsetWidth - 8, rect.right - chrome.offsetWidth - 12)) + 'px';
        };

        const setHover = (section) => {
            if (hoveredSection === section) {
                return placeChrome(section);
            }

            hoveredSection?.classList.remove('sm-section-hover');
            hoveredSection = section;
            section?.classList.add('sm-section-hover');
            placeChrome(section);
        };

        document.addEventListener('mouseover', (event) => {
            if (! isEditing()) {
                return;
            }

            if (chrome.contains(event.target)) {
                return;
            }

            setHover(event.target.closest?.('[data-section]') || null);
        });

        document.addEventListener('mouseleave', () => setHover(null));
        window.addEventListener('scroll', () => placeChrome(hoveredSection), { passive: true });
        window.addEventListener('resize', () => placeChrome(hoveredSection));

        chrome.addEventListener('click', (event) => {
            const button = event.target.closest('button[data-act]');

            if (! button || button.disabled) {
                return;
            }

            send('section', { action: button.dataset.act, key: chrome.dataset.section });
        });

        // ---- Inline editing -------------------------------------------------------------

        const stopEditing = (commit = true) => {
            if (! active) {
                return;
            }

            const { el, original, timer } = active;
            clearTimeout(timer);
            el.removeAttribute('contenteditable');
            el.removeAttribute('spellcheck');

            const value = readValue(el);
            active = null;

            if (! commit) {
                writeValue(el, original);
                mirror(el.dataset.edit, original, el);

                return;
            }

            if (value !== original) {
                mirror(el.dataset.edit, value, el);
                send('edit', { ...meta(el), value, final: true });
            }
        };

        const startEditing = (el) => {
            if (active?.el === el) {
                return;
            }

            stopEditing();
            closeOverlay(true);
            markSelected(el);
            clearSectionSelection();
            setHover(null);

            const info = meta(el);
            send('select', { kind: 'text', ...info, value: readValue(el) });

            if (info.multiline) {
                openOverlay(el, info);

                return;
            }

            const original = readValue(el);
            active = { el, original, timer: null };
            el.setAttribute('contenteditable', 'plaintext-only');

            if (el.contentEditable !== 'plaintext-only') {
                el.setAttribute('contenteditable', 'true');
            }

            el.setAttribute('spellcheck', 'true');
            el.focus();

            const range = document.createRange();
            range.selectNodeContents(el);
            range.collapse(false);
            const selection = window.getSelection();
            selection.removeAllRanges();
            selection.addRange(range);
        };

        const insertPlain = (text) => {
            document.execCommand('insertText', false, text);
        };

        document.addEventListener('beforeinput', (event) => {
            if (! active || event.target !== active.el) {
                return;
            }

            const max = Number(active.el.dataset.editMax || 200);
            const current = active.el.textContent.length;
            const selection = window.getSelection().toString().length;

            if (event.inputType === 'insertParagraph' || event.inputType === 'insertLineBreak') {
                event.preventDefault();

                return;
            }

            if (event.inputType === 'insertText' && event.data && current - selection + event.data.length > max) {
                event.preventDefault();
            }
        });

        document.addEventListener('paste', (event) => {
            if (! active || event.target !== active.el) {
                return;
            }

            event.preventDefault();

            const max = Number(active.el.dataset.editMax || 200);
            const room = Math.max(0, max - (active.el.textContent.length - window.getSelection().toString().length));
            const text = (event.clipboardData?.getData('text/plain') || '').replace(/\s+/g, ' ').slice(0, room);

            if (text) {
                insertPlain(text);
            }
        });

        document.addEventListener('input', (event) => {
            if (! active || event.target !== active.el) {
                return;
            }

            const el = active.el;
            clearTimeout(active.timer);
            active.timer = setTimeout(() => {
                const value = readValue(el);
                mirror(el.dataset.edit, value, el);
                send('edit', { ...meta(el), value, final: false });
            }, 350);
        });

        document.addEventListener('focusout', (event) => {
            if (active && event.target === active.el) {
                stopEditing(true);
            }
        });

        document.addEventListener('keydown', (event) => {
            if (active && event.target === active.el) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    stopEditing(true);
                } else if (event.key === 'Escape') {
                    event.preventDefault();
                    stopEditing(false);
                }

                return;
            }

            if (overlay) {
                return;
            }

            const modifier = event.ctrlKey || event.metaKey;
            const key = event.key.toLowerCase();

            if (modifier && key === 'z' && ! event.shiftKey) {
                event.preventDefault();
                send('shortcut', { name: 'undo' });
            } else if (modifier && (key === 'y' || (key === 'z' && event.shiftKey))) {
                event.preventDefault();
                send('shortcut', { name: 'redo' });
            } else if (modifier && key === 's') {
                event.preventDefault();
                send('shortcut', { name: 'save' });
            } else if (event.key === 'Escape') {
                clearSelection();
                clearSectionSelection();
                send('select', { kind: 'none' });
            }
        });

        document.addEventListener('keyup', (event) => {
            if (active && event.target === active.el && event.key === ' ') {
                event.preventDefault();
            }
        });

        // ---- Multiline overlay ----------------------------------------------------------

        function closeOverlay(commit) {
            if (! overlay) {
                return;
            }

            const { wrap, textarea, el, original, info, timer } = overlay;
            clearTimeout(timer);
            overlay = null;
            const value = textarea.value.replace(/\r\n/g, '\n').trim();
            wrap.remove();
            el.classList.remove('sm-selected');

            if (commit && value !== original) {
                mirror(info.path, value, null);
                send('edit', { ...info, value, final: true });
            }
        }

        function openOverlay(el, info) {
            const original = readMultiline(el);
            const rect = el.getBoundingClientRect();
            const wrap = document.createElement('div');
            wrap.className = 'sm-textarea-wrap';
            wrap.style.left = Math.max(8, rect.left + window.scrollX) + 'px';
            wrap.style.top = (rect.top + window.scrollY) + 'px';
            wrap.style.width = Math.min(Math.max(rect.width, 320), window.innerWidth - 16) + 'px';

            const textarea = document.createElement('textarea');
            textarea.value = original;
            textarea.maxLength = info.max;
            textarea.setAttribute('aria-label', info.label);

            const footer = document.createElement('div');
            footer.className = 'sm-meta';
            const count = document.createElement('span');
            const hint = document.createElement('span');
            hint.textContent = 'Ctrl+Enter to save, Esc to cancel';
            footer.append(count, hint);
            wrap.append(textarea, footer);
            document.body.appendChild(wrap);

            overlay = { wrap, textarea, el, original, info, timer: null };
            el.classList.add('sm-selected');

            const updateCount = () => {
                count.textContent = textarea.value.length + ' / ' + info.max;
            };

            updateCount();
            textarea.style.height = Math.min(Math.max(rect.height + 24, 112), 420) + 'px';
            textarea.focus();

            textarea.addEventListener('input', () => {
                updateCount();

                if (info.reload) {
                    return;
                }

                clearTimeout(overlay?.timer);

                if (overlay) {
                    overlay.timer = setTimeout(() => {
                        const value = textarea.value.replace(/\r\n/g, '\n').trim();
                        mirror(info.path, value, null);
                        send('edit', { ...info, value, final: false });
                    }, 350);
                }
            });

            textarea.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    event.preventDefault();
                    mirror(info.path, original, null);
                    closeOverlay(false);
                } else if (event.key === 'Enter' && (event.ctrlKey || event.metaKey)) {
                    event.preventDefault();
                    closeOverlay(true);
                }
            });

            textarea.addEventListener('blur', () => closeOverlay(true));
        }

        // ---- Click handling -------------------------------------------------------------

        document.addEventListener('click', (event) => {
            if (chrome.contains(event.target) || overlay?.wrap.contains(event.target)) {
                return;
            }

            if (! isEditing()) {
                const anchor = event.target.closest?.('a[href]');

                if (anchor && ! isInternal(anchor)) {
                    event.preventDefault();
                }

                return;
            }

            const target = event.target;

            if (active && active.el.contains(target)) {
                return;
            }

            const image = target.closest?.('[data-edit-image]');

            if (image) {
                event.preventDefault();
                event.stopPropagation();
                markSelected(image);
                send('select', {
                    kind: 'image',
                    slot: image.dataset.editImage,
                    index: image.dataset.editIndex === '' ? null : Number(image.dataset.editIndex),
                    label: image.dataset.editLabel || 'Image',
                });
                send('image', {
                    slot: image.dataset.editImage,
                    index: image.dataset.editIndex === '' ? null : Number(image.dataset.editIndex),
                    label: image.dataset.editLabel || 'Image',
                });

                return;
            }

            const text = target.closest?.('[data-edit]');

            if (text) {
                event.preventDefault();
                event.stopPropagation();
                startEditing(text);

                return;
            }

            stopEditing(true);
            const anchor = target.closest?.('a[href]');
            const section = target.closest?.('[data-section]');

            if (anchor) {
                if (isInternal(anchor) && ! anchor.getAttribute('href').startsWith('#')) {
                    return;
                }

                event.preventDefault();

                return;
            }

            if (target.closest?.('form, button, summary, [role="button"]')) {
                event.preventDefault();
            }

            clearSelection();

            if (section) {
                selectSection(section);
                send('select', { kind: 'section', key: section.dataset.section, label: section.dataset.sectionLabel || section.dataset.section });
            } else {
                clearSectionSelection();
                send('select', { kind: 'none' });
            }
        }, true);

        function isInternal(anchor) {
            try {
                const url = new URL(anchor.href, window.location.href);

                return url.origin === origin && url.searchParams.get('editor') === '1';
            } catch (error) {
                return false;
            }
        }

        document.addEventListener('submit', (event) => event.preventDefault(), true);

        // ---- Host messages --------------------------------------------------------------

        window.addEventListener('message', (event) => {
            if (event.origin !== origin || event.source !== window.parent || event.data?.source !== HOST) {
                return;
            }

            const message = event.data;

            if (message.type === 'mode') {
                stopEditing(true);
                closeOverlay(true);
                mode = message.mode === 'preview' ? 'preview' : 'edit';
                root.classList.toggle('sm-preview-mode', mode === 'preview');
                setHover(null);
                clearSelection();
                clearSectionSelection();
            } else if (message.type === 'apply') {
                mirror(String(message.path), String(message.value ?? ''), active?.el ?? overlay?.el ?? null);
            } else if (message.type === 'select') {
                const el = textEls(String(message.path))[0];

                if (el) {
                    markSelected(el);
                    el.scrollIntoView({ block: 'center', behavior: 'smooth' });
                }
            } else if (message.type === 'focus') {
                const el = textEls(String(message.path))[0];

                if (el) {
                    el.scrollIntoView({ block: 'center' });
                    startEditing(el);
                }
            } else if (message.type === 'section') {
                const section = sections().find((item) => item.dataset.section === message.key);

                if (section) {
                    section.scrollIntoView({ block: 'start', behavior: 'smooth' });
                    selectSection(section);
                }
            } else if (message.type === 'reload') {
                sessionStorage.setItem(SCROLL_KEY, String(window.scrollY));
                window.location.reload();
            } else if (message.type === 'deselect') {
                stopEditing(true);
                closeOverlay(true);
                clearSelection();
                clearSectionSelection();
            }
        });

        // ---- Startup --------------------------------------------------------------------

        document.querySelectorAll('details:not(.mobile-menu):not(.claim-discuss)').forEach((details) => {
            details.open = true;
        });

        const saved = sessionStorage.getItem(SCROLL_KEY);

        if (saved !== null) {
            sessionStorage.removeItem(SCROLL_KEY);
            window.scrollTo(0, Number(saved));
        }

        send('ready', {
            path: window.location.pathname,
            title: document.title,
            sections: sections().map((item) => ({ key: item.dataset.section, label: item.dataset.sectionLabel || item.dataset.section })),
        });
    })();
</script>
