<template>
    <div id="ai-chatbox-wrapper" :class="`ai-chatbox--${position}`">

        <!-- Offline toast -->
        <div id="ai-chatbox-offline-toast" role="alert" aria-live="assertive" :class="{ visible: toastVisible }">
            {{ toastMessage }}
        </div>

        <!-- Floating toggle button -->
        <button id="ai-chatbox-toggle" :disabled="isChecking" aria-label="Toggle chat" :title="title" @click="toggleChat">
            <svg v-if="!isOpen && !isChecking" id="ai-chatbox-icon-open" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                <path d="M20 2H4a2 2 0 0 0-2 2v18l4-4h14a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2z"/>
            </svg>
            <svg v-if="isOpen && !isChecking" id="ai-chatbox-icon-close" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
            </svg>
            <svg v-if="isChecking" id="ai-chatbox-icon-loading" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <circle cx="12" cy="12" r="10" stroke-opacity=".25"/>
                <path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round">
                    <animateTransform attributeName="transform" type="rotate" from="0 12 12" to="360 12 12" dur="0.8s" repeatCount="indefinite"/>
                </path>
            </svg>
        </button>

        <!-- Chat window -->
        <div id="ai-chatbox-window" aria-live="polite" :class="{ open: isOpen }">

            <div id="ai-chatbox-header">
                <span>{{ title }}</span>
                <div class="ai-chatbox-header-actions">
                    <button class="ai-chatbox-header-btn" title="New conversation" aria-label="New conversation" @click="newConversation">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>
                        </svg>
                    </button>
                    <button id="ai-chatbox-clear" class="ai-chatbox-header-btn" title="Clear conversation" aria-label="Clear conversation" @click="clearConversation">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>
                        </svg>
                    </button>
                </div>
            </div>

            <div id="ai-chatbox-messages" ref="messagesRef">
                <template v-for="(msg, i) in messages" :key="i">
                    <div v-if="msg.role === 'ai' && markdown && !msg.streaming"
                         class="ai-chatbox-msg ai ai-chatbox-markdown"
                         v-html="renderMarkdown(msg.text)">
                    </div>
                    <div v-else :class="['ai-chatbox-msg', msg.role, { 'ai-chatbox-streaming': msg.streaming }]">{{ msg.text }}</div>
                </template>
                <div v-if="isTyping" class="ai-chatbox-typing">
                    <span></span><span></span><span></span>
                </div>
            </div>

            <form id="ai-chatbox-form" autocomplete="off" @submit.prevent="sendMessage">
                <input
                    type="text"
                    id="ai-chatbox-input"
                    ref="inputRef"
                    v-model="inputText"
                    :placeholder="placeholder"
                    maxlength="2000"
                    :disabled="isLoading"
                    required
                >
                <button type="submit" id="ai-chatbox-send" aria-label="Send" :disabled="isLoading">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                    </svg>
                </button>
            </form>

        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, nextTick } from 'vue'
import { marked } from 'marked'
import DOMPurify from 'dompurify'
import axios from 'axios'

// ── Config ──
const cfg = window.AiChatboxConfig || {}
const url            = cfg.url
const streamUrl      = cfg.streamUrl
const stream         = cfg.stream !== false && !!streamUrl && typeof ReadableStream !== 'undefined'
const clearUrl       = cfg.clearUrl
const healthUrl      = cfg.healthUrl
const title          = cfg.title || 'AI Assistant'
const placeholder    = cfg.placeholder || 'Type your message...'
const greeting       = cfg.greeting || ''
const markdown       = cfg.markdown !== false
const soundOn        = cfg.sound !== false
const soundVolume    = typeof cfg.soundVolume === 'number' ? cfg.soundVolume : 0.4
const healthCheck    = cfg.healthCheck !== false
const offlineMessage = cfg.offlineMessage || 'AI service is currently unreachable.'
const position       = cfg.position || 'bottom-right'
const STORAGE_KEY    = cfg.storageKey || 'ai_chatbox_ui'
const THREAD_KEY     = STORAGE_KEY + '_tid'
const storageDriver  = cfg.storageType === 'session' ? sessionStorage : localStorage

