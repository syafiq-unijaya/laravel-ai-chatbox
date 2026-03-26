(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {

        var cfg = window.AiChatboxConfig || {};
        var url = cfg.url;
        var clearUrl = cfg.clearUrl;
        var healthUrl = cfg.healthUrl;
        var token = cfg.token;
        var greeting = cfg.greeting || '';
        var markdown = cfg.markdown && typeof marked !== 'undefined' && typeof DOMPurify !== 'undefined';
        var soundOn = cfg.sound !== false;
        var soundVolume = typeof cfg.soundVolume === 'number' ? cfg.soundVolume : 0.4;
        var healthCheck = cfg.healthCheck !== false;

        var STORAGE_KEY    = cfg.storageKey || 'ai_chatbox_ui';
        var storageDriver  = cfg.storageType === 'session' ? sessionStorage : localStorage;

        var toggle = document.getElementById('ai-chatbox-toggle');
        var window_ = document.getElementById('ai-chatbox-window');
        var messages = document.getElementById('ai-chatbox-messages');
        var form = document.getElementById('ai-chatbox-form');
        var input = document.getElementById('ai-chatbox-input');
        var sendBtn = document.getElementById('ai-chatbox-send');
        var clearBtn = document.getElementById('ai-chatbox-clear');
        var iconOpen = document.getElementById('ai-chatbox-icon-open');
        var iconClose = document.getElementById('ai-chatbox-icon-close');
        var iconLoading = document.getElementById('ai-chatbox-icon-loading');
        var offlineToast = document.getElementById('ai-chatbox-offline-toast');

        if (!toggle || !window_ || !form || !input) return;

        var greetingShown = false;
        var isChecking = false;
        var msgHistory = [];

        /* ── Always return the freshest CSRF headers ──
         * Priority:
         *  1. XSRF-TOKEN cookie — Laravel refreshes this on every response,
         *     so it survives login/logout/session regeneration. Must be sent
         *     as X-XSRF-TOKEN (Laravel decrypts it server-side).
         *  2. <meta name="csrf-token"> — standard Laravel layout tag.
         *  3. Token baked into AiChatboxConfig at page load (last resort).
         */
        function getCsrfHeaders() {
            var cookieMatch = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
            if (cookieMatch) {
                return { 'X-XSRF-TOKEN': decodeURIComponent(cookieMatch[1]) };
            }
            var meta = document.querySelector('meta[name="csrf-token"]');
            return { 'X-CSRF-TOKEN': (meta && meta.getAttribute('content')) || token };
        }

        /* ── localStorage helpers ── */

        function saveToStorage() {
            try {
                storageDriver.setItem(STORAGE_KEY, JSON.stringify(msgHistory));
            } catch (_) { }
        }

        function loadFromStorage() {
            try {
                var stored = JSON.parse(storageDriver.getItem(STORAGE_KEY) || 'null');
                if (!Array.isArray(stored) || stored.length === 0) return;
                stored.forEach(function (item) {
                    renderBubble(item.role, item.text);
                });
                msgHistory = stored;
                // If the first stored message is the greeting, don't show it again
                if (greeting && stored[0] && stored[0].role === 'ai' && stored[0].text === greeting) {
                    greetingShown = true;
                }
            } catch (_) { }
        }

        // Restore persisted messages before user opens the window
        loadFromStorage();

        function openWindow() {
            window_.classList.add('open');
            iconOpen.style.display = 'none';
            iconClose.style.display = '';
            if (!greetingShown && greeting) {
                appendMessage('ai', greeting);
                greetingShown = true;
            }
            input.focus();
            scrollToBottom();
        }

        /* ── Toggle open/close ── */
        toggle.addEventListener('click', function () {

            // If already open, close immediately — no health check needed
            if (window_.classList.contains('open')) {
                window_.classList.remove('open');
                iconOpen.style.display = '';
                iconClose.style.display = 'none';
                return;
            }

            // Skip health check — open immediately
            if (!healthCheck) {
                openWindow();
                return;
            }

            // Prevent double-clicking while checking
            if (isChecking) return;

            isChecking = true;
            setToggleLoading(true);

            get(healthUrl, function () {
                isChecking = false;
                setToggleLoading(false);
                openWindow();
            }, function (msg) {
                isChecking = false;
                setToggleLoading(false);
                showOfflineToast(msg || 'AI service is currently unreachable.');
            });
        });

        /* ── Clear conversation ── */
        clearBtn.addEventListener('click', function () {
            post(clearUrl, {}, function () {
                messages.innerHTML = '';
                msgHistory = [];
                try { storageDriver.removeItem(STORAGE_KEY); } catch (_) { }
                greetingShown = false;
                if (greeting) {
                    appendMessage('ai', greeting);
                    greetingShown = true;
                }
            }, function () {
                // silently ignore clear errors
            });
        });

        /* ── Submit handler ── */
        form.addEventListener('submit', function (e) {
            e.preventDefault();

            var text = input.value.trim();
            if (!text) return;

            appendMessage('user', text);
            input.value = '';
            setLoading(true);

            var typing = appendTyping();

            post(url, { message: text }, function (data) {
                removeTyping(typing);
                appendMessage('ai', data.reply || 'No response.');
                ping();
                setLoading(false);
            }, function (errorMsg) {
                removeTyping(typing);
                appendMessage('error', errorMsg);
                setLoading(false);
            });
        });

        /* ── Shared POST helper (jQuery or fetch) ── */
        function post(endpoint, payload, onSuccess, onError) {
            if (typeof jQuery !== 'undefined') {
                jQuery.ajax({
                    url: endpoint,
                    method: 'POST',
                    headers: getCsrfHeaders(),
                    contentType: 'application/json',
                    data: JSON.stringify(payload),
                    success: onSuccess,
                    error: function (xhr) {
                        if (xhr.status === 419) {
                            onError('Your session has expired. Please refresh the page.');
                            return;
                        }
                        var msg = 'Something went wrong. Please try again.';
                        try {
                            var body = JSON.parse(xhr.responseText);
                            if (body.error) msg = body.error;
                        } catch (_) { }
                        onError(msg);
                    }
                });
            } else {
                fetch(endpoint, {
                    method: 'POST',
                    headers: Object.assign({ 'Content-Type': 'application/json', 'Accept': 'application/json' }, getCsrfHeaders()),
                    body: JSON.stringify(payload),
                })
                    .then(function (res) {
                        return res.json().then(function (data) {
                            return { ok: res.ok, status: res.status, data: data };
                        });
                    })
                    .then(function (result) {
                        if (result.ok) {
                            onSuccess(result.data);
                        } else if (result.status === 419) {
                            onError('Your session has expired. Please refresh the page.');
                        } else {
                            onError(result.data.error || 'Something went wrong.');
                        }
                    })
                    .catch(function () {
                        onError('Network error. Please check your connection.');
                    });
            }
        }

        /* ── Helpers ── */

        // Renders a bubble in the DOM only (used for both new messages and restoring from storage)
        function renderBubble(role, text) {
            var bubble = document.createElement('div');
            bubble.className = 'ai-chatbox-msg ' + role;
            bubble.textContent = text;

            // Render markdown for AI replies only (not errors)
            if (role === 'ai' && markdown) {
                bubble.innerHTML = DOMPurify.sanitize(marked.parse(text));
                bubble.classList.add('ai-chatbox-markdown');
            }

            messages.appendChild(bubble);
            scrollToBottom();
            return bubble;
        }

        // Renders a bubble AND persists it to history (errors are not persisted)
        function appendMessage(role, text) {
            var bubble = renderBubble(role, text);
            if (role !== 'error') {
                msgHistory.push({ role: role, text: text });
                saveToStorage();
            }
            return bubble;
        }

        function appendTyping() {
            var div = document.createElement('div');
            div.className = 'ai-chatbox-typing';
            div.innerHTML = '<span></span><span></span><span></span>';
            messages.appendChild(div);
            scrollToBottom();
            return div;
        }

        function removeTyping(el) {
            if (el && el.parentNode) el.parentNode.removeChild(el);
        }

        function setLoading(state) {
            sendBtn.disabled = state;
            input.disabled = state;
        }

        function setToggleLoading(state) {
            toggle.disabled = state;
            iconOpen.style.display = state ? 'none' : '';
            iconClose.style.display = 'none';
            iconLoading.style.display = state ? '' : 'none';
        }

        var toastTimer = null;
        function showOfflineToast(msg) {
            offlineToast.textContent = msg;
            offlineToast.classList.add('visible');
            if (toastTimer) clearTimeout(toastTimer);
            toastTimer = setTimeout(function () {
                offlineToast.classList.remove('visible');
            }, 4000);
        }

        /* ── GET helper (health check) ── */
        function get(endpoint, onSuccess, onError) {
            if (typeof jQuery !== 'undefined') {
                jQuery.ajax({
                    url: endpoint,
                    method: 'GET',
                    headers: getCsrfHeaders(),
                    success: onSuccess,
                    error: function (xhr) {
                        var msg = 'AI service is currently unreachable.';
                        try {
                            var body = JSON.parse(xhr.responseText);
                            if (body.message) msg = body.message;
                        } catch (_) { }
                        onError(msg);
                    }
                });
            } else {
                fetch(endpoint, {
                    method: 'GET',
                    headers: Object.assign({ 'Accept': 'application/json' }, getCsrfHeaders()),
                })
                    .then(function (res) {
                        return res.json().then(function (data) {
                            return { ok: res.ok, data: data };
                        });
                    })
                    .then(function (result) {
                        if (result.ok) {
                            onSuccess(result.data);
                        } else {
                            onError(result.data.message || 'AI service is currently unreachable.');
                        }
                    })
                    .catch(function () {
                        onError('Could not reach the server. Check your connection.');
                    });
            }
        }

        function scrollToBottom() {
            messages.scrollTop = messages.scrollHeight;
        }

        function ping() {
            if (!soundOn) return;
            try {
                var w = /** @type {any} */ (window);
                var AudioCtx = w.AudioContext || w.webkitAudioContext;
                if (!AudioCtx) return;
                var ctx = new AudioCtx();
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
                osc.onended = function () { ctx.close(); };
            } catch (_) { /* Web Audio not supported — silently skip */ }
        }
    });

})();
