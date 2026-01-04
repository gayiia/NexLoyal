@props([
    'eyebrow' => null,
    'title',
    'subtitle' => null,
    'breadcrumb' => null,
])

<header {{ $attributes->merge(['class' => 'flex flex-wrap items-center justify-between gap-4']) }}>
    <div>
        @if ($eyebrow)
            <p class="text-xs uppercase tracking-[0.3em] text-slate-400 nl-text-muted">{{ $eyebrow }}</p>
        @endif
        <h1 class="text-3xl font-semibold text-slate-50">{{ $title }}</h1>
        @if ($subtitle)
            <p class="mt-2 text-sm text-slate-300 nl-text-muted">{{ $subtitle }}</p>
        @endif
    </div>
    <div class="flex flex-wrap items-center gap-3 text-xs text-slate-400">
        @if ($breadcrumb)
            <span>{{ $breadcrumb }}</span>
        @endif
        {{ $actions ?? $slot }}
    </div>
</header>
