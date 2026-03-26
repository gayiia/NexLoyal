{{-- This sidebar renders the main admin navigation and highlights the active route. --}}
@php
    // Helpers compute which menu items should appear active or expanded.
    $isActive = fn (string $route) => request()->routeIs($route);
    $isSettings =
        request()->routeIs('profile.edit') ||
        request()->routeIs('user-password.edit') ||
        request()->routeIs('two-factor.show') ||
        request()->routeIs('appearance.edit') ||
        request()->routeIs('customer-groups') ||
        request()->routeIs('tier-rules') ||
        request()->routeIs('point-rules');
    $isNotifications =
        request()->routeIs('exclusive-chat') ||
        request()->routeIs('exclusive-chat.settings') ||
        request()->routeIs('exclusive-chat.view');
    $isAiInsights =
        request()->routeIs('ai-insights') ||
        request()->routeIs('ai-insights.awards.create') ||
        request()->routeIs('ai-insights.awards.edit');
    $isAiDataImport =
        request()->routeIs('ai-data-import') ||
        request()->routeIs('ai-data-import.store');
    $isAiSandbox =
        request()->routeIs('ai-sandbox') ||
        request()->routeIs('ai-sandbox.compute') ||
        request()->routeIs('ai-sandbox.train');
    $isAiFeatures = request()->routeIs('ai-features');
    $isAiMenu = $isAiInsights || $isAiSandbox || $isAiFeatures || $isAiDataImport;
    $isReports =
        request()->routeIs('reports') ||
        request()->routeIs('reports.generate') ||
        request()->routeIs('reports.export.excel') ||
        request()->routeIs('reports.export.pdf');
@endphp

<div class="nl-mobile-header fixed inset-x-0 top-0 z-40 border-b border-slate-800/70 bg-slate-950/90 px-4 pb-3 backdrop-blur lg:hidden nl-panel">
    <div class="flex items-center justify-between gap-3">
        <div class="flex w-32 items-center">
            {{-- Brand logo anchors the mobile header. --}}
            <img src="{{ URL::asset('build\\Images\\logo-light.png') }}" alt="NexLoyal" class="h-8 w-auto">
        </div>
        <button id="mobile-nav-toggle"
                type="button"
                class="rounded-lg border border-slate-700 px-3 py-2 text-xs font-medium text-slate-100"
                aria-controls="admin-sidebar"
                aria-expanded="false">
            Menu
        </button>
    </div>
</div>

<div id="mobile-nav-overlay" class="hidden fixed inset-0 z-40 bg-slate-950/70 backdrop-blur-sm lg:hidden"></div>

