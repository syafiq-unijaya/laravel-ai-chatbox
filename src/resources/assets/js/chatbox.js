(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {

        var cfg = window.AiChatboxConfig || {};
        var url = cfg.url;
        var clearUrl = cfg.clearUrl;
        var healthUrl = cfg.healthUrl;
        var token = cfg.token;
        var greeting = cfg.greeting || '';
        var avatar = cfg.avatar || '';
        var markdown = cfg.markdown && typeof marked !== 'undefined' && typeof DOMPurify !== 'undefined';
        var soundOn = cfg.sound !== false;
        var soundVolume = typeof cfg.soundVolume === 'number' ? cfg.soundVolume : 0.4;
        var healthCheck = cfg.healthCheck !== false;

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
                    headers: { 'X-CSRF-TOKEN': token },
                    contentType: 'application/json',
                    data: JSON.stringify(payload),
                    success: onSuccess,
                    error: function (xhr) {
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
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': token,
                    },
                    body: JSON.stringify(payload),
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
                            onError(result.data.error || 'Something went wrong.');
                        }
                    })
                    .catch(function () {
                        onError('Network error. Please check your connection.');
                    });
            }
        }

        /* ── Helpers ── */

        function appendMessage(role, text) {
            var bubble = document.createElement('div');
            bubble.className = 'ai-chatbox-msg ' + role;
            bubble.textContent = text;

            // Wrap AI (and error) messages in a row with the avatar
            if (role === 'ai' || role === 'error') {
                // Render markdown for AI replies only (not errors)
                if (role === 'ai' && markdown) {
                    bubble.innerHTML = DOMPurify.sanitize(marked.parse(text));
                    bubble.classList.add('ai-chatbox-markdown');
                }

                var row = document.createElement('div');
                row.className = 'ai-chatbox-row';

                var avatarEl;
                if (avatar) {
                    avatarEl = document.createElement('img');
                    avatarEl.src = avatar;
                    avatarEl.alt = 'Bot';
                    avatarEl.className = 'ai-chatbox-bubble-avatar';
                } else {
                    avatarEl = document.createElement('div');
                    avatarEl.className = 'ai-chatbox-bubble-avatar-icon';
                    avatarEl.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 14H9V8h2v8zm4 0h-2V8h2v8z"/></svg>';
                }

                row.appendChild(avatarEl);
                row.appendChild(bubble);
                messages.appendChild(row);
                scrollToBottom();
                return row;
            }

            messages.appendChild(bubble);
            scrollToBottom();
            return bubble;
        }

        function appendTyping() {
            var row = document.createElement('div');
            row.className = 'ai-chatbox-row';

            var avatarEl;
            if (avatar) {
                avatarEl = document.createElement('img');
                avatarEl.src = avatar;
                avatarEl.alt = 'Bot';
                avatarEl.className = 'ai-chatbox-bubble-avatar';
            } else {
                avatarEl = document.createElement('div');
                avatarEl.className = 'ai-chatbox-bubble-avatar-icon';
                avatarEl.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 14H9V8h2v8zm4 0h-2V8h2v8z"/></svg>';
            }

            var dot = document.createElement('div');
            dot.className = 'ai-chatbox-typing';
            dot.innerHTML = '<span></span><span></span><span></span>';

            row.appendChild(avatarEl);
            row.appendChild(dot);
            messages.appendChild(row);
            scrollToBottom();
            return row;
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
                    headers: { 'X-CSRF-TOKEN': token },
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
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
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
