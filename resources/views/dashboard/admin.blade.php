@extends('layouts.dashboard')

@section('title', 'Dashboard Administrator')
@section('page-title', 'Dashboard Administrator')

@section('nav-items')
    <x-admin-nav active="overview" />
@endsection

@section('topbar-actions')
    <div class="flex items-center gap-2">
        <a href="{{ route('admin.users.create') }}" class="flex h-9 items-center gap-1.5 rounded-xl bg-primary px-3 text-xs font-bold text-white transition hover:bg-primary/90">
            + Tambah User
        </a>
        <a href="{{ route('admin.roles.create') }}" class="flex h-9 items-center gap-1.5 rounded-xl border border-line bg-card px-3 text-xs font-semibold text-ink/70 transition hover:border-primary hover:text-primary">
            + Buat Role
        </a>
    </div>
@endsection

@section('content')
    <div class="mx-auto max-w-5xl space-y-6">
        {{-- System Control Header --}}
        <div class="relative overflow-hidden rounded-2xl border border-line bg-card p-6 shadow-sm">
            <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                <div class="flex items-start gap-4">
                    <span class="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-danger text-white shadow-xs">
                        <x-icon name="meters" class="size-7" />
                    </span>
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="rounded bg-danger/10 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-danger">
                                National Emergency Dispatch
                            </span>
                            <span class="inline-flex items-center gap-1 rounded-full bg-success-50 px-2 py-0.5 text-[10px] font-bold text-success">
                                <span class="size-1.5 rounded-full bg-success animate-pulse"></span> Sistem Online
                            </span>
                        </div>
                        <h2 class="mt-1.5 text-2xl font-extrabold text-ink">Pusat Komando Administrator</h2>
                        <p class="text-xs text-ink/70">Monitoring terpadu operasional ambulans, data pengguna, hak akses, dan rumah sakit mitra</p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2 sm:self-end md:self-center">
                    <a href="{{ route('dashboard.hospital') }}" class="flex h-10 items-center gap-2 rounded-xl border border-line bg-surface/80 px-3.5 text-xs font-bold text-ink transition hover:border-primary hover:bg-card">
                        <x-icon name="hospital" class="size-4 text-primary" />
                        <span>Buka Dashboard RS</span>
                    </a>
                </div>
            </div>
        </div>

        {{-- Overview Stats Grid --}}
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-6">
            <x-stat-card icon="users" :value="number_format($totalUsers)" label="Total Pengguna" tone="info" />
            <x-stat-card icon="hospital" :value="number_format($totalHospitals)" label="Rumah Sakit" tone="info" />
            <x-stat-card icon="ambulance" :value="number_format($totalAmbulances)" label="Total Ambulan" tone="danger" />
            <x-stat-card icon="check" :value="number_format($activeAmbulances)" label="Ambulan Siap" tone="success" />
            <x-stat-card icon="alert" :value="number_format($busyAmbulances)" label="Ambulan Tugas" tone="warning" />
            <x-stat-card icon="sos" :value="number_format($totalEmergencyRequests)" label="Permintaan" tone="danger" />
        </div>

        {{-- Segmented Tab Navigation Bar --}}
        <div data-tabs-container data-default-tab="overview" class="space-y-6">
            <div class="flex flex-wrap items-center gap-1.5 rounded-2xl border border-line bg-surface/90 p-1.5 shadow-xs">
                <button
                    type="button"
                    data-tab-target="overview"
                    role="tab"
                    aria-selected="true"
                    class="tab-btn inline-flex items-center gap-2 rounded-xl border border-line/80 bg-card px-4 py-2.5 text-xs font-bold text-ink shadow-xs transition hover:text-ink cursor-pointer"
                >
                    <x-icon name="meters" class="size-4 text-primary" />
                    <span>Overview Sistem</span>
                    <span class="rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-bold text-primary">
                        Live
                    </span>
                </button>

                <button
                    type="button"
                    data-tab-target="users"
                    role="tab"
                    aria-selected="false"
                    class="tab-btn inline-flex items-center gap-2 rounded-xl border border-transparent px-4 py-2.5 text-xs font-semibold text-ink/60 transition hover:text-ink cursor-pointer"
                >
                    <x-icon name="users" class="size-4 text-primary" />
                    <span>Manajemen Pengguna</span>
                    <span class="rounded-full bg-surface px-2 py-0.5 text-[10px] font-semibold text-ink/70">
                        {{ $totalUsers }}
                    </span>
                </button>

                <button
                    type="button"
                    data-tab-target="roles"
                    role="tab"
                    aria-selected="false"
                    class="tab-btn inline-flex items-center gap-2 rounded-xl border border-transparent px-4 py-2.5 text-xs font-semibold text-ink/60 transition hover:text-ink cursor-pointer"
                >
                    <x-icon name="shield" class="size-4 text-danger" />
                    <span>Manajemen Role</span>
                    <span class="rounded-full bg-surface px-2 py-0.5 text-[10px] font-semibold text-ink/70">
                        {{ $roles->count() }}
                    </span>
                </button>

                <button
                    type="button"
                    data-tab-target="hospitals"
                    role="tab"
                    aria-selected="false"
                    class="tab-btn inline-flex items-center gap-2 rounded-xl border border-transparent px-4 py-2.5 text-xs font-semibold text-ink/60 transition hover:text-ink cursor-pointer"
                >
                    <x-icon name="hospital" class="size-4 text-success" />
                    <span>Daftar Rumah Sakit</span>
                    <span class="rounded-full bg-surface px-2 py-0.5 text-[10px] font-semibold text-ink/70">
                        {{ $hospitals->count() }}
                    </span>
                </button>
            </div>

            {{-- TAB 1: Overview Sistem & Feed Panggilan Terkini --}}
            <div data-tab-content="overview" class="space-y-6">
                {{-- Role Distribution Pills --}}
                <div class="rounded-2xl border border-line bg-card p-5 shadow-sm">
                    <h3 class="mb-3 text-xs font-bold uppercase tracking-wider text-muted">Distribusi Akun & Hak Akses</h3>
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        @foreach($usersByRole as $ubr)
                            <div class="rounded-xl border border-line bg-surface/50 p-3">
                                <span class="block text-lg font-extrabold text-ink">{{ number_format($ubr->users_count) }}</span>
                                <span class="text-xs font-semibold text-muted capitalize">{{ $ubr->name }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Recent Emergency Requests Section --}}
                <section class="rounded-2xl border border-line bg-card p-6 shadow-sm">
                    <div class="mb-4 flex flex-col justify-between gap-2 sm:flex-row sm:items-center">
                        <div>
                            <h3 class="flex items-center gap-2 text-base font-bold text-ink">
                                <x-icon name="sos" class="size-5 text-warning" /> Permintaan Darurat Terbaru
                            </h3>
                            <p class="text-xs text-ink/60">10 aktivitas panggilan darurat terakhir di seluruh sistem</p>
                        </div>
                    </div>

                    <div class="overflow-x-auto rounded-xl border border-line">
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr class="border-b border-line bg-surface text-xs uppercase tracking-wide text-muted">
                                    <th class="px-4 py-3 font-semibold">Kode</th>
                                    <th class="px-4 py-3 font-semibold">Pemohon</th>
                                    <th class="hidden px-4 py-3 font-semibold sm:table-cell">Tipe</th>
                                    <th class="px-4 py-3 font-semibold">Level</th>
                                    <th class="px-4 py-3 font-semibold">Status</th>
                                    <th class="hidden px-4 py-3 font-semibold md:table-cell">RS Tujuan</th>
                                    <th class="hidden px-4 py-3 font-semibold md:table-cell">Waktu</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line">
                                @forelse($recentEmergencyRequests as $req)
                                    <tr class="hover:bg-surface/60 transition">
                                        <td class="px-4 py-3 font-mono text-xs font-bold text-muted">{{ $req->request_code }}</td>
                                        <td class="px-4 py-3 font-bold text-ink text-xs sm:text-sm">{{ $req->user?->full_name ?? '-' }}</td>
                                        <td class="hidden px-4 py-3 text-xs sm:table-cell">{{ $req->emergency_type ?? '-' }}</td>
                                        <td class="px-4 py-3">
                                            <x-status-badge :status="match($req->severity_level) { 'critical' => 'cancelled', 'high' => 'searching_driver', default => 'arrive_at_pickup' }">
                                                {{ match($req->severity_level) { 'critical' => 'Kritis', 'high' => 'Tinggi', default => 'Normal' } }}
                                            </x-status-badge>
                                        </td>
                                        <td class="px-4 py-3">
                                            <x-status-badge :status="$req->status" dot>{{ $req->status }}</x-status-badge>
                                        </td>
                                        <td class="hidden px-4 py-3 text-xs md:table-cell font-medium">{{ $req->hospital?->name ?? '-' }}</td>
                                        <td class="hidden px-4 py-3 text-xs text-muted md:table-cell">{{ $req->created_at->diffForHumans() }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-4 py-10 text-center text-sm text-muted">
                                            Belum ada permintaan darurat yang tercatat.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>

                {{-- Recent Dispatch Logs Section --}}
                @if($recentDispatchLogs->isNotEmpty())
                    <section class="rounded-2xl border border-line bg-card p-6 shadow-sm">
                        <div class="mb-4">
                            <h3 class="flex items-center gap-2 text-base font-bold text-ink">
                                <x-icon name="history" class="size-5 text-primary" /> Log Penugasan Driver Terkini
                            </h3>
                            <p class="text-xs text-ink/60">Catatan dispatch penugasan ambulans kepada pengemudi terdekat</p>
                        </div>

                        <div class="overflow-x-auto rounded-xl border border-line">
                            <table class="w-full text-left text-sm">
                                <thead>
                                    <tr class="border-b border-line bg-surface text-xs uppercase tracking-wide text-muted">
                                        <th class="px-4 py-3 font-semibold">Panggilan</th>
                                        <th class="px-4 py-3 font-semibold">Driver Ditugaskan</th>
                                        <th class="px-4 py-3 font-semibold">Status Dispatch</th>
                                        <th class="px-4 py-3 font-semibold text-right">Waktu Penawaran</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-line">
                                    @foreach($recentDispatchLogs as $log)
                                        <tr class="hover:bg-surface/50">
                                            <td class="px-4 py-3 font-mono text-xs font-semibold text-ink">
                                                {{ $log->emergencyRequest?->request_code ?? '-' }}
                                            </td>
                                            <td class="px-4 py-3 text-xs font-medium">
                                                {{ $log->driver?->user?->full_name ?? 'Driver' }}
                                            </td>
                                            <td class="px-4 py-3">
                                                <x-status-badge :status="$log->status">{{ $log->status }}</x-status-badge>
                                            </td>
                                            <td class="px-4 py-3 text-right text-xs text-muted">
                                                {{ $log->created_at->diffForHumans() }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>
                @endif
            </div>

            {{-- TAB 2: Manajemen Pengguna --}}
            <div data-tab-content="users" class="space-y-4 hidden">
                <div class="rounded-2xl border border-line bg-card p-6 shadow-sm">
                    <div class="mb-4 flex flex-col justify-between gap-3 border-b border-line pb-4 sm:flex-row sm:items-center">
                        <div>
                            <h3 class="flex items-center gap-2 text-base font-bold text-ink">
                                <x-icon name="users" class="size-5 text-primary" /> Manajemen Pengguna
                            </h3>
                            <p class="text-xs text-ink/60">{{ number_format($totalUsers) }} akun pengguna terdaftar dalam sistem</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="relative">
                                <input
                                    type="text"
                                    placeholder="Filter nama / email..."
                                    data-quick-filter="#users-table"
                                    class="h-9 w-48 sm:w-60 rounded-xl border border-line bg-surface/50 pl-8 pr-3 text-xs text-ink focus:border-primary focus:bg-card focus:outline-none"
                                />
                                <span class="pointer-events-none absolute left-2.5 top-2.5 text-muted">
                                    <x-icon name="search" class="size-4" />
                                </span>
                            </div>
                            <a href="{{ route('admin.users.create') }}" class="flex h-9 items-center gap-1.5 rounded-xl bg-primary px-3 text-xs font-bold text-white transition hover:bg-primary/90">
                                + Tambah
                            </a>
                            <a href="{{ route('admin.users.index') }}" class="flex h-9 items-center rounded-xl border border-line bg-surface px-3 text-xs font-semibold text-ink/70 transition hover:border-primary hover:text-primary">
                                Semua User &rarr;
                            </a>
                        </div>
                    </div>

                    <div class="overflow-x-auto rounded-xl border border-line">
                        <table id="users-table" class="w-full text-left text-sm">
                            <thead>
                                <tr class="border-b border-line bg-surface text-xs uppercase tracking-wide text-muted">
                                    <th class="px-4 py-3 font-semibold">Pengguna</th>
                                    <th class="px-4 py-3 font-semibold">Role</th>
                                    <th class="hidden px-4 py-3 font-semibold sm:table-cell">Bergabung</th>
                                    <th class="px-4 py-3 font-semibold text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line">
                                @forelse($recentUsers as $usr)
                                    <tr class="hover:bg-surface/50 transition">
                                        <td class="px-4 py-3">
                                            <div class="flex items-center gap-3">
                                                <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-primary text-xs font-bold text-white">
                                                    {{ substr($usr->full_name, 0, 1) }}
                                                </span>
                                                <div class="min-w-0">
                                                    <p class="font-bold text-ink text-xs sm:text-sm">{{ $usr->full_name }}</p>
                                                    <p class="text-[11px] text-muted truncate">{{ $usr->email ?? $usr->phone }}</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex flex-wrap gap-1">
                                                @forelse($usr->roles as $role)
                                                    <span class="rounded bg-primary-50 px-2 py-0.5 text-[10px] font-bold text-primary capitalize">{{ $role->name }}</span>
                                                @empty
                                                    <span class="text-[10px] text-muted">-</span>
                                                @endforelse
                                            </div>
                                        </td>
                                        <td class="hidden px-4 py-3 text-xs text-muted sm:table-cell">{{ $usr->created_at->format('d M Y') }}</td>
                                        <td class="px-4 py-3 text-right">
                                            <a href="{{ route('admin.users.show', $usr) }}" class="inline-flex h-8 items-center rounded-lg border border-line bg-card px-3 text-xs font-semibold text-ink/75 transition hover:border-primary hover:text-primary">
                                                Kelola
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr data-empty-row>
                                        <td colspan="4" class="px-4 py-8 text-center text-xs text-muted">Belum ada pengguna.</td>
                                    </tr>
                                @endforelse
                                <tr data-filter-empty style="display: none;">
                                    <td colspan="4" class="px-4 py-8 text-center text-xs text-muted">Tidak ada pengguna yang cocok dengan filter pencarian.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4 pt-3 border-t border-line text-right">
                        <a href="{{ route('admin.users.index') }}" class="inline-flex items-center gap-1 text-xs font-bold text-primary hover:underline">
                            Buka Halaman Manajemen User Selengkapnya &rarr;
                        </a>
                    </div>
                </div>
            </div>

            {{-- TAB 3: Manajemen Role --}}
            <div data-tab-content="roles" class="space-y-4 hidden">
                <div class="rounded-2xl border border-line bg-card p-6 shadow-sm">
                    <div class="mb-4 flex flex-col justify-between gap-3 border-b border-line pb-4 sm:flex-row sm:items-center">
                        <div>
                            <h3 class="flex items-center gap-2 text-base font-bold text-ink">
                                <x-icon name="shield" class="size-5 text-danger" /> Manajemen Role
                            </h3>
                            <p class="text-xs text-ink/60">{{ $roles->count() }} peran hak akses sistem terdaftar</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <a href="{{ route('admin.roles.create') }}" class="flex h-9 items-center gap-1.5 rounded-xl bg-danger px-3 text-xs font-bold text-white transition hover:bg-danger/90">
                                + Buat Role
                            </a>
                            <a href="{{ route('admin.roles.index') }}" class="flex h-9 items-center rounded-xl border border-line bg-surface px-3 text-xs font-semibold text-ink/70 transition hover:border-danger hover:text-danger">
                                Semua Role &rarr;
                            </a>
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        @forelse($roles as $role)
                            <div class="flex flex-col justify-between rounded-xl border border-line bg-surface/40 p-4 transition hover:border-primary/50 hover:bg-card hover:shadow-xs">
                                <div>
                                    <div class="flex items-center gap-3">
                                        <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-card border border-line text-base shadow-xs">
                                            🔐
                                        </span>
                                        <div class="min-w-0">
                                            <p class="text-sm font-bold text-ink capitalize truncate">{{ $role->name }}</p>
                                            <p class="text-xs text-muted">{{ $role->users_count }} pengguna aktif</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-4 flex items-center justify-end gap-2 border-t border-line/60 pt-3">
                                    <a href="{{ route('admin.roles.show', $role) }}" class="rounded-lg border border-line bg-card px-2.5 py-1 text-xs font-semibold text-ink/75 transition hover:border-primary hover:text-primary">
                                        Detail
                                    </a>
                                    <a href="{{ route('admin.roles.edit', $role) }}" class="rounded-lg border border-line bg-card px-2.5 py-1 text-xs font-semibold text-ink/75 transition hover:border-primary hover:text-primary">
                                        Edit
                                    </a>
                                </div>
                            </div>
                        @empty
                            <p class="col-span-3 text-center text-xs text-muted py-8">Belum ada role terdaftar.</p>
                        @endforelse
                    </div>

                    <div class="mt-4 pt-3 border-t border-line text-right">
                        <a href="{{ route('admin.roles.index') }}" class="inline-flex items-center gap-1 text-xs font-bold text-primary hover:underline">
                            Buka Halaman Manajemen Role Selengkapnya &rarr;
                        </a>
                    </div>
                </div>
            </div>

            {{-- TAB 4: Daftar Rumah Sakit --}}
            <div data-tab-content="hospitals" class="space-y-4 hidden">
                <div class="rounded-2xl border border-line bg-card p-6 shadow-sm">
                    <div class="mb-4 flex flex-col justify-between gap-3 border-b border-line pb-4 sm:flex-row sm:items-center">
                        <div>
                            <h3 class="flex items-center gap-2 text-base font-bold text-ink">
                                <x-icon name="hospital" class="size-5 text-primary" /> Daftar Rumah Sakit ({{ $hospitals->count() }})
                            </h3>
                            <p class="text-xs text-ink/60">Rumah sakit mitra terhubung dalam penanganan pasien dan penugasan ambulan</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="relative">
                                <input
                                    type="text"
                                    placeholder="Cari faskes / alamat..."
                                    data-quick-filter="#hospitals-table"
                                    class="h-9 w-48 sm:w-60 rounded-xl border border-line bg-surface/50 pl-8 pr-3 text-xs text-ink focus:border-primary focus:bg-card focus:outline-none"
                                />
                                <span class="pointer-events-none absolute left-2.5 top-2.5 text-muted">
                                    <x-icon name="search" class="size-4" />
                                </span>
                            </div>
                            <a href="{{ route('dashboard.hospital') }}" class="flex h-9 items-center gap-1.5 rounded-xl border border-line bg-card px-3 text-xs font-semibold text-ink/70 transition hover:border-primary hover:text-primary">
                                Dashboard RS <x-icon name="arrow-right" class="size-3.5" />
                            </a>
                        </div>
                    </div>

                    <div class="overflow-x-auto rounded-xl border border-line">
                        <table id="hospitals-table" class="w-full text-left text-sm">
                            <thead>
                                <tr class="border-b border-line bg-surface text-xs uppercase tracking-wide text-muted">
                                    <th class="px-4 py-3 font-semibold">Rumah Sakit</th>
                                    <th class="hidden px-4 py-3 font-semibold md:table-cell">Alamat</th>
                                    <th class="px-4 py-3 font-semibold">Kontak Darurat</th>
                                    <th class="px-4 py-3 font-semibold text-center">Armada</th>
                                    <th class="px-4 py-3 font-semibold text-center">Driver</th>
                                    <th class="hidden px-4 py-3 font-semibold sm:table-cell text-center">Permintaan</th>
                                    <th class="px-4 py-3 font-semibold text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line">
                                @forelse($hospitals as $hospital)
                                    <tr class="hover:bg-surface/50 transition">
                                        <td class="px-4 py-3 font-bold text-ink">
                                            <div class="flex items-center gap-2.5">
                                                <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-primary">
                                                    <x-icon name="hospital" class="size-4" />
                                                </span>
                                                <span>{{ $hospital->name }}</span>
                                            </div>
                                        </td>
                                        <td class="hidden max-w-xs truncate px-4 py-3 text-xs text-ink/70 md:table-cell">
                                            {{ $hospital->address }}
                                        </td>
                                        <td class="px-4 py-3 text-xs">
                                            @if($hospital->emergency_phone)
                                                <a href="tel:{{ $hospital->emergency_phone }}" class="inline-flex items-center gap-1 font-semibold text-danger hover:underline font-mono">
                                                    <x-icon name="phone" class="size-3" /> {{ $hospital->emergency_phone }}
                                                </a>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <span class="inline-flex rounded-full bg-success-50 px-2 py-0.5 text-xs font-bold text-success">
                                                {{ $hospital->ambulances_count }} ambulan
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <span class="inline-flex rounded-full bg-primary-50 px-2 py-0.5 text-xs font-bold text-primary">
                                                {{ $hospital->driver_profiles_count }} driver
                                            </span>
                                        </td>
                                        <td class="hidden px-4 py-3 text-center text-xs text-muted sm:table-cell">
                                            {{ $hospital->emergency_requests_count }} total
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <a href="{{ route('dashboard.hospital', ['hospital_id' => $hospital->id]) }}" class="inline-flex h-8 items-center gap-1 rounded-lg border border-line bg-card px-2.5 text-xs font-semibold text-ink/70 transition hover:border-primary hover:text-primary">
                                                Dashboard <x-icon name="arrow-right" class="size-3" />
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr data-empty-row>
                                        <td colspan="7" class="px-4 py-10 text-center text-sm text-muted">
                                            Belum ada rumah sakit yang terdaftar dalam sistem.
                                        </td>
                                    </tr>
                                @endforelse
                                <tr data-filter-empty style="display: none;">
                                    <td colspan="7" class="px-4 py-8 text-center text-xs text-muted">
                                        Tidak ada rumah sakit yang cocok dengan filter pencarian.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
