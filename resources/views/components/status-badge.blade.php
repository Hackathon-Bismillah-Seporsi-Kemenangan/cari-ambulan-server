@props(['status' => '', 'tone' => null, 'dot' => false])
@php
$tone = $tone ?? match(true) {
    str_contains((string) $status, 'search') || str_contains((string) $status, 'pending') => 'warning',
    str_contains((string) $status, 'complete') => 'success',
    str_contains((string) $status, 'cancel') => 'danger',
    str_contains((string) $status, 'assign') || str_contains((string) $status, 'accept') || str_contains((string) $status, 'route') || str_contains((string) $status, 'pickup') => 'info',
    default => 'muted',
};
@endphp
<span {{ $attributes->merge([
    'class' => 'inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold capitalize ' . match($tone) {
        'warning' => 'bg-warning-50 text-warning',
        'success' => 'bg-success-50 text-success',
        'danger' => 'bg-danger-50 text-danger',
        'info' => 'bg-primary-50 text-primary',
        default => 'bg-surface text-muted',
    },
]) }}>
    @if($dot ?? false)
        <span class="size-1.5 rounded-full {{ match($tone) { 'warning' => 'bg-warning', 'success' => 'bg-success', 'danger' => 'bg-danger', 'info' => 'bg-primary', default => 'bg-muted' } }}"></span>
    @endif
    {{ $slot }}
</span>
