{{-- This reusable component renders a page title block with optional metadata and actions. --}}
@props([
    'eyebrow' => null,
    'title',
    'subtitle' => null,
    'breadcrumb' => null,
])

<header {{ $attributes->merge(['class' => 'flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between']) }}>
    <div class="min-w-0">
        {{-- Eyebrow text is optional and used for small section labels. --}}
        @if ($eyebrow)
            <p class="text-xs uppercase tracking-[0.3em] text-slate-400 nl-text-muted">{{ $eyebrow }}</p>
        @endif
        {{-- Title is required and acts as the main heading. --}}
        <h1 class="text-2xl font-semibold text-slate-50 sm:text-3xl">{{ $title }}</h1>
        {{-- Subtitle is optional supporting text. --}}
        @if ($subtitle)
            <p class="mt-2 text-sm text-slate-300 nl-text-muted">{{ $subtitle }}</p>
        @endif
    </div>
    <div class="flex w-full flex-wrap items-center gap-3 text-xs text-slate-400 sm:w-auto sm:justify-end">
        {{-- Breadcrumbs provide lightweight navigation context. --}}
        @if ($breadcrumb)
            <span>{{ $breadcrumb }}</span>
        @endif
        {{-- Actions render either the named slot or default slot content. --}}
        {{ $actions ?? $slot }}
    </div>
</header>