// ── Thread ID ──
function generateUUID() {
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => {
        const r = Math.random() * 16 | 0
        const v = c === 'x' ? r : (r & 0x3 | 0x8)
        return v.toString(16)
    })
}

function loadOrCreateThreadId() {
    try {
        const stored = storageDriver.getItem(THREAD_KEY)
        if (stored) return stored
    } catch (_) {}
    const id = generateUUID()
    try { storageDriver.setItem(THREAD_KEY, id) } catch (_) {}
    return id
}

// ── State ──
const isOpen        = ref(false)
const isChecking    = ref(false)
const isLoading     = ref(false)
const isTyping      = ref(false)
const greetingShown = ref(false)
const inputText     = ref('')
const messages      = ref([])
const toastMessage  = ref('')
const toastVisible  = ref(false)
const messagesRef   = ref(null)
const inputRef      = ref(null)
const threadId      = ref('')

let toastTimer  = null
let audioCtx    = null
const AudioCtx  = window.AudioContext || window.webkitAudioContext

// ── CSRF ──
function getCsrfHeaders() {
    const cookieMatch = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/)
    if (cookieMatch) {
        return { 'X-XSRF-TOKEN': decodeURIComponent(cookieMatch[1]) }
    }
    const meta = document.querySelector('meta[name="csrf-token"]')
    return { 'X-CSRF-TOKEN': (meta && meta.getAttribute('content')) || cfg.token }
}

// ── Storage ──
function saveToStorage() {
    try {
        const persisted = messages.value.filter(m => m.role !== 'error')
        storageDriver.setItem(STORAGE_KEY, JSON.stringify(persisted))
    } catch (_) {}
}

function loadFromStorage() {
    try {
        const stored = JSON.parse(storageDriver.getItem(STORAGE_KEY) || 'null')
        if (!Array.isArray(stored) || stored.length === 0) return
        messages.value = stored
        if (greeting && stored[0] && stored[0].role === 'ai' && stored[0].text === greeting) {
            greetingShown.value = true
        }
    } catch (_) {}
}

// ── Markdown ──
function renderMarkdown(text) {
    return DOMPurify.sanitize(marked.parse(text))
}

// ── Scroll ──
async function scrollToBottom() {
    await nextTick()
    if (messagesRef.value) {
        messagesRef.value.scrollTop = messagesRef.value.scrollHeight
    }
}

// ── Toast ──
function showOfflineToast(msg) {
    toastMessage.value = msg
    toastVisible.value = true
    if (toastTimer) clearTimeout(toastTimer)
    toastTimer = setTimeout(() => { toastVisible.value = false }, 4000)
}

// ── Sound ──
function ping() {
    if (!soundOn || !AudioCtx) return
    try {
        if (!audioCtx) audioCtx = new AudioCtx()
        const ctx  = audioCtx
        const gain = ctx.createGain()
        gain.connect(ctx.destination)
        gain.gain.setValueAtTime(soundVolume, ctx.currentTime)
        gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.4)
        const osc = ctx.createOscillator()
        osc.connect(gain)
        osc.type = 'sine'
        osc.frequency.setValueAtTime(880, ctx.currentTime)
        osc.frequency.exponentialRampToValueAtTime(660, ctx.currentTime + 0.15)
        osc.start(ctx.currentTime)
        osc.stop(ctx.currentTime + 0.4)
    } catch (_) {}
}

// ── HTTP ──
async function httpPost(endpoint, payload) {
    const headers = Object.assign({ 'Content-Type': 'application/json', 'Accept': 'application/json' }, getCsrfHeaders())
    const res = await axios.post(endpoint, payload, { headers })
    return res.data
}

async function httpGet(endpoint) {
    const headers = Object.assign({ 'Accept': 'application/json' }, getCsrfHeaders())
    const res = await axios.get(endpoint, { headers })
    return res.data
}

// ── Open/Close ──
function openWindow() {
    isOpen.value = true
    if (!greetingShown.value && greeting) {
        messages.value.push({ role: 'ai', text: greeting })
        greetingShown.value = true
        saveToStorage()
    }
    nextTick(() => {
        if (inputRef.value) inputRef.value.focus()
        scrollToBottom()
    })
}

