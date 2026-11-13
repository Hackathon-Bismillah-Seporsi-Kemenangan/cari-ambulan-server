@props(['active' => 'overview'])

<div class="hidden md:block px-2 pb-1.5 text-[10px] font-bold uppercase tracking-wider text-slate-400">
    Pusat Komando
</div>

<x-nav-link
    :href="route('dashboard.admin')"
    label="Pusat Komando"
    icon="meters"
    :active="$active === 'overview'"
    badge="Live"
    badgeTone="success"
/>

<x-nav-link
    :href="route('dashboard.admin') . '#emergency'"
    label="Panggilan Darurat"
    icon="sos"
    :active="$active === 'emergency'"
/>

<div class="hidden md:block my-2 border-t border-slate-800"></div>

<div class="hidden md:block px-2 pt-1 pb-1.5 text-[10px] font-bold uppercase tracking-wider text-slate-400">
    Master Data & Akun
</div>

<x-nav-link
    :href="route('admin.users.index')"
    label="Manajemen User"
    icon="users"
    :active="$active === 'users'"
/>

<x-nav-link
    :href="route('admin.roles.index')"
    label="Manajemen Role"
    icon="shield"
    :active="$active === 'roles'"
/>

<x-nav-link
    :href="route('dashboard.admin') . '#hospitals'"
    label="Daftar Rumah Sakit"
    icon="hospital"
    :active="$active === 'hospitals'"
/>

<div class="hidden md:block my-2 border-t border-slate-800"></div>

<div class="hidden md:block px-2 pt-1 pb-1.5 text-[10px] font-bold uppercase tracking-wider text-slate-400">
    Operasional IGD
</div>

<x-nav-link
    :href="route('dashboard.hospital')"
    label="Dashboard RS"
    icon="ambulance"
    :active="$active === 'hospital_dash'"
/>

<a
    href="tel:119"
    class="hidden md:flex h-10 w-full items-center gap-2.5 rounded-xl px-3 text-xs font-semibold text-slate-300 transition hover:bg-slate-800/80 hover:text-rose-400"
>
    <x-icon name="phone" class="size-4 shrink-0 text-rose-400" />
    <span class="truncate">Hotline SPGDT 119</span>
</a>
