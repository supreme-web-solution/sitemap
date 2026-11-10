/*
 * Website assistant widget — photoreal presenter stage + collapsible chat.
 *
 * <script src="…/widget/assistant.js" data-assistant-key="KEY" defer></script>
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
    var api = origin + '/assistant/' + encodeURIComponent(key);
    var storeKey = 'sm-assistant:' + key;
    var MAX_TURNS = 16;

    var config = null;
    var businessName = '';
    var state = loadState();
    var host;
    var root;
    var els = {};
    var sending = false;
    var speaking = false;
    var currentAudio = null;
    var mouthTimer = 0;

    function loadState() {
        try {
            var saved = JSON.parse(sessionStorage.getItem(storeKey) || '{}');

            return {
                history: Array.isArray(saved.history) ? saved.history : [],
                open: !!saved.open,
                chatOpen: saved.chatOpen !== false,
                muted: localStorage.getItem(storeKey + ':muted') === '1',
                leadSent: !!saved.leadSent,
            };
        } catch (e) {
            return { history: [], open: false, chatOpen: true, muted: false, leadSent: false };
        }
    }

    function saveState() {
        try {
            sessionStorage.setItem(storeKey, JSON.stringify({
                history: state.history.slice(-MAX_TURNS),
                open: state.open,
                chatOpen: state.chatOpen,
                leadSent: state.leadSent,
            }));
            localStorage.setItem(storeKey + ':muted', state.muted ? '1' : '0');
        } catch (e) { /* storage may be blocked */ }
    }

    function request(path, options) {
        var controller = new AbortController();
        var timer = setTimeout(function () { controller.abort(); }, 45000);

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
        '.sm{--accent:#2563eb;--on-accent:#fff;--lift:0px;font:15px/1.45 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;color:#18181b}',
        '.launcher{position:fixed;right:20px;bottom:calc(20px + var(--lift));z-index:2147483000;display:flex;align-items:center;gap:12px;flex-direction:row-reverse}',
        '.bubble{width:72px;height:72px;border-radius:50%;border:3px solid #fff;padding:0;background:#111;cursor:pointer;overflow:hidden;box-shadow:0 10px 28px rgba(0,0,0,.28);position:relative}',
        '.bubble img{width:100%;height:100%;object-fit:cover;display:block}',
        '.bubble::after{content:"";position:absolute;inset:-4px;border-radius:50%;border:3px solid var(--accent);animation:pulse 2.4s ease-out infinite;pointer-events:none}',
        '.teaser{background:#fff;border-radius:14px;padding:10px 14px;box-shadow:0 8px 24px rgba(0,0,0,.18);max-width:240px;font-size:14px;cursor:pointer;animation:rise .3s ease-out}',
        '.panel{position:fixed;right:20px;bottom:calc(20px + var(--lift));z-index:2147483001;width:min(920px,calc(100vw - 24px));height:min(580px,calc(100vh - 48px - var(--lift)));background:#0b0b0f;border-radius:22px;box-shadow:0 24px 70px rgba(0,0,0,.45);display:flex;overflow:hidden;animation:rise .25s ease-out;color:#fff}',
        '.stage{position:relative;flex:1.15;min-width:0;background:#111;overflow:hidden}',
        '.stage.solo{flex:1}',
        '.stage-photo{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;object-position:center top;transform:scale(1.02);transition:transform 4s ease}',
        '.stage.talking .stage-photo{transform:scale(1.05)}',
        '.stage-shade{position:absolute;inset:0;background:linear-gradient(180deg,rgba(0,0,0,.15) 0%,rgba(0,0,0,.05) 40%,rgba(0,0,0,.72) 100%);pointer-events:none}',
        '.stage-ring{position:absolute;inset:18px;border-radius:18px;border:1px solid rgba(255,255,255,.12);pointer-events:none;box-shadow:inset 0 0 0 1px rgba(0,0,0,.2)}',
        '.stage.talking .stage-ring{border-color:rgba(255,255,255,.35);box-shadow:0 0 0 1px rgba(255,255,255,.08),0 0 40px color-mix(in srgb,var(--accent) 35%,transparent)}',
        '.live{position:absolute;top:18px;left:18px;display:inline-flex;align-items:center;gap:7px;padding:6px 10px;border-radius:999px;background:rgba(0,0,0,.45);backdrop-filter:blur(8px);font-size:12px;font-weight:600;letter-spacing:.02em}',
        '.live i{width:8px;height:8px;border-radius:50%;background:#22c55e;box-shadow:0 0 0 0 rgba(34,197,94,.7);animation:live 1.6s infinite}',
        '.stage-meta{position:absolute;left:18px;right:18px;bottom:18px}',
        '.stage-meta b{display:block;font-size:22px;line-height:1.15;text-shadow:0 2px 12px rgba(0,0,0,.45)}',
        '.stage-meta span{display:block;margin-top:4px;font-size:13px;opacity:.88}',
        '.speaking-bar{margin-top:12px;height:3px;width:120px;border-radius:999px;background:rgba(255,255,255,.2);overflow:hidden}',
        '.speaking-bar em{display:block;height:100%;width:0;background:var(--accent);border-radius:inherit}',
        '.stage.talking .speaking-bar em{animation:fill 1.2s ease-in-out infinite}',
        '.stage-tools{position:absolute;top:14px;right:14px;display:flex;gap:6px}',
        '.icon-btn{background:rgba(0,0,0,.4);border:0;color:#fff;width:36px;height:36px;border-radius:10px;cursor:pointer;display:grid;place-items:center;backdrop-filter:blur(8px)}',
        '.icon-btn:hover{background:rgba(0,0,0,.62)}',
        '.icon-btn svg{width:18px;height:18px}',
        '.chat{width:340px;max-width:42%;flex:none;display:flex;flex-direction:column;background:#0f1117;border-left:1px solid rgba(255,255,255,.08)}',
        '.chat-head{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:14px 14px 10px;border-bottom:1px solid rgba(255,255,255,.08)}',
        '.chat-head b{font-size:14px}',
        '.log{flex:1;overflow-y:auto;padding:14px;display:flex;flex-direction:column;gap:10px;background:linear-gradient(180deg,#12141c,#0f1117)}',
        '.msg{max-width:90%;padding:9px 12px;border-radius:16px;white-space:pre-wrap;word-wrap:break-word;font-size:14px}',
        '.msg.bot{background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.08);border-bottom-left-radius:5px;align-self:flex-start}',
        '.msg.me{background:var(--accent);color:var(--on-accent);border-bottom-right-radius:5px;align-self:flex-end}',
        '.typing{display:flex;gap:4px;align-self:flex-start;background:rgba(255,255,255,.07);border-radius:16px;padding:12px 14px}',
        '.typing i{width:7px;height:7px;border-radius:50%;background:#a1a1aa;animation:bounce 1s infinite}',
        '.typing i:nth-child(2){animation-delay:.15s}.typing i:nth-child(3){animation-delay:.3s}',
        '.chips{display:flex;flex-wrap:wrap;gap:6px;align-self:flex-start}',
        '.chip{border:1px solid rgba(255,255,255,.22);color:#fff;background:transparent;border-radius:999px;padding:6px 12px;font:inherit;font-size:13px;cursor:pointer}',
        '.chip:hover{background:var(--accent);border-color:var(--accent);color:var(--on-accent)}',
        '.foot{border-top:1px solid rgba(255,255,255,.08);padding:10px;background:#0c0e14}',
        '.row{display:flex;gap:8px}',
        '.field{flex:1;min-width:0;border:1px solid rgba(255,255,255,.12);border-radius:12px;padding:10px 12px;font:inherit;color:#fff;background:rgba(255,255,255,.04)}',
        '.field::placeholder{color:rgba(255,255,255,.45)}',
        '.field:focus{outline:2px solid var(--accent);outline-offset:0;border-color:transparent}',
        '.send{border:0;background:var(--accent);color:var(--on-accent);border-radius:12px;width:44px;cursor:pointer;display:grid;place-items:center}',
        '.send:disabled{opacity:.5;cursor:default}',
        '.send svg{width:18px;height:18px}',
        '.form{display:flex;flex-direction:column;gap:8px}',
        '.form h4{margin:0;font-size:14px;color:#fff}',
        '.form .actions{display:flex;gap:8px}',
        '.btn{border:0;background:var(--accent);color:var(--on-accent);border-radius:12px;padding:10px 14px;font:inherit;font-weight:600;cursor:pointer;flex:1}',
        '.btn.ghost{background:rgba(255,255,255,.08);color:#fff;flex:none}',
        '.err{color:#fda4af;font-size:13px;margin:0}',
        '.link{background:none;border:0;padding:6px 0 0;color:rgba(255,255,255,.62);font:inherit;font-size:12px;text-decoration:underline;cursor:pointer;display:block;margin:0 auto}',
        '.hp{position:absolute;left:-9999px;width:1px;height:1px;opacity:0}',
        '.bubble:focus-visible,.icon-btn:focus-visible,.send:focus-visible,.chip:focus-visible,.btn:focus-visible{outline:3px solid var(--accent);outline-offset:2px}',
        '@keyframes pulse{0%{transform:scale(1);opacity:.55}100%{transform:scale(1.35);opacity:0}}',
        '@keyframes rise{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}',
        '@keyframes bounce{0%,60%,100%{transform:translateY(0)}30%{transform:translateY(-4px)}}',
        '@keyframes live{0%{box-shadow:0 0 0 0 rgba(34,197,94,.55)}70%{box-shadow:0 0 0 8px rgba(34,197,94,0)}100%{box-shadow:0 0 0 0 rgba(34,197,94,0)}}',
        '@keyframes fill{0%,100%{width:18%}50%{width:92%}}',
        '@media (max-width:820px){.panel{flex-direction:column;width:min(420px,calc(100vw - 16px));height:min(720px,calc(100vh - 24px - var(--lift)))}.stage{flex:1.05;min-height:240px}.chat{width:100%;max-width:none;flex:1;border-left:0;border-top:1px solid rgba(255,255,255,.08)}.stage.solo{min-height:100%}}',
        '@media (max-width:520px){.panel{right:0;bottom:0;width:100vw;max-width:100vw;height:100%;max-height:100%;border-radius:0}.launcher{right:14px;bottom:calc(14px + var(--lift))}}',
        '@media (prefers-reduced-motion:reduce){.bubble::after,.panel,.teaser,.live i,.speaking-bar em,.stage-photo{animation:none;transition:none}}',
    ].join('');

    var ICONS = {
        close: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>',
        send: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M3.4 20.4l17.45-7.48a1 1 0 000-1.84L3.4 3.6a.99.99 0 00-1.39.91L2 9.12c0 .5.37.93.87.99L17 12 2.87 13.88c-.5.07-.87.5-.87 1l.01 4.61c0 .71.73 1.2 1.39.91z"/></svg>',
        on: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 5L6 9H2v6h4l5 4V5z"/><path d="M15.5 8.5a5 5 0 010 7M18.5 5.5a9 9 0 010 13"/></svg>',
        off: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 5L6 9H2v6h4l5 4V5z"/><path d="M22 9l-6 6M16 9l6 6"/></svg>',
        chat: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a4 4 0 01-4 4H8l-5 3V7a4 4 0 014-4h10a4 4 0 014 4z"/></svg>',
        hide: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M15 6l6 6-6 6M3 12h17"/></svg>',
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

    function liftAbovePreviewBar() {
        var bar = document.querySelector('.preview-bar');
        var height = bar && getComputedStyle(bar).position === 'fixed' ? bar.offsetHeight : 0;

        els.wrap.style.setProperty('--lift', height + 'px');
    }

    function stopSpeaking() {
        if (currentAudio) {
            currentAudio.pause();
            currentAudio = null;
        }

        speaking = false;
        clearInterval(mouthTimer);
        mouthTimer = 0;

        if (els.stage) {
            els.stage.classList.remove('talking');
        }
    }

    function startTalkingVisual() {
        if (!els.stage) {
            return;
        }

        els.stage.classList.add('talking');
        speaking = true;
    }

    function playAudio(base64, mime) {
        stopSpeaking();

        if (state.muted || !base64) {
            return Promise.resolve();
        }

        return new Promise(function (resolve) {
            var audio = new Audio('data:' + (mime || 'audio/mpeg') + ';base64,' + base64);
            currentAudio = audio;
            startTalkingVisual();

            audio.onended = audio.onerror = function () {
                stopSpeaking();
                resolve();
            };

            audio.play().catch(function () {
                stopSpeaking();
                resolve();
            });
        });
    }

    function speakText(text, preloaded) {
        if (preloaded && preloaded.audio) {
            return playAudio(preloaded.audio, preloaded.audio_mime);
        }

        if (!config.voice_on || state.muted) {
            return Promise.resolve();
        }

        return post('/speak', { text: text }).then(function (data) {
            return playAudio(data.audio, data.audio_mime);
        }).catch(function () { /* text still shows */ });
    }

    function build() {
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

        liftAbovePreviewBar();
        window.addEventListener('resize', liftAbovePreviewBar);
        renderLauncher();

        if (state.open) {
            openPanel(true);
        }

        document.body.appendChild(host);
    }

    function faceUrl() {
        return (config.presenter && config.presenter.image_url) || '';
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

        var img = document.createElement('img');
        img.src = faceUrl();
        img.alt = config.name;
        bubble.appendChild(img);
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
            }, 3500);
        }
    }

    function openPanel(restoring) {
        state.open = true;
        saveState();
        els.launcher.hidden = true;

        var panel = el('section', 'panel');
        panel.setAttribute('role', 'dialog');
        panel.setAttribute('aria-label', 'Talk with ' + config.name);

        var stage = el('div', 'stage' + (state.chatOpen ? '' : ' solo'));
        var photo = document.createElement('img');
        photo.className = 'stage-photo';
        photo.src = faceUrl();
        photo.alt = config.name;
        stage.appendChild(photo);
        stage.appendChild(el('div', 'stage-shade'));
        stage.appendChild(el('div', 'stage-ring'));

        var live = el('div', 'live');
        live.innerHTML = '<i></i><span>Live</span>';
        stage.appendChild(live);

        var tools = el('div', 'stage-tools');
        var mute = el('button', 'icon-btn');
        mute.type = 'button';

        function paintMute() {
            mute.innerHTML = state.muted ? ICONS.off : ICONS.on;
            mute.setAttribute('aria-label', state.muted ? 'Turn voice on' : 'Turn voice off');
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

        var toggleChat = el('button', 'icon-btn');
        toggleChat.type = 'button';
        toggleChat.innerHTML = state.chatOpen ? ICONS.hide : ICONS.chat;
        toggleChat.setAttribute('aria-label', state.chatOpen ? 'Hide chat' : 'Show chat');
        toggleChat.addEventListener('click', function () {
            state.chatOpen = !state.chatOpen;
            saveState();
            els.chat.hidden = !state.chatOpen;
            stage.classList.toggle('solo', !state.chatOpen);
            toggleChat.innerHTML = state.chatOpen ? ICONS.hide : ICONS.chat;
            toggleChat.setAttribute('aria-label', state.chatOpen ? 'Hide chat' : 'Show chat');
        });

        var closeBtn = el('button', 'icon-btn');
        closeBtn.type = 'button';
        closeBtn.setAttribute('aria-label', 'Close');
        closeBtn.innerHTML = ICONS.close;
        closeBtn.addEventListener('click', closePanel);

        if (config.voice_on) {
            tools.appendChild(mute);
        }

        tools.appendChild(toggleChat);
        tools.appendChild(closeBtn);
        stage.appendChild(tools);

        var meta = el('div', 'stage-meta');
        meta.appendChild(el('b', '', config.name));
        meta.appendChild(el('span', '', 'Virtual sales assistant for ' + businessName));
        var bar = el('div', 'speaking-bar');
        bar.appendChild(document.createElement('em'));
        meta.appendChild(bar);
        stage.appendChild(meta);

        var chat = el('aside', 'chat');
        chat.hidden = !state.chatOpen;

        var chatHead = el('div', 'chat-head');
        chatHead.appendChild(el('b', '', 'Chat'));
        chat.appendChild(chatHead);

        var log = el('div', 'log');
        log.setAttribute('role', 'log');
        log.setAttribute('aria-live', 'polite');
        chat.appendChild(log);

        var foot = el('div', 'foot');
        chat.appendChild(foot);

        panel.appendChild(stage);
        panel.appendChild(chat);
        panel.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closePanel();
            }
        });

        els.panel = panel;
        els.stage = stage;
        els.chat = chat;
        els.log = log;
        els.foot = foot;
        els.wrap.appendChild(panel);

        if (state.history.length) {
            state.history.forEach(function (turn) {
                addMessage(turn.role === 'user' ? 'me' : 'bot', turn.content);
            });
        } else {
            addMessage('bot', config.greeting);
            speakText(config.greeting);
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
            els.stage = null;
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

        if (!state.chatOpen) {
            state.chatOpen = true;
            saveState();
            els.chat.hidden = false;
            els.stage.classList.remove('solo');
        }

        var history = state.history.slice(-MAX_TURNS);
        state.history.push({ role: 'user', content: text });
        saveState();

        var typing = showTyping();

        post('/chat', { message: text, history: history }).then(function (data) {
            typing.remove();
            reply(data.reply, !!data.ask_for_contact, data);
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

    function reply(text, offerForm, audioPayload) {
        if (!els.log) {
            return;
        }

        state.history.push({ role: 'assistant', content: text });
        saveState();
        addMessage('bot', text);
        speakText(text, audioPayload);

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
        if (!state.chatOpen) {
            state.chatOpen = true;
            saveState();
            els.chat.hidden = false;
            els.stage.classList.remove('solo');
        }

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
                speakText(thanks);
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

    function start() {
        request('/config').then(function (data) {
            if (!data.presenter || !data.presenter.image_url) {
                return;
            }

            config = data;
            businessName = (data.business || '').replace(/[.\s]+$/, '');
            build();
        }).catch(function () { /* assistant unavailable */ });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