async function toggleChat() {
    if (isOpen.value) {
        isOpen.value = false
        return
    }
    if (!healthCheck) {
        openWindow()
        return
    }
    if (isChecking.value) return
    isChecking.value = true
    try {
        await httpGet(healthUrl)
        openWindow()
    } catch (err) {
        let msg = offlineMessage
        if (err.response?.data?.message) msg = err.response.data.message
        showOfflineToast(msg)
    } finally {
        isChecking.value = false
    }
}

// ── Clear (current thread) ──
async function clearConversation() {
    try {
        await httpPost(clearUrl, { thread_id: threadId.value })
    } catch (_) {}
    messages.value = []
    try { storageDriver.removeItem(STORAGE_KEY) } catch (_) {}
    greetingShown.value = false
    if (greeting) {
        messages.value.push({ role: 'ai', text: greeting })
        greetingShown.value = true
        saveToStorage()
    }
}

// ── New conversation (fresh thread) ──
function newConversation() {
    const id = generateUUID()
    threadId.value = id
    try { storageDriver.setItem(THREAD_KEY, id) } catch (_) {}
    messages.value = []
    try { storageDriver.removeItem(STORAGE_KEY) } catch (_) {}
    greetingShown.value = false
    if (greeting) {
        messages.value.push({ role: 'ai', text: greeting })
        greetingShown.value = true
        saveToStorage()
    }
}

// ── Send ──
async function sendMessage() {
    const text = inputText.value.trim()
    if (!text) return

    messages.value.push({ role: 'user', text })
    saveToStorage()
    inputText.value = ''
    isLoading.value = true
    isTyping.value  = true
    scrollToBottom()

    if (stream) {
        await sendStreaming(text)
    } else {
        await sendFallback(text)
    }
}

// ── Streaming send (SSE via fetch + ReadableStream) ──
async function sendStreaming(text) {
    // Replace typing indicator with an empty streaming bubble
    isTyping.value = false
    messages.value.push({ role: 'ai', text: '', streaming: true })
    const idx = messages.value.length - 1

    try {
        const headers = Object.assign(
            { 'Content-Type': 'application/json', 'Accept': 'text/event-stream' },
            getCsrfHeaders()
        )

        const response = await fetch(streamUrl, {
            method: 'POST',
            headers,
            body: JSON.stringify({ message: text, thread_id: threadId.value }),
        })

        if (!response.ok) {
            const errData = await response.json().catch(() => ({}))
            messages.value[idx] = { role: 'error', text: errData.error || 'Something went wrong. Please try again.' }
            return
        }

        const reader  = response.body.getReader()
        const decoder = new TextDecoder()
        let   buffer  = ''

        outer: while (true) {
            const { done, value } = await reader.read()
            if (done) break

            buffer += decoder.decode(value, { stream: true })

            // Process every complete SSE event (separated by double newline)
            const parts = buffer.split('\n\n')
            buffer = parts.pop() // keep the last incomplete chunk

            for (const part of parts) {
                const dataLine = part.split('\n').find(l => l.startsWith('data: '))
                if (!dataLine) continue

                const raw = dataLine.slice(6)
                if (raw === '[DONE]') break outer

                try {
                    const json = JSON.parse(raw)
                    if (json.token) {
                        messages.value[idx].text += json.token
                        scrollToBottom()
                    }
                } catch (_) {}
            }
        }

        const finalText = messages.value[idx].text
        messages.value[idx].streaming = false

        if (finalText) {
            saveToStorage()
            ping()
        } else {
            messages.value[idx] = { role: 'error', text: 'No response received. Please try again.' }
        }

    } catch (_) {
        messages.value[idx] = { role: 'error', text: 'Network error. Please check your connection.' }
    } finally {
        isLoading.value = false
        scrollToBottom()
    }
}

// ── Fallback send (standard POST, used when stream: false) ──
async function sendFallback(text) {
    try {
        const data = await httpPost(url, { message: text, thread_id: threadId.value })
        isTyping.value = false
        messages.value.push({ role: 'ai', text: data.reply || 'No response.' })
        saveToStorage()
        ping()
        scrollToBottom()
    } catch (err) {
        isTyping.value = false
        let errorMsg = 'Something went wrong. Please try again.'
        if (err.response?.status === 419) {
            errorMsg = 'Your session has expired. Please refresh the page.'
        } else if (err.response?.data?.error) {
            errorMsg = err.response.data.error
        } else if (!err.response) {
            errorMsg = 'Network error. Please check your connection.'
        }
        messages.value.push({ role: 'error', text: errorMsg })
        scrollToBottom()
    } finally {
        isLoading.value = false
    }
}

