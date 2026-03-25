(function () {
    'use strict';

    /* ── Wait for DOM ── */
    document.addEventListener('DOMContentLoaded', function () {

        var cfg      = window.AiChatboxConfig || {};
        var url      = cfg.url;
        var token    = cfg.token;

        var toggle   = document.getElementById('ai-chatbox-toggle');
        var window_  = document.getElementById('ai-chatbox-window');
        var messages = document.getElementById('ai-chatbox-messages');
        var form     = document.getElementById('ai-chatbox-form');
        var input    = document.getElementById('ai-chatbox-input');
        var sendBtn  = document.getElementById('ai-chatbox-send');
        var iconOpen = document.getElementById('ai-chatbox-icon-open');
        var iconClose= document.getElementById('ai-chatbox-icon-close');

        if (!toggle || !window_ || !form || !input) return;

        /* ── Toggle open/close ── */
        toggle.addEventListener('click', function () {
            var isOpen = window_.classList.toggle('open');
            iconOpen.style.display  = isOpen ? 'none'  : '';
            iconClose.style.display = isOpen ? ''      : 'none';
            if (isOpen) {
                input.focus();
                scrollToBottom();
            }
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

            /* Use jQuery if available, otherwise plain fetch */
            if (typeof jQuery !== 'undefined') {
                jQuery.ajax({
                    url: url,
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': token },
                    contentType: 'application/json',
                    data: JSON.stringify({ message: text }),
                    success: function (data) {
                        removeTyping(typing);
                        appendMessage('ai', data.reply || 'No response.');
                        setLoading(false);
                    },
                    error: function (xhr) {
                        removeTyping(typing);
                        var msg = 'Something went wrong. Please try again.';
                        try {
                            var body = JSON.parse(xhr.responseText);
                            if (body.error) msg = body.error;
                        } catch (_) {}
                        appendMessage('error', msg);
                        setLoading(false);
                    }
                });
            } else {
                fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type':  'application/json',
                        'Accept':        'application/json',
                        'X-CSRF-TOKEN':  token,
                    },
                    body: JSON.stringify({ message: text }),
                })
                .then(function (res) {
                    return res.json().then(function (data) {
                        return { ok: res.ok, data: data };
                    });
                })
                .then(function (result) {
                    removeTyping(typing);
                    if (result.ok) {
                        appendMessage('ai', result.data.reply || 'No response.');
                    } else {
                        appendMessage('error', result.data.error || 'Something went wrong.');
                    }
                    setLoading(false);
                })
                .catch(function () {
                    removeTyping(typing);
                    appendMessage('error', 'Network error. Please check your connection.');
                    setLoading(false);
                });
            }
        });

        /* ── Helpers ── */

        function appendMessage(role, text) {
            var div = document.createElement('div');
            div.className = 'ai-chatbox-msg ' + role;
            div.textContent = text;
            messages.appendChild(div);
            scrollToBottom();
            return div;
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
            input.disabled   = state;
        }

        function scrollToBottom() {
            messages.scrollTop = messages.scrollHeight;
        }
    });

})();
