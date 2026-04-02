@php $scheme = $colorScheme ?? 'auto'; @endphp
<!DOCTYPE html>
<html lang="en" @if($scheme === 'dark') class="dark" @endif>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Chatbox — Conversations</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { darkMode: 'class' }</script>
    @if($scheme === 'auto')
    <script>
        (function () {
            var mq = window.matchMedia('(prefers-color-scheme: dark)');
            function apply() { document.documentElement.classList.toggle('dark', mq.matches); }
            apply();
            mq.addEventListener('change', apply);
        })();
    </script>
    @endif
    <style>
        :root { --theme: {{ $themeColor }}; }

        .btn-primary {
            background-color: var(--theme);
            color: #fff;
            display: inline-flex; align-items: center; gap: 0.5rem;
            border-radius: 0.5rem; padding: 0.5rem 1.25rem;
            font-size: 0.875rem; font-weight: 500;
            transition: filter 0.15s; text-decoration: none;
        }
        .btn-primary:hover { filter: brightness(0.88); }

        .btn-secondary {
            display: inline-flex; align-items: center; gap: 0.5rem;
            border-radius: 0.5rem; padding: 0.5rem 1.25rem;
            font-size: 0.875rem; font-weight: 500;
            color: var(--theme);
            background-color: color-mix(in srgb, var(--theme) 10%, transparent);
            transition: background-color 0.15s; text-decoration: none;
            border: 1px solid color-mix(in srgb, var(--theme) 25%, transparent);
        }
        .btn-secondary:hover { background-color: color-mix(in srgb, var(--theme) 18%, transparent); }

        .section-heading {
            font-size: 0.7rem; font-weight: 700; letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--theme);
        }

        .badge { display: inline-flex; align-items: center; padding: 0.125rem 0.5rem; border-radius: 9999px; font-size: 0.7rem; font-weight: 600; }
        .badge-blue   { background: #dbeafe; color: #1e40af; }
        .badge-gray   { background: #f3f4f6; color: #374151; }
        .dark .badge-blue   { background: #1e3a5f; color: #93c5fd; }
        .dark .badge-gray   { background: #374151; color: #d1d5db; }

        /* Row hover highlight */
        .conv-row { cursor: pointer; transition: background-color 0.1s; }
        .conv-row:hover { background-color: color-mix(in srgb, var(--theme) 6%, transparent); }

        /* Skeleton pulse */
        @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.4} }
        .skeleton { animation: pulse 1.4s ease-in-out infinite; background: #e5e7eb; border-radius: 4px; height: 1rem; }
        .dark .skeleton { background: #374151; }

        /* Pagination button */
        .page-btn {
            display: inline-flex; align-items: center; justify-content: center;
            width: 2rem; height: 2rem; border-radius: 0.375rem;
            font-size: 0.8rem; font-weight: 500;
            border: 1px solid #d1d5db;
            background: #fff;
            color: #374151;
            cursor: pointer; transition: background 0.1s;
        }
        .dark .page-btn { background: #1f2937; border-color: #374151; color: #d1d5db; }
        .page-btn:hover:not(:disabled) { background: color-mix(in srgb, var(--theme) 10%, transparent); }
        .page-btn.active { background-color: var(--theme); color: #fff; border-color: var(--theme); }
        .page-btn:disabled { opacity: 0.4; cursor: not-allowed; }

        /* Modal */
        #msg-modal-backdrop {
            position: fixed; inset: 0; z-index: 50;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(2px);
            align-items: center; justify-content: center;
            padding: 1rem;
        }
        #msg-modal {
            background: #fff;
            border-radius: 0.75rem;
            width: 100%; max-width: 640px;
            max-height: 85vh;
            display: flex; flex-direction: column;
            box-shadow: 0 20px 60px rgba(0,0,0,0.25);
            overflow: hidden;
        }
        .dark #msg-modal { background: #1f2937; }

        .bubble-user {
            align-self: flex-end;
            background-color: var(--theme);
            color: #fff;
            border-radius: 1rem 1rem 0.25rem 1rem;
            padding: 0.6rem 0.9rem;
            max-width: 78%;
            white-space: pre-wrap;
            word-break: break-word;
            font-size: 0.875rem;
        }
        .bubble-assistant {
            align-self: flex-start;
            background: #f3f4f6;
            color: #111827;
            border-radius: 1rem 1rem 1rem 0.25rem;
            padding: 0.6rem 0.9rem;
            max-width: 78%;
            white-space: pre-wrap;
            word-break: break-word;
            font-size: 0.875rem;
        }
        .dark .bubble-assistant { background: #374151; color: #f3f4f6; }

        /* Spinner */
        .spin { animation: spin 0.7s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body class="bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 antialiased min-h-screen">

<div class="w-full px-6 py-10">

    {{-- ── Header ──────────────────────────────────────────────────────────── --}}
    <div class="flex items-center justify-between mb-8 gap-4 flex-wrap">
        <div>
            <a href="{{ route('ai-chatbox.admin.index') }}"
               class="inline-flex items-center gap-1 text-sm mb-2 text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                </svg>
                Admin
            </a>
            <h1 class="text-2xl font-bold tracking-tight">Conversations</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Click a row to view full message history.</p>
        </div>
        <span id="total-badge" class="badge badge-blue text-sm px-3 py-1"></span>
    </div>

    {{-- ── Table card ───────────────────────────────────────────────────────── --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">

        {{-- Loading bar (top of card) --}}
        <div id="loading-bar" class="h-0.5 w-full overflow-hidden hidden">
            <div class="h-full animate-pulse" style="background:var(--theme);width:60%;margin-left:20%;border-radius:9999px;"></div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-900/40 border-b border-gray-200 dark:border-gray-700">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-gray-500 dark:text-gray-400 text-xs uppercase tracking-wide">Thread ID</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-500 dark:text-gray-400 text-xs uppercase tracking-wide">User</th>
                    <th class="px-4 py-3 text-center font-semibold text-gray-500 dark:text-gray-400 text-xs uppercase tracking-wide">Msgs</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-500 dark:text-gray-400 text-xs uppercase tracking-wide">Last Message</th>
                    <th class="px-4 py-3 text-right font-semibold text-gray-500 dark:text-gray-400 text-xs uppercase tracking-wide">Last Active</th>
                </tr>
                </thead>
                <tbody id="conv-tbody" class="divide-y divide-gray-100 dark:divide-gray-700/50">
                {{-- Rows injected by JS --}}
                </tbody>
            </table>
        </div>

        {{-- Pagination footer --}}
        <div id="pagination-wrap" class="flex items-center justify-between gap-4 px-4 py-3 border-t border-gray-200 dark:border-gray-700 flex-wrap">
            <p id="pagination-info" class="text-xs text-gray-500 dark:text-gray-400"></p>
            <div id="pagination-btns" class="flex items-center gap-1.5"></div>
        </div>
    </div>

</div>

{{-- ── Message modal (hidden until row click) ──────────────────────────── --}}
<div id="msg-modal-backdrop" style="display:none" role="dialog" aria-modal="true" aria-labelledby="modal-title">
    <div id="msg-modal">
        {{-- Modal header --}}
        <div class="flex items-start justify-between px-5 py-4 border-b border-gray-200 dark:border-gray-700 shrink-0">
            <div>
                <h2 id="modal-title" class="font-semibold text-base">Conversation</h2>
                <p id="modal-meta" class="text-xs text-gray-500 dark:text-gray-400 mt-0.5"></p>
            </div>
            <button id="modal-close-btn"
                    class="ml-4 p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition-colors"
                    aria-label="Close">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- Modal body --}}
        <div id="modal-body" class="flex-1 overflow-y-auto px-5 py-4 flex flex-col gap-3">
            {{-- Messages injected by JS --}}
        </div>
    </div>
</div>

<script>
    const DATA_URL     = @json($dataUrl);
    const MESSAGES_URL = @json($messagesUrl); // contains __id__ placeholder

    let currentPage = 1;
    let totalPages  = 1;

    // ── Fetch & render conversations ────────────────────────────────────────
    async function loadPage(page) {
        setLoading(true);
        try {
            const res  = await fetch(`${DATA_URL}?page=${page}`);
            const json = await res.json();
            renderRows(json.data);
            renderPagination(json);
            currentPage = json.current_page;
            totalPages  = json.last_page;

            const totalEl = document.getElementById('total-badge');
            totalEl.textContent = `${json.total} conversation${json.total !== 1 ? 's' : ''}`;
        } catch (e) {
            document.getElementById('conv-tbody').innerHTML =
                `<tr><td colspan="5" class="px-4 py-8 text-center text-sm text-red-500">Failed to load conversations. Please refresh.</td></tr>`;
        } finally {
            setLoading(false);
        }
    }

    function setLoading(on) {
        document.getElementById('loading-bar').classList.toggle('hidden', !on);
        if (on) {
            document.getElementById('conv-tbody').innerHTML = skeletonRows(8);
        }
    }

    function skeletonRows(n) {
        return Array.from({length: n}, () => `
            <tr>
                <td class="px-4 py-3"><div class="skeleton w-28"></div></td>
                <td class="px-4 py-3"><div class="skeleton w-16"></div></td>
                <td class="px-4 py-3 text-center"><div class="skeleton w-6 mx-auto"></div></td>
                <td class="px-4 py-3"><div class="skeleton w-48"></div></td>
                <td class="px-4 py-3 text-right"><div class="skeleton w-20 ml-auto"></div></td>
            </tr>`).join('');
    }

    function renderRows(rows) {
        const tbody = document.getElementById('conv-tbody');
        if (!rows.length) {
            tbody.innerHTML = `<tr><td colspan="5" class="px-4 py-10 text-center text-sm text-gray-400 dark:text-gray-500">No conversations yet.</td></tr>`;
            return;
        }
        tbody.innerHTML = rows.map(row => {
            const thread  = row.thread_id ? row.thread_id.substring(0, 12) + (row.thread_id.length > 12 ? '…' : '') : '—';
            const user    = row.user_name ? escHtml(row.user_name) : '<span class="text-gray-400 italic">Guest</span>';
            const preview = row.last_preview
                ? escHtml(row.last_preview)
                : '<span class="text-gray-400 italic">empty</span>';
            const roleClass = row.last_role === 'user' ? 'badge-blue' : 'badge-gray';
            const roleBadge = row.last_role
                ? `<span class="badge ${roleClass} mr-1.5">${escHtml(row.last_role)}</span>`
                : '';

            return `
            <tr class="conv-row" data-id="${row.id}" tabindex="0" role="button" aria-label="View conversation ${thread}">
                <td class="px-4 py-3 font-mono text-xs text-gray-600 dark:text-gray-300 whitespace-nowrap">${escHtml(thread)}</td>
                <td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-300 whitespace-nowrap">${user}</td>
                <td class="px-4 py-3 text-center text-xs font-semibold">${row.messages_count}</td>
                <td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-300 max-w-xs truncate">${roleBadge}${preview}</td>
                <td class="px-4 py-3 text-right text-xs text-gray-400 dark:text-gray-500 whitespace-nowrap">${escHtml(row.updated_at)}</td>
            </tr>`;
        }).join('');

        // Attach click & keyboard handlers
        tbody.querySelectorAll('.conv-row').forEach(tr => {
            tr.addEventListener('click',   () => openModal(+tr.dataset.id));
            tr.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openModal(+tr.dataset.id); } });
        });
    }

    function renderPagination(json) {
        const info = document.getElementById('pagination-info');
        const wrap = document.getElementById('pagination-btns');

        const from = (json.current_page - 1) * json.per_page + 1;
        const to   = Math.min(json.current_page * json.per_page, json.total);
        info.textContent = json.total ? `Showing ${from}–${to} of ${json.total}` : '';

        wrap.innerHTML = '';

        // Prev
        const prev = pageBtn('‹', json.current_page <= 1);
        prev.addEventListener('click', () => loadPage(json.current_page - 1));
        wrap.appendChild(prev);

        // Page numbers (show up to 7 around current)
        const pages = pageWindow(json.current_page, json.last_page);
        let lastP = 0;
        pages.forEach(p => {
            if (p - lastP > 1) {
                const dots = document.createElement('span');
                dots.textContent = '…';
                dots.className = 'text-gray-400 text-xs px-1';
                wrap.appendChild(dots);
            }
            const btn = pageBtn(p, false, p === json.current_page);
            btn.addEventListener('click', () => { if (p !== json.current_page) loadPage(p); });
            wrap.appendChild(btn);
            lastP = p;
        });

        // Next
        const next = pageBtn('›', json.current_page >= json.last_page);
        next.addEventListener('click', () => loadPage(json.current_page + 1));
        wrap.appendChild(next);
    }

    function pageBtn(label, disabled, active = false) {
        const btn = document.createElement('button');
        btn.textContent = label;
        btn.className   = 'page-btn' + (active ? ' active' : '');
        btn.disabled    = disabled;
        return btn;
    }

    function pageWindow(cur, last, delta = 3) {
        const range = new Set([1, last]);
        for (let i = Math.max(1, cur - delta); i <= Math.min(last, cur + delta); i++) range.add(i);
        return [...range].sort((a, b) => a - b);
    }

    // ── Modal ────────────────────────────────────────────────────────────────
    async function openModal(id) {
        const backdrop = document.getElementById('msg-modal-backdrop');
        const body     = document.getElementById('modal-body');
        const meta     = document.getElementById('modal-meta');
        const title    = document.getElementById('modal-title');

        title.textContent = 'Loading…';
        meta.textContent  = '';
        body.innerHTML    = `<div class="flex justify-center py-8">
            <svg class="spin w-8 h-8 text-gray-400" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
            </svg>
        </div>`;
        backdrop.style.display = 'flex';
        document.body.style.overflow = 'hidden';

        try {
            const url = MESSAGES_URL.replace('__id__', id);
            const res = await fetch(url);
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const json = await res.json();

            const userName = json.user_name || (json.user_id ? String(json.user_id) : 'User');

            title.textContent = `Thread: ${json.thread_id ?? '—'}`;
            meta.textContent  = json.user_id ? `User: ${json.user_name || json.user_id}` : 'Guest session';

            if (!json.messages.length) {
                body.innerHTML = `<p class="text-center text-sm text-gray-400 py-8">No messages in this conversation.</p>`;
                return;
            }

            body.innerHTML = json.messages.map(m => {
                const isUser = m.role === 'user';
                const bubble = isUser ? 'bubble-user' : 'bubble-assistant';
                const label  = isUser ? userName : 'Assistant';
                const align  = isUser ? 'items-end' : 'items-start';
                return `
                <div class="flex flex-col ${align} gap-0.5">
                    <span class="text-[0.65rem] text-gray-400 px-1">${escHtml(label)} · ${escHtml(m.created_at ?? '')}</span>
                    <div class="${bubble}">${escHtml(m.content)}</div>
                </div>`;
            }).join('');

            // Scroll to bottom
            body.scrollTop = body.scrollHeight;
        } catch (e) {
            body.innerHTML = `<p class="text-center text-sm text-red-500 py-8">Failed to load messages: ${e.message}</p>`;
        }
    }

    function closeModal() {
        document.getElementById('msg-modal-backdrop').style.display = 'none';
        document.body.style.overflow = '';
    }

    document.getElementById('modal-close-btn').addEventListener('click', closeModal);
    document.getElementById('msg-modal-backdrop').addEventListener('click', function (e) {
        if (e.target === this) closeModal();
    });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

    // ── Helpers ──────────────────────────────────────────────────────────────
    function escHtml(str) {
        if (str == null) return '';
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // ── Init ─────────────────────────────────────────────────────────────────
    loadPage(1);
</script>
</body>
</html>
