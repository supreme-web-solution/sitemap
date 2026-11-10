/*
 * Website assistant widget.
 *
 * Added to a generated website with:
 *   <script src="https://app.example/widget/assistant.js" data-assistant-key="KEY" defer></script>
 *
 * Plain JavaScript with no dependencies. Everything lives in a shadow root so the host
 * website's styles never touch it and it never touches the website's styles.
 */
(function () {
    'use strict';

    if (window.__smAssistantLoaded) {
        return;
    }

    var script = document.currentScript || document.querySelector('script[data-assistant-key]');
    var key = script && script.getAttribute('data-assistant-key');

    if (!key || !script.src) {
        return;
    }

    window.__smAssistantLoaded = true;

    var origin = new URL(script.src, location.href).origin;
    var businessName = '';
    var api = origin + '/assistant/' + encodeURIComponent(key);
    var storeKey = 'sm-assistant:' + key;
    var MAX_TURNS = 16;

    var config = null;
    var state = loadState();
    var host;
    var root;
    var els = {};
    var sending = false;
    var speaking = false;
    var mouthTimer = 0;
    var chosenVoice = null;

    function loadState() {
        try {
            var saved = JSON.parse(sessionStorage.getItem(storeKey) || '{}');

            return {
                history: Array.isArray(saved.history) ? saved.history : [],
                open: !!saved.open,
                muted: localStorage.getItem(storeKey + ':muted') === '1',
                leadSent: !!saved.leadSent,
            };
        } catch (e) {
            return { history: [], open: false, muted: false, leadSent: false };
        }
    }

    function saveState() {
        try {
            sessionStorage.setItem(storeKey, JSON.stringify({
                history: state.history.slice(-MAX_TURNS),
                open: state.open,
                leadSent: state.leadSent,
            }));
            localStorage.setItem(storeKey + ':muted', state.muted ? '1' : '0');
        } catch (e) {
            /* Storage can be blocked; the assistant still works without it. */
        }
    }

    function request(path, options) {
        var controller = new AbortController();
        var timer = setTimeout(function () { controller.abort(); }, 25000);

        options = options || {};
        options.signal = controller.signal;
        options.headers = Object.assign({ Accept: 'application/json' }, options.headers || {});

        return fetch(api + path, options).then(function (response) {
            clearTimeout(timer);

            if (!response.ok) {
                var error = new Error('HTTP ' + response.status);
                error.response = response;
                throw error;
            }

            return response.json();
        }, function (error) {
            clearTimeout(timer);
            throw error;
        });
    }

    function post(path, body) {
        return request(path, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body),
        });
    }

    function readableOn(hex) {
        var value = (hex || '').replace('#', '');

        if (value.length === 3) {
            value = value.split('').map(function (c) { return c + c; }).join('');
        }

        if (!/^[0-9a-f]{6}$/i.test(value)) {
            return '#ffffff';
        }

        var channels = [0, 2, 4].map(function (i) {
            var c = parseInt(value.substr(i, 2), 16) / 255;

            return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
        });

        return 0.2126 * channels[0] + 0.7152 * channels[1] + 0.0722 * channels[2] > 0.179 ? '#0c0a09' : '#ffffff';
    }

    var CSS = [
        ':host{all:initial}',
        '*{box-sizing:border-box}',
        '[hidden]{display:none!important}',
        '.sm{--accent:#2563eb;--on-accent:#fff;font:15px/1.45 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;color:#18181b}',
        '.launcher{position:fixed;right:20px;bottom:calc(20px + var(--lift,0px));z-index:2147483000;display:flex;align-items:center;gap:12px;flex-direction:row-reverse}',
        '.bubble{width:64px;height:64px;border-radius:50%;border:3px solid var(--accent);padding:0;background:#fff;cursor:pointer;overflow:hidden;box-shadow:0 8px 24px rgba(0,0,0,.25);position:relative}',
        '.bubble:focus-visible,.icon-btn:focus-visible,.send:focus-visible,.chip:focus-visible{outline:3px solid var(--accent);outline-offset:2px}',
        '.bubble svg{width:100%;height:100%;display:block}',
        '.bubble::after{content:"";position:absolute;inset:-3px;border-radius:50%;border:3px solid var(--accent);animation:pulse 2.4s ease-out infinite}',
        '.teaser{background:#fff;border-radius:14px;padding:10px 14px;box-shadow:0 8px 24px rgba(0,0,0,.18);max-width:230px;font-size:14px;cursor:pointer;animation:rise .3s ease-out}',
        '.panel{position:fixed;right:20px;bottom:calc(20px + var(--lift,0px));z-index:2147483001;width:380px;max-width:calc(100vw - 24px);height:560px;max-height:calc(100vh - 40px - var(--lift,0px));background:#fff;border-radius:18px;box-shadow:0 20px 60px rgba(0,0,0,.3);display:flex;flex-direction:column;overflow:hidden;animation:rise .25s ease-out}',
        '.head{background:var(--accent);color:var(--on-accent);display:flex;align-items:center;gap:12px;padding:12px 14px}',
        '.face{width:52px;height:52px;border-radius:50%;overflow:hidden;background:#fff;flex:none;border:2px solid rgba(255,255,255,.7)}',
        '.face svg{width:100%;height:100%;display:block}',
        '.who{flex:1;min-width:0}',
        '.who b{display:block;font-size:16px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}',
        '.who span{font-size:12px;opacity:.85}',
        '.icon-btn{background:transparent;border:0;color:inherit;width:34px;height:34px;border-radius:8px;cursor:pointer;display:grid;place-items:center;opacity:.9}',
        '.icon-btn:hover{background:rgba(255,255,255,.18);opacity:1}',
        '.icon-btn svg{width:20px;height:20px}',
        '.log{flex:1;overflow-y:auto;padding:14px;background:#f6f7f9;display:flex;flex-direction:column;gap:10px}',
        '.msg{max-width:86%;padding:9px 13px;border-radius:16px;white-space:pre-wrap;word-wrap:break-word}',
        '.msg.bot{background:#fff;border:1px solid #e4e4e7;border-bottom-left-radius:5px;align-self:flex-start}',
        '.msg.me{background:var(--accent);color:var(--on-accent);border-bottom-right-radius:5px;align-self:flex-end}',
        '.typing{display:flex;gap:4px;align-self:flex-start;background:#fff;border:1px solid #e4e4e7;border-radius:16px;padding:12px 14px}',
        '.typing i{width:7px;height:7px;border-radius:50%;background:#a1a1aa;animation:bounce 1s infinite}',
        '.typing i:nth-child(2){animation-delay:.15s}.typing i:nth-child(3){animation-delay:.3s}',
        '.chips{display:flex;flex-wrap:wrap;gap:6px;align-self:flex-start}',
        '.chip{border:1px solid var(--accent);color:var(--accent);background:#fff;border-radius:999px;padding:6px 12px;font:inherit;font-size:13px;cursor:pointer}',
        '.chip:hover{background:var(--accent);color:var(--on-accent)}',
        '.foot{border-top:1px solid #e4e4e7;background:#fff;padding:10px}',
        '.row{display:flex;gap:8px}',
        '.field{flex:1;min-width:0;border:1px solid #d4d4d8;border-radius:12px;padding:10px 12px;font:inherit;color:inherit;background:#fff}',
        '.field:focus{outline:2px solid var(--accent);outline-offset:0;border-color:transparent}',
        '.send{border:0;background:var(--accent);color:var(--on-accent);border-radius:12px;width:44px;cursor:pointer;display:grid;place-items:center}',
        '.send:disabled{opacity:.5;cursor:default}',
        '.send svg{width:20px;height:20px}',
        '.form{display:flex;flex-direction:column;gap:8px}',
        '.form h4{margin:0;font-size:14px}',
        '.form .actions{display:flex;gap:8px}',
        '.btn{border:0;background:var(--accent);color:var(--on-accent);border-radius:12px;padding:10px 14px;font:inherit;font-weight:600;cursor:pointer;flex:1}',
        '.btn.ghost{background:#f4f4f5;color:#3f3f46;flex:none}',
        '.err{color:#b91c1c;font-size:13px;margin:0}',
        '.link{background:none;border:0;padding:6px 0 0;color:#52525b;font:inherit;font-size:12px;text-decoration:underline;cursor:pointer;display:block;margin:0 auto}',
        '.hp{position:absolute;left:-9999px;width:1px;height:1px;opacity:0}',
        '.sm-eye{transform-box:fill-box;transform-origin:center;animation:blink 5s infinite}',
        '@keyframes blink{0%,93%,100%{transform:scaleY(1)}96%{transform:scaleY(.1)}}',
        '@keyframes pulse{0%{transform:scale(1);opacity:.55}100%{transform:scale(1.35);opacity:0}}',
        '@keyframes rise{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}',
        '@keyframes bounce{0%,60%,100%{transform:translateY(0)}30%{transform:translateY(-4px)}}',
        '@media (max-width:520px){.panel{right:0;bottom:0;max-height:100%;width:100vw;max-width:100vw;height:100%;max-height:100%;border-radius:0}.launcher{right:14px;bottom:calc(14px + var(--lift,0px))}}',
        '@media (prefers-reduced-motion:reduce){.bubble::after,.sm-eye,.panel,.teaser{animation:none}}',
    ].join('');

    var ICONS = {
        close: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>',
        send: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M3.4 20.4l17.45-7.48a1 1 0 000-1.84L3.4 3.6a.99.99 0 00-1.39.91L2 9.12c0 .5.37.93.87.99L17 12 2.87 13.88c-.5.07-.87.5-.87 1l.01 4.61c0 .71.73 1.2 1.39.91z"/></svg>',
        on: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 5L6 9H2v6h4l5 4V5z"/><path d="M15.5 8.5a5 5 0 010 7M18.5 5.5a9 9 0 010 13"/></svg>',
        off: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 5L6 9H2v6h4l5 4V5z"/><path d="M22 9l-6 6M16 9l6 6"/></svg>',
    };

    function el(tag, className, text) {
        var node = document.createElement(tag);

        if (className) {
            node.className = className;
        }

        if (text !== undefined) {
            node.textContent = text;
        }

        return node;
    }

    /* ---------- Speech and mouth animation ---------- */

    var FEMALE = /female|zira|jenny|aria|samantha|karen|moira|tessa|victoria|susan|hazel|libby|sonia|natasha|michelle|emma|ava|allison|serena|fiona|joanna/i;
    var MALE = /\bmale\b|david|guy|mark|daniel|alex|fred|george|ryan|davis|james|richard|thomas|oliver|brian|eric|liam|aaron/i;

    function pickVoice() {
        if (!('speechSynthesis' in window) || !config || config.voice === 'off') {
            return null;
        }

        var voices = window.speechSynthesis.getVoices() || [];
        var lang = (document.documentElement.lang || navigator.language || 'en').toLowerCase().split('-')[0];
        var matching = voices.filter(function (voice) { return voice.lang.toLowerCase().indexOf(lang) === 0; });
        var pool = matching.length ? matching : voices;
        var wantsFemale = config.voice === 'female';
        var wanted = wantsFemale ? FEMALE : MALE;
        var unwanted = wantsFemale ? MALE : FEMALE;
        var best = null;
        var bestScore = -1;

        pool.forEach(function (voice) {
            var score = 0;

            if (wanted.test(voice.name)) { score += 4; }
            if (unwanted.test(voice.name)) { score -= 4; }
            if (/natural|online|neural/i.test(voice.name)) { score += 3; }
            if (voice.localService === false) { score += 1; }
            if (/google/i.test(voice.name)) { score += 1; }
            if (score > bestScore) { best = voice; bestScore = score; }
        });

        return best;
    }

    function setMouth(open) {
        var mouths = root.querySelectorAll('#sm-mouth');

        for (var i = 0; i < mouths.length; i++) {
            mouths[i].setAttribute('ry', (2.5 + open * 7).toFixed(2));
            mouths[i].setAttribute('rx', (10 - open * 1.5).toFixed(2));
        }
    }

    function startTalking() {
        stopMouth();
        var started = performance.now();

        mouthTimer = setInterval(function () {
            var t = (performance.now() - started) / 1000;
            var open = 0.5 + 0.5 * Math.sin(t * 13) * Math.sin(t * 5.3 + 1);

            setMouth(Math.max(0, Math.min(1, open)));
        }, 60);
    }

    function stopMouth() {
        clearInterval(mouthTimer);
        mouthTimer = 0;
        setMouth(0);
    }

    function stopSpeaking() {
        if ('speechSynthesis' in window) {
            window.speechSynthesis.cancel();
        }

        speaking = false;
        stopMouth();
    }

    function speak(text) {
        stopSpeaking();

        if (state.muted || !config || config.voice === 'off' || !('speechSynthesis' in window)) {
            // No audio: still move the lips for the time it takes to read the reply.
            speaking = true;
            startTalking();
            setTimeout(function () { speaking = false; stopMouth(); }, Math.min(8000, 600 + text.length * 40));

            return;
        }

        var utterance = new SpeechSynthesisUtterance(text);
        chosenVoice = chosenVoice || pickVoice();

        if (chosenVoice) {
            utterance.voice = chosenVoice;
            utterance.lang = chosenVoice.lang;
        }

        utterance.rate = 1.03;
        utterance.onstart = function () { speaking = true; startTalking(); };
        utterance.onend = utterance.onerror = function () { speaking = false; stopMouth(); };
        window.speechSynthesis.speak(utterance);
    }

    /* ---------- Interface ---------- */

    function build(svgText) {
        host = document.createElement('div');
        host.setAttribute('data-sm-assistant', '');
        root = host.attachShadow({ mode: 'open' });

        var style = document.createElement('style');
        style.textContent = CSS;
        root.appendChild(style);

        var wrap = el('div', 'sm');
        wrap.style.setProperty('--accent', config.accent);
        wrap.style.setProperty('--on-accent', readableOn(config.accent));
        root.appendChild(wrap);
        els.wrap = wrap;
        els.svg = svgText;

        liftAbovePreviewBar();
        window.addEventListener('resize', liftAbovePreviewBar);
        renderLauncher();

        if (state.open) {
            openPanel(true);
        }

        document.body.appendChild(host);
    }

    /* The preview bar of an unpublished website is fixed to the bottom; sit above it instead of covering it. */
    function liftAbovePreviewBar() {
        var bar = document.querySelector('.preview-bar');
        var height = bar && getComputedStyle(bar).position === 'fixed' ? bar.offsetHeight : 0;

        els.wrap.style.setProperty('--lift', height + 'px');
    }

    function avatarHtml() {
        return els.svg || '';
    }

    function renderLauncher() {
        if (els.launcher) {
            els.launcher.hidden = false;

            return;
        }

        var launcher = el('div', 'launcher');
        var bubble = el('button', 'bubble');

        bubble.type = 'button';
        bubble.setAttribute('aria-label', 'Chat with ' + config.name);
        bubble.innerHTML = avatarHtml();
        bubble.addEventListener('click', function () { openPanel(false); });
        launcher.appendChild(bubble);

        var teaser = el('div', 'teaser', 'Hi, I\u2019m ' + config.name + '. Ask me anything about ' + businessName + '.');
        teaser.addEventListener('click', function () { openPanel(false); });
        teaser.hidden = true;
        launcher.appendChild(teaser);

        els.wrap.appendChild(launcher);
        els.launcher = launcher;
        els.teaser = teaser;

        if (!state.history.length) {
            setTimeout(function () {
                if (!state.open && els.teaser) {
                    els.teaser.hidden = false;
                }
            }, 4000);
        }
    }

    function openPanel(restoring) {
        state.open = true;
        saveState();
        els.launcher.hidden = true;

        var panel = el('section', 'panel');
        panel.setAttribute('role', 'dialog');
        panel.setAttribute('aria-label', 'Chat with ' + config.name);

        var head = el('div', 'head');
        var face = el('div', 'face');
        face.innerHTML = avatarHtml();
        var who = el('div', 'who');
        who.appendChild(el('b', '', config.name));
        who.appendChild(el('span', '', 'Virtual assistant for ' + businessName));

        var mute = el('button', 'icon-btn');
        mute.type = 'button';
        var closeBtn = el('button', 'icon-btn');
        closeBtn.type = 'button';
        closeBtn.setAttribute('aria-label', 'Close chat');
        closeBtn.innerHTML = ICONS.close;

        function paintMute() {
            mute.innerHTML = state.muted ? ICONS.off : ICONS.on;
            mute.setAttribute('aria-label', state.muted ? 'Turn voice on' : 'Turn voice off');
            mute.title = mute.getAttribute('aria-label');
        }

        paintMute();
        mute.addEventListener('click', function () {
            state.muted = !state.muted;

            if (state.muted) {
                stopSpeaking();
            }

            paintMute();
            saveState();
        });
        closeBtn.addEventListener('click', closePanel);

        head.appendChild(face);
        head.appendChild(who);

        if (config.voice !== 'off' && 'speechSynthesis' in window) {
            head.appendChild(mute);
        }

        head.appendChild(closeBtn);

        var log = el('div', 'log');
        log.setAttribute('role', 'log');
        log.setAttribute('aria-live', 'polite');

        var foot = el('div', 'foot');

        panel.appendChild(head);
        panel.appendChild(log);
        panel.appendChild(foot);
        panel.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closePanel();
            }
        });

        els.panel = panel;
        els.log = log;
        els.foot = foot;
        els.wrap.appendChild(panel);

        if (state.history.length) {
            state.history.forEach(function (turn) { addMessage(turn.role === 'user' ? 'me' : 'bot', turn.content); });
        } else {
            addMessage('bot', config.greeting);
            speak(config.greeting);
        }

        renderInput();

        if (!restoring) {
            els.input && els.input.focus();
        }
    }

    function closePanel() {
        state.open = false;
        saveState();
        stopSpeaking();

        if (els.panel) {
            els.panel.remove();
            els.panel = null;
        }

        els.launcher.hidden = false;

        if (els.teaser) {
            els.teaser.hidden = true;
        }
    }

    function addMessage(side, text) {
        var node = el('div', 'msg ' + side, text);
        els.log.appendChild(node);
        els.log.scrollTop = els.log.scrollHeight;

        return node;
    }

    function showTyping() {
        var typing = el('div', 'typing');
        typing.innerHTML = '<i></i><i></i><i></i>';
        els.log.appendChild(typing);
        els.log.scrollTop = els.log.scrollHeight;

        return typing;
    }

    function renderInput() {
        els.foot.textContent = '';

        var row = el('form', 'row');
        var input = el('input', 'field');
        input.type = 'text';
        input.placeholder = 'Type your question\u2026';
        input.maxLength = 500;
        input.autocomplete = 'off';
        input.setAttribute('aria-label', 'Your message');
        var send = el('button', 'send');
        send.type = 'submit';
        send.setAttribute('aria-label', 'Send');
        send.innerHTML = ICONS.send;

        row.appendChild(input);
        row.appendChild(send);
        row.addEventListener('submit', function (event) {
            event.preventDefault();
            var text = input.value.trim();

            if (text && !sending) {
                input.value = '';
                ask(text);
            }
        });

        els.foot.appendChild(row);
        els.input = input;
        els.sendButton = send;

        if (!state.leadSent) {
            var link = el('button', 'link', 'Prefer a call back? Leave your details');
            link.type = 'button';
            link.addEventListener('click', showLeadForm);
            els.foot.appendChild(link);
        }
    }

    function ask(text) {
        stopSpeaking();
        sending = true;
        els.sendButton.disabled = true;
        addMessage('me', text);

        var history = state.history.slice(-MAX_TURNS);
        state.history.push({ role: 'user', content: text });
        saveState();

        var typing = showTyping();

        post('/chat', { message: text, history: history }).then(function (data) {
            typing.remove();
            reply(data.reply, !!data.ask_for_contact);
        }, function (error) {
            typing.remove();

            var rateLimited = error.response && error.response.status === 429;
            reply(rateLimited
                ? 'You are sending messages quickly. Give me a moment and try again.'
                : 'Sorry, I could not reach the team just now. Please leave your details and we will get back to you.',
                !rateLimited);
        }).then(function () {
            sending = false;

            if (els.sendButton) {
                els.sendButton.disabled = false;
                els.input && els.input.focus();
            }
        });
    }

    function reply(text, offerForm) {
        if (!els.log) {
            return;
        }

        state.history.push({ role: 'assistant', content: text });
        saveState();
        addMessage('bot', text);
        speak(text);

        if (offerForm && !state.leadSent) {
            var chips = el('div', 'chips');
            var chip = el('button', 'chip', 'Leave my details');
            chip.type = 'button';
            chip.addEventListener('click', function () { chips.remove(); showLeadForm(); });
            chips.appendChild(chip);
            els.log.appendChild(chips);
            els.log.scrollTop = els.log.scrollHeight;
        }
    }

    function showLeadForm() {
        els.foot.textContent = '';

        var form = el('form', 'form');
        form.appendChild(el('h4', '', 'Where can the team reach you?'));

        var name = field('text', 'Your name', 100);
        var contact = field('text', 'Phone or email', 150);
        var note = field('text', 'How can we help? (optional)', 500);
        var honeypot = field('text', '', 200);
        honeypot.className = 'hp';
        honeypot.tabIndex = -1;
        honeypot.setAttribute('autocomplete', 'off');
        honeypot.setAttribute('aria-hidden', 'true');
        var error = el('p', 'err');
        error.hidden = true;
        var actions = el('div', 'actions');
        var submit = el('button', 'btn', 'Send my details');
        submit.type = 'submit';
        var cancel = el('button', 'btn ghost', 'Back');
        cancel.type = 'button';
        cancel.addEventListener('click', renderInput);
        actions.appendChild(submit);
        actions.appendChild(cancel);

        [name, contact, note, honeypot, error, actions].forEach(function (node) { form.appendChild(node); });
        els.foot.appendChild(form);
        name.focus();

        form.addEventListener('submit', function (event) {
            event.preventDefault();

            if (!name.value.trim() || !contact.value.trim()) {
                error.textContent = 'Please add your name and a phone number or email.';
                error.hidden = false;

                return;
            }

            submit.disabled = true;
            error.hidden = true;

            post('/lead', {
                name: name.value.trim(),
                contact: contact.value.trim(),
                message: note.value.trim(),
                website: honeypot.value,
                history: state.history.slice(-MAX_TURNS),
            }).then(function () {
                state.leadSent = true;
                saveState();
                var thanks = 'Thank you, ' + name.value.trim().split(' ')[0] + '. I have passed your details to the team and they will be in touch soon.';
                state.history.push({ role: 'assistant', content: thanks });
                saveState();
                addMessage('bot', thanks);
                speak(thanks);
                renderInput();
            }, function () {
                submit.disabled = false;
                error.textContent = 'That did not go through. Please check your details and try again.';
                error.hidden = false;
            });
        });
    }

    function field(type, placeholder, max) {
        var input = el('input', 'field');
        input.type = type;
        input.placeholder = placeholder;
        input.maxLength = max;
        input.setAttribute('aria-label', placeholder || 'Leave empty');

        return input;
    }

    /* ---------- Start ---------- */

    function start() {
        request('/config').then(function (data) {
            config = data;
            businessName = (data.business || '').replace(/[.\s]+$/, '');

            return fetch(data.avatar_url).then(function (response) { return response.ok ? response.text() : ''; });
        }).then(function (svg) {
            build(svg);

            if ('speechSynthesis' in window) {
                window.speechSynthesis.onvoiceschanged = function () { chosenVoice = null; };
            }
        }).catch(function () {
            /* The assistant is switched off or unreachable: the website simply carries on without it. */
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
