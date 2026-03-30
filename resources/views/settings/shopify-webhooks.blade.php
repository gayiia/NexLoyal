{{-- This view shows one row per Shopify webhook with modal-based delivery details. --}}
@php($webhookFeedback = session('shopify_webhook_feedback'))
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'NexLoyal') }} - Shopify Webhooks</title>
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
            .nl-theme-light .nl-webhook-page .text-slate-50,
            .nl-theme-light .nl-webhook-page .text-slate-100,
            .nl-theme-light .nl-webhook-page .text-slate-200 { color: #0b1736; }
            .nl-theme-light .nl-webhook-page .text-slate-300 { color: #1f2f4d; }
            .nl-theme-light .nl-webhook-page .text-slate-400,
            .nl-theme-light .nl-webhook-page .text-slate-500 { color: #4b5f7a; }
            .nl-card { border-radius: 22px; border: 1px solid rgba(148, 163, 184, 0.2); background: rgba(15, 23, 42, 0.62); overflow: hidden; }
            .nl-card-head { background: rgba(2, 6, 23, 0.35); }
            .nl-theme-light .nl-card { background: rgba(255, 255, 255, 0.98); border-color: rgba(15, 23, 42, 0.14); }
            .nl-theme-light .nl-card-head { background: rgba(229, 238, 252, 0.75); }
            .nl-table-wrap { overflow-x: auto; }
            .nl-table { width: 100%; border-collapse: collapse; min-width: 760px; }
            .nl-table th { font-size: 11px; text-transform: uppercase; letter-spacing: 0.18em; color: #94a3b8; text-align: left; padding: 14px 18px; }
            .nl-table td { padding: 18px; border-top: 1px solid rgba(148, 163, 184, 0.14); vertical-align: middle; }
            .nl-topic { font-family: "Courier New", Courier, monospace; font-size: 12px; color: #cbd5e1; }
            .nl-status { display: inline-flex; align-items: center; gap: 8px; font-size: 12px; font-weight: 600; }
            .nl-status-dot { width: 10px; height: 10px; border-radius: 999px; display: inline-block; }
            .nl-status-connected { color: #86efac; }
            .nl-status-connected .nl-status-dot { background: #22c55e; box-shadow: 0 0 0 4px rgba(34, 197, 94, 0.14); }
            .nl-status-issue { color: #fda4af; }
            .nl-status-issue .nl-status-dot { background: #fb7185; box-shadow: 0 0 0 4px rgba(244, 63, 94, 0.14); }
            .nl-status-waiting { color: #fde68a; }
            .nl-status-waiting .nl-status-dot { background: #f59e0b; box-shadow: 0 0 0 4px rgba(245, 158, 11, 0.14); }
            .nl-flash { border-radius: 22px; border: 1px solid rgba(148, 163, 184, 0.2); padding: 20px 22px; }
            .nl-flash-success { background: rgba(16, 185, 129, 0.12); border-color: rgba(16, 185, 129, 0.28); }
            .nl-flash-warning { background: rgba(245, 158, 11, 0.12); border-color: rgba(245, 158, 11, 0.28); }
            .nl-flash-error { background: rgba(244, 63, 94, 0.12); border-color: rgba(244, 63, 94, 0.28); }
            .nl-result-list { margin-top: 16px; display: grid; gap: 10px; }
            .nl-result-item { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; border-radius: 14px; padding: 12px 14px; background: rgba(2, 6, 23, 0.24); }
            .nl-result-pill { display: inline-flex; align-items: center; gap: 8px; border-radius: 999px; padding: 6px 10px; font-size: 11px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; }
            .nl-result-pill-created { background: rgba(34, 197, 94, 0.14); color: #86efac; }
            .nl-result-pill-deleted { background: rgba(250, 204, 21, 0.14); color: #fde68a; }
            .nl-result-pill-existing { background: rgba(59, 130, 246, 0.14); color: #93c5fd; }
            .nl-result-pill-missing { background: rgba(59, 130, 246, 0.14); color: #93c5fd; }
            .nl-result-pill-failed { background: rgba(244, 63, 94, 0.14); color: #fda4af; }
            .nl-action-bar { display: flex; gap: 10px; justify-content: flex-end; }
            .nl-button { border: 1px solid rgba(148, 163, 184, 0.25); background: transparent; color: #e2e8f0; border-radius: 10px; padding: 9px 14px; font-size: 12px; font-weight: 600; cursor: pointer; }
            .nl-button:hover { border-color: rgba(148, 163, 184, 0.45); }
            .nl-button-primary { border-color: rgba(16, 185, 129, 0.3); background: rgba(16, 185, 129, 0.12); color: #d1fae5; }
            .nl-button-primary:hover { border-color: rgba(16, 185, 129, 0.5); }
            .nl-button-danger { border-color: rgba(244, 63, 94, 0.3); background: rgba(244, 63, 94, 0.12); color: #fecdd3; }
            .nl-button-danger:hover { border-color: rgba(244, 63, 94, 0.5); }
            .nl-theme-light .nl-button { color: #0b1736; }
            .nl-theme-light .nl-button-primary { color: #065f46; }
            .nl-theme-light .nl-button-danger { color: #9f1239; }
            .nl-panel-box { border-radius: 16px; border: 1px solid rgba(148, 163, 184, 0.16); background: rgba(2, 6, 23, 0.24); padding: 16px; }
            .nl-theme-light .nl-panel-box { background: rgba(236, 243, 255, 0.82); border-color: rgba(15, 23, 42, 0.14); }
            .nl-theme-light .nl-result-item { background: rgba(236, 243, 255, 0.82); }
            .nl-muted-label { font-size: 11px; text-transform: uppercase; letter-spacing: 0.16em; color: #94a3b8; }
            .nl-value { margin-top: 8px; color: #e2e8f0; font-size: 14px; }
            .nl-code { font-family: "Courier New", Courier, monospace; font-size: 12px; word-break: break-all; }
            .nl-modal { position: fixed; inset: 0; z-index: 80; display: none; align-items: center; justify-content: center; padding: 20px; background: rgba(2, 6, 23, 0.72); backdrop-filter: blur(6px); }
            .nl-modal.is-open { display: flex; }
            .nl-modal-card { width: min(920px, 100%); max-height: min(86vh, 860px); overflow: auto; border-radius: 22px; border: 1px solid rgba(148, 163, 184, 0.2); background: #0f172a; box-shadow: 0 30px 80px rgba(2, 6, 23, 0.45); }
            .nl-theme-light .nl-modal-card { background: #ffffff; border-color: rgba(15, 23, 42, 0.14); }
            .nl-modal-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; padding: 22px 24px; border-bottom: 1px solid rgba(148, 163, 184, 0.14); }
            .nl-modal-body { padding: 24px; display: grid; gap: 18px; }
            .nl-modal-close { border: none; background: transparent; color: #94a3b8; font-size: 26px; line-height: 1; cursor: pointer; }
            .nl-log-card { border-radius: 14px; border: 1px solid rgba(148, 163, 184, 0.14); background: rgba(2, 6, 23, 0.2); padding: 16px; }
            .nl-theme-light .nl-log-card { background: rgba(236, 243, 255, 0.76); border-color: rgba(15, 23, 42, 0.14); }
            .nl-log-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 10px; }
            .nl-log-meta { font-size: 12px; color: #94a3b8; }
            .nl-pre { margin-top: 10px; border-radius: 12px; background: rgba(15, 23, 42, 0.82); color: #e2e8f0; padding: 14px; font-size: 12px; white-space: pre-wrap; word-break: break-word; overflow: auto; }
            .nl-theme-light .nl-pre { background: #0f172a; color: #e2e8f0; }
            .nl-webhook-page details summary { cursor: pointer; font-size: 12px; font-weight: 600; color: #93c5fd; }
            @media (max-width: 820px) {
                .nl-action-bar { flex-direction: column; align-items: stretch; }
                .nl-modal-head { flex-direction: column; }
            }
        </style>
    </head>
    <body class="min-h-screen bg-slate-950 text-slate-100 antialiased">
        <div class="min-h-screen bg-[radial-gradient(900px_circle_at_top,rgba(56,189,248,0.18),transparent_60%)]">
            <div class="min-h-screen bg-[radial-gradient(700px_circle_at_bottom,rgba(30,64,175,0.22),transparent_60%)]">
                <div class="min-h-screen bg-[linear-gradient(120deg,rgba(15,23,42,0.9),rgba(2,6,23,0.95))] nl-shell">
                    <div class="flex min-h-screen flex-col lg:flex-row">
                        @include('partials.admin-sidebar')

                        <main class="nl-webhook-page flex-1 px-4 py-6 sm:px-6 lg:px-10">
                            <div class="mx-auto w-full max-w-7xl">
                                <x-page-header eyebrow="" title="Shopify Webhooks" breadcrumb="Settings / Shopify webhooks">
                                    <x-slot name="actions">
                                        <div class="flex flex-wrap items-center justify-end gap-3">
                                            <form method="POST" action="{{ route('shopify-webhooks.register') }}">
                                                @csrf
                                                <button class="nl-button nl-button-primary" type="submit">Connect</button>
                                            </form>
                                            <form method="POST" action="{{ route('shopify-webhooks.destroy') }}" onsubmit="return confirm('Delete the listed Shopify webhooks from Shopify?');">
                                                @csrf
                                                @method('DELETE')
                                                <button class="nl-button nl-button-danger" type="submit">Delete webhooks</button>
                                            </form>
                                            <button id="theme-toggle" class="rounded-xl border border-slate-800 bg-slate-900/60 px-4 py-2 text-xs text-slate-200 nl-panel-muted" type="button">Switch theme</button>
                                        </div>
                                    </x-slot>
                                </x-page-header>

                                @if ($webhookFeedback)
                                    <section class="mt-6 nl-flash nl-flash-{{ $webhookFeedback['level'] ?? 'success' }}">
                                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                            <div>
                                                <p class="text-sm font-semibold text-slate-50">{{ $webhookFeedback['title'] ?? 'Webhook result' }}</p>
                                                <p class="mt-2 text-sm leading-6 text-slate-200">{{ $webhookFeedback['message'] ?? 'Shopify webhook action finished.' }}</p>
                                            </div>
                                            <div class="grid grid-cols-3 gap-3 text-center">
                                                @foreach (($webhookFeedback['stats'] ?? []) as $stat)
                                                    <div class="rounded-2xl border border-slate-700/60 px-4 py-3">
                                                        <p class="text-[11px] uppercase tracking-[0.18em] text-slate-400">{{ $stat['label'] ?? 'Count' }}</p>
                                                        <p class="mt-2 text-lg font-semibold text-slate-50">{{ $stat['value'] ?? 0 }}</p>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>

                                        @if (!empty($webhookFeedback['results']))
                                            <div class="nl-result-list">
                                                @foreach ($webhookFeedback['results'] as $result)
                                                    <div class="nl-result-item">
                                                        <div>
                                                            <p class="font-semibold text-slate-50">{{ $result['label'] ?? $result['topic'] }}</p>
                                                            <p class="mt-1 nl-topic">{{ $result['topic'] ?? '' }}</p>
                                                            <p class="mt-2 text-sm text-slate-300">{{ $result['message'] ?? '' }}</p>
                                                        </div>
                                                        <div class="text-right">
                                                            <span class="nl-result-pill nl-result-pill-{{ $result['status'] ?? 'existing' }}">
                                                                {{ strtoupper($result['status'] ?? 'existing') }}
                                                            </span>
                                                            @if (!empty($result['id']))
                                                                <p class="mt-2 text-xs text-slate-400">ID {{ $result['id'] }}</p>
                                                            @endif
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </section>
                                @endif

                                @if (!empty($shopifyVerificationError))
                                    <section class="mt-6 nl-flash nl-flash-error">
                                        <p class="text-sm font-semibold text-slate-50">Shopify verification issue</p>
                                        <p class="mt-2 text-sm leading-6 text-slate-200">{{ $shopifyVerificationError }}</p>
                                    </section>
                                @endif

                                <section class="mt-6 rounded-[22px] border border-sky-500/20 bg-sky-500/10 p-6 nl-panel">
                                    <p class="text-sm font-semibold text-sky-100">Send test notifications from Shopify</p>
                                    <p class="mt-2 text-sm leading-6 text-slate-200">
                                        In Shopify Admin go to <span class="font-medium text-slate-50">Settings</span> -> <span class="font-medium text-slate-50">Notifications</span> -> <span class="font-medium text-slate-50">Webhooks</span> and use <span class="font-medium text-slate-50">Send test notification</span>.
                                        This screen shows one row per webhook and lets you inspect the latest delivery and log history in popups.
                                    </p>
                                    <p class="mt-3 text-xs uppercase tracking-[0.18em] text-sky-100/80">Connect verifies Shopify registration and creates any missing webhooks using the env credentials configured on this server.</p>
                                </section>

                                <section class="mt-6 nl-card">
                                    <div class="flex items-center justify-between gap-4 px-6 py-4 nl-card-head">
                                        <div>
                                            <p class="text-sm font-semibold text-slate-100">Webhook Monitor</p>
                                            <p class="text-xs text-slate-400">One row per webhook. Use the action buttons to inspect delivery details.</p>
                                        </div>
                                    </div>

                                    <div class="nl-table-wrap">
                                        <table class="nl-table">
                                            <thead>
                                                <tr>
                                                    <th>Webhook</th>
                                                    <th>Topic</th>
                                                    <th>Status</th>
                                                    <th class="text-right">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($webhooks as $webhook)
                                                    <tr>
                                                        <td>
                                                            <div class="font-semibold text-slate-100">{{ $webhook['label'] }}</div>
                                                        </td>
                                                        <td>
                                                            <div class="nl-topic">{{ $webhook['topic'] }}</div>
                                                        </td>
                                                        <td>
                                                            <span class="nl-status nl-status-{{ $webhook['status'] }}">
                                                                <span class="nl-status-dot"></span>
                                                                <span>{{ $webhook['status_label'] }}</span>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <div class="nl-action-bar">
                                                                <button
                                                                    type="button"
                                                                    class="nl-button"
                                                                    data-view-webhook="{{ $webhook['topic'] }}"
                                                                >
                                                                    View
                                                                </button>
                                                                <button
                                                                    type="button"
                                                                    class="nl-button"
                                                                    data-view-logs="{{ $webhook['topic'] }}"
                                                                >
                                                                    View logs
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </section>
                            </div>
                        </main>
                    </div>
                </div>
            </div>
        </div>

        <div id="webhook-view-modal" class="nl-modal" aria-hidden="true">
            <div class="nl-modal-card">
                <div class="nl-modal-head">
                    <div>
                        <p id="webhook-view-title" class="text-lg font-semibold text-slate-100"></p>
                        <p id="webhook-view-topic" class="mt-2 nl-topic"></p>
                    </div>
                    <button type="button" class="nl-modal-close" data-close-modal="webhook-view-modal" aria-label="Close">&times;</button>
                </div>
                <div class="nl-modal-body">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="nl-panel-box">
                            <p class="nl-muted-label">Expected URL</p>
                            <p id="webhook-view-url" class="nl-value nl-code"></p>
                        </div>
                        <div class="nl-panel-box">
                            <p class="nl-muted-label">Connection status</p>
                            <div class="mt-2">
                                <span id="webhook-view-status" class="nl-status nl-status-waiting">
                                    <span class="nl-status-dot"></span>
                                    <span>Waiting</span>
                                </span>
                            </div>
                            <p id="webhook-view-meta" class="nl-value"></p>
                        </div>
                    </div>
                    <div class="nl-panel-box">
                        <p class="nl-muted-label">Last log reference</p>
                        <p id="webhook-view-reference" class="nl-value nl-code"></p>
                    </div>
                    <div class="nl-panel-box">
                        <p class="nl-muted-label">Request path</p>
                        <p id="webhook-view-path" class="nl-value nl-code"></p>
                    </div>
                    <div id="webhook-view-error-box" class="nl-panel-box" style="display:none;">
                        <p class="nl-muted-label">Error</p>
                        <p id="webhook-view-error" class="nl-value"></p>
                    </div>
                </div>
            </div>
        </div>

        <div id="webhook-logs-modal" class="nl-modal" aria-hidden="true">
            <div class="nl-modal-card">
                <div class="nl-modal-head">
                    <div>
                        <p id="webhook-logs-title" class="text-lg font-semibold text-slate-100"></p>
                        <p id="webhook-logs-topic" class="mt-2 nl-topic"></p>
                    </div>
                    <button type="button" class="nl-modal-close" data-close-modal="webhook-logs-modal" aria-label="Close">&times;</button>
                </div>
                <div id="webhook-logs-body" class="nl-modal-body"></div>
            </div>
        </div>

        <script id="webhook-monitor-data" type="application/json">@json($webhooks->values(), JSON_UNESCAPED_SLASHES)</script>
        <script>
            (function () {
                const html = document.documentElement;
                const themeToggle = document.getElementById('theme-toggle');
                const storedTheme = localStorage.getItem('nl-admin-theme');
                const dataElement = document.getElementById('webhook-monitor-data');
                const monitorData = dataElement ? JSON.parse(dataElement.textContent) : [];
                const webhooks = {};

                monitorData.forEach((item) => {
                    webhooks[item.topic] = item;
                });

                const applyTheme = (theme) => {
                    html.classList.toggle('nl-theme-light', theme === 'light');
                    localStorage.setItem('nl-admin-theme', theme);
                };

                const escapeHtml = (value) => {
                    return String(value ?? '')
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;')
                        .replace(/'/g, '&#039;');
                };

                const statusClass = (status) => {
                    if (status === 'connected') return 'nl-status-connected';
                    if (status === 'issue') return 'nl-status-issue';
                    return 'nl-status-waiting';
                };

                const deliveryLabel = (state) => {
                    if (!state) return 'No delivery';
                    return state.replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase());
                };

                const openModal = (id) => {
                    const modal = document.getElementById(id);
                    if (!modal) return;
                    modal.classList.add('is-open');
                    modal.setAttribute('aria-hidden', 'false');
                };

                const closeModal = (id) => {
                    const modal = document.getElementById(id);
                    if (!modal) return;
                    modal.classList.remove('is-open');
                    modal.setAttribute('aria-hidden', 'true');
                };

                const renderViewModal = (item) => {
                    const latest = item.latest_log;
                    document.getElementById('webhook-view-title').textContent = item.label;
                    document.getElementById('webhook-view-topic').textContent = item.topic;
                    document.getElementById('webhook-view-url').textContent = item.address || '';
                    document.getElementById('webhook-view-path').textContent = latest ? (latest.request_path || 'n/a') : 'No delivery yet';
                    document.getElementById('webhook-view-reference').textContent = latest ? latest.reference_url : 'No log recorded yet';

                    const statusElement = document.getElementById('webhook-view-status');
                    statusElement.className = 'nl-status ' + statusClass(item.status);
                    statusElement.innerHTML = '<span class="nl-status-dot"></span><span>' + escapeHtml(item.status_label) + '</span>';

                    if (latest) {
                        document.getElementById('webhook-view-meta').textContent =
                            'HTTP ' + latest.response_status + ' · ' + (latest.created_at_label || 'Unknown time') + ' · ' + (latest.shop_domain || 'unknown shop');
                    } else {
                        document.getElementById('webhook-view-meta').textContent = 'No delivery recorded yet.';
                    }

                    const connectionBits = [item.connection_message || ''];
                    if (item.shopify_webhook_id) connectionBits.push('Shopify ID ' + item.shopify_webhook_id);
                    if (item.checked_at_label) connectionBits.push('Verified ' + item.checked_at_label);
                    document.getElementById('webhook-view-meta').textContent = connectionBits.filter(Boolean).join(' · ') || 'Connection not verified yet.';

                    const errorBox = document.getElementById('webhook-view-error-box');
                    const errorValue = document.getElementById('webhook-view-error');
                    if (item.verification_error) {
                        errorValue.textContent = item.verification_error;
                        errorBox.style.display = '';
                        openModal('webhook-view-modal');
                        return;
                    }

                    if (latest && latest.error_message) {
                        errorValue.textContent = latest.error_message;
                        errorBox.style.display = '';
                    } else {
                        errorValue.textContent = '';
                        errorBox.style.display = 'none';
                    }

                    openModal('webhook-view-modal');
                };

                const renderLogsModal = (item) => {
                    document.getElementById('webhook-logs-title').textContent = item.label + ' Logs';
                    document.getElementById('webhook-logs-topic').textContent = item.topic;

                    const body = document.getElementById('webhook-logs-body');
                    if (!item.logs || !item.logs.length) {
                        body.innerHTML = '<div class="nl-panel-box"><p class="text-sm text-slate-200">No logs recorded yet.</p></div>';
                        openModal('webhook-logs-modal');
                        return;
                    }

                    body.innerHTML = item.logs.map((log) => {
                        return '' +
                            '<div class="nl-log-card">' +
                                '<div class="nl-log-head">' +
                                    '<div>' +
                                        '<span class="nl-status ' + statusClass(log.delivery_state === 'processed' || log.delivery_state === 'ignored' ? 'connected' : 'issue') + '">' +
                                            '<span class="nl-status-dot"></span>' +
                                            '<span>' + escapeHtml(deliveryLabel(log.delivery_state)) + '</span>' +
                                        '</span>' +
                                        '<div class="nl-log-meta" style="margin-top:8px;">HTTP ' + escapeHtml(log.response_status) + ' · ' + escapeHtml(log.created_at_label || 'Unknown time') + '</div>' +
                                    '</div>' +
                                    '<div class="nl-log-meta">#' + escapeHtml(log.id) + '</div>' +
                                '</div>' +
                                '<div class="nl-log-meta">Expected URL: ' + escapeHtml(item.address || '') + '</div>' +
                                '<div class="nl-log-meta" style="margin-top:6px;">Request URL: ' + escapeHtml(log.request_url || '') + '</div>' +
                                '<div class="nl-log-meta" style="margin-top:6px;">Log URL: ' + escapeHtml(log.reference_url || '') + '</div>' +
                                (log.error_message ? '<div class="nl-log-meta" style="margin-top:6px; color:#fda4af;">Error: ' + escapeHtml(log.error_message) + '</div>' : '') +
                                '<details style="margin-top:12px;">' +
                                    '<summary>Headers</summary>' +
                                    '<pre class="nl-pre">' + escapeHtml(log.headers || '{}') + '</pre>' +
                                '</details>' +
                                '<details style="margin-top:12px;">' +
                                    '<summary>Payload</summary>' +
                                    '<pre class="nl-pre">' + escapeHtml(log.payload || '{}') + '</pre>' +
                                '</details>' +
                            '</div>';
                    }).join('');

                    openModal('webhook-logs-modal');
                };

                applyTheme(storedTheme === 'light' ? 'light' : 'dark');

                themeToggle?.addEventListener('click', () => {
                    applyTheme(html.classList.contains('nl-theme-light') ? 'dark' : 'light');
                });

                document.querySelectorAll('[data-view-webhook]').forEach((button) => {
                    button.addEventListener('click', () => {
                        const topic = button.getAttribute('data-view-webhook');
                        if (!topic || !webhooks[topic]) return;
                        renderViewModal(webhooks[topic]);
                    });
                });

                document.querySelectorAll('[data-view-logs]').forEach((button) => {
                    button.addEventListener('click', () => {
                        const topic = button.getAttribute('data-view-logs');
                        if (!topic || !webhooks[topic]) return;
                        renderLogsModal(webhooks[topic]);
                    });
                });

                document.querySelectorAll('[data-close-modal]').forEach((button) => {
                    button.addEventListener('click', () => {
                        const modalId = button.getAttribute('data-close-modal');
                        if (modalId) closeModal(modalId);
                    });
                });

                document.querySelectorAll('.nl-modal').forEach((modal) => {
                    modal.addEventListener('click', (event) => {
                        if (event.target === modal) {
                            closeModal(modal.id);
                        }
                    });
                });

                document.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape') {
                        closeModal('webhook-view-modal');
                        closeModal('webhook-logs-modal');
                    }
                });
            })();
        </script>
    </body>
</html>
