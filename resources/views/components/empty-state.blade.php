<div {{ $attributes->merge(['class' => 'flex flex-col items-center gap-3 rounded-xl border border-line bg-card px-6 py-12 text-center']) }}>
    <span class="flex size-14 items-center justify-center rounded-full bg-surface text-muted">
        <x-icon name="{{ $icon ?? 'check-circle' }}" class="size-6" />
    </span>
    <p class="text-base font-bold">{{ $title }}</p>
    @isset($description)
        <p class="max-w-xs text-sm text-ink/60">{{ $description }}</p>
    @endisset
    {{ $slot }}
</div>
