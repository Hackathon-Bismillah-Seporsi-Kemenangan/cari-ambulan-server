@props(['href', 'label', 'icon', 'active' => null, 'badge' => null, 'badgeTone' => 'default', 'routeIs' => null])
@php
    $active = $active ?? request()->routeIs($routeIs ?? (parse_url($href, PHP_URL_PATH)));
@endphp
<a
    href="{{ $href }}"
    {{ $attributes->merge([
        'class' => 'group flex h-12 min-w-0 md:h-11 md:w-full flex-col md:flex-row items-center justify-center md:justify-between gap-0.5 md:gap-3 px-2.5 md:px-3 self-stretch rounded-xl text-[10px] md:text-xs font-semibold transition cursor-pointer ' .
        ($active
            ? 'bg-sky-600 text-white font-bold shadow-xs'
            : 'text-slate-300 hover:bg-slate-800/80 hover:text-white')
    ]) }}
>
    <div class="flex items-center gap-1 md:gap-2.5 min-w-0">
        <x-icon name="{{ $icon }}" class="size-4 shrink-0 {{ $active ? 'text-white' : 'text-slate-400 group-hover:text-white' }}" />
        <span class="truncate">{{ $label }}</span>
    </div>
    @if($badge !== null)
        <span class="hidden md:inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold {{ $active ? 'bg-white/20 text-white border border-white/20' : ($badgeTone === 'danger' ? 'bg-rose-500/20 text-rose-300 border border-rose-500/30' : ($badgeTone === 'success' ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' : 'bg-slate-800 text-slate-300 border border-slate-700/60')) }}">
            {{ $badge }}
        </span>
    @endif
</a>