<aside id="admin-sidebar" class="fixed inset-y-0 left-0 z-50 flex h-dvh w-[min(18rem,calc(100vw-1rem))] max-w-full -translate-x-full flex-col overflow-y-auto border-r border-slate-800/70 bg-slate-950/95 px-5 py-6 text-slate-100 transition-transform duration-300 ease-out lg:static lg:z-auto lg:h-auto lg:w-72 lg:translate-x-0 lg:overflow-visible nl-panel">
    <div class="flex items-center justify-between gap-3 px-2">
        <div class="flex w-40 items-center justify-center lg:justify-start">
            {{-- Brand logo anchors the navigation. --}}
            <img src="{{ URL::asset('build\\Images\\logo-light.png') }}" alt="NexLoyal" class="w-auto">
        </div>
        <button id="mobile-nav-close"
                type="button"
                class="rounded-lg border border-slate-700 px-3 py-2 text-xs font-medium text-slate-100 lg:hidden">
            Close
        </button>
    </div>

    {{-- Primary navigation links for the admin dashboard. --}}
    <nav class="mt-8 flex-1 space-y-1 text-sm">
        <a href="{{ route('dashboard') }}"
           @class([
               'flex items-center justify-between rounded-lg border border-transparent px-3 py-2 transition nl-sidebar-link',
               'border-slate-800 bg-slate-900/80 text-slate-100 nl-sidebar-link-active' => $isActive('dashboard'),
               'text-slate-300 hover:border-slate-800 hover:bg-slate-900/60' => !$isActive('dashboard'),
           ])>
            <span>Dashboard</span>
            <span class="text-xs text-slate-500 nl-text-muted">Overview</span>
        </a>
        <a href="{{ route('customers') }}"
           @class([
               'flex items-center justify-between rounded-lg border border-transparent px-3 py-2 transition nl-sidebar-link',
               'border-slate-800 bg-slate-900/80 text-slate-100 nl-sidebar-link-active' => $isActive('customers'),
               'text-slate-300 hover:border-slate-800 hover:bg-slate-900/60' => !$isActive('customers'),
           ])>
            <span>Customers</span>
            <span class="text-xs text-slate-500 nl-text-muted">Segments</span>
        </a>
        <a href="{{ route('coupons') }}"
           @class([
               'flex items-center justify-between rounded-lg border border-transparent px-3 py-2 transition nl-sidebar-link',
               'border-slate-800 bg-slate-900/80 text-slate-100 nl-sidebar-link-active' => $isActive('coupons'),
               'text-slate-300 hover:border-slate-800 hover:bg-slate-900/60' => !$isActive('coupons'),
           ])>
            <span>Coupons</span>
            <span class="text-xs text-slate-500 nl-text-muted">Rewards</span>
        </a>
        <a href="{{ route('mystery-boxes') }}"
           @class([
               'flex items-center justify-between rounded-lg border border-transparent px-3 py-2 transition nl-sidebar-link',
               'border-slate-800 bg-slate-900/80 text-slate-100 nl-sidebar-link-active' => $isActive('mystery-boxes'),
               'text-slate-300 hover:border-slate-800 hover:bg-slate-900/60' => !$isActive('mystery-boxes'),
           ])>
            <span>Mystery Box</span>
            <span class="text-xs text-slate-500 nl-text-muted">Coupons</span>
        </a>
        <details class="group" @if($isAiMenu) open @endif>
            {{-- AI submenu expands when any AI route is active. --}}
            <summary class="flex cursor-pointer items-center justify-between rounded-lg border border-transparent px-3 py-2 text-slate-300 transition hover:border-slate-800 hover:bg-slate-900/60 nl-sidebar-link">
                <span>AI</span>
                <span class="text-xs text-slate-500 nl-text-muted">Modules</span>
            </summary>
            <div class="mt-2 space-y-1 pl-3 text-xs">
                <a href="{{ route('ai-sandbox') }}" class="block rounded-md px-2 py-1.5 text-slate-300 hover:bg-slate-900/60 @if($isAiSandbox) bg-slate-900/70 text-slate-100 @endif">AI Sandbox</a>
                <a href="{{ route('ai-features') }}" class="block rounded-md px-2 py-1.5 text-slate-300 hover:bg-slate-900/60 @if($isAiFeatures) bg-slate-900/70 text-slate-100 @endif">Feature Preview</a>
                <a href="{{ route('ai-data-import') }}" class="block rounded-md px-2 py-1.5 text-slate-300 hover:bg-slate-900/60 @if($isAiDataImport) bg-slate-900/70 text-slate-100 @endif">AI Data Import</a>
                <a href="{{ route('ai-insights') }}" class="block rounded-md px-2 py-1.5 text-slate-300 hover:bg-slate-900/60 @if($isAiInsights) bg-slate-900/70 text-slate-100 @endif">AI Insights</a>
            </div>
        </details>
        <details class="group" @if($isReports) open @endif>
            {{-- Reports submenu expands when any report route is active. --}}
            <summary class="flex cursor-pointer items-center justify-between rounded-lg border border-transparent px-3 py-2 text-slate-300 transition hover:border-slate-800 hover:bg-slate-900/60 nl-sidebar-link">
                <span>Reports</span>
                <span class="text-xs text-slate-500 nl-text-muted">Builder</span>
            </summary>
            <div class="mt-2 space-y-1 pl-3 text-xs">
                <a href="{{ route('reports') }}" class="block rounded-md px-2 py-1.5 text-slate-300 hover:bg-slate-900/60 @if($isReports) bg-slate-900/70 text-slate-100 @endif">Report Builder</a>
            </div>
        </details>
        <details class="group" @if($isNotifications) open @endif>
            {{-- Notifications submenu expands when exclusive chat routes are active. --}}
            <summary class="flex cursor-pointer items-center justify-between rounded-lg border border-transparent px-3 py-2 text-slate-300 transition hover:border-slate-800 hover:bg-slate-900/60 nl-sidebar-link">
                <span>Notifications</span>
                <span class="text-xs text-slate-500 nl-text-muted">Engage</span>
            </summary>
            <div class="mt-2 space-y-1 pl-3 text-xs">
                <a href="{{ route('exclusive-chat') }}" class="block rounded-md px-2 py-1.5 text-slate-300 hover:bg-slate-900/60">Exclusive Chat</a>
            </div>
        </details>
        <div>
            {{-- Settings are handled by a custom toggle to keep the menu compact. --}}
            <button id="settings-toggle"
                    type="button"
                    class="flex w-full items-center justify-between rounded-lg border border-transparent px-3 py-2 text-slate-300 transition hover:border-slate-800 hover:bg-slate-900/60 nl-sidebar-link">
                <span>Settings</span>
                <span class="text-xs text-slate-500 nl-text-muted">Rules</span>
            </button>
            <div id="settings-menu" class="mt-2 space-y-1 pl-3 text-xs @if(!$isSettings) hidden @endif">
                {{-- Settings links remain expanded when a settings route is active. --}}
                <a href="{{ route('profile.edit') }}" class="block rounded-md px-2 py-1.5 text-slate-300 hover:bg-slate-900/60">Profile</a>
                <a href="{{ route('user-password.edit') }}" class="block rounded-md px-2 py-1.5 text-slate-300 hover:bg-slate-900/60">Password</a>
                <a href="{{ route('two-factor.show') }}" class="block rounded-md px-2 py-1.5 text-slate-300 hover:bg-slate-900/60">Two-Factor Auth</a>
                <a href="{{ route('appearance.edit') }}" class="block rounded-md px-2 py-1.5 text-slate-300 hover:bg-slate-900/60">Appearance</a>
                <a href="{{ route('customer-groups') }}" class="block rounded-md px-2 py-1.5 text-slate-300 hover:bg-slate-900/60">Customer groups</a>
                <a href="{{ route('tier-rules') }}" class="block rounded-md px-2 py-1.5 text-slate-300 hover:bg-slate-900/60">Tier rules</a>
                <a href="{{ route('point-rules') }}" class="block rounded-md px-2 py-1.5 text-slate-300 hover:bg-slate-900/60">Point rules</a>
            </div>
        </div>
    </nav>

    {{-- Account summary and logout action appear at the bottom of the sidebar. --}}
    <div class="mt-8 rounded-lg border border-slate-800/70 bg-slate-900/60 p-3 text-xs text-slate-300 nl-panel-muted">
        <p class="text-[10px] uppercase tracking-[0.2em] text-slate-400 nl-text-muted">Account</p>
        <p class="mt-2 text-sm font-medium text-slate-100">gayindu</p>
        <form method="POST" action="{{ route('logout') }}" class="mt-3">
            @csrf
            {{-- Log out ends the current authenticated session. --}}
            <button type="submit" class="w-full rounded-md border border-slate-700 px-3 py-2 text-xs text-slate-200 hover:border-slate-500">
                Log out
            </button>
        </form>
    </div>