// ── Mount ──
onMounted(() => {
    if (cfg.themeColor) {
        document.documentElement.style.setProperty('--chatbox-color', cfg.themeColor)
    }
    threadId.value = loadOrCreateThreadId()
    loadFromStorage()
    scrollToBottom()
})
</script>

<style>
/* ── AI Chatbox Widget ─────────────────────────────────────────────────── */

:root {
  --chatbox-color: #4f46e5;
  --chatbox-radius: 14px;
  --chatbox-shadow: 0 8px 32px rgba(0, 0, 0, 0.18);
  --chatbox-width: 360px;
  --chatbox-height: 480px;
  --chatbox-z: 9999;
  --chatbox-font: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
  --chatbox-bg: #ffffff;
  --chatbox-msg-bg: #f3f4f6;
  --chatbox-text: #111827;
  --chatbox-muted: #6b7280;
  --chatbox-border: #e5e7eb;
  --chatbox-input-bg: #f9fafb;
  --chatbox-scrollbar: #d1d5db;
}

/* ── Dark mode ── */
@media (prefers-color-scheme: dark) {
  :root {
    --chatbox-shadow:    0 8px 32px rgba(0, 0, 0, 0.5);
    --chatbox-bg:        #1e1e2e;
    --chatbox-msg-bg:    #2a2a3d;
    --chatbox-text:      #e2e8f0;
    --chatbox-muted:     #94a3b8;
    --chatbox-border:    #374151;
    --chatbox-input-bg:  #2a2a3d;
    --chatbox-scrollbar: #4b5563;
  }
}

/* ── Wrapper ── */
#ai-chatbox-wrapper {
  position: fixed;
  display: flex;
  flex-direction: column;
  z-index: var(--chatbox-z);
  font-family: var(--chatbox-font);
  isolation: isolate;
}

/* ── Position variants ── */
#ai-chatbox-wrapper.ai-chatbox--bottom-right { bottom: 24px; right: 24px; }
#ai-chatbox-wrapper.ai-chatbox--bottom-left  { bottom: 24px; left: 24px; }
#ai-chatbox-wrapper.ai-chatbox--top-right    { top: 24px; right: 24px; }
#ai-chatbox-wrapper.ai-chatbox--top-left     { top: 24px; left: 24px; }

/* ── Offline toast ── */
#ai-chatbox-offline-toast {
  position: absolute;
  bottom: 68px;
  right: 0;
  background: #1f2937;
  color: #f9fafb;
  font-size: 13px;
  padding: 10px 14px;
  border-radius: 10px;
  box-shadow: var(--chatbox-shadow);
  max-width: 260px;
  white-space: normal;
  pointer-events: none;
  opacity: 0;
  transform: translateY(6px);
  transition: opacity .2s ease, transform .2s ease;
  z-index: 1;
}
#ai-chatbox-offline-toast.visible {
  opacity: 1;
  transform: translateY(0);
}
.ai-chatbox--bottom-left  #ai-chatbox-offline-toast,
.ai-chatbox--top-left     #ai-chatbox-offline-toast { right: auto; left: 0; }
.ai-chatbox--top-right    #ai-chatbox-offline-toast,
.ai-chatbox--top-left     #ai-chatbox-offline-toast { bottom: auto; top: 68px; }

/* ── Toggle button ── */
#ai-chatbox-toggle {
  width: 56px;
  height: 56px;
  border-radius: 50%;
  background: var(--chatbox-color);
  color: #fff;
  border: none;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: var(--chatbox-shadow);
  transition: transform 0.2s, box-shadow 0.2s;
  margin-left: auto;
}
.ai-chatbox--bottom-left #ai-chatbox-toggle,
.ai-chatbox--top-left    #ai-chatbox-toggle { margin-left: 0; margin-right: auto; }
#ai-chatbox-toggle:hover { transform: scale(1.08); box-shadow: 0 12px 36px rgba(0,0,0,.24); }
#ai-chatbox-toggle svg { width: 26px; height: 26px; pointer-events: none; }

