@php $style = match($tone ?? 'info') {
    'success' => ['bg-success-50 border-success/30', 'text-success', 'check-circle'],
    'warning' => ['bg-warning-50 border-warning/30', 'text-warning', 'alert'],
    'danger' => ['bg-danger-50 border-danger/30', 'text-danger', 'alert'],
    default => ['bg-primary-50 border-primary/30', 'text-primary', 'check-circle'],
} @endphp
<div class="flex items-start gap-3 rounded-xl border p-4 {{ $style[0] }}" {{ $attributes->except('tone') }}>
    <x-icon name="{{ $style[2] }}" class="mt-0.5 size-4 shrink-0 {{ $style[1] }}" />
    <div class="min-w-0 flex-1 text-sm text-ink/80">{{ $slot }}</div>
    @if($dismissible ?? false)
        <button type="button" class="shrink-0 text-muted transition hover:text-ink" data-dismiss>&times;</button>
    @endif
</div>
