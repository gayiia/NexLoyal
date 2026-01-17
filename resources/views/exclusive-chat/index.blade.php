@php
    use Illuminate\Support\Str;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'NexLoyal') }} - Exclusive Chat</title>
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
            .nl-table-head { background: rgba(15, 23, 42, 0.6); }
            .nl-table-row:hover { background: rgba(30, 41, 59, 0.45); }
            .nl-theme-light .nl-table-head { background: rgba(226, 232, 240, 0.8); }
            .nl-theme-light .nl-table-row:hover { background: rgba(226, 232, 240, 0.8); }
            .nl-badge { border-radius: 999px; padding: 4px 10px; font-size: 11px; font-weight: 600; }
            .nl-tab { border-radius: 999px; padding: 6px 14px; font-size: 11px; font-weight: 600; }
            .nl-upload-spinner {
                width: 18px;
                height: 18px;
                border-radius: 999px;
                border: 2px solid rgba(148, 163, 184, 0.35);
                border-top-color: rgba(56, 189, 248, 0.9);
                animation: nl-spin 1s linear infinite;
                display: none;
            }
            .nl-upload-spinner.is-active { display: inline-block; }
            @keyframes nl-spin { to { transform: rotate(360deg); } }
        </style>
    </head>
    <body class="min-h-screen bg-slate-950 text-slate-100 antialiased">
        <div class="min-h-screen bg-[radial-gradient(900px_circle_at_top,rgba(56,189,248,0.18),transparent_60%)]">
            <div class="min-h-screen bg-[linear-gradient(120deg,rgba(15,23,42,0.9),rgba(2,6,23,0.95))] nl-shell">
                <div class="flex min-h-screen flex-col lg:flex-row">
                    @include('partials.admin-sidebar')

                    <main class="flex-1 px-4 py-6 sm:px-6 lg:px-10 lg:py-8">
                        <x-page-header eyebrow="" title="Exclusive Chat" breadcrumb="Notifications / Exclusive Chat">
                            <x-slot name="actions">
                                <button id="theme-toggle" class="rounded-xl border border-slate-800 bg-slate-900/60 px-4 py-2 text-xs text-slate-200 nl-panel-muted" type="button">
                                    Switch theme
                                </button>
                            </x-slot>
                        </x-page-header>

                        <div class="mt-6 flex flex-wrap gap-3">
                            <a href="{{ route('exclusive-chat') }}" class="nl-tab border border-slate-700 bg-slate-100 text-slate-900">Messages</a>
                            <a href="{{ route('exclusive-chat.settings') }}" class="nl-tab border border-slate-700 text-slate-200">Settings</a>
                        </div>

                        <section class="mt-6 rounded-2xl border border-slate-800 bg-slate-900/70 p-6 nl-panel">
                            <div class="flex flex-wrap items-start justify-between gap-4">
                                <div>
                                    <p class="text-sm font-semibold text-slate-100">Create message</p>
                                    <p class="mt-1 text-xs text-slate-400">Send a text update or poll to eligible tiers.</p>
                                </div>
                            </div>

                            @if ($errors->any())
                                <div class="mt-4 rounded-xl border border-rose-500/40 bg-rose-500/10 px-4 py-3 text-xs text-rose-200">
                                    <p class="font-semibold text-rose-100">Unable to save message.</p>
                                    <p class="mt-1 text-rose-200">{{ $errors->first() }}</p>
                                </div>
                            @endif

                            <form id="chat-message-form" class="mt-5 grid gap-4" method="POST" action="{{ route('exclusive-chat.messages.store') }}" enctype="multipart/form-data">
                                @csrf
                                <div class="grid gap-3 md:grid-cols-2">
                                    <div>
                                        <label class="text-xs uppercase tracking-[0.2em] text-slate-400">Type</label>
                                        <select id="chat-type" name="type" class="mt-2 w-full rounded-lg border border-slate-700 bg-slate-950/60 px-3 py-2 text-sm text-slate-200">
                                            <option value="TEXT" @selected(old('type', 'TEXT') === 'TEXT')>Text</option>
                                            <option value="POLL" @selected(old('type', 'TEXT') === 'POLL')>Poll</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="text-xs uppercase tracking-[0.2em] text-slate-400">Title (optional)</label>
                                        <input name="title" class="mt-2 w-full rounded-lg border border-slate-700 bg-slate-950/60 px-3 py-2 text-sm text-slate-200" value="{{ old('title') }}">
                                    </div>
                                </div>

                                <div>
                                    <label class="text-xs uppercase tracking-[0.2em] text-slate-400">Body</label>
                                    <textarea name="body" class="mt-2 min-h-[120px] w-full rounded-lg border border-slate-700 bg-slate-950/60 px-3 py-2 text-sm text-slate-200" required>{{ old('body') }}</textarea>
                                </div>

                                <div>
                                    <label class="text-xs uppercase tracking-[0.2em] text-slate-400">Tier visibility</label>
                                    <div class="mt-2 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                        @foreach ($tiers as $tier)
                                            <label class="flex items-center gap-2 text-xs text-slate-300">
                                                <input type="checkbox" name="tier_visibility[]" value="{{ $tier->id }}" @checked(in_array($tier->id, old('tier_visibility', $settings->allowed_tiers ?? []), true))>
                                                {{ $tier->title }}
                                            </label>
                                        @endforeach
                                    </div>
                                </div>

                                <div>
                                    <label class="text-xs uppercase tracking-[0.2em] text-slate-400">Attach images</label>
                                    <div class="mt-2 flex flex-wrap items-center gap-3">
                                        <label class="inline-flex cursor-pointer items-center rounded-lg border border-slate-700 px-3 py-2 text-xs text-slate-200">
                                            <input id="chat-attachments" type="file" name="attachments[]" multiple accept="image/*" class="hidden">
                                            Choose files
                                        </label>
                                        <span id="chat-upload-spinner" class="nl-upload-spinner" aria-hidden="true"></span>
                                        <span class="text-xs text-slate-400">PNG, JPG, or GIF up to 5MB each.</span>
                                    </div>
                                    <p id="chat-attachments-error" class="mt-2 hidden text-xs text-rose-300"></p>
                                </div>

                                <div id="poll-options" class="grid gap-3 rounded-xl border border-slate-800/80 bg-slate-950/40 p-4">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <p class="text-xs uppercase tracking-[0.2em] text-slate-400">Poll options (2-6)</p>
                                        <button type="button" id="add-option" class="rounded-lg border border-slate-700 px-3 py-1 text-xs text-slate-200">Add option</button>
                                    </div>
                                    <div id="option-list" class="grid gap-2">
                                        <input name="poll_options[]" class="w-full rounded-lg border border-slate-700 bg-slate-950/60 px-3 py-2 text-sm text-slate-200" placeholder="Option 1">
                                        <input name="poll_options[]" class="w-full rounded-lg border border-slate-700 bg-slate-950/60 px-3 py-2 text-sm text-slate-200" placeholder="Option 2">
                                    </div>
                                    <div>
                                        <label class="text-xs uppercase tracking-[0.2em] text-slate-400">Poll closes at (optional)</label>
                                        <input type="datetime-local" name="closes_at" class="mt-2 w-full rounded-lg border border-slate-700 bg-slate-950/60 px-3 py-2 text-sm text-slate-200">
                                    </div>
                                </div>

                                <div class="flex items-center justify-end gap-3">
                                    <button class="rounded-xl bg-slate-100 px-4 py-2 text-xs font-semibold text-slate-900" type="submit">
                                        Send message
                                    </button>
                                </div>
                            </form>
                        </section>

                        <section class="mt-6 overflow-hidden rounded-2xl border border-slate-800 bg-slate-900/70 nl-panel">
                            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-800/70 px-6 py-4">
                                <div>
                                    <p class="text-sm font-semibold text-slate-100">Messages</p>
                                    <p class="mt-1 text-xs text-slate-400">One-way broadcast feed for customers.</p>
                                </div>
                                <a href="{{ route('exclusive-chat.export') }}" class="rounded-xl border border-slate-700 px-4 py-2 text-xs text-slate-200">
                                    Export CSV
                                </a>
                            </div>

                            <div class="overflow-x-auto px-6 py-5">
                                <table class="min-w-full text-left text-sm">
                                    <thead class="text-xs uppercase tracking-[0.2em] text-slate-400">
                                        <tr class="nl-table-head">
                                            <th class="px-4 py-3">Type</th>
                                            <th class="px-4 py-3">Message</th>
                                            <th class="px-4 py-3">Tiers</th>
                                            <th class="px-4 py-3">Sent</th>
                                            <th class="px-4 py-3 text-right">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-800/70 text-slate-200">
                                        @forelse ($messages as $message)
                                            @php
                                                $tierIds = collect($message->tier_visibility ?? [])->map(fn ($id) => (int) $id)->all();
                                                $tierNames = $tiers->whereIn('id', $tierIds)->pluck('title')->all();
                                            @endphp
                                            <tr class="nl-table-row">
                                                <td class="px-4 py-4 text-xs">
                                                    <span class="nl-badge border border-slate-500/50 bg-slate-800/70 text-slate-200">{{ $message->type }}</span>
                                                </td>
                                                <td class="px-4 py-4">
                                                    <div class="font-semibold text-slate-100">{{ $message->title ?: 'Message' }}</div>
                                                    <div class="text-xs text-slate-400">{{ Str::limit($message->body, 120) }}</div>
                                                </td>
                                                <td class="px-4 py-4 text-xs text-slate-300">
                                                    {{ $tierNames ? implode(', ', $tierNames) : 'Default tiers' }}
                                                </td>
                                                <td class="px-4 py-4 text-xs text-slate-400">
                                                    {{ $message->sent_at?->format('Y-m-d H:i') ?? '—' }}
                                                </td>
                                                <td class="px-4 py-4 text-right text-xs">
                                                    <div class="flex flex-wrap justify-end gap-2">
                                                        @if ($message->type === 'POLL')
                                                            <a href="{{ route('exclusive-chat.view', $message) }}" class="rounded-lg border border-slate-700 px-3 py-1.5 text-slate-200">View</a>
                                                        @endif
                                                        <form method="POST" action="{{ route('exclusive-chat.destroy', $message) }}" onsubmit="return confirm('Delete this message?');">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="rounded-lg border border-rose-500/60 px-3 py-1.5 text-rose-200">Delete</button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="px-4 py-10 text-center text-slate-400">No messages yet. Send the first update.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>

                            <div class="border-t border-slate-800/70 px-6 py-4 text-xs text-slate-400">
                                Showing {{ $messages->firstItem() ?? 0 }} to {{ $messages->lastItem() ?? 0 }} of {{ $messages->total() }} entries
                            </div>
                        </section>
                    </main>
                </div>
            </div>
        </div>

        <script>
            (function () {
                const storageKey = 'nl-theme';
                const body = document.body;
                const button = document.getElementById('theme-toggle');

                const applyTheme = (theme) => {
                    body.classList.toggle('nl-theme-light', theme === 'light');
                    if (button) {
                        button.textContent = theme === 'light' ? 'Switch to dark' : 'Switch to light';
                    }
                };

                const stored = localStorage.getItem(storageKey);
                applyTheme(stored || 'dark');

                if (button) {
                    button.addEventListener('click', () => {
                        const next = body.classList.contains('nl-theme-light') ? 'dark' : 'light';
                        localStorage.setItem(storageKey, next);
                        applyTheme(next);
                    });
                }

                const settingsToggle = document.getElementById('settings-toggle');
                const settingsMenu = document.getElementById('settings-menu');
                const shouldOpenSettings = window.location.pathname.startsWith('/settings');
                if (settingsMenu) {
                    settingsMenu.classList.toggle('hidden', !shouldOpenSettings);
                }
                if (settingsToggle && settingsMenu) {
                    settingsToggle.addEventListener('click', () => {
                        settingsMenu.classList.toggle('hidden');
                    });
                }

                const typeSelect = document.getElementById('chat-type');
                const pollOptions = document.getElementById('poll-options');
                const optionList = document.getElementById('option-list');
                const addOptionButton = document.getElementById('add-option');
                const form = document.getElementById('chat-message-form');
                const attachmentsInput = document.getElementById('chat-attachments');
                const uploadSpinner = document.getElementById('chat-upload-spinner');
                const attachmentsError = document.getElementById('chat-attachments-error');

                const updatePollVisibility = () => {
                    if (!typeSelect || !pollOptions) {
                        return;
                    }
                    pollOptions.classList.toggle('hidden', typeSelect.value !== 'POLL');
                };

                const addOption = () => {
                    if (!optionList) {
                        return;
                    }
                    const count = optionList.querySelectorAll('input').length;
                    if (count >= 6) {
                        return;
                    }
                    const input = document.createElement('input');
                    input.name = 'poll_options[]';
                    input.className = 'w-full rounded-lg border border-slate-700 bg-slate-950/60 px-3 py-2 text-sm text-slate-200';
                    input.placeholder = `Option ${count + 1}`;
                    optionList.appendChild(input);
                };

                if (typeSelect) {
                    typeSelect.addEventListener('change', updatePollVisibility);
                    updatePollVisibility();
                }

                if (addOptionButton) {
                    addOptionButton.addEventListener('click', addOption);
                }

                if (form && attachmentsInput && uploadSpinner) {
                    attachmentsInput.addEventListener('change', () => {
                        uploadSpinner.classList.remove('is-active');
                        if (attachmentsError) {
                            attachmentsError.classList.add('hidden');
                            attachmentsError.textContent = '';
                        }
                    });

                    form.addEventListener('submit', (event) => {
                        if (typeSelect && typeSelect.value === 'POLL' && attachmentsInput.files && attachmentsInput.files.length === 0) {
                            event.preventDefault();
                            if (attachmentsError) {
                                attachmentsError.textContent = 'Please upload at least one image before sending a poll.';
                                attachmentsError.classList.remove('hidden');
                            }
                            return;
                        }
                        if (attachmentsInput.files && attachmentsInput.files.length > 0) {
                            uploadSpinner.classList.add('is-active');
                        }
                    });
                }
            })();
        </script>
    </body>
</html>
