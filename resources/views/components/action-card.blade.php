@php $accent = match($tone ?? 'primary') {
    'success' => ['text-success', 'bg-success-50', 'bg-success/10'],
    'warning' => ['text-warning', 'bg-warning-50', 'bg-warning/10'],
    'danger' => ['text-danger', 'bg-danger-50', 'bg-danger/10'],
    'info' => ['text-primary', 'bg-primary-50', 'bg-primary/10'],
    default => ['text-primary', 'bg-primary-50', 'bg-primary/10'],
} @endphp
<div {{ $attributes->merge(['class' => 'flex gap-4 rounded-xl border border-line bg-card p-4']) }}>
    <span class="flex size-11 shrink-0 items-center justify-center rounded-lg {{ $accent[1] }} {{ $accent[0] }}">
        <x-icon name="{{ $icon }}" class="size-5" />
    </span>
    <div class="min-w-0">
        <div class="flex items-start justify-between gap-3">
            <p class="text-base font-bold leading-tight">{{ $title }}</p>
            @isset($code)
                <span class="shrink-0 rounded-md bg-surface px-1.5 py-0.5 text-[10px] font-semibold text-muted">{{ $code }}</span>
            @endisset
        </div>
        <p class="mt-1 text-xs leading-5 text-ink/60">{{ $subtitle }}</p>
        @isset($slot)
            <div class="mt-2">{{ $slot }}</div>
        @endisset
    </div>
</div>
