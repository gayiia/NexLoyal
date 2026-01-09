<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'NexLoyal') }} - Customer Groups</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
        @vite(['resources/css/app.css'])
        <style>
            :root {
                color-scheme: dark;
            }
            body {
                letter-spacing: 0.01em;
            }
            .nl-theme-light {
                color-scheme: light;
                background-color: #f8fafc;
                color: #0f172a;
            }
            .nl-theme-light .nl-shell {
                background: linear-gradient(120deg, rgba(248, 250, 252, 0.95), rgba(226, 232, 240, 0.95));
            }
            .nl-theme-light .nl-panel {
                background-color: rgba(255, 255, 255, 0.85);
                border-color: rgba(148, 163, 184, 0.4);
                color: #0f172a;
            }
            .nl-theme-light .nl-panel-muted {
                background-color: rgba(226, 232, 240, 0.6);
                border-color: rgba(148, 163, 184, 0.4);
                color: #0f172a;
            }
            .nl-theme-light .nl-text-muted {
                color: #475569;
            }
            .nl-theme-light .nl-sidebar-link {
                color: #0f172a;
            }
            .nl-theme-light .nl-sidebar-link:hover {
                background-color: rgba(226, 232, 240, 0.8);
                border-color: rgba(148, 163, 184, 0.6);
            }
            .nl-theme-light .nl-sidebar-link-active {
                background-color: rgba(226, 232, 240, 0.9);
                border-color: rgba(148, 163, 184, 0.6);
                color: #0f172a;
            }
            .nl-theme-light .text-slate-50,
            .nl-theme-light .text-slate-100,
            .nl-theme-light .text-slate-200 {
                color: #0f172a;
            }
            .nl-theme-light .text-slate-300 {
                color: #334155;
            }
            .nl-theme-light .text-slate-400,
            .nl-theme-light .text-slate-500 {
                color: #475569;
            }
            .nl-modal-backdrop {
                position: fixed;
                inset: 0;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 24px;
                background: rgba(2, 6, 23, 0.7);
                opacity: 0;
                pointer-events: none;
                transition: opacity 200ms ease;
                z-index: 50;
            }
            .nl-modal-backdrop.is-open {
                opacity: 1;
                pointer-events: auto;
            }
            .nl-modal-panel {
                width: 100%;
                max-width: 720px;
                border-radius: 18px;
                background: linear-gradient(160deg, rgba(15, 23, 42, 0.98), rgba(2, 6, 23, 0.95));
                border: 1px solid rgba(148, 163, 184, 0.2);
                box-shadow: 0 30px 80px rgba(2, 6, 23, 0.55);
            }
            .nl-modal-divider {
                border-color: rgba(148, 163, 184, 0.16);
            }
            .nl-modal-label {
                font-size: 11px;
                letter-spacing: 0.18em;
            }
            .nl-modal-input {
                height: 42px;
                font-size: 13px;
            }
            .nl-modal-primary {
                background: linear-gradient(135deg, #38bdf8, #2563eb);
                color: #020617;
                font-weight: 600;
                box-shadow: 0 12px 24px rgba(37, 99, 235, 0.4);
            }
            .nl-theme-light .nl-modal-backdrop {
                background: rgba(148, 163, 184, 0.55);
            }
            .nl-theme-light .nl-modal-panel {
                background: linear-gradient(160deg, rgba(255, 255, 255, 0.95), rgba(241, 245, 249, 0.95));
                border-color: rgba(148, 163, 184, 0.4);
                color: #0f172a;
            }
            .nl-theme-light .nl-modal-divider {
                border-color: rgba(148, 163, 184, 0.3);
            }
        </style>
    </head>
    <body class="min-h-screen bg-slate-950 text-slate-100 antialiased">
        <div class="min-h-screen bg-[radial-gradient(900px_circle_at_top,rgba(56,189,248,0.18),transparent_60%)]">
            <div class="min-h-screen bg-[radial-gradient(700px_circle_at_bottom,rgba(30,64,175,0.22),transparent_60%)]">
                <div class="min-h-screen bg-[linear-gradient(120deg,rgba(15,23,42,0.9),rgba(2,6,23,0.95))] nl-shell">
                    <div class="flex min-h-screen">
                        <aside class="w-72 border-r border-slate-800/70 bg-slate-950/80 px-6 py-8 nl-panel">
                            <div class="flex items-center gap-3">
                                <div class="flex items-center gap-4 nl-animate-up nl-delay-1">
                                    <div class="flex w-40 items-center justify-center">
                                        <img src="{{ URL::asset('build\Images\logo-light.png') }}" alt="NexLoyal" class="w-auto">
                                    </div>
                                </div>
                            </div>

                            <nav class="mt-10 space-y-2 text-sm">
                                <a href="{{ route('dashboard') }}" class="flex items-center justify-between rounded-xl border border-transparent px-4 py-3 text-slate-300 hover:border-slate-800 hover:bg-slate-900/60 nl-sidebar-link">
                                    <span>Dashboard</span>
                                    <span class="text-xs text-slate-500 nl-text-muted">Overview</span>
                                </a>
                                <a href="{{ route('customers') }}" class="flex items-center justify-between rounded-xl border border-transparent px-4 py-3 text-slate-300 hover:border-slate-800 hover:bg-slate-900/60 nl-sidebar-link">
                                    <span>Customers</span>
                                    <span class="text-xs text-slate-500 nl-text-muted">Segments</span>
                                </a>
                                <a href="{{ route('coupons') }}" class="flex items-center justify-between rounded-xl border border-transparent px-4 py-3 text-slate-300 hover:border-slate-800 hover:bg-slate-900/60 nl-sidebar-link">
                                    <span>Coupons</span>
                                    <span class="text-xs text-slate-500 nl-text-muted">Rewards</span>
                                </a>
                                <a href="#" class="flex items-center justify-between rounded-xl border border-transparent px-4 py-3 text-slate-300 hover:border-slate-800 hover:bg-slate-900/60 nl-sidebar-link">
                                    <span>Notifications</span>
                                    <span class="text-xs text-slate-500 nl-text-muted">Engage</span>
                                </a>
                                <div>
                                    <button id="settings-toggle" type="button" class="flex w-full items-center justify-between rounded-xl border border-transparent px-4 py-3 text-slate-100 bg-slate-900/80 border-slate-800 nl-sidebar-link nl-sidebar-link-active">
                                        <span>Settings</span>
                                        <span class="text-xs text-slate-400 nl-text-muted">Rules</span>
                                    </button>
                                    <div id="settings-menu" class="mt-2 space-y-1 pl-4 text-xs">
                                        <a href="{{ route('profile.edit') }}" class="block rounded-lg px-3 py-2 text-slate-300 hover:bg-slate-900/60">Profile</a>
                                        <a href="{{ route('user-password.edit') }}" class="block rounded-lg px-3 py-2 text-slate-300 hover:bg-slate-900/60">Password</a>
                                        <a href="{{ route('two-factor.show') }}" class="block rounded-lg px-3 py-2 text-slate-300 hover:bg-slate-900/60">Two-Factor Auth</a>
                                        <a href="{{ route('appearance.edit') }}" class="block rounded-lg px-3 py-2 text-slate-300 hover:bg-slate-900/60">Appearance</a>
                                        <a href="{{ route('customer-groups') }}" class="block rounded-lg bg-slate-900/80 px-3 py-2 text-slate-100">Customer groups</a>
                                        <a href="{{ route('tier-rules') }}" class="block rounded-lg px-3 py-2 text-slate-300 hover:bg-slate-900/60">Tier rules</a>
                                    </div>
                                </div>
                            </nav>
                        </aside>

                        <main class="flex-1 px-10 py-8">
                            <x-page-header eyebrow="" title="Customer groups" breadcrumb="Settings / Customer groups">
                                <x-slot name="actions">
                                    <button id="theme-toggle" class="rounded-xl border border-slate-800 bg-slate-900/60 px-4 py-2 text-xs text-slate-200 nl-panel-muted" type="button">
                                        Switch theme
                                    </button>
                                </x-slot>
                            </x-page-header>

                            <section class="mt-6 overflow-hidden rounded-2xl border border-slate-800 bg-slate-900/70 nl-panel">
                                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-800/70 px-6 py-4">
                                    <div>
                                        <p class="text-sm font-semibold text-slate-100">Customer groups</p>
                                        <p class="text-xs text-slate-400">Organize customers by tiers or hand-picked lists.</p>
                                    </div>
                                    <button id="open-create-group" class="rounded-xl bg-slate-100 px-4 py-2 text-xs font-semibold text-slate-900" type="button">
                                        Create group
                                    </button>
                                </div>

                                <div class="px-6 py-5">
                                    <div class="overflow-x-auto">
                                        <table class="w-full text-left text-xs">
                                            <thead class="bg-slate-900/70 text-slate-300">
                                                <tr>
                                                    <th class="px-4 py-3 font-semibold">No</th>
                                                    <th class="px-4 py-3 font-semibold">Group name</th>
                                                    <th class="px-4 py-3 font-semibold">Created date</th>
                                                    <th class="px-4 py-3 font-semibold">Status</th>
                                                    <th class="px-4 py-3 text-center font-semibold">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody id="group-rows" class="divide-y divide-slate-800/80 text-slate-200">
                                                <tr id="group-empty">
                                                    <td colspan="5" class="px-4 py-10 text-center text-slate-400">No groups yet. Create one to get started.</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </section>
                        </main>
                    </div>
                </div>
            </div>
        </div>

        <div id="create-group-modal" class="nl-modal-backdrop" aria-hidden="true">
            <div class="nl-modal-panel">
                <div class="flex items-start justify-between border-b border-slate-800 px-6 py-5 nl-modal-divider">
                    <div>
                        <p id="group-modal-eyebrow" class="text-xs uppercase tracking-[0.35em] text-slate-400">Create group</p>
                        <p id="group-modal-title" class="mt-2 text-lg font-semibold text-slate-100">Build a customer group</p>
                        <p id="group-modal-subtitle" class="mt-1 text-xs text-slate-400">Select tiers or customers to include.</p>
                    </div>
                    <button type="button" class="rounded-full border border-slate-700 px-2.5 py-1 text-xs text-slate-200" data-modal-close>
                        Close
                    </button>
                </div>
                <form id="create-group-form" class="px-6 py-6">
                    <input id="group-id" type="hidden" value="">
                    <div id="group-error" class="mb-5 hidden rounded-xl border border-rose-500/40 bg-rose-500/10 px-4 py-3 text-xs text-rose-200">
                        Please complete the group name and select at least one item.
                    </div>
                    <div class="grid gap-4">
                        <div class="flex flex-col gap-2">
                            <label class="nl-modal-label uppercase text-slate-400">Group name</label>
                            <input id="group-name" class="nl-modal-input rounded-lg border border-slate-700 bg-slate-950/60 px-3 text-slate-200" type="text" placeholder="VIP Launch List">
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="nl-modal-label uppercase text-slate-400">Type</label>
                            <select id="group-type" class="nl-modal-input rounded-lg border border-slate-700 bg-slate-950/60 px-3 text-slate-200">
                                <option value="">Select type</option>
                                <option value="tiers">Tiers</option>
                                <option value="customers">Customers</option>
                            </select>
                        </div>
                        <div id="tier-options" class="hidden rounded-xl border border-slate-800 bg-slate-950/40 p-4">
                            <p class="text-xs uppercase tracking-[0.2em] text-slate-400">Select tiers</p>
                            <div class="mt-3 grid gap-2 text-sm text-slate-200">
                                @foreach ($tiers as $tier)
                                    <label class="flex items-center gap-2">
                                        <input type="checkbox" class="tier-checkbox rounded border-slate-600 bg-slate-900 text-sky-400" value="{{ $tier }}">
                                        <span>{{ $tier }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        <div id="customer-options" class="hidden rounded-xl border border-slate-800 bg-slate-950/40 p-4">
                            <p class="text-xs uppercase tracking-[0.2em] text-slate-400">Select customers</p>
                            <div class="mt-3 max-h-52 overflow-y-auto space-y-2 text-sm text-slate-200">
                                @foreach ($customers as $customer)
                                    <label class="flex items-center gap-2">
                                        <input type="checkbox" class="customer-checkbox rounded border-slate-600 bg-slate-900 text-sky-400" value="{{ $customer['id'] }}">
                                        <span>{{ $customer['name'] }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    <div class="mt-6 flex flex-wrap items-center justify-end gap-3 border-t border-slate-800 pt-5 nl-modal-divider">
                        <button type="button" class="rounded-xl border border-slate-700 px-4 py-2 text-xs text-slate-200" data-modal-close>
                            Cancel
                        </button>
                        <button type="button" id="save-group" class="nl-modal-primary rounded-xl px-5 py-2 text-xs">
                            Save group
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <script>
            (function () {
                const storageKey = 'nl-theme';
                const body = document.body;
                const button = document.getElementById('theme-toggle');
                const modal = document.getElementById('create-group-modal');
                const openModalButton = document.getElementById('open-create-group');
                const closeButtons = modal ? modal.querySelectorAll('[data-modal-close]') : [];
                const groupType = document.getElementById('group-type');
                const tierOptions = document.getElementById('tier-options');
                const customerOptions = document.getElementById('customer-options');
                const groupName = document.getElementById('group-name');
                const saveButton = document.getElementById('save-group');
                const groupRows = document.getElementById('group-rows');
                const groupEmpty = document.getElementById('group-empty');
                const errorBanner = document.getElementById('group-error');
                const groupIdInput = document.getElementById('group-id');
                const modalEyebrow = document.getElementById('group-modal-eyebrow');
                const modalTitle = document.getElementById('group-modal-title');
                const modalSubtitle = document.getElementById('group-modal-subtitle');

                const groups = [];

                const applyTheme = (theme) => {
                    if (theme === 'light') {
                        body.classList.add('nl-theme-light');
                    } else {
                        body.classList.remove('nl-theme-light');
                    }
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

                const setModalOpen = (isOpen) => {
                    if (!modal) {
                        return;
                    }
                    modal.classList.toggle('is-open', isOpen);
                    modal.setAttribute('aria-hidden', String(!isOpen));
                    if (!isOpen) {
                        const form = document.getElementById('create-group-form');
                        if (form) {
                            form.reset();
                        }
                        if (groupIdInput) {
                            groupIdInput.value = '';
                        }
                        if (tierOptions) {
                            tierOptions.classList.add('hidden');
                        }
                        if (customerOptions) {
                            customerOptions.classList.add('hidden');
                        }
                        if (errorBanner) {
                            errorBanner.classList.add('hidden');
                        }
                        if (modalEyebrow) {
                            modalEyebrow.textContent = 'Create group';
                        }
                        if (modalTitle) {
                            modalTitle.textContent = 'Build a customer group';
                        }
                        if (modalSubtitle) {
                            modalSubtitle.textContent = 'Select tiers or customers to include.';
                        }
                        if (saveButton) {
                            saveButton.textContent = 'Save group';
                        }
                    }
                };

                if (openModalButton) {
                    openModalButton.addEventListener('click', () => setModalOpen(true));
                }

                if (modal) {
                    modal.addEventListener('click', (event) => {
                        if (event.target === modal) {
                            setModalOpen(false);
                        }
                    });
                }

                closeButtons.forEach((button) => {
                    button.addEventListener('click', () => setModalOpen(false));
                });

                document.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape') {
                        setModalOpen(false);
                    }
                });

                if (groupType) {
                    groupType.addEventListener('change', () => {
                        const value = groupType.value;
                        if (tierOptions) {
                            tierOptions.classList.toggle('hidden', value !== 'tiers');
                        }
                        if (customerOptions) {
                            customerOptions.classList.toggle('hidden', value !== 'customers');
                        }
                    });
                }

                const getSelectedValues = (selector) =>
                    Array.from(document.querySelectorAll(selector))
                        .filter((input) => input.checked)
                        .map((input) => input.value);

                const setCheckedValues = (selector, values) => {
                    const lookup = new Set(values);
                    document.querySelectorAll(selector).forEach((input) => {
                        input.checked = lookup.has(input.value);
                    });
                };

                const renderGroups = () => {
                    if (!groupRows) {
                        return;
                    }
                    groupRows.innerHTML = '';

                    if (groups.length === 0) {
                        if (groupEmpty) {
                            groupRows.appendChild(groupEmpty);
                        }
                        return;
                    }

                    groups.forEach((group, index) => {
                        const row = document.createElement('tr');
                        row.className = 'nl-table-row';
                        row.innerHTML = `
                            <td class="px-4 py-4">${index + 1}</td>
                            <td class="px-4 py-4">${group.name}</td>
                            <td class="px-4 py-4 text-slate-300">${group.createdAt}</td>
                            <td class="px-4 py-4 text-slate-300">Active</td>
                            <td class="px-4 py-4 text-center">
                                <button type="button" class="group-view rounded-lg border border-slate-700 px-3 py-1 text-xs text-slate-200" data-group-id="${group.id}">View</button>
                            </td>
                        `;
                        groupRows.appendChild(row);
                    });
                };

                const openEditModal = (group) => {
                    if (!groupName || !groupType || !groupIdInput) {
                        return;
                    }
                    groupIdInput.value = String(group.id);
                    groupName.value = group.name;
                    groupType.value = group.type;

                    if (tierOptions) {
                        tierOptions.classList.toggle('hidden', group.type !== 'tiers');
                    }
                    if (customerOptions) {
                        customerOptions.classList.toggle('hidden', group.type !== 'customers');
                    }

                    setCheckedValues('.tier-checkbox', group.tiers || []);
                    setCheckedValues('.customer-checkbox', (group.customers || []).map(String));

                    if (modalEyebrow) {
                        modalEyebrow.textContent = 'Edit group';
                    }
                    if (modalTitle) {
                        modalTitle.textContent = 'Update customer group';
                    }
                    if (modalSubtitle) {
                        modalSubtitle.textContent = 'Adjust tiers or customers in this group.';
                    }
                    if (saveButton) {
                        saveButton.textContent = 'Update group';
                    }
                    setModalOpen(true);
                };

                if (saveButton) {
                    saveButton.addEventListener('click', () => {
                        if (!groupRows || !groupName || !groupType) {
                            return;
                        }

                        const selectedTiers = getSelectedValues('.tier-checkbox');
                        const selectedCustomers = getSelectedValues('.customer-checkbox');
                        const type = groupType.value;

                        const isValid = groupName.value.trim() !== '' && (
                            (type === 'tiers' && selectedTiers.length > 0) ||
                            (type === 'customers' && selectedCustomers.length > 0)
                        );

                        if (!isValid) {
                            if (errorBanner) {
                                errorBanner.classList.remove('hidden');
                            }
                            return;
                        }

                        if (errorBanner) {
                            errorBanner.classList.add('hidden');
                        }

                        const now = new Date();
                        const createdAt = now.toLocaleDateString('en-US', {
                            month: 'short',
                            day: '2-digit',
                            year: 'numeric',
                        });
                        const groupId = groupIdInput ? groupIdInput.value : '';
                        const payload = {
                            id: groupId ? Number(groupId) : Date.now(),
                            name: groupName.value.trim(),
                            type,
                            tiers: type === 'tiers' ? selectedTiers : [],
                            customers: type === 'customers' ? selectedCustomers : [],
                            createdAt: groupId ? null : createdAt,
                        };

                        if (groupId) {
                            const index = groups.findIndex((group) => group.id === payload.id);
                            if (index !== -1) {
                                groups[index] = {
                                    ...groups[index],
                                    name: payload.name,
                                    type: payload.type,
                                    tiers: payload.tiers,
                                    customers: payload.customers,
                                };
                            }
                        } else {
                            groups.push({
                                id: payload.id,
                                name: payload.name,
                                type: payload.type,
                                tiers: payload.tiers,
                                customers: payload.customers,
                                createdAt,
                            });
                        }

                        renderGroups();
                        setModalOpen(false);
                    });
                }

                if (groupRows) {
                    groupRows.addEventListener('click', (event) => {
                        const target = event.target;
                        if (!target || !target.classList.contains('group-view')) {
                            return;
                        }
                        const groupId = Number(target.getAttribute('data-group-id'));
                        const group = groups.find((item) => item.id === groupId);
                        if (group) {
                            openEditModal(group);
                        }
                    });
                }

                const settingsToggle = document.getElementById('settings-toggle');
                const settingsMenu = document.getElementById('settings-menu');
                if (settingsToggle && settingsMenu) {
                    settingsToggle.addEventListener('click', () => {
                        settingsMenu.classList.toggle('hidden');
                    });
                }

                renderGroups();
            })();
        </script>
    </body>
</html>
