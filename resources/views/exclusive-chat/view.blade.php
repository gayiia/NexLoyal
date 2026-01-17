<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'NexLoyal') }} - Poll Analytics</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
        @vite(['resources/css/app.css'])
        <style>
            :root { color-scheme: dark; }
            body { letter-spacing: 0.01em; }
            .nl-theme-light { color-scheme: light; background-color: #f8fafc; color: #0f172a; }
            .nl-theme-light .nl-shell { background: linear-gradient(120deg, rgba(248, 250, 252, 0.95), rgba(226, 232, 240, 0.95)); }
            .nl-theme-light .nl-panel { background-color: rgba(255, 255, 255, 0.85); border-color: rgba(148, 163, 184, 0.4); color: #0f172a; }
            .nl-theme-light .nl-panel-muted { background-color: rgba(226, 232, 240, 0.6); border-color: rgba(148, 163, 184, 0.4); color: #0f172a; }
            .nl-theme-light .nl-text-muted { color: #475569; }
            .nl-theme-light .nl-sidebar-link { color: #0f172a; }
            .nl-theme-light .nl-sidebar-link:hover { background-color: rgba(226, 232, 240, 0.8); border-color: rgba(148, 163, 184, 0.6); }
            .nl-theme-light .nl-sidebar-link-active { background-color: rgba(226, 232, 240, 0.9); border-color: rgba(148, 163, 184, 0.6); color: #0f172a; }
            .nl-modal-backdrop {
                position: fixed;
                inset: 0;
                background: rgba(15, 23, 42, 0.6);
                display: none;
                align-items: center;
                justify-content: center;
                padding: 20px;
                z-index: 50;
            }
            .nl-modal-backdrop.is-open { display: flex; }
        </style>
    </head>
    <body class="min-h-screen bg-slate-950 text-slate-100 antialiased">
        <div class="min-h-screen bg-[radial-gradient(900px_circle_at_top,rgba(56,189,248,0.18),transparent_60%)]">
            <div class="min-h-screen bg-[linear-gradient(120deg,rgba(15,23,42,0.9),rgba(2,6,23,0.95))] nl-shell">
                <div class="flex min-h-screen flex-col lg:flex-row">
                    @include('partials.admin-sidebar')

                    <main class="flex-1 px-4 py-6 sm:px-6 lg:px-10 lg:py-8">
                        <x-page-header eyebrow="" title="Poll analytics" breadcrumb="Notifications / Exclusive Chat / Poll">
                            <x-slot name="actions">
                                <a href="{{ route('exclusive-chat') }}" class="rounded-xl border border-slate-800 bg-slate-900/60 px-4 py-2 text-xs text-slate-200 nl-panel-muted">
                                    Back to messages
                                </a>
                                <a href="{{ route('exclusive-chat.view.export', $message) }}" class="rounded-xl border border-slate-700 px-4 py-2 text-xs text-slate-200">
                                    Export CSV
                                </a>
                            </x-slot>
                        </x-page-header>

                        <section class="mt-6 rounded-2xl border border-slate-800 bg-slate-900/70 p-6 nl-panel">
                            <div class="flex flex-wrap items-start justify-between gap-4">
                                <div>
                                    <p class="text-sm font-semibold text-slate-100">{{ $message->title ?: 'Poll message' }}</p>
                                    <p class="mt-2 text-sm text-slate-300">{{ $message->body }}</p>
                                    <p class="mt-2 text-xs text-slate-500">Sent {{ $message->sent_at?->format('Y-m-d H:i') ?? '—' }}</p>
                                </div>
                                <div class="rounded-xl border border-slate-800/70 bg-slate-950/60 px-4 py-3 text-xs text-slate-200">
                                    Total votes: <span class="font-semibold text-slate-100">{{ $totalVotes }}</span>
                                </div>
                            </div>

                            @if ($message->attachments->count())
                                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                    @foreach ($message->attachments as $attachment)
                                        @if ($attachment->resolved_url)
                                            <img src="{{ $attachment->resolved_url }}" alt="Attachment" class="w-full rounded-xl border border-slate-800 object-cover">
                                        @endif
                                    @endforeach
                                </div>
                            @endif
                        </section>

                        <section class="mt-6 rounded-2xl border border-slate-800 bg-slate-900/70 p-6 nl-panel">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-semibold text-slate-100">Results</p>
                                    <p class="mt-1 text-xs text-slate-400">Click a count to view voters.</p>
                                </div>
                            </div>

                            <div class="mt-4 grid gap-4">
                                @foreach ($options as $option)
                                    <div class="rounded-xl border border-slate-800/70 bg-slate-950/40 p-4">
                                        <div class="flex items-center justify-between">
                                            <p class="text-sm font-semibold text-slate-100">{{ $option['label'] }}</p>
                                            <button type="button"
                                                    class="rounded-lg border border-slate-700 px-3 py-1 text-xs text-slate-200"
                                                    data-option-id="{{ $option['id'] }}">
                                                {{ $option['count'] }} votes ({{ $option['percent'] }}%)
                                            </button>
                                        </div>
                                        <div class="mt-3 h-2 w-full rounded-full bg-slate-800/80">
                                            <div class="h-2 rounded-full bg-sky-400/80" style="width: {{ $option['percent'] }}%"></div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </section>
                    </main>
                </div>
            </div>
        </div>

        <div id="voters-modal" class="nl-modal-backdrop" aria-hidden="true">
            <div class="w-full max-w-2xl rounded-2xl border border-slate-800 bg-slate-950 p-6 text-slate-100">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-semibold text-slate-100">Voters</p>
                        <p class="mt-1 text-xs text-slate-400">Customers who selected this option.</p>
                    </div>
                    <button type="button" id="voters-close" class="text-xs text-slate-400">Close</button>
                </div>

                <div class="mt-4 flex flex-wrap items-center gap-2">
                    <input id="voters-search" class="w-full rounded-lg border border-slate-700 bg-slate-900/60 px-3 py-2 text-xs text-slate-200" placeholder="Search name or email">
                    <button type="button" id="voters-search-button" class="rounded-lg border border-slate-700 px-3 py-2 text-xs text-slate-200">Search</button>
                </div>

                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="text-xs uppercase tracking-[0.2em] text-slate-400">
                            <tr class="bg-slate-900/60">
                                <th class="px-4 py-3">Customer</th>
                                <th class="px-4 py-3">Email</th>
                                <th class="px-4 py-3">Tier</th>
                                <th class="px-4 py-3">Voted at</th>
                            </tr>
                        </thead>
                        <tbody id="voters-table" class="divide-y divide-slate-800/70 text-slate-200"></tbody>
                    </table>
                </div>

                <div id="voters-pagination" class="mt-4 flex items-center justify-between text-xs text-slate-400"></div>
            </div>
        </div>

        <script>
            (function () {
                const modal = document.getElementById('voters-modal');
                const closeButton = document.getElementById('voters-close');
                const tableBody = document.getElementById('voters-table');
                const pagination = document.getElementById('voters-pagination');
                const searchInput = document.getElementById('voters-search');
                const searchButton = document.getElementById('voters-search-button');
                let activeOption = null;
                let activePage = 1;

                const openModal = () => {
                    if (modal) {
                        modal.classList.add('is-open');
                        modal.setAttribute('aria-hidden', 'false');
                    }
                };

                const closeModal = () => {
                    if (modal) {
                        modal.classList.remove('is-open');
                        modal.setAttribute('aria-hidden', 'true');
                    }
                };

                const renderRows = (rows) => {
                    if (!tableBody) {
                        return;
                    }
                    tableBody.innerHTML = rows.length
                        ? rows.map((row) => `
                            <tr>
                                <td class="px-4 py-3">${row.name}</td>
                                <td class="px-4 py-3">${row.email}</td>
                                <td class="px-4 py-3">${row.tier}</td>
                                <td class="px-4 py-3">${row.voted_at ? row.voted_at.replace('T', ' ').slice(0, 19) : '—'}</td>
                            </tr>
                        `).join('')
                        : '<tr><td colspan="4" class="px-4 py-6 text-center text-slate-400">No voters found.</td></tr>';
                };

                const renderPagination = (meta) => {
                    if (!pagination) {
                        return;
                    }
                    const prevDisabled = meta.current_page <= 1;
                    const nextDisabled = meta.current_page >= meta.last_page;
                    pagination.innerHTML = `
                        <span>Page ${meta.current_page} of ${meta.last_page}</span>
                        <div class="flex gap-2">
                            <button type="button" data-page="${meta.current_page - 1}" ${prevDisabled ? 'disabled' : ''} class="rounded-lg border border-slate-700 px-3 py-1 text-slate-200 ${prevDisabled ? 'opacity-50' : ''}">Prev</button>
                            <button type="button" data-page="${meta.current_page + 1}" ${nextDisabled ? 'disabled' : ''} class="rounded-lg border border-slate-700 px-3 py-1 text-slate-200 ${nextDisabled ? 'opacity-50' : ''}">Next</button>
                        </div>
                    `;
                    pagination.querySelectorAll('button[data-page]').forEach((button) => {
                        button.addEventListener('click', () => {
                            const next = Number(button.getAttribute('data-page'));
                            if (Number.isFinite(next)) {
                                activePage = next;
                                fetchVoters();
                            }
                        });
                    });
                };

                const fetchVoters = () => {
                    if (!activeOption) {
                        return;
                    }
                    const search = searchInput ? searchInput.value.trim() : '';
                    const url = `/admin/api/chat/polls/{{ $poll->id }}/options/${activeOption}/voters?page=${activePage}&search=${encodeURIComponent(search)}`;
                    fetch(url, { method: 'GET' })
                        .then((response) => response.json())
                        .then((payload) => {
                            renderRows(payload.data || []);
                            renderPagination(payload.meta || { current_page: 1, last_page: 1 });
                        })
                        .catch(() => {
                            renderRows([]);
                            renderPagination({ current_page: 1, last_page: 1 });
                        });
                };

                document.querySelectorAll('[data-option-id]').forEach((button) => {
                    button.addEventListener('click', () => {
                        activeOption = button.getAttribute('data-option-id');
                        activePage = 1;
                        openModal();
                        fetchVoters();
                    });
                });

                if (closeButton) {
                    closeButton.addEventListener('click', closeModal);
                }

                if (modal) {
                    modal.addEventListener('click', (event) => {
                        if (event.target === modal) {
                            closeModal();
                        }
                    });
                }

                if (searchButton) {
                    searchButton.addEventListener('click', () => {
                        activePage = 1;
                        fetchVoters();
                    });
                }
            })();
        </script>
    </body>
</html>
