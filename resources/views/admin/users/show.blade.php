@extends('layouts.dashboard')

@section('title', 'Detail User — ' . $user->full_name)
@section('page-title', 'Detail User')

@section('nav-items')
    <x-admin-nav active="users" />
@endsection

@section('topbar-actions')
    <a href="{{ route('admin.users.index') }}" class="flex h-9 items-center gap-1.5 rounded-xl border border-line bg-card px-3 text-xs font-semibold text-ink/70 transition hover:border-primary hover:text-primary">
        <x-icon name="arrow-left" class="size-3.5" /> Kembali ke Daftar User
    </a>
@endsection

@section('content')
    <div class="space-y-4">
        {{-- Breadcrumb Navigation --}}
        <nav class="flex items-center gap-2 text-xs text-slate-400">
            <a href="{{ route('dashboard.admin') }}" class="inline-flex items-center gap-1 font-semibold text-sky-400 hover:text-sky-300 transition">
                <x-icon name="meters" class="size-3.5" /> Pusat Komando
            </a>
            <span class="text-slate-600">/</span>
            <a href="{{ route('admin.users.index') }}" class="font-semibold text-sky-400 hover:text-sky-300 transition">
                Manajemen User
            </a>
            <span class="text-slate-600">/</span>
            <span class="text-white font-bold">{{ $user->full_name }}</span>
        </nav>

        <div class="grid gap-4 lg:grid-cols-2">
        <div class="rounded-xl border border-line bg-card p-6">
            <h3 class="mb-5 text-sm font-bold">Informasi pengguna</h3>
            <div class="mb-6 flex items-center gap-4">
                <div class="flex size-14 items-center justify-center rounded-full bg-primary text-xl font-extrabold text-white">
                    {{ substr($user->full_name, 0, 1) }}
                </div>
                <div>
                    <h4 class="text-lg font-extrabold">{{ $user->full_name }}</h4>
                    <p class="text-xs text-muted">Bergabung: {{ $user->created_at->format('d M Y') }}</p>
                </div>
            </div>
            <dl class="space-y-3 text-sm">
                <div class="flex gap-3"><dt class="w-20 shrink-0 text-muted">Email</dt><dd class="font-semibold">{{ $user->email ?? '-' }}</dd></div>
                <div class="flex gap-3"><dt class="w-20 shrink-0 text-muted">Telepon</dt><dd class="font-semibold">{{ $user->phone }}</dd></div>
                <div class="flex gap-3"><dt class="w-20 shrink-0 text-muted">Alamat</dt><dd class="font-semibold">{{ $user->alamat }}</dd></div>
            </dl>

            @if($user->driverProfile)
                <div class="mt-5 rounded-xl border border-primary/25 bg-primary-50 p-4">
                    <p class="mb-2 text-[11px] font-bold uppercase tracking-wide text-primary">Profil driver</p>
                    <div class="space-y-1 text-sm">
                        <p>SIM: <span class="font-semibold">{{ $user->driverProfile->license_number }}</span></p>
                        <p>Ambulan: <span class="font-semibold">{{ $user->driverProfile->ambulance?->plate_number ?? 'Belum ada' }}</span></p>
                        <p>Status: <x-status-badge dot>{{ $user->driverProfile->availability_status }}</x-status-badge></p>
                    </div>
                </div>
            @endif
        </div>

        <div class="rounded-xl border border-line bg-card p-6">
            <h3 class="mb-1 text-sm font-bold">Kelola Role (1 User = 1 Role)</h3>
            <p class="mb-5 text-xs text-muted">Pilih 1 role yang ditetapkan untuk pengguna ini.</p>
            <form method="POST" action="{{ route('admin.users.update', $user) }}">
                @csrf
                @method('PUT')
                <div class="mb-5 space-y-2.5">
                    @foreach($allRoles as $role)
                        <label class="flex min-h-12 cursor-pointer items-center gap-3 rounded-xl border border-line bg-surface px-4 transition hover:border-primary">
                            <input
                                type="radio"
                                name="role_id"
                                value="{{ $role->id }}"
                                {{ $user->roles->contains($role) ? 'checked' : '' }}
                                class="size-4 accent-primary"
                            >
                            <span class="text-sm font-semibold capitalize">{{ $role->name }}</span>
                        </label>
                    @endforeach
                </div>
                <button type="submit" class="flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-primary text-sm font-bold text-white transition hover:bg-primary/90">
                    Simpan Perubahan Role
                </button>
            </form>
        </div>
    </div>

    @if($user->emergencyRequests->isNotEmpty())
        <div class="mt-4 overflow-x-auto rounded-xl border border-line bg-card">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-line bg-surface text-xs uppercase tracking-wide text-muted">
                        <th class="px-4 py-3 font-semibold">Kode</th>
                        <th class="px-4 py-3 font-semibold">Tipe</th>
                        <th class="hidden px-4 py-3 font-semibold sm:table-cell">Level</th>
                        <th class="px-4 py-3 font-semibold">Status</th>
                        <th class="hidden px-4 py-3 font-semibold sm:table-cell">Tanggal</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($user->emergencyRequests as $req)
                        <tr class="border-b border-line last:border-0 hover:bg-surface/60">
                            <td class="px-4 py-3 font-mono text-xs text-muted">{{ $req->request_code }}</td>
                            <td class="px-4 py-3">{{ $req->emergency_type ?? '-' }}</td>
                            <td class="hidden px-4 py-3 capitalize sm:table-cell">{{ $req->severity_level ?? '-' }}</td>
                            <td class="px-4 py-3"><x-status-badge :status="$req->status">{{ $req->status }}</x-status-badge></td>
                            <td class="hidden px-4 py-3 text-xs text-muted sm:table-cell">{{ $req->created_at->format('d M Y') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div class="mt-4 rounded-xl border border-danger/30 bg-card p-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-sm font-bold text-danger">Zona berbahaya</p>
                <p class="text-xs text-muted">Hapus pengguna. Tindakan ini tidak dapat dibatalkan, semua data terkait akan terhapus.</p>
            </div>
            <form method="POST" action="{{ route('admin.users.destroy', $user) }}"
                  onsubmit="return confirm('Apakah Anda yakin ingin menghapus {{ $user->full_name }}? Tindakan ini tidak dapat dibatalkan.');">
                @csrf
                @method('DELETE')
                <button type="submit" class="flex h-11 items-center gap-2 rounded-xl bg-danger px-4 text-sm font-bold text-white transition hover:bg-danger/90">
                    Hapus User
                </button>
            </form>
        </div>
    </div>
@endsection
