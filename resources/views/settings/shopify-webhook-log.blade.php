{{-- This view shows the payload and headers for a single webhook delivery log. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'NexLoyal') }} - Webhook Log</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
        @vite(['resources/css/app.css'])
        <style>
            :root { color-scheme: dark; }
            body { letter-spacing: 0.01em; }
            .nl-theme-light { color-scheme: light; background-color: #f7f9fc; color: #0f172a; }
            .nl-theme-light .nl-shell { background: linear-gradient(130deg, rgba(245, 248, 255, 0.95), rgba(226, 235, 250, 0.98)); }
            .nl-theme-light .nl-panel { background-color: rgba(255, 255, 255, 0.96); border-color: rgba(15, 23, 42, 0.12); color: #0b1736; }
            .nl-theme-light .nl-panel-muted { background-color: rgba(240, 245, 255, 0.9); border-color: rgba(15, 23, 42, 0.16); color: #0b1736; }
            .nl-theme-light .nl-text-muted { color: #5b6b84; }
            .nl-theme-light .nl-sidebar-link { color: #0b1736; }
            .nl-theme-light .nl-sidebar-link:hover { background-color: rgba(214, 229, 248, 0.7); border-color: rgba(15, 23, 42, 0.2); }
            .nl-theme-light .nl-sidebar-link-active { background-color: rgba(199, 219, 245, 0.9); border-color: rgba(15, 23, 42, 0.24); color: #0b1736; }
            .nl-theme-light .text-slate-50, .nl-theme-light .text-slate-100, .nl-theme-light .text-slate-200 { color: #0b1736; }
            .nl-theme-light .text-slate-300 { color: #1f2f4d; }
            .nl-theme-light .text-slate-400, .nl-theme-light .text-slate-500 { color: #4b5f7a; }
            .nl-card { border-radius: 22px; border: 1px solid rgba(148, 163, 184, 0.2); background: rgba(15, 23, 42, 0.62); }
            .nl-card-head { background: rgba(2, 6, 23, 0.35); }
            .nl-theme-light .nl-card { background: rgba(255, 255, 255, 0.98); border-color: rgba(15, 23, 42, 0.14); }
            .nl-theme-light .nl-card-head { background: rgba(229, 238, 252, 0.75); }
            pre { white-space: pre-wrap; word-break: break-word; }
        </style>
    </head>
    <body class="min-h-screen bg-slate-950 text-slate-100 antialiased">
        @php
            use Illuminate\Support\Str;

            $badgeClasses = match ($log->delivery_state) {
                'processed' => 'border-emerald-400/30 bg-emerald-500/15 text-emerald-200',
                'ignored' => 'border-slate-700 bg-slate-900/70 text-slate-200',
                'invalid_signature', 'invalid_payload' => 'border-amber-400/30 bg-amber-500/15 text-amber-100',
                'misconfigured', 'error' => 'border-rose-400/30 bg-rose-500/15 text-rose-100',
                default => 'border-slate-700 bg-slate-900/70 text-slate-200',
            };
        @endphp

        <div class="min-h-screen bg-[radial-gradient(900px_circle_at_top,rgba(56,189,248,0.18),transparent_60%)]">
            <div class="min-h-screen bg-[radial-gradient(700px_circle_at_bottom,rgba(30,64,175,0.22),transparent_60%)]">
                <div class="min-h-screen bg-[linear-gradient(120deg,rgba(15,23,42,0.9),rgba(2,6,23,0.95))] nl-shell">
                    <div class="flex min-h-screen flex-col lg:flex-row">
                        @include('partials.admin-sidebar')

                        <main class="flex-1 px-4 py-6 sm:px-6 lg:px-10">
                            <div class="mx-auto w-full max-w-6xl">
                                <x-page-header eyebrow="" title="Webhook Log" breadcrumb="Settings / Shopify webhooks / Log">
                                    <x-slot name="actions">
                                        <div class="flex items-center gap-3">
                                            <a href="{{ route('shopify-webhooks') }}" class="rounded-xl border border-slate-700 px-4 py-2 text-xs text-slate-200 hover:border-slate-500">Back to monitor</a>
                                            <button id="theme-toggle" class="rounded-xl border border-slate-800 bg-slate-900/60 px-4 py-2 text-xs text-slate-200 nl-panel-muted" type="button">Switch theme</button>
                                        </div>
                                    </x-slot>
                                </x-page-header>

                                <section class="mt-6 overflow-hidden nl-card">
                                    <div class="flex flex-col gap-4 px-6 py-5 nl-card-head sm:flex-row sm:items-start sm:justify-between">
                                        <div>
                                            <div class="flex items-center gap-3">
                                                <h2 class="text-lg font-semibold text-slate-100">{{ $definition['label'] ?? 'Webhook delivery' }}</h2>
                                                <span class="rounded-full border px-2.5 py-1 text-[11px] font-medium {{ $badgeClasses }}">
                                                    {{ Str::of($log->delivery_state)->replace('_', ' ')->title() }}
                                                </span>
                                            </div>
                                            <p class="mt-2 font-mono text-xs text-slate-300">{{ $log->topic ?: 'missing topic header' }}</p>
                                        </div>
                                        <div class="text-left text-xs text-slate-300 sm:text-right">
                                            <p class="text-slate-400">Log reference</p>
                                            <p class="mt-1 text-sm font-semibold text-slate-100">#{{ $log->id }}</p>
                                        </div>
                                    </div>

                                    <div class="grid gap-4 px-6 py-5 lg:grid-cols-2">
                                        <div class="rounded-2xl border border-slate-800/70 bg-slate-950/40 p-4 nl-panel-muted">
                                            <p class="text-[11px] uppercase tracking-[0.2em] text-slate-400">Summary</p>
                                            <dl class="mt-3 space-y-3 text-sm text-slate-200">
                                                <div class="flex justify-between gap-4">
                                                    <dt class="text-slate-400">Response</dt>
                                                    <dd>HTTP {{ $log->response_status }}</dd>
                                                </div>
                                                <div class="flex justify-between gap-4">
                                                    <dt class="text-slate-400">Received</dt>
                                                    <dd>{{ $log->created_at?->format('M d, Y H:i:s') }}</dd>
                                                </div>
                                                <div class="flex justify-between gap-4">
                                                    <dt class="text-slate-400">Webhook key</dt>
                                                    <dd class="font-mono">{{ $log->webhook_key }}</dd>
                                                </div>
                                                <div class="flex justify-between gap-4">
                                                    <dt class="text-slate-400">Shop domain</dt>
                                                    <dd>{{ $log->shop_domain ?: 'unknown' }}</dd>
                                                </div>
                                                <div class="flex justify-between gap-4">
                                                    <dt class="text-slate-400">HMAC valid</dt>
                                                    <dd>{{ $log->hmac_valid === null ? 'n/a' : ($log->hmac_valid ? 'Yes' : 'No') }}</dd>
                                                </div>
                                            </dl>
                                        </div>

                                        <div class="rounded-2xl border border-slate-800/70 bg-slate-950/40 p-4 nl-panel-muted">
                                            <p class="text-[11px] uppercase tracking-[0.2em] text-slate-400">Routing</p>
                                            <dl class="mt-3 space-y-3 text-sm text-slate-200">
                                                <div>
                                                    <dt class="text-slate-400">Request path</dt>
                                                    <dd class="mt-1 break-all font-mono text-xs text-slate-200">{{ $log->request_path }}</dd>
                                                </div>
                                                <div>
                                                    <dt class="text-slate-400">Request URL</dt>
                                                    <dd class="mt-1 break-all font-mono text-xs text-slate-200">{{ $log->request_url }}</dd>
                                                </div>
                                                @if ($definition)
                                                    <div>
                                                        <dt class="text-slate-400">Expected URL</dt>
                                                        <dd class="mt-1 break-all font-mono text-xs text-slate-200">{{ $definition['address'] }}</dd>
                                                    </div>
                                                @endif
                                                @if ($log->error_message)
                                                    <div>
                                                        <dt class="text-slate-400">Error</dt>
                                                        <dd class="mt-1 text-sm text-rose-200">{{ $log->error_message }}</dd>
                                                    </div>
                                                @endif
                                            </dl>
                                        </div>
                                    </div>
                                </section>

                                <section class="mt-6 overflow-hidden nl-card">
                                    <div class="px-6 py-4 nl-card-head">
                                        <p class="text-sm font-semibold text-slate-100">Request headers</p>
                                    </div>
                                    <div class="px-6 py-5">
                                        <pre class="rounded-2xl border border-slate-800/70 bg-slate-950/50 p-4 text-xs text-slate-200">{{ $headers }}</pre>
                                    </div>
                                </section>

                                <section class="mt-6 overflow-hidden nl-card">
                                    <div class="px-6 py-4 nl-card-head">
                                        <p class="text-sm font-semibold text-slate-100">Payload</p>
                                    </div>
                                    <div class="px-6 py-5">
                                        <pre class="rounded-2xl border border-slate-800/70 bg-slate-950/50 p-4 text-xs text-slate-200">{{ $payload }}</pre>
                                    </div>
                                </section>
                            </div>
                        </main>
                    </div>
                </div>
            </div>
        </div>

        <script>
            (function () {
                const html = document.documentElement;
                const themeToggle = document.getElementById('theme-toggle');
                const storedTheme = localStorage.getItem('nl-admin-theme');
                const applyTheme = (theme) => {
                    html.classList.toggle('nl-theme-light', theme === 'light');
                    localStorage.setItem('nl-admin-theme', theme);
                };
                applyTheme(storedTheme === 'light' ? 'light' : 'dark');
                themeToggle?.addEventListener('click', () => {
                    applyTheme(html.classList.contains('nl-theme-light') ? 'dark' : 'light');
                });
            })();
        </script>
    </body>
</html>