/* ── Chat window ── */
#ai-chatbox-window {
  display: none;
  flex-direction: column;
  width: var(--chatbox-width);
  height: var(--chatbox-height);
  background: var(--chatbox-bg);
  border-radius: var(--chatbox-radius);
  box-shadow: var(--chatbox-shadow);
  overflow: hidden;
  margin-bottom: 12px;
  animation: chatbox-slide-up 0.22s ease;
}
.ai-chatbox--top-right #ai-chatbox-window,
.ai-chatbox--top-left  #ai-chatbox-window {
  margin-bottom: 0;
  margin-top: 12px;
  order: 1;
  animation: chatbox-slide-down 0.22s ease;
}
.ai-chatbox--top-right #ai-chatbox-toggle,
.ai-chatbox--top-left  #ai-chatbox-toggle { order: 0; }

@keyframes chatbox-slide-down {
  from { opacity: 0; transform: translateY(-20px); }
  to   { opacity: 1; transform: translateY(0); }
}
#ai-chatbox-window.open { display: flex; }
@keyframes chatbox-slide-up {
  from { opacity: 0; transform: translateY(20px); }
  to   { opacity: 1; transform: translateY(0); }
}

/* ── Header ── */
#ai-chatbox-header {
  background: var(--chatbox-color);
  color: #fff;
  padding: 10px 14px;
  font-weight: 600;
  font-size: 15px;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}
/* Header action buttons */
.ai-chatbox-header-actions { display: flex; align-items: center; gap: 2px; flex-shrink: 0; }
.ai-chatbox-header-btn {
  background: transparent;
  border: none;
  color: rgba(255,255,255,0.8);
  cursor: pointer;
  padding: 4px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 6px;
  transition: color .15s, background .15s;
}
.ai-chatbox-header-btn:hover { color: #fff; background: rgba(255,255,255,0.15); }
.ai-chatbox-header-btn svg { width: 18px; height: 18px; }

/* ── Messages area ── */
#ai-chatbox-messages {
  flex: 1;
  overflow-y: auto;
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 10px;
  scroll-behavior: smooth;
}
#ai-chatbox-messages::-webkit-scrollbar { width: 5px; }
#ai-chatbox-messages::-webkit-scrollbar-thumb { background: var(--chatbox-scrollbar); border-radius: 4px; }

/* ── Message bubbles ── */
.ai-chatbox-msg {
  max-width: 78%;
  padding: 10px 14px;
  border-radius: 12px;
  font-size: 14px;
  line-height: 1.5;
  word-break: break-word;
  white-space: pre-wrap;
}
.ai-chatbox-msg.user {
  background: var(--chatbox-color);
  color: #fff;
  border-bottom-right-radius: 4px;
  align-self: flex-end;
}
.ai-chatbox-msg.ai {
  background: var(--chatbox-msg-bg);
  color: var(--chatbox-text);
  border-bottom-left-radius: 4px;
  align-self: flex-start;
}

/* ── Streaming cursor ── */
.ai-chatbox-streaming::after {
  content: '▋';
  animation: chatbox-cursor 1s step-start infinite;
  margin-left: 1px;
  opacity: 1;
}
@keyframes chatbox-cursor {
  50% { opacity: 0; }
}

/* ── Typing indicator ── */
.ai-chatbox-typing {
  display: flex;
  gap: 5px;
  align-items: center;
  padding: 10px 14px;
  background: var(--chatbox-msg-bg);
  border-radius: 12px;
  border-bottom-left-radius: 4px;
  align-self: flex-start;
}
.ai-chatbox-typing span {
  width: 7px;
  height: 7px;
  background: var(--chatbox-muted);
  border-radius: 50%;
  animation: chatbox-bounce 0.9s infinite ease-in-out;
}
.ai-chatbox-typing span:nth-child(2) { animation-delay: 0.15s; }
.ai-chatbox-typing span:nth-child(3) { animation-delay: 0.3s; }
@keyframes chatbox-bounce {
  0%, 60%, 100% { transform: translateY(0); }
  30%           { transform: translateY(-6px); }
}

