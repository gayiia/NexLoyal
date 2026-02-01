{{-- This widget view lets customers claim a mystery box reward. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        {{-- The title uses the app name configuration with a fallback for local/dev environments. --}}
        <title>{{ config('app.name', 'NexLoyal') }} - Mystery Box</title>
        {{-- Preconnect and load the UI font used for the widget. --}}
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
        {{-- Vite builds and injects the compiled CSS for this page. --}}
        @vite(['resources/css/app.css'])
        <style>
            {{-- These styles are self-contained to avoid Shopify theme conflicts. --}}
            :root { color-scheme: dark; }
            body {
                font-family: "Instrument Sans", ui-sans-serif, system-ui, sans-serif;
                background: #0b1220;
                color: #e2e8f0;
            }
            .nl-card {
                background: #0f172a;
                border: 1px solid rgba(148, 163, 184, 0.18);
                border-radius: 16px;
                padding: 20px;
                box-shadow: 0 24px 48px rgba(2, 6, 23, 0.45);
            }
            .nl-wheel {
                border: 1px solid rgba(148, 163, 184, 0.25);
                border-radius: 14px;
                background: #0b1324;
                height: 180px;
                overflow: hidden;
                position: relative;
            }
            .nl-wheel::after {
                content: "";
                position: absolute;
                left: 16px;
                right: 16px;
                top: 50%;
                height: 36px;
                transform: translateY(-50%);
                border: 1px solid rgba(56, 189, 248, 0.35);
                border-radius: 10px;
                pointer-events: none;
            }
            .nl-wheel-list {
                transition: transform 2.6s cubic-bezier(0.2, 0.8, 0.2, 1);
            }
            .nl-wheel-item {
                height: 36px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 13px;
                color: #e2e8f0;
            }
        </style>
    </head>
    <body class="min-h-screen">
        <div class="mx-auto flex min-h-screen max-w-3xl flex-col gap-6 px-6 py-12">
            <header>
                <p class="text-xs uppercase tracking-[0.3em] text-slate-400">Rewards</p>
                <h1 class="text-3xl font-semibold text-slate-100">Mystery Box</h1>
                {{-- This subtitle explains the interaction to customers. --}}
                <p class="mt-2 text-sm text-slate-400">Spin the wheel to reveal your reward.</p>
            </header>

            <section class="nl-card">
                {{-- If the widget cannot load, show a friendly error. --}}
                @if (!empty($error))
                    <p class="text-sm text-rose-300">{{ $error }}</p>
                @else
                    <div id="mystery-box-content">
                        <div class="flex items-center gap-3 text-sm text-slate-300">
                            <div class="h-4 w-4 animate-spin rounded-full border-2 border-slate-500 border-t-slate-200"></div>
                            Loading Mystery Box...
                        </div>
                    </div>
                @endif
            </section>
        </div>

        {{-- The widget logic only runs when the backend didn't return an error. --}}
        @if (empty($error))
        <script>
            (function () {
                // Token identifies the customer and is required for all widget API calls.
                const token = @json($token ?? '');
                const content = document.getElementById('mystery-box-content');

                if (!token) {
                    content.textContent = 'Missing token.';
                    return;
                }

                // Fetch the active mystery box for this customer.
                const activeUrl = `/api/widget/mystery-box/active?token=${encodeURIComponent(token)}`;

                fetch(activeUrl, { method: 'GET' })
                    .then((response) => response.json())
                    .then((payload) => {
                        const box = payload && payload.box ? payload.box : null;
                        const wheelItems = payload && Array.isArray(payload.wheel_items) ? payload.wheel_items : [];

                        if (!box) {
                            content.textContent = payload && payload.message ? payload.message : 'No active Mystery Box available.';
                            return;
                        }

                        // Can claim indicates whether the user is currently eligible.
                        const canClaim = !!box.can_claim;
                        const nextClaim = box.next_claim_at ? new Date(box.next_claim_at) : null;
                        const nextLabel = nextClaim && !isNaN(nextClaim.getTime())
                            ? nextClaim.toISOString().slice(0, 10)
                            : '';

                        content.innerHTML = `
                            <p class="text-sm text-slate-300">Try your luck and unlock a surprise reward.</p>
                            <p class="mt-2 text-xs text-slate-400">Your reward will be added to My Coupons.</p>
                            <div class="mt-6 flex flex-wrap gap-3">
                                <button id="claim-button" class="rounded-lg bg-slate-100 px-4 py-2 text-xs font-semibold text-slate-900" ${canClaim ? '' : 'disabled'}>
                                    Claim reward
                                </button>
                                <a href="/loyalty/my-coupons?token=${encodeURIComponent(token)}" class="rounded-lg border border-slate-700 px-4 py-2 text-xs text-slate-200">
                                    View My Coupons
                                </a>
                            </div>
                            ${canClaim ? '' : `<p class="mt-3 text-xs text-slate-400">Already claimed${nextLabel ? `. Try again on ${nextLabel}` : ''}.</p>`}
                        `;

                        const claimButton = document.getElementById('claim-button');
                        if (!claimButton) {
                            return;
                        }

                        claimButton.addEventListener('click', () => {
                            if (claimButton.disabled) {
                                return;
                            }
                            // Disable the button while the claim is processed.
                            claimButton.disabled = true;
                            claimButton.textContent = 'Spinning...';

                            // Claiming triggers the backend to select a reward.
                            fetch(`/api/widget/mystery-box/${box.id}/claim?token=${encodeURIComponent(token)}`, { method: 'POST' })
                                .then((response) => response.json())
                                .then((result) => {
                                    if (!result || !result.won) {
                                        throw new Error(result && result.message ? result.message : 'Unable to claim right now');
                                    }

                                    // Build a looping wheel from the reward titles.
                                    const won = result.won;
                                    let titles = (Array.isArray(result.wheel_items) ? result.wheel_items : wheelItems)
                                        .map((item) => item.title)
                                        .filter(Boolean);
                                    if (!titles.length) {
                                        titles = [won.title || 'Reward'];
                                    }

                                    const repeats = 4;
                                    let itemsHtml = '';
                                    for (let i = 0; i < repeats; i++) {
                                        titles.forEach((title) => {
                                            itemsHtml += `<div class="nl-wheel-item">${title}</div>`;
                                        });
                                    }

                                    content.innerHTML = `
                                        <div class="nl-wheel">
                                            <div id="wheel-list" class="nl-wheel-list">${itemsHtml}</div>
                                        </div>
                                        <div id="wheel-result" class="mt-5"></div>
                                    `;

                                    const wheelList = document.getElementById('wheel-list');
                                    const itemHeight = 36;
                                    let baseIndex = titles.indexOf(won.title);
                                    if (baseIndex < 0) {
                                        baseIndex = 0;
                                    }
                                    const targetIndex = baseIndex + (titles.length * 2);
                                    // Animate the wheel to the winning position.
                                    setTimeout(() => {
                                        if (wheelList) {
                                            wheelList.style.transform = `translateY(${-targetIndex * itemHeight}px)`;
                                        }
                                    }, 60);

                                    // Show the reward details after the spin completes.
                                    setTimeout(() => {
                                        const resultEl = document.getElementById('wheel-result');
                                        if (!resultEl) {
                                            return;
                                        }
                                        resultEl.innerHTML = `
                                            <div class="mt-4 rounded-xl border border-slate-800 bg-slate-950/60 p-4">
                                                <p class="text-sm text-slate-300">You won:</p>
                                                <p class="mt-1 text-lg font-semibold text-slate-100">${won.title || 'Reward'}</p>
                                                <p class="mt-2 rounded-lg border border-slate-700 bg-slate-900/70 px-3 py-2 text-center text-sm tracking-[0.2em] text-slate-200">${won.code}</p>
                                            </div>
                                        `;
                                    }, 2700);
                                })
                                .catch((error) => {
                                    // Fall back to a plain error message on failure.
                                    content.textContent = error.message || 'Unable to claim right now';
                                });
                        });
                    })
                    .catch(() => {
                        // Network or server errors show a simple fallback message.
                        content.textContent = 'Unable to load Mystery Box right now.';
                    });
            })();
        </script>
        @endif
    </body>
</html>
