<div {{ $attributes->merge(['class' => 'rounded-xl border border-line bg-card p-4']) }}>
    <div class="flex items-center gap-3">
        <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-bold text-white">{{ substr($name, 0, 1) }}</span>
        <div class="min-w-0">
            <p class="truncate text-sm font-bold">{{ $name }}</p>
            <p class="truncate text-xs text-ink/60">{{ $meta ?? '' }}</p>
        </div>
        @isset($code)
            <x-status-badge tone="danger" class="ml-auto shrink-0">{{ $code }}</x-status-badge>
        @endisset
    </div>
    <div class="mt-3 grid grid-cols-2 gap-2">
        @isset($phone)
            <a href="tel:{{ $phone }}" class="flex h-11 items-center justify-center gap-2 rounded-lg bg-primary text-sm font-semibold text-white transition hover:bg-primary/90">
                <x-icon name="phone" class="size-4" /> Telepon
            </a>
        @else
            <span class="col-span-2 flex h-11 items-center justify-center rounded-lg bg-surface text-sm font-medium text-muted">Detail peserta</span>
        @endisset
    </div>
</div>