/* ── Error message ── */
.ai-chatbox-msg.error {
  background: #fee2e2;
  color: #991b1b;
  align-self: flex-start;
}
@media (prefers-color-scheme: dark) {
  .ai-chatbox-msg.error { background: #450a0a; color: #fca5a5; }
}

/* ── Form ── */
#ai-chatbox-form {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 14px;
  border-top: 1px solid var(--chatbox-border);
  flex-shrink: 0;
}
#ai-chatbox-input {
  flex: 1;
  border: 1px solid var(--chatbox-border);
  border-radius: 8px;
  padding: 9px 13px;
  font-size: 14px;
  font-family: var(--chatbox-font);
  outline: none;
  transition: border-color 0.15s, background 0.15s;
  background: var(--chatbox-input-bg);
  color: var(--chatbox-text);
}
#ai-chatbox-input:focus { border-color: var(--chatbox-color); background: var(--chatbox-bg); }
#ai-chatbox-send {
  width: 38px;
  height: 38px;
  border-radius: 8px;
  background: var(--chatbox-color);
  color: #fff;
  border: none;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  transition: opacity 0.15s;
}
#ai-chatbox-send:disabled { opacity: 0.5; cursor: not-allowed; }
#ai-chatbox-send svg { width: 18px; height: 18px; }

/* ── Markdown ── */
.ai-chatbox-markdown { white-space: normal; }
.ai-chatbox-markdown p              { margin: 0 0 8px; }
.ai-chatbox-markdown p:last-child   { margin-bottom: 0; }
.ai-chatbox-markdown ul,
.ai-chatbox-markdown ol             { margin: 0 0 8px; padding-left: 18px; }
.ai-chatbox-markdown li             { margin-bottom: 2px; }
.ai-chatbox-markdown strong         { font-weight: 700; }
.ai-chatbox-markdown em             { font-style: italic; }
.ai-chatbox-markdown a              { color: var(--chatbox-color); text-decoration: underline; }
.ai-chatbox-markdown code           { background: rgba(0,0,0,.08); border-radius: 4px; padding: 1px 5px; font-size: 12px; font-family: monospace; }
.ai-chatbox-markdown pre            { background: #1e1e2e; border-radius: 8px; padding: 12px; overflow-x: auto; margin: 6px 0; }
.ai-chatbox-markdown pre code       { background: none; padding: 0; font-size: 12px; color: #cdd6f4; }
.ai-chatbox-markdown blockquote     { border-left: 3px solid var(--chatbox-color); margin: 6px 0; padding: 4px 10px; color: var(--chatbox-muted); font-style: italic; }
.ai-chatbox-markdown h1,
.ai-chatbox-markdown h2,
.ai-chatbox-markdown h3             { font-weight: 700; margin: 8px 0 4px; line-height: 1.3; }
.ai-chatbox-markdown h1             { font-size: 16px; }
.ai-chatbox-markdown h2             { font-size: 15px; }
.ai-chatbox-markdown h3             { font-size: 14px; }
.ai-chatbox-markdown hr             { border: none; border-top: 1px solid var(--chatbox-border); margin: 8px 0; }
.ai-chatbox-markdown table          { border-collapse: collapse; width: 100%; font-size: 13px; margin: 6px 0; }
.ai-chatbox-markdown th,
.ai-chatbox-markdown td             { border: 1px solid var(--chatbox-border); padding: 5px 8px; text-align: left; }
.ai-chatbox-markdown th             { background: rgba(128,128,128,.1); font-weight: 600; }

/* ── Responsive ── */
@media (max-width: 420px) {
  #ai-chatbox-window { width: calc(100vw - 32px); height: 70vh; }
  #ai-chatbox-wrapper.ai-chatbox--bottom-right { bottom: 16px; right: 16px; }
  #ai-chatbox-wrapper.ai-chatbox--bottom-left  { bottom: 16px; left:  16px; }
  #ai-chatbox-wrapper.ai-chatbox--top-right    { top:    16px; right: 16px; }
  #ai-chatbox-wrapper.ai-chatbox--top-left     { top:    16px; left:  16px; }
}
</style>
