@extends('layouts.dashboard')

@section('title', 'Manajemen User')
@section('page-title', 'Manajemen User')

@section('nav-items')
    <x-admin-nav active="users" />
@endsection

@section('topbar-actions')
    <div class="flex items-center gap-2">
        <a href="{{ route('admin.users.create') }}" class="flex h-9 items-center gap-1.5 rounded-xl bg-sky-600 hover:bg-sky-500 px-3.5 text-xs font-bold text-white transition shadow-xs">
            + Tambah User
        </a>
    </div>
@endsection

@section('content')
    <div class="space-y-6">
        {{-- Breadcrumb Navigation --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <nav class="flex items-center gap-2 text-xs text-slate-400">
                <a href="{{ route('dashboard.admin') }}" class="inline-flex items-center gap-1 font-semibold text-sky-400 hover:text-sky-300 transition">
                    <x-icon name="meters" class="size-3.5" /> Pusat Komando
                </a>
                <span class="text-slate-600">/</span>
                <span class="text-white font-bold">Manajemen User</span>
            </nav>
            <div class="text-xs text-slate-400">
                Total <span class="font-bold text-white font-mono">{{ number_format($totalUsers ?? $users->total()) }}</span> Akun Pengguna Terdaftar
            </div>
        </div>

        {{-- Hero Header Card --}}
        <div class="relative overflow-hidden rounded-2xl border border-line bg-card p-6 shadow-xs">
            <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                <div class="flex items-start gap-4">
                    <span class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-sky-600 text-white shadow-xs">
                        <x-icon name="users" class="size-6" />
                    </span>
                    <div class="min-w-0">
                        <h2 class="text-xl font-extrabold text-ink">Direktori Pengguna Sistem</h2>
                        <p class="text-xs text-ink/70 mt-0.5">Kelola data seluruh akun: Administrator, Pengemudi Ambulans, Petugas IGD Rumah Sakit, dan Warga/Pasien.</p>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('admin.users.create') }}" class="flex h-10 items-center gap-1.5 rounded-xl bg-sky-600 hover:bg-sky-500 px-4 text-xs font-bold text-white transition shadow-xs">
                        + Tambah User Baru
                    </a>
                </div>
            </div>
        </div>

        {{-- Role Quick-Filter Tabs --}}
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('admin.users.index', array_filter(['search' => $search])) }}"
               class="inline-flex items-center gap-1.5 rounded-xl px-3.5 py-2 text-xs font-bold transition {{ empty($selectedRole) ? 'bg-sky-600 text-white shadow-xs' : 'border border-line bg-card text-ink/70 hover:border-primary hover:text-ink' }}">
                <span>Semua Akun</span>
                <span class="rounded-full px-2 py-0.5 text-[10px] {{ empty($selectedRole) ? 'bg-white/20 text-white' : 'bg-surface text-muted' }}">
                    {{ number_format($totalUsers ?? $users->total()) }}
                </span>
            </a>
            @foreach($roles as $r)
                @php
                    $isRoleActive = ($selectedRole === $r->name);
                    $roleLabel = match($r->name) {
                        'administrator' => 'Administrator',
                        'hospital_admin' => 'Petugas RS / IGD',
                        'driver' => 'Driver Ambulans',
                        'civilian' => 'Warga / Pasien',
                        default => ucfirst($r->name),
                    };
                @endphp
                <a href="{{ route('admin.users.index', array_filter(['search' => $search, 'role' => $r->name])) }}"
                   class="inline-flex items-center gap-1.5 rounded-xl px-3.5 py-2 text-xs font-bold transition {{ $isRoleActive ? 'bg-sky-600 text-white shadow-xs' : 'border border-line bg-card text-ink/70 hover:border-primary hover:text-ink' }}">
                    <span>{{ $roleLabel }}</span>
                    <span class="rounded-full px-2 py-0.5 text-[10px] {{ $isRoleActive ? 'bg-white/20 text-white' : 'bg-surface text-muted' }}">
                        {{ $r->users_count ?? 0 }}
                    </span>
                </a>
            @endforeach
        </div>

        {{-- Search Toolbar & Table Container --}}
        <div class="rounded-2xl border border-line bg-card shadow-xs">
            <div class="flex flex-col justify-between gap-3 border-b border-line p-5 sm:flex-row sm:items-center">
                <div>
                    <h3 class="text-base font-bold text-ink">Daftar Pengguna</h3>
                    <p class="text-xs text-ink/60 mt-0.5">
                        @if($selectedRole)
                            Menampilkan akun dengan role <span class="font-bold text-primary uppercase">{{ $selectedRole }}</span>
                        @else
                            Menampilkan seluruh akun dalam database
                        @endif
                    </p>
                </div>

                <form method="GET" action="{{ route('admin.users.index') }}" class="flex flex-wrap items-center gap-2">
                    @if($selectedRole)
                        <input type="hidden" name="role" value="{{ $selectedRole }}">
                    @endif
                    <div class="relative">
                        <input
                            type="text"
                            name="search"
                            value="{{ $search }}"
                            placeholder="Cari nama, email, telepon..."
                            class="h-9.5 w-56 sm:w-72 rounded-xl border border-line bg-surface/50 pl-9 pr-3 text-xs text-ink placeholder-muted focus:border-primary focus:bg-card focus:outline-none"
                        />
                        <span class="pointer-events-none absolute left-3 top-2.5 text-muted">
                            <x-icon name="search" class="size-4" />
                        </span>
                    </div>
                    <button type="submit" class="flex h-9.5 items-center gap-1.5 rounded-xl bg-sky-600 hover:bg-sky-500 px-3.5 text-xs font-bold text-white transition">
                        Cari
                    </button>
                    @if($search || $selectedRole)
                        <a href="{{ route('admin.users.index') }}" class="flex h-9.5 items-center rounded-xl border border-line bg-card px-3 text-xs font-semibold text-ink/70 hover:border-danger hover:text-danger transition">
                            Reset
                        </a>
                    @endif
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-line bg-surface text-[11px] font-bold uppercase tracking-wider text-muted">
                            <th class="px-5 py-3.5">Pengguna</th>
                            <th class="px-5 py-3.5">Kontak</th>
                            <th class="px-5 py-3.5">Peran / Role</th>
                            <th class="hidden px-5 py-3.5 md:table-cell">Detail Penugasan</th>
                            <th class="hidden px-5 py-3.5 lg:table-cell">Terdaftar</th>
                            <th class="px-5 py-3.5 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line text-xs">
                        @forelse($users as $user)
                            <tr class="hover:bg-surface/50 transition">
                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-3">
                                        <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-sky-600/10 border border-sky-500/20 text-sky-500 text-sm font-black">
                                            {{ strtoupper(substr($user->full_name, 0, 1)) }}
                                        </span>
                                        <div class="min-w-0">
                                            <p class="font-bold text-ink text-sm">{{ $user->full_name }}</p>
                                            <p class="text-[11px] text-muted truncate">{{ $user->email ?? '-' }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-4">
                                    <div class="space-y-0.5">
                                        @if($user->phone)
                                            <a href="tel:{{ $user->phone }}" class="font-mono text-xs text-sky-600 hover:underline">
                                                {{ $user->phone }}
                                            </a>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                        @if($user->alamat)
                                            <p class="text-[11px] text-muted truncate max-w-44" title="{{ $user->alamat }}">{{ $user->alamat }}</p>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex flex-wrap gap-1">
                                        @forelse($user->roles as $role)
                                            @php
                                                $badgeStyle = match($role->name) {
                                                    'administrator' => 'bg-rose-500/10 text-rose-400 border-rose-500/20',
                                                    'hospital_admin' => 'bg-sky-500/10 text-sky-400 border-sky-500/20',
                                                    'driver' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
                                                    default => 'bg-slate-500/10 text-slate-300 border-slate-500/20',
                                                };
                                            @endphp
                                            <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-[11px] font-bold capitalize {{ $badgeStyle }}">
                                                {{ match($role->name) {
                                                    'administrator' => 'Administrator',
                                                    'hospital_admin' => 'Admin RS',
                                                    'driver' => 'Driver',
                                                    'civilian' => 'Warga',
                                                    default => $role->name,
                                                } }}
                                            </span>
                                        @empty
                                            <span class="text-[11px] text-muted">Tanpa role</span>
                                        @endforelse
                                    </div>
                                </td>
                                <td class="hidden px-5 py-4 md:table-cell">
                                    @if($user->driverProfile)
                                        <div class="space-y-0.5">
                                            <p class="font-mono font-semibold text-ink">SIM: {{ $user->driverProfile->license_number }}</p>
                                            <p class="text-[11px] text-muted">
                                                {{ $user->driverProfile->hospital?->name ?? 'Armada Umum' }}
                                                @if($user->driverProfile->ambulance)
                                                    • <span class="font-mono font-bold text-sky-600">{{ $user->driverProfile->ambulance->plate_number }}</span>
                                                @endif
                                            </p>
                                        </div>
                                    @elseif($user->hasRole('hospital_admin'))
                                        <span class="text-xs font-medium text-ink">Admin Fasilitas Kesehatan</span>
                                    @else
                                        <span class="text-xs text-muted">Pasien / Pelapor</span>
                                    @endif
                                </td>
                                <td class="hidden px-5 py-4 text-xs text-muted lg:table-cell">
                                    <p class="font-medium text-ink">{{ $user->created_at->format('d M Y') }}</p>
                                    <p class="text-[10px] text-muted">{{ $user->created_at->diffForHumans() }}</p>
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <a href="{{ route('admin.users.show', $user) }}" class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-line bg-surface/80 hover:bg-card px-3 text-xs font-semibold text-ink transition hover:border-primary hover:text-primary">
                                        Kelola <x-icon name="arrow-right" class="size-3" />
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-12 text-center text-sm text-muted">
                                    @if($search || $selectedRole)
                                        Tidak ada akun pengguna yang cocok dengan kriteria filter.
                                        <div class="mt-2">
                                            <a href="{{ route('admin.users.index') }}" class="font-semibold text-primary hover:underline">Reset semua filter</a>
                                        </div>
                                    @else
                                        Belum ada pengguna terdaftar dalam sistem.
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($users->hasPages())
                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line p-4">
                    <span class="text-xs text-muted">
                        Menampilkan <span class="font-bold text-ink">{{ $users->firstItem() }}</span> - <span class="font-bold text-ink">{{ $users->lastItem() }}</span> dari <span class="font-bold text-ink">{{ $users->total() }}</span> pengguna
                    </span>
                    <div class="flex items-center gap-2">
                        @if($users->onFirstPage())
                            <span class="flex h-8 items-center rounded-lg border border-line px-3 text-xs text-muted opacity-50 cursor-not-allowed">
                                <x-icon name="arrow-left" class="size-3 mr-1" /> Sebelumnya
                            </span>
                        @else
                            <a href="{{ $users->previousPageUrl() }}" class="flex h-8 items-center rounded-lg border border-line bg-surface hover:bg-card px-3 text-xs font-semibold text-ink transition hover:border-primary hover:text-primary">
                                <x-icon name="arrow-left" class="size-3 mr-1" /> Sebelumnya
                            </a>
                        @endif

                        @if($users->hasMorePages())
                            <a href="{{ $users->nextPageUrl() }}" class="flex h-8 items-center rounded-lg border border-line bg-surface hover:bg-card px-3 text-xs font-semibold text-ink transition hover:border-primary hover:text-primary">
                                Selanjutnya <x-icon name="arrow-right" class="size-3 ml-1" />
                            </a>
                        @else
                            <span class="flex h-8 items-center rounded-lg border border-line px-3 text-xs text-muted opacity-50 cursor-not-allowed">
                                Selanjutnya <x-icon name="arrow-right" class="size-3 ml-1" />
                            </span>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection
