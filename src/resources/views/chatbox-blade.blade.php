{{-- ── Stylesheet (shared with Vue driver) ── --}}
<link rel="stylesheet" href="{{ asset('vendor/ai-chatbox/css/chatbox.css') }}?v={{ $aiChatboxVersion }}">

@if(config('ai-chatbox.markdown', true))
<script src="https://cdn.jsdelivr.net/npm/marked@13/marked.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/dompurify@3/dist/purify.min.js"></script>
@endif

{{-- ── Widget HTML ── --}}
<div id="ai-chatbox-wrapper" class="ai-chatbox--{{ config('ai-chatbox.position', 'bottom-right') }}">

    <div id="ai-chatbox-offline-toast" role="alert" aria-live="assertive"></div>

    <button id="ai-chatbox-toggle" aria-label="Toggle chat">
        <svg id="aicb-icon-open" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
            <path d="M20 2H4a2 2 0 0 0-2 2v18l4-4h14a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2z"/>
        </svg>
        <svg id="aicb-icon-close" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="display:none">
            <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
        </svg>
        <svg id="aicb-icon-loading" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="display:none">
            <circle cx="12" cy="12" r="10" stroke-opacity=".25"/>
            <path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round">
                <animateTransform attributeName="transform" type="rotate" from="0 12 12" to="360 12 12" dur="0.8s" repeatCount="indefinite"/>
            </path>
        </svg>
    </button>

    <div id="ai-chatbox-window">
        <div id="ai-chatbox-header">
            <span id="aicb-title"></span>
            <div class="ai-chatbox-header-actions">
                <button id="aicb-new" class="ai-chatbox-header-btn" title="New conversation" aria-label="New conversation">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>
                    </svg>
                </button>
                <button id="aicb-clear" class="ai-chatbox-header-btn" title="Clear conversation" aria-label="Clear conversation">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>
                    </svg>
                </button>
            </div>
        </div>

        <div id="ai-chatbox-messages"></div>

        <form id="ai-chatbox-form" autocomplete="off">
            <input type="text" id="ai-chatbox-input" maxlength="2000" required>
            <button type="submit" id="ai-chatbox-send" aria-label="Send">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                </svg>
            </button>
        </form>
    </div>
</div>

<script>
(function () {
    'use strict';
    var cfg          = window.AiChatboxConfig || {};
    var url          = cfg.url;
    var streamUrl    = cfg.streamUrl;
    var clearUrl     = cfg.clearUrl;
    var healthUrl    = cfg.healthUrl;
    var title        = cfg.title        || 'AI Assistant';
    var placeholder  = cfg.placeholder  || 'Type your message...';
    var greeting     = cfg.greeting     || '';
    var markdownOn   = cfg.markdown     !== false;
    var soundOn      = cfg.sound        !== false;
    var soundVolume  = typeof cfg.soundVolume === 'number' ? cfg.soundVolume : 0.4;
    var healthCheck  = cfg.healthCheck  !== false;
    var offlineMsg   = cfg.offlineMessage || 'AI service is currently unreachable.';
    var streamOn     = cfg.stream !== false && !!streamUrl && typeof ReadableStream !== 'undefined';
    var STORAGE_KEY  = cfg.storageKey   || 'ai_chatbox_ui';
    var THREAD_KEY   = STORAGE_KEY + '_tid';
    var storage      = cfg.storageType === 'session' ? sessionStorage : localStorage;

    // ── DOM refs ──
    var wrap        = document.getElementById('ai-chatbox-wrapper');
    var win         = document.getElementById('ai-chatbox-window');
    var msgs        = document.getElementById('ai-chatbox-messages');
    var input       = document.getElementById('ai-chatbox-input');
    var toast       = document.getElementById('ai-chatbox-offline-toast');
    var toggleBtn   = document.getElementById('ai-chatbox-toggle');
    var sendBtn     = document.getElementById('ai-chatbox-send');
    var form        = document.getElementById('ai-chatbox-form');
    var iconOpen    = document.getElementById('aicb-icon-open');
    var iconClose   = document.getElementById('aicb-icon-close');
    var iconLoading = document.getElementById('aicb-icon-loading');
    var titleEl     = document.getElementById('aicb-title');
    var newBtn      = document.getElementById('aicb-new');
    var clearBtn    = document.getElementById('aicb-clear');

    // ── State ──
    var isOpen        = false;
    var isChecking    = false;
    var isLoading     = false;
    var isTyping      = false;
    var greetingShown = false;
    var messages      = [];
    var threadId      = '';
    var toastTimer    = null;
    var audioCtx      = null;
    var AudioCtx      = window.AudioContext || window.webkitAudioContext;

    // ── Init ──
    titleEl.textContent = title;
    input.placeholder   = placeholder;
    if (cfg.themeColor) {
        document.documentElement.style.setProperty('--chatbox-color', cfg.themeColor);
    }
    threadId = loadOrCreateThreadId();
    loadFromStorage();
    renderMessages();
    updateUI();

    // ── UUID ──
    function generateUUID() {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
            var r = Math.random() * 16 | 0;
            return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
        });
    }
    function loadOrCreateThreadId() {
        try {
            var stored = storage.getItem(THREAD_KEY);
            if (stored) return stored;
        } catch (_) {}
        var id = generateUUID();
        try { storage.setItem(THREAD_KEY, id); } catch (_) {}
        return id;
    }

    // ── Storage ──
    function saveToStorage() {
        try {
            var persisted = messages.filter(function (m) { return m.role !== 'error'; });
            storage.setItem(STORAGE_KEY, JSON.stringify(persisted));
        } catch (_) {}
    }
    function loadFromStorage() {
        try {
            var stored = JSON.parse(storage.getItem(STORAGE_KEY) || 'null');
            if (!Array.isArray(stored) || stored.length === 0) return;
            messages = stored;
            if (greeting && stored[0] && stored[0].role === 'ai' && stored[0].text === greeting) {
                greetingShown = true;
            }
        } catch (_) {}
    }

    // ── CSRF ──
    function getCsrfHeaders() {
        var cookieMatch = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
        if (cookieMatch) {
            return { 'X-XSRF-TOKEN': decodeURIComponent(cookieMatch[1]) };
        }
        var meta = document.querySelector('meta[name="csrf-token"]');
        return { 'X-CSRF-TOKEN': (meta && meta.getAttribute('content')) || cfg.token };
    }

    // ── Escape HTML ──
    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // ── Render messages ──
    function renderMessages() {
        var html = messages.map(function (msg) {
            if (msg.role === 'ai' && markdownOn && !msg.streaming
                    && typeof window.marked !== 'undefined'
                    && typeof window.DOMPurify !== 'undefined') {
                return '<div class="ai-chatbox-msg ai ai-chatbox-markdown">'
                    + window.DOMPurify.sanitize(window.marked.parse(msg.text))
                    + '</div>';
            }
            var cls = 'ai-chatbox-msg ' + msg.role + (msg.streaming ? ' ai-chatbox-streaming' : '');
            return '<div class="' + cls + '">' + escapeHtml(msg.text) + '</div>';
        }).join('');

        if (isTyping) {
            html += '<div class="ai-chatbox-typing"><span></span><span></span><span></span></div>';
        }
        msgs.innerHTML = html;
        scrollToBottom();
    }

    // ── Streaming bubble ──
    function getOrCreateStreamBubble() {
        var el = msgs.querySelector('.ai-chatbox-streaming');
        if (!el) {
            el = document.createElement('div');
            el.className = 'ai-chatbox-msg ai ai-chatbox-streaming';
            msgs.appendChild(el);
        }
        return el;
    }

    // ── Scroll ──
    function scrollToBottom() {
        msgs.scrollTop = msgs.scrollHeight;
    }

    // ── UI state sync ──
    function updateUI() {
        win.classList.toggle('open', isOpen);
        iconOpen.style.display    = (!isOpen && !isChecking) ? '' : 'none';
        iconClose.style.display   = (isOpen && !isChecking)  ? '' : 'none';
        iconLoading.style.display = isChecking               ? '' : 'none';
        toggleBtn.disabled = isChecking;
        input.disabled     = isLoading;
        sendBtn.disabled   = isLoading;
    }

    // ── Toast ──
    function showToast(msg) {
        toast.textContent = msg;
        toast.classList.add('visible');
        if (toastTimer) clearTimeout(toastTimer);
        toastTimer = setTimeout(function () { toast.classList.remove('visible'); }, 4000);
    }

    // ── Sound ──
    function ping() {
        if (!soundOn || !AudioCtx) return;
        try {
            if (!audioCtx) audioCtx = new AudioCtx();
            var ctx  = audioCtx;
            var gain = ctx.createGain();
            gain.connect(ctx.destination);
            gain.gain.setValueAtTime(soundVolume, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.4);
            var osc = ctx.createOscillator();
            osc.connect(gain);
            osc.type = 'sine';
            osc.frequency.setValueAtTime(880, ctx.currentTime);
            osc.frequency.exponentialRampToValueAtTime(660, ctx.currentTime + 0.15);
            osc.start(ctx.currentTime);
            osc.stop(ctx.currentTime + 0.4);
        } catch (_) {}
    }

    // ── HTTP helpers ──
    function postJson(endpoint, payload) {
        var headers = Object.assign(
            { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            getCsrfHeaders()
        );
        return fetch(endpoint, {
            method: 'POST',
            headers: headers,
            body: JSON.stringify(payload)
        }).then(function (r) {
            if (!r.ok) return r.json().then(function (d) { return Promise.reject(d); });
            return r.json();
        });
    }
    function getJson(endpoint) {
        var headers = Object.assign({ 'Accept': 'application/json' }, getCsrfHeaders());
        return fetch(endpoint, { headers: headers }).then(function (r) {
            if (!r.ok) return r.json().then(function (d) { return Promise.reject(d); });
            return r.json();
        });
    }

    // ── Open / close ──
    function openWindow() {
        isOpen = true;
        if (!greetingShown && greeting) {
            messages.push({ role: 'ai', text: greeting });
            greetingShown = true;
            saveToStorage();
        }
        updateUI();
        renderMessages();
        setTimeout(function () { input.focus(); }, 50);
    }

    function toggleChat() {
        if (isOpen) {
            isOpen = false;
            updateUI();
            return;
        }
        if (!healthCheck) { openWindow(); return; }
        if (isChecking) return;
        isChecking = true;
        updateUI();
        getJson(healthUrl).then(function () {
            isChecking = false;
            openWindow();
        }).catch(function (err) {
            isChecking = false;
            updateUI();
            showToast((err && err.message) ? err.message : offlineMsg);
        });
    }

    // ── Clear ──
    function clearConversation() {
        postJson(clearUrl, { thread_id: threadId }).catch(function () {});
        messages = [];
        try { storage.removeItem(STORAGE_KEY); } catch (_) {}
        greetingShown = false;
        if (greeting) {
            messages.push({ role: 'ai', text: greeting });
            greetingShown = true;
            saveToStorage();
        }
        renderMessages();
    }

    // ── New conversation ──
    function newConversation() {
        threadId = generateUUID();
        try { storage.setItem(THREAD_KEY, threadId); } catch (_) {}
        messages = [];
        try { storage.removeItem(STORAGE_KEY); } catch (_) {}
        greetingShown = false;
        if (greeting) {
            messages.push({ role: 'ai', text: greeting });
            greetingShown = true;
            saveToStorage();
        }
        renderMessages();
    }

    // ── Send ──
    function sendMessage(text) {
        messages.push({ role: 'user', text: text });
        saveToStorage();
        isLoading = true;
        isTyping  = true;
        updateUI();
        renderMessages();

        if (streamOn) {
            sendStreaming(text);
        } else {
            sendFallback(text);
        }
    }

    // ── Streaming ──
    function sendStreaming(text) {
        isTyping = false;
        // Add a streaming bubble to the messages array so it persists correctly
        messages.push({ role: 'ai', text: '', streaming: true });
        var idx = messages.length - 1;
        renderMessages();

        var headers = Object.assign(
            { 'Content-Type': 'application/json', 'Accept': 'text/event-stream' },
            getCsrfHeaders()
        );

        fetch(streamUrl, {
            method: 'POST',
            headers: headers,
            body: JSON.stringify({ message: text, thread_id: threadId })
        }).then(function (response) {
            if (!response.ok) {
                return response.json().catch(function () { return {}; }).then(function (d) {
                    messages[idx] = { role: 'error', text: (d && d.error) || 'Something went wrong. Please try again.' };
                    renderMessages();
                    isLoading = false;
                    updateUI();
                });
            }

            var reader  = response.body.getReader();
            var decoder = new TextDecoder();
            var buffer  = '';
            var done    = false;

            function read() {
                if (done) return;
                reader.read().then(function (result) {
                    if (result.done) {
                        finalise();
                        return;
                    }
                    buffer += decoder.decode(result.value, { stream: true });
                    var parts = buffer.split('\n\n');
                    buffer = parts.pop();

                    for (var i = 0; i < parts.length; i++) {
                        var part = parts[i];
                        var dataLine = part.split('\n').find(function (l) { return l.indexOf('data: ') === 0; });
                        if (!dataLine) continue;
                        var raw = dataLine.slice(6);
                        if (raw === '[DONE]') { done = true; break; }
                        try {
                            var json = JSON.parse(raw);
                            if (json.token) {
                                messages[idx].text += json.token;
                                // Update streaming bubble directly for performance
                                var bubble = msgs.querySelector('.ai-chatbox-streaming');
                                if (bubble) {
                                    bubble.textContent = messages[idx].text;
                                    scrollToBottom();
                                }
                            }
                        } catch (_) {}
                    }

                    if (done) { finalise(); } else { read(); }
                }).catch(function () { finalise(); });
            }

            function finalise() {
                var finalText = messages[idx].text;
                messages[idx].streaming = false;
                if (finalText) {
                    saveToStorage();
                    ping();
                } else {
                    messages[idx] = { role: 'error', text: 'No response received. Please try again.' };
                }
                isLoading = false;
                updateUI();
                renderMessages();
            }

            read();

        }).catch(function () {
            messages[idx] = { role: 'error', text: 'Network error. Please check your connection.' };
            isLoading = false;
            updateUI();
            renderMessages();
        });
    }

    // ── Fallback POST ──
    function sendFallback(text) {
        postJson(url, { message: text, thread_id: threadId }).then(function (data) {
            isTyping = false;
            messages.push({ role: 'ai', text: data.reply || 'No response.' });
            saveToStorage();
            ping();
            isLoading = false;
            updateUI();
            renderMessages();
        }).catch(function (err) {
            isTyping = false;
            var errorMsg = 'Something went wrong. Please try again.';
            if (err && err.error) errorMsg = err.error;
            messages.push({ role: 'error', text: errorMsg });
            isLoading = false;
            updateUI();
            renderMessages();
        });
    }

    // ── Event listeners ──
    toggleBtn.addEventListener('click', toggleChat);
    newBtn.addEventListener('click', newConversation);
    clearBtn.addEventListener('click', clearConversation);
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var text = input.value.trim();
        if (!text || isLoading) return;
        input.value = '';
        sendMessage(text);
    });

})();
</script>