</aside>

<script>
    (function () {
        const html = document.documentElement;
        const sidebar = document.getElementById('admin-sidebar');
        const overlay = document.getElementById('mobile-nav-overlay');
        const openButton = document.getElementById('mobile-nav-toggle');
        const closeButton = document.getElementById('mobile-nav-close');
        const settingsToggle = document.getElementById('settings-toggle');
        const settingsMenu = document.getElementById('settings-menu');
        const desktopQuery = window.matchMedia('(min-width: 1024px)');

        if (!sidebar) {
            return;
        }

        const setMenuOpen = (isOpen) => {
            const shouldOpen = !desktopQuery.matches && isOpen;
            sidebar.classList.toggle('translate-x-0', shouldOpen);
            sidebar.classList.toggle('-translate-x-full', !shouldOpen);
            overlay?.classList.toggle('hidden', !shouldOpen);
            html.classList.toggle('nl-nav-open', shouldOpen);
            openButton?.setAttribute('aria-expanded', String(shouldOpen));
        };

        openButton?.addEventListener('click', () => setMenuOpen(true));
        closeButton?.addEventListener('click', () => setMenuOpen(false));
        overlay?.addEventListener('click', () => setMenuOpen(false));

        sidebar.querySelectorAll('a').forEach((link) => {
            link.addEventListener('click', () => setMenuOpen(false));
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                setMenuOpen(false);
            }
        });

        const syncDesktopState = () => {
            if (desktopQuery.matches) {
                overlay?.classList.add('hidden');
                html.classList.remove('nl-nav-open');
                openButton?.setAttribute('aria-expanded', 'false');
            }
        };

        if (typeof desktopQuery.addEventListener === 'function') {
            desktopQuery.addEventListener('change', syncDesktopState);
        } else if (typeof desktopQuery.addListener === 'function') {
            desktopQuery.addListener(syncDesktopState);
        }

        settingsToggle?.addEventListener('click', () => {
            settingsMenu?.classList.toggle('hidden');
        });

        const initMobileTables = () => {
            document.querySelectorAll('main table').forEach((table) => {
                const headers = Array.from(table.querySelectorAll('thead th')).map((header) =>
                    (header.textContent || '').replace(/\s+/g, ' ').trim()
                );

                if (!headers.length) {
                    return;
                }

                table.classList.add('nl-mobile-table');

                table.querySelectorAll('tbody tr').forEach((row) => {
                    Array.from(row.children).forEach((cell, index) => {
                        if (!(cell instanceof HTMLTableCellElement)) {
                            return;
                        }

                        if (cell.colSpan > 1) {
                            cell.dataset.label = '';
                            cell.classList.add('nl-mobile-cell-full');
                            return;
                        }

                        cell.dataset.label = headers[index] || '';
                    });
                });
            });
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initMobileTables, { once: true });
        } else {
            initMobileTables();
        }

        syncDesktopState();
    })();
</script>
