@php [$text, $iconBg, $bar] = match($tone ?? 'primary') {
    'success' => ['text-success', 'bg-success-50', 'bg-success'],
    'warning' => ['text-warning', 'bg-warning-50', 'bg-warning'],
    'danger' => ['text-danger', 'bg-danger-50', 'bg-danger'],
    'info' => ['text-primary', 'bg-primary-50', 'bg-primary'],
    default => ['text-primary', 'bg-primary-50', 'bg-primary'],
} @endphp
<div {{ $attributes->merge(['class' => 'flex items-center gap-4 rounded-xl border border-line bg-card p-4']) }}>
    <div class="flex h-16 w-1.5 flex-col items-center justify-center overflow-hidden rounded-full bg-surface">
        <span class="h-full w-1.5 {{ $bar }}"></span>
    </div>
    <span class="flex size-11 shrink-0 items-center justify-center rounded-lg {{ $iconBg }} {{ $text }}">
        <x-icon name="{{ $icon }}" class="size-5" />
    </span>
    <div>
        <p class="text-3xl font-extrabold leading-none tracking-tight">{{ $value }}</p>
        <p class="mt-1 text-xs font-medium text-ink/60">{{ $label }}</p>
        @isset($slot)
            <div class="mt-1.5">{{ $slot }}</div>
        @endisset
    </div>
</div>
