{{-- This view renders the admin login page with branding and the sign-in form. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- The title uses the app name configuration with a fallback for local/dev environments. --}}
        <title>{{ config('app.name', 'NexLoyal') }} - Login</title>

        {{-- Preconnect and load the UI font used across the admin experience. --}}
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

        {{-- Vite builds and injects the compiled CSS for this page. --}}
        @vite(['resources/css/app.css'])
        <style>
            {{-- These keyframes power simple entrance animations on load. --}}
            @keyframes nl-fade-up {
                0% { opacity: 0; transform: translateY(18px); }
                100% { opacity: 1; transform: translateY(0); }
            }
            @keyframes nl-fade-right {
                0% { opacity: 0; transform: translateX(18px); }
                100% { opacity: 1; transform: translateX(0); }
            }
            .nl-animate-up { animation: nl-fade-up 700ms ease-out both; }
            .nl-animate-right { animation: nl-fade-right 700ms ease-out both; }
            .nl-delay-1 { animation-delay: 120ms; }
            .nl-delay-2 { animation-delay: 240ms; }
            .nl-delay-3 { animation-delay: 360ms; }
            .nl-delay-4 { animation-delay: 480ms; }
        </style>
    </head>
    <body class="min-h-screen bg-slate-950 text-slate-100 antialiased">
        <div class="relative min-h-screen overflow-hidden">
            <div class="absolute inset-0 bg-[radial-gradient(900px_circle_at_top,rgba(56,189,248,0.22),transparent_55%)]"></div>
            <div class="absolute inset-0 bg-[radial-gradient(700px_circle_at_bottom,rgba(30,64,175,0.25),transparent_60%)]"></div>
            <div class="absolute inset-0 bg-[linear-gradient(135deg,rgba(15,23,42,0.85),rgba(2,6,23,0.95))]"></div>

            <main class="relative flex min-h-screen items-center justify-center px-6 py-12">
                <div class="w-full max-w-6xl">
                    <div class="grid gap-12 lg:grid-cols-[1.1fr_0.9fr] lg:items-center">
                        <section class="space-y-10">


                            {{-- Logo and marketing copy introduce the platform. --}}
                            <div class="flex items-center gap-4 nl-animate-up nl-delay-1">
                                <div class="flex w-40 items-center justify-center">
                                    <img src="{{ $appLogoUrl }}" alt="NexLoyal" class="w-auto">
                                </div>
                            </div>

                            {{-- Product value propositions are summarized for visitors. --}}
                            <div class="space-y-4 nl-animate-up nl-delay-2">
                                <h1 class="text-3xl font-semibold tracking-tight text-slate-50 sm:text-4xl">
                                    Loyalty that feels personal, powered by data.
                                </h1>
                                <p class="max-w-xl text-base text-slate-300">
                                    Reward repeat customers with points, tiers, and targeted perks. Automate campaigns, surface
                                    real-time progress, and turn visits into lasting relationships.
                                </p>
                            </div>

                            {{-- Highlight cards provide quick product proof points. --}}
                            <div class="grid gap-4 sm:grid-cols-3 nl-animate-up nl-delay-3">
                                <div class="rounded-xl border border-slate-800 bg-slate-900/70 p-4 shadow-lg shadow-slate-950/40">
                                    <p class="text-xs uppercase tracking-[0.2em] text-slate-400">Average lift</p>
                                    <p class="mt-2 text-lg font-semibold text-slate-50">18% revenue</p>
                                    <p class="mt-1 text-xs text-slate-400">from repeat purchases</p>
                                </div>
                                <div class="rounded-xl border border-slate-800 bg-slate-900/70 p-4 shadow-lg shadow-slate-950/40">
                                    <p class="text-xs uppercase tracking-[0.2em] text-slate-400">Reward options</p>
                                    <p class="mt-2 text-lg font-semibold text-slate-50">5 types</p>
                                    <p class="mt-1 text-xs text-slate-400">points, tiers, referrals</p>
                                </div>
                                <div class="rounded-xl border border-slate-800 bg-slate-900/70 p-4 shadow-lg shadow-slate-950/40">
                                    <p class="text-xs uppercase tracking-[0.2em] text-slate-400">Engagement</p>
                                    <p class="mt-2 text-lg font-semibold text-slate-50">Chat + polls</p>
                                    <p class="mt-1 text-xs text-slate-400">always-on feedback</p>
                                </div>
                            </div>

                          

                            {{-- The footer note reinforces positioning. --}}
                            <p class="text-sm text-slate-400 nl-animate-up nl-delay-4">
                                Trusted by teams building loyalty without heavy Shopify app overhead.
                            </p>
                        </section>

                        {{-- The sign-in panel posts to the auth endpoint. --}}
                        <section class="rounded-2xl border border-slate-800 bg-slate-900/85 p-6 shadow-2xl shadow-slate-950/60 backdrop-blur sm:p-8 nl-animate-right nl-delay-2">
                            <div class="mb-6 space-y-2">
                                <h2 class="text-2xl font-semibold text-slate-50">Sign in</h2>
                                <p class="text-sm text-slate-300">Access your NexLoyal admin workspace.</p>
                            </div>

                            {{-- Validation errors are listed above the form. --}}
                            @if ($errors->any())
                                <div class="mb-4 rounded-md border border-rose-500/40 bg-rose-500/10 px-4 py-3 text-sm text-rose-200">
                                    <ul class="space-y-1">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            {{-- The login form expects email and password. --}}
                            <form method="POST" action="{{ route('login') }}" class="space-y-5">
                                @csrf

                                <div class="space-y-2">
                                    {{-- Email is used as the login identifier. --}}
                                    <label for="email" class="text-sm font-medium text-slate-100">Email address</label>
                                    <input
                                        id="email"
                                        name="email"
                                        type="email"
                                        autocomplete="email"
                                        required
                                        autofocus
                                        value="{{ old('email') }}"
                                        class="flex h-11 w-full rounded-md border border-slate-800 bg-slate-950/60 px-3 py-2 text-sm text-slate-100 shadow-sm transition-colors placeholder:text-slate-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-400"
                                        placeholder="example@email.com"
                                    >
                                </div>

                                <div class="space-y-2">
                                    <div class="flex items-center justify-between">
                                        <label for="password" class="text-sm font-medium text-slate-100">Password</label>

                                    </div>
                                    {{-- Password is required for authentication. --}}
                                    <input
                                        id="password"
                                        name="password"
                                        type="password"
                                        autocomplete="current-password"
                                        required
                                        class="flex h-11 w-full rounded-md border border-slate-800 bg-slate-950/60 px-3 py-2 text-sm text-slate-100 shadow-sm transition-colors placeholder:text-slate-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-400"
                                        placeholder="Enter your password"
                                    >
                                </div>

                                <div class="flex items-center justify-between">
                                    {{-- Remember me toggles a long-lived session cookie. --}}
                                    <label class="flex items-center gap-2 text-sm text-slate-400" for="remember">
                                        <input
                                            id="remember"
                                            name="remember"
                                            type="checkbox"
                                            class="h-4 w-4 rounded border border-slate-700 bg-slate-950 text-sky-400 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-400"
                                            {{ old('remember') ? 'checked' : '' }}
                                        >
                                        Remember me
                                    </label>
                                </div>

                                {{-- Submit triggers the login action. --}}
                                <button
                                    type="submit"
                                    class="inline-flex h-11 w-full items-center justify-center rounded-md bg-sky-400 px-4 py-2 text-sm font-medium text-slate-950 shadow-lg shadow-sky-500/40 transition-colors hover:bg-sky-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-300"
                                >
                                    Sign in
                                </button>
                            </form>
                        </section>
                    </div>
                </div>
            </main>
        </div>
    </body>
</html>
