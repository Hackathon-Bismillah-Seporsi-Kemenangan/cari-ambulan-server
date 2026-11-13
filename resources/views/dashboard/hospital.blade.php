@extends('layouts.dashboard')

@section('title', 'Dashboard Rumah Sakit')
@section('page-title', 'Dashboard Rumah Sakit')

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
<style>
    .leaflet-container { font-family: inherit; }
</style>
@endpush

@section('nav-items')
    @if($hospital)
        <div class="hidden md:block mb-3 rounded-xl border border-slate-800 bg-slate-800/60 p-3">
            <div class="flex items-center gap-2">
                <span class="size-2 rounded-full bg-emerald-400 animate-pulse"></span>
                <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-400">IGD Standby 24 Jam</span>
            </div>
            <p class="mt-1.5 truncate text-xs font-bold text-white">{{ $hospital->name }}</p>
            @if($hospital->emergency_phone)
                <a href="tel:{{ $hospital->emergency_phone }}" class="mt-1 inline-flex items-center gap-1.5 text-[11px] font-semibold text-rose-400 hover:text-rose-300">
                    <x-icon name="phone" class="size-3" />
                    <span class="font-mono">{{ $hospital->emergency_phone }}</span>
                </a>
            @endif
        </div>
    @endif

    <div class="hidden md:block px-2 pb-1.5 text-[10px] font-bold uppercase tracking-wider text-slate-400">
        Operasional IGD
    </div>

    <a
        href="#permintaan"
        data-tab-target="permintaan"
        role="tab"
        aria-selected="true"
        class="sidebar-tab-link group flex h-12 min-w-0 md:h-11 md:w-full flex-col md:flex-row items-center justify-center md:justify-between gap-0.5 md:gap-3 px-2 md:px-3 self-stretch rounded-xl text-[10px] md:text-xs font-semibold text-slate-300 transition hover:bg-slate-800/80 hover:text-white cursor-pointer"
    >
        <div class="flex items-center gap-1 md:gap-2.5 min-w-0">
            <x-icon name="sos" class="size-4 shrink-0 text-rose-400 group-[.bg-primary]:text-white" />
            <span class="truncate">Antrean & Rujukan IGD</span>
        </div>
        <span class="hidden md:inline-flex items-center rounded-full border border-rose-500/30 bg-rose-500/20 px-2 py-0.5 text-[10px] font-bold text-rose-300 group-[.bg-primary]:border-white/30 group-[.bg-primary]:bg-white/20 group-[.bg-primary]:text-white">
            {{ $recentRequests->count() }}
        </span>
    </a>

    <a
        href="#driver"
        data-tab-target="driver"
        role="tab"
        aria-selected="false"
        class="sidebar-tab-link group flex h-12 min-w-0 md:h-11 md:w-full flex-col md:flex-row items-center justify-center md:justify-between gap-0.5 md:gap-3 px-2 md:px-3 self-stretch rounded-xl text-[10px] md:text-xs font-semibold text-slate-300 transition hover:bg-slate-800/80 hover:text-white cursor-pointer"
    >
        <div class="flex items-center gap-1 md:gap-2.5 min-w-0">
            <x-icon name="truck" class="size-4 shrink-0 text-emerald-400 group-[.bg-primary]:text-white" />
            <span class="truncate">Tim Driver</span>
        </div>
        <span class="hidden md:inline-flex items-center rounded-full border border-slate-700/60 bg-slate-800 px-2 py-0.5 text-[10px] font-semibold text-slate-300 group-[.bg-primary]:border-white/30 group-[.bg-primary]:bg-white/20 group-[.bg-primary]:text-white">
            {{ $availableDrivers->count() }} On
        </span>
    </a>

    <a
        href="#faskes"
        data-tab-target="faskes"
        role="tab"
        aria-selected="false"
        class="sidebar-tab-link group flex h-12 min-w-0 md:h-11 md:w-full flex-col md:flex-row items-center justify-center md:justify-between gap-0.5 md:gap-3 px-2 md:px-3 self-stretch rounded-xl text-[10px] md:text-xs font-semibold text-slate-300 transition hover:bg-slate-800/80 hover:text-white cursor-pointer"
    >
        <div class="flex items-center gap-1 md:gap-2.5 min-w-0">
            <x-icon name="hospital" class="size-4 shrink-0 text-slate-400 group-[.bg-primary]:text-white" />
            <span class="truncate">Info & Gerbang IGD</span>
        </div>
        <span class="hidden md:inline-flex items-center rounded-full border border-slate-700/60 bg-slate-800 px-2 py-0.5 text-[10px] font-bold text-slate-300 group-[.bg-primary]:border-white/30 group-[.bg-primary]:bg-white/20 group-[.bg-primary]:text-white">
            Faskes
        </span>
    </a>

    <div class="hidden md:block my-2 border-t border-slate-800"></div>

    <div class="hidden md:block px-2 pt-1 pb-1.5 text-[10px] font-bold uppercase tracking-wider text-slate-400">
        Bantuan & Integrasi
    </div>

    <a
        href="tel:119"
        class="hidden md:flex h-10 w-full items-center gap-2.5 rounded-xl px-3 text-xs font-semibold text-slate-300 transition hover:bg-slate-800/80 hover:text-rose-400"
    >
        <x-icon name="phone" class="size-4 shrink-0 text-rose-400" />
        <span class="truncate">Panggilan SPGDT 119</span>
    </a>

    @if(auth()->user()->hasRole('administrator'))
        <a
            href="{{ route('dashboard.admin') }}"
            class="flex h-12 md:h-10 min-w-0 md:w-full flex-col md:flex-row items-center justify-center md:justify-start gap-0.5 md:gap-2.5 px-2 md:px-3 rounded-xl text-[10px] md:text-xs font-semibold text-slate-300 transition hover:bg-slate-800/80 hover:text-white"
        >
            <x-icon name="meters" class="size-4 shrink-0" />
            <span class="truncate">Kembali ke Admin</span>
        </a>
    @endif
@endsection

@section('topbar-actions')
    @if(isset($allHospitals) && $allHospitals->count() > 1 && auth()->user()->hasRole('administrator'))
        <form method="GET" action="{{ route('dashboard.hospital') }}" class="flex items-center gap-1.5">
            <label for="hospital_id" class="hidden sm:inline text-xs text-muted font-medium">Pilih RS:</label>
            <select
                id="hospital_id"
                name="hospital_id"
                onchange="this.form.submit()"
                class="h-9 rounded-xl border border-line bg-card px-3 text-xs font-semibold text-ink focus:border-primary focus:outline-none"
            >
                @foreach($allHospitals as $hosp)
                    <option value="{{ $hosp->id }}" {{ $hospital?->id === $hosp->id ? 'selected' : '' }}>
                        {{ $hosp->name }}
                    </option>
                @endforeach
            </select>
        </form>
    @endif
@endsection

@section('content')
    @if(session('success'))
        <div class="mx-auto max-w-5xl mb-6">
            <x-alert-banner tone="success">{{ session('success') }}</x-alert-banner>
        </div>
    @endif
    @if($errors->any())
        <div class="mx-auto max-w-5xl mb-6">
            <x-alert-banner tone="danger">{{ $errors->first() }}</x-alert-banner>
        </div>
    @endif

    @if(! $hospital)
        <div class="mx-auto max-w-2xl py-6">
            <div class="rounded-2xl border border-line bg-card p-6 shadow-sm">
                <div class="flex items-center gap-3 border-b border-line pb-4">
                    <span class="flex size-12 items-center justify-center rounded-xl bg-primary text-white shadow-md shadow-primary/20">
                        <x-icon name="hospital" class="size-6" />
                    </span>
                    <div>
                        <h2 class="text-xl font-extrabold text-ink">Lengkapi Data Fasilitas Rumah Sakit</h2>
                        <p class="text-xs text-ink/60">Akun Anda belum memiliki profil rumah sakit terhubung. Daftarkan fasilitas Anda untuk mengaktifkan pemantauan IGD.</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('dashboard.hospital.setup') }}" class="mt-6 space-y-4">
                    @csrf

                    <x-input name="name" label="Nama Rumah Sakit" icon="hospital" placeholder="Contoh: RSUD Medika Utama" required :error="$errors->first('name')" />

                    <x-input name="address" label="Alamat Lengkap Rumah Sakit" icon="map" placeholder="Jl. Kesehatan No. 10, Jakarta Pusat" required :error="$errors->first('address')" />

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <x-input name="emergency_phone" type="tel" label="Nomor Darurat / Hotline IGD" icon="phone" placeholder="021-5550119" required :error="$errors->first('emergency_phone')" />
                        <x-input name="ambulance_entrance" label="Jalur / Pintu Masuk Ambulans" icon="ambulance" placeholder="Contoh: Gerbang IGD Timur" :error="$errors->first('ambulance_entrance')" />
                    </div>

                    {{-- Fasilitas Kesehatan (Faskes) Tersedia --}}
                    @php
                        $setupFacilityOptions = [
                            'IGD 24 Jam',
                            'ICU',
                            'NICU',
                            'PICU',
                            'Ruang Operasi',
                            'Radiologi',
                            'Laboratorium',
                            'Farmasi',
                            'Rawat Inap',
                            'Hemodialisis',
                        ];
                        $selectedSetupFacilities = old('health_facilities', ['IGD 24 Jam']);
                    @endphp
                    <div class="rounded-xl border border-line bg-surface p-4">
                        <label class="block text-xs font-bold text-ink">Fasilitas Kesehatan (Faskes) Tersedia</label>
                        <p class="mb-3 text-[11px] text-ink/60">Pilih fasilitas yang tersedia untuk mendukung kesiapan rujukan IGD:</p>
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                            @foreach($setupFacilityOptions as $fac)
                                <label class="flex items-center gap-2 rounded-lg border border-line bg-card px-3 py-2 text-xs font-semibold text-ink/80 transition hover:border-primary/40 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        name="health_facilities[]"
                                        value="{{ $fac }}"
                                        @checked(in_array($fac, $selectedSetupFacilities, true))
                                        class="size-4 rounded border-line text-primary focus:ring-primary/40"
                                    >
                                    <span>{{ $fac }}</span>
                                </label>
                            @endforeach
                        </div>
                        @error('health_facilities')
                            <p class="mt-2 text-xs font-medium text-danger">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Titik GPS Statis Faskes --}}
                    <div class="rounded-xl border border-line bg-surface p-4 space-y-3">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                            <div>
                                <span class="block text-xs font-bold text-ink">Titik Koordinat GPS Statis Faskes</span>
                                <span class="text-[11px] text-muted">Titik fisik permanen untuk rujukan dan navigasi ambulans</span>
                            </div>
                        </div>

                        {{-- Pencarian Lokasi Cepat dari Nama/Alamat RS --}}
                        <div class="rounded-xl border border-line bg-card p-3 space-y-2">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1.5">
                                <span class="text-xs font-bold text-ink flex items-center gap-1">
                                    <x-icon name="search" class="size-3.5 text-primary" /> Cari Titik Lokasi Rumah Sakit di Peta:
                                </span>
                                <button type="button" id="btnSetupSyncFromForm" class="text-[11px] font-semibold text-primary hover:underline self-start sm:self-auto cursor-pointer">
                                    Gunakan Nama &amp; Alamat dari Form &rarr;
                                </button>
                            </div>
                            <div class="flex gap-2">
                                <input
                                    type="text"
                                    id="setupMapSearchInput"
                                    placeholder="Ketik nama RS atau alamat (contoh: RSUD Tarakan Jakarta)..."
                                    class="h-9 flex-1 rounded-xl border border-line bg-surface px-3 text-xs text-ink placeholder:text-muted focus:border-primary focus:outline-none"
                                >
                                <button
                                    type="button"
                                    id="btnSetupSearchLocation"
                                    class="inline-flex h-9 items-center justify-center gap-1.5 rounded-xl bg-primary px-3 text-xs font-bold text-white shadow-xs transition hover:bg-primary/90 shrink-0 cursor-pointer"
                                >
                                    <x-icon name="search" class="size-3.5" /> Cari di Peta
                                </button>
                            </div>
                        </div>

                        {{-- Opsi GPS & Status --}}
                        <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-2">
                            <button
                                type="button"
                                id="btnGetSetupLocation"
                                class="inline-flex h-9 items-center justify-center gap-1.5 rounded-xl border border-line bg-card px-3 text-xs font-bold text-ink shadow-xs transition hover:border-danger hover:text-danger active:scale-95 cursor-pointer"
                            >
                                <x-icon name="map" class="size-3.5 text-danger" /> Ambil dari GPS Perangkat Saat Ini
                            </button>
                            <div id="setupGpsBadge" class="text-[11px] font-semibold text-muted text-center sm:text-right">
                                Geser pin atau klik pada peta untuk menentukan posisi gedung / gerbang IGD
                            </div>
                        </div>

                        {{-- Pemberitahuan Ramah jika Browser Membatasi HTTP --}}
                        <div id="setupHttpGpsNotice" class="hidden rounded-xl border border-warning/30 bg-warning-50 p-2.5 text-xs text-ink/80 leading-relaxed">
                            <span class="font-bold text-warning block mb-0.5">💡 Info Akses Lokasi Browser:</span>
                            Browser Anda membatasi deteksi GPS otomatis karena website diakses via HTTP. Anda dapat menggunakan kotak <strong>Cari Titik Lokasi</strong> di atas, atau klik langsung pada peta untuk menandai titik IGD.
                        </div>

                        <div class="overflow-hidden rounded-xl border border-line">
                            <div id="setupHospitalMap" class="h-48 w-full bg-card"></div>
                        </div>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <label for="setup_latitude" class="block text-xs font-bold text-ink mb-1">Latitude</label>
                                <input
                                    type="number"
                                    step="any"
                                    id="setup_latitude"
                                    name="latitude"
                                    value="{{ old('latitude', '-6.20880000') }}"
                                    placeholder="-6.20880000"
                                    required
                                    class="h-10 w-full rounded-xl border border-line bg-card px-3 font-mono text-xs font-bold text-ink focus:border-primary focus:outline-none"
                                >
                            </div>
                            <div>
                                <label for="setup_longitude" class="block text-xs font-bold text-ink mb-1">Longitude</label>
                                <input
                                    type="number"
                                    step="any"
                                    id="setup_longitude"
                                    name="longitude"
                                    value="{{ old('longitude', '106.84560000') }}"
                                    placeholder="106.84560000"
                                    required
                                    class="h-10 w-full rounded-xl border border-line bg-card px-3 font-mono text-xs font-bold text-ink focus:border-primary focus:outline-none"
                                >
                            </div>
                        </div>
                    </div>

                    <div class="pt-2">
                        <button type="submit" class="flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-primary text-sm font-bold text-white shadow-sm transition hover:bg-primary/90 active:scale-[0.98]">
                            Simpan &amp; Aktifkan Dashboard Rumah Sakit <x-icon name="arrow-right" class="size-4" />
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @else
        <div class="space-y-6">
            {{-- Executive Hospital Command Bar (Hospital Kit Standard) --}}
            <div class="rounded-2xl border border-slate-800 bg-slate-900/90 p-5 shadow-xs">
                <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-center">
                    <div class="flex items-start gap-4">
                        <div class="flex size-14 shrink-0 items-center justify-center rounded-2xl border border-slate-800 bg-slate-950 text-sky-400">
                            <x-icon name="hospital" class="size-7" />
                        </div>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-500/30 bg-emerald-500/10 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-emerald-400">
                                    <span class="size-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                    IGD SIAGA 24 JAM
                                </span>
                                @if($hospital->ambulance_entrance)
                                    <span class="inline-flex items-center gap-1.5 rounded-full border border-rose-500/30 bg-rose-500/10 px-2.5 py-0.5 text-[10px] font-bold text-rose-300">
                                        <x-icon name="ambulance" class="size-3 text-rose-400" />
                                        {{ $hospital->ambulance_entrance }}
                                    </span>
                                @endif
                                <span class="inline-flex items-center rounded-full border border-slate-700 bg-slate-800/80 px-2.5 py-0.5 text-[10px] font-semibold text-slate-300">
                                    Mitra Faskes Terverifikasi
                                </span>
                            </div>
                            <h2 class="mt-1.5 text-2xl font-black tracking-tight text-white truncate">{{ $hospital->name }}</h2>
                            <p class="text-xs text-slate-400 truncate">{{ $hospital->address }}</p>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2.5 sm:self-end lg:self-center">
                        @if($hospital->emergency_phone)
                            <a href="tel:{{ $hospital->emergency_phone }}" class="inline-flex h-10 items-center gap-2 rounded-xl border border-rose-500/30 bg-rose-500/10 px-4 text-xs font-bold text-rose-300 transition hover:bg-rose-500/20">
                                <x-icon name="phone" class="size-3.5 text-rose-400" />
                                <span>Hotline IGD: <span class="font-mono font-bold">{{ $hospital->emergency_phone }}</span></span>
                            </a>
                        @endif

                        <div class="flex items-center gap-2 rounded-xl border border-slate-800 bg-slate-950/80 px-3.5 py-2">
                            <span class="size-2 rounded-full bg-emerald-400 animate-pulse"></span>
                            <div class="text-left text-xs font-medium text-slate-400">
                                <span class="font-bold text-white">{{ $availableDrivers->count() }} Driver Online</span> &bull;
                                <span class="font-bold text-white">{{ $totalDrivers }} Total Driver</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Operational KPI Metric Cards (Hospital Kit Standard) --}}
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <div class="rounded-xl border border-slate-800 bg-slate-900/90 p-4 transition hover:border-slate-700">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-400">Tim Driver</span>
                        <span class="flex size-8 items-center justify-center rounded-lg border border-sky-500/20 bg-sky-500/10 text-sky-400">
                            <x-icon name="truck" class="size-4" />
                        </span>
                    </div>
                    <div class="mt-3">
                        <span class="text-2xl font-black tracking-tight text-white">{{ $totalDrivers }}</span>
                        <span class="block text-[11px] text-slate-400 mt-0.5">Pengemudi terdaftar</span>
                    </div>
                </div>

                <div class="rounded-xl border border-slate-800 bg-slate-900/90 p-4 transition hover:border-slate-700">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-400">Driver Standby</span>
                        <span class="flex size-8 items-center justify-center rounded-lg border border-emerald-500/20 bg-emerald-500/10 text-emerald-400">
                            <x-icon name="check" class="size-4" />
                        </span>
                    </div>
                    <div class="mt-3">
                        <span class="text-2xl font-black tracking-tight text-emerald-400">{{ $availableDrivers->count() }}</span>
                        <span class="block text-[11px] text-slate-400 mt-0.5">Siap menerima tugas</span>
                    </div>
                </div>

                <div class="rounded-xl border border-slate-800 bg-slate-900/90 p-4 transition hover:border-slate-700">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-400">Total Permintaan</span>
                        <span class="flex size-8 items-center justify-center rounded-lg border border-rose-500/20 bg-rose-500/10 text-rose-400">
                            <x-icon name="sos" class="size-4" />
                        </span>
                    </div>
                    <div class="mt-3">
                        <span class="text-2xl font-black tracking-tight text-white">{{ $totalRequests }}</span>
                        <span class="block text-[11px] text-slate-400 mt-0.5">Akumulasi panggilan</span>
                    </div>
                </div>

                <div class="rounded-xl border border-slate-800 bg-slate-900/90 p-4 transition hover:border-slate-700">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-400">Rujukan Selesai</span>
                        <span class="flex size-8 items-center justify-center rounded-lg border border-emerald-500/20 bg-emerald-500/10 text-emerald-400">
                            <x-icon name="check-circle" class="size-4" />
                        </span>
                    </div>
                    <div class="mt-3">
                        <span class="text-2xl font-black tracking-tight text-white">{{ $completedRequests }}</span>
                        <span class="block text-[11px] text-emerald-400/90 mt-0.5">Tertangani sukses di IGD</span>
                    </div>
                </div>
            </div>

            {{-- Operational Panels Container --}}
            <div data-tabs-container data-default-tab="permintaan" class="space-y-6">
                {{-- TAB 1: Antrean & Rujukan Pasien IGD --}}
                <div data-tab-content="permintaan" class="space-y-5">
                    {{-- Active Triage Alert Banner if any open request --}}
                    @php
                        $activeIncoming = $recentRequests->filter(fn($r) => in_array($r->status, [
                            \App\Enums\EmergencyStatus::DriverAssigned->value,
                            \App\Enums\EmergencyStatus::EnRouteToPickup->value,
                            \App\Enums\EmergencyStatus::ArriveAtPickup->value,
                            \App\Enums\EmergencyStatus::EnRouteToHospital->value,
                            'driver_assigned',
                            'en_route_to_pickup',
                            'arrived_at_pickup',
                            'arrive_at_pickup',
                            'on_board_to_hospital',
                            'en_route_to_hospital',
                        ]));
                    @endphp
                    @if($activeIncoming->isNotEmpty())
                        <div class="space-y-3">
                            <div class="flex items-center justify-between px-1">
                                <div class="flex items-center gap-2">
                                    <span class="size-2 rounded-full bg-rose-500 animate-ping"></span>
                                    <h3 class="text-xs font-bold uppercase tracking-wider text-rose-400">
                                        Peringatan Triase: Pasien Dalam Rujukan Menuju IGD ({{ $activeIncoming->count() }})
                                    </h3>
                                </div>
                                <span class="text-[11px] text-slate-400">Siapkan ruang resusitasi & tim medis</span>
                            </div>

                            @foreach($activeIncoming as $activeReq)
                                <div class="rounded-xl border-l-4 border-l-rose-500 border border-slate-800 bg-slate-900/95 p-4 shadow-xs">
                                    <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-center">
                                        <div class="space-y-1.5 min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="font-mono text-xs font-bold text-rose-400 bg-rose-500/10 border border-rose-500/20 px-2 py-0.5 rounded">
                                                    {{ $activeReq->request_code }}
                                                </span>
                                                <span class="text-sm font-bold text-white">
                                                    Pasien: {{ $activeReq->user?->full_name ?? 'Pasien Darurat' }}
                                                </span>
                                                <x-status-badge :status="$activeReq->status" class="text-[10px] px-2 py-0.5" />
                                            </div>

                                            <div class="flex items-start gap-2 text-xs">
                                                <span class="font-bold text-rose-400 shrink-0">{{ $activeReq->emergency_type ?? 'Kondisi Darurat' }}:</span>
                                                <span class="font-medium text-slate-200">{{ $activeReq->igdPreparation() }}</span>
                                            </div>

                                            @if($activeReq->patient_notes)
                                                <div class="rounded-lg border border-slate-800 bg-slate-950/70 px-3 py-1.5 text-xs text-slate-300">
                                                    <span class="text-slate-400 font-semibold">Catatan Pasien:</span>
                                                    <span class="italic">"{{ $activeReq->patient_notes }}"</span>
                                                </div>
                                            @endif
                                        </div>

                                        <div class="flex flex-wrap shrink-0 items-center gap-3">
                                            <div class="flex shrink-0 items-center gap-3 rounded-xl border border-slate-800 bg-slate-950/80 px-3.5 py-2 text-xs">
                                                <div>
                                                    <span class="block text-[10px] font-semibold text-slate-400 uppercase">Unit Evakuasi</span>
                                                    <span class="font-mono font-bold text-white">{{ $activeReq->ambulance?->plate_number ?? '-' }}</span>
                                                </div>
                                                <div class="h-7 w-px bg-slate-800"></div>
                                                <div>
                                                    <span class="block text-[10px] font-semibold text-slate-400 uppercase">Driver</span>
                                                    <span class="font-medium text-slate-200">{{ $activeReq->driverProfile?->user?->full_name ?? '-' }}</span>
                                                </div>
                                            </div>

                                            <div class="flex items-center gap-1.5">
                                                <form method="POST" action="{{ route('dashboard.hospital.requests.update-status', $activeReq->id) }}" class="inline">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="status" value="completed">
                                                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white px-3 py-2 text-xs font-bold transition shadow-xs" title="Tandai pasien telah diterima di IGD">
                                                        <x-icon name="check" class="size-3.5" /> Selesai di IGD
                                                    </button>
                                                </form>

                                                <form method="POST" action="{{ route('dashboard.hospital.requests.update-status', $activeReq->id) }}" class="inline" onsubmit="return confirm('Apakah Anda yakin ingin membatalkan antrean pasien ini?')">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="status" value="cancelled">
                                                    <input type="hidden" name="cancel_reason" value="Dibatalkan oleh Petugas IGD">
                                                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg border border-rose-500/30 bg-rose-500/10 hover:bg-rose-500/20 text-rose-300 px-2.5 py-2 text-xs font-semibold transition" title="Batalkan antrean jika terjadi kendala">
                                                        <x-icon name="x" class="size-3.5" /> Batal
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    {{-- Patient Queue Table (Hospital Kit Standard) --}}
                    <div class="rounded-2xl border border-slate-800 bg-slate-900/90 shadow-xs">
                        <div class="flex flex-col justify-between gap-3 border-b border-slate-800 p-5 sm:flex-row sm:items-center">
                            <div>
                                <div class="flex items-center gap-2">
                                    <h3 class="text-base font-bold text-white">Antrean & Rujukan Pasien IGD</h3>
                                    <span id="ws-status-indicator" class="inline-flex items-center gap-1.5 rounded-full border border-emerald-500/30 bg-emerald-500/10 px-2.5 py-0.5 text-[10px] font-bold text-emerald-400">
                                        <span class="size-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                        WebSocket Realtime
                                    </span>
                                </div>
                                <p class="text-xs text-slate-400 mt-0.5">Daftar panggilan darurat dan pasien aktif yang sedang dirujuk menuju rumah sakit ini</p>
                            </div>

                            <div class="relative">
                                <input
                                    type="text"
                                    placeholder="Cari kode, pasien, driver..."
                                    data-quick-filter="#requests-table"
                                    class="h-9.5 w-60 sm:w-72 rounded-xl border border-slate-700 bg-slate-950/80 pl-9 pr-3 text-xs text-white placeholder-slate-500 focus:border-primary focus:outline-none"
                                />
                                <span class="pointer-events-none absolute left-3 top-2.5 text-slate-400">
                                    <x-icon name="search" class="size-4" />
                                </span>
                            </div>
                        </div>

                        <div class="overflow-x-auto">
                            <table id="requests-table" data-hospital-id="{{ $hospital->id }}" class="w-full text-left text-sm">
                                <thead>
                                    <tr class="border-b border-slate-800 bg-slate-950/60 text-[11px] font-bold uppercase tracking-wider text-slate-400">
                                        <th class="px-5 py-3.5">Kode</th>
                                        <th class="px-5 py-3.5">Pasien</th>
                                        <th class="px-5 py-3.5">Kondisi & Persiapan IGD</th>
                                        <th class="hidden px-5 py-3.5 sm:table-cell">Driver & Unit</th>
                                        <th class="px-5 py-3.5">Status</th>
                                        <th class="hidden px-5 py-3.5 md:table-cell">Waktu</th>
                                        <th class="px-5 py-3.5 text-right">Kelola Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-800 text-xs">
                                    @forelse($recentRequests as $req)
                                        <tr class="hover:bg-slate-800/40 transition duration-300" data-request-id="{{ $req->id }}" data-request-code="{{ $req->request_code }}">
                                            <td class="px-5 py-4 font-mono font-bold text-sky-400">
                                                {{ $req->request_code }}
                                            </td>
                                            <td class="px-5 py-4">
                                                <p class="font-bold text-white text-xs sm:text-sm">{{ $req->user?->full_name ?? '-' }}</p>
                                                @if($req->user?->phone)
                                                    <a href="tel:{{ $req->user->phone }}" class="text-[11px] text-slate-400 hover:text-white font-mono">
                                                        {{ $req->user->phone }}
                                                    </a>
                                                @endif
                                            </td>
                                            <td class="px-5 py-4">
                                                <div class="font-bold text-slate-200 flex items-center gap-1.5">
                                                    <span>{{ $req->emergency_type ?? '-' }}</span>
                                                    @if($req->severity_level === 'critical')
                                                        <span class="size-2 rounded-full bg-rose-500 animate-pulse" title="Kritis"></span>
                                                    @endif
                                                </div>
                                                <p class="text-[11px] text-slate-400 mt-0.5">{{ $req->igdPreparation() }}</p>
                                            </td>
                                            <td class="hidden px-5 py-4 sm:table-cell" data-field="driver-unit">
                                                @if($req->driverProfile?->user)
                                                    <p class="font-semibold text-white">{{ $req->driverProfile->user->full_name }}</p>
                                                    <span class="font-mono text-[11px] text-slate-400 font-semibold">{{ $req->ambulance?->plate_number ?? '-' }}</span>
                                                @else
                                                    <span class="text-slate-500 italic">Mencari driver...</span>
                                                @endif
                                            </td>
                                            <td class="px-5 py-4" data-field="status">
                                                <x-status-badge :status="$req->status" dot>{{ $req->status }}</x-status-badge>
                                            </td>
                                            <td class="hidden px-5 py-4 text-slate-400 md:table-cell">
                                                {{ $req->created_at->diffForHumans() }}
                                            </td>
                                            <td class="px-5 py-4 text-right">
                                                <form method="POST" action="{{ route('dashboard.hospital.requests.update-status', $req->id) }}" class="inline-flex items-center gap-1.5 justify-end">
                                                    @csrf
                                                    @method('PATCH')
                                                    <select name="status" class="rounded-lg border border-slate-700 bg-slate-950 px-2 py-1 text-xs text-white focus:border-sky-500 focus:outline-none">
                                                        <option value="searching_driver" @selected($req->status === 'searching_driver')>Mencari Driver</option>
                                                        <option value="driver_assigned" @selected($req->status === 'driver_assigned')>Driver Ditugaskan</option>
                                                        <option value="en_route_to_pickup" @selected($req->status === 'en_route_to_pickup')>Menuju Pasien</option>
                                                        <option value="arrive_at_pickup" @selected($req->status === 'arrive_at_pickup')>Tiba di Lokasi</option>
                                                        <option value="en_route_to_hospital" @selected($req->status === 'en_route_to_hospital')>Menuju RS/IGD</option>
                                                        <option value="completed" @selected($req->status === 'completed')>Selesai di IGD</option>
                                                        <option value="cancelled" @selected($req->status === 'cancelled')>Batalkan</option>
                                                    </select>
                                                    <button type="submit" class="rounded-lg bg-sky-600 hover:bg-sky-500 text-white px-2.5 py-1 text-xs font-semibold transition" title="Update status antrean">
                                                        Ubah
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr data-empty-row>
                                            <td colspan="7" class="px-5 py-12 text-center text-slate-400">
                                                Belum ada permintaan darurat yang tercatat untuk rumah sakit ini.
                                            </td>
                                        </tr>
                                    @endforelse
                                    <tr data-filter-empty style="display: none;">
                                        <td colspan="7" class="px-5 py-8 text-center text-slate-400">
                                            Tidak ada permintaan darurat yang sesuai dengan filter pencarian.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- TAB 2: Tim Driver --}}
                <div data-tab-content="driver" class="space-y-4 hidden">
                    <div class="rounded-2xl border border-slate-800 bg-slate-900/90 p-5 shadow-xs">
                        <div class="mb-5 flex flex-col justify-between gap-3 border-b border-slate-800 pb-4 sm:flex-row sm:items-center">
                            <div>
                                <h3 class="text-base font-bold text-white flex items-center gap-2">
                                    <x-icon name="truck" class="size-5 text-emerald-400" />
                                    Tim Driver Ambulans ({{ $drivers->count() }})
                                </h3>
                                <p class="text-xs text-slate-400 mt-0.5">Pengemudi terlatih yang beroperasi di bawah unit rujukan rumah sakit ini</p>
                            </div>
                            <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-500/30 bg-emerald-500/10 px-3 py-1 text-xs font-bold text-emerald-400">
                                <span class="size-1.5 rounded-full bg-emerald-400 animate-pulse"></span> {{ $availableDrivers->count() }} Driver Online
                            </span>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            @forelse($drivers as $drv)
                                <div class="flex flex-col justify-between rounded-xl border border-slate-800 bg-slate-950/70 p-4 transition hover:border-slate-700 hover:bg-slate-950">
                                    <div>
                                        <div class="flex items-start justify-between gap-2">
                                            <div class="flex items-center gap-3">
                                                <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-slate-800 text-sm font-bold text-white border border-slate-700">
                                                    {{ substr($drv->user?->full_name ?? 'D', 0, 1) }}
                                                </span>
                                                <div class="min-w-0">
                                                    <h4 class="text-sm font-bold text-white truncate">{{ $drv->user?->full_name ?? 'Driver' }}</h4>
                                                    <p class="text-xs text-slate-400 font-mono">{{ $drv->user?->phone ?? '-' }}</p>
                                                </div>
                                            </div>
                                            <span class="rounded-full px-2.5 py-0.5 text-[10px] font-bold capitalize {{ $drv->availability_status === 'online' ? 'border border-emerald-500/30 bg-emerald-500/10 text-emerald-400' : ($drv->availability_status === 'busy' ? 'border border-amber-500/30 bg-amber-500/10 text-amber-400' : 'border border-slate-700 bg-slate-800 text-slate-400') }}">
                                                {{ $drv->availability_status }}
                                            </span>
                                        </div>

                                        <div class="mt-4 border-t border-slate-800/80 pt-3 space-y-1.5 text-xs">
                                            <div class="flex items-center justify-between">
                                                <span class="text-slate-400">Nomor SIM:</span>
                                                <span class="font-semibold font-mono text-slate-200">{{ $drv->license_number }}</span>
                                            </div>
                                            <div class="flex items-center justify-between">
                                                <span class="text-slate-400">Ambulans:</span>
                                                <span class="font-semibold font-mono text-white">{{ $drv->ambulance?->plate_number ?? 'Belum ada unit' }}</span>
                                            </div>
                                        </div>
                                    </div>

                                    @if($drv->user?->phone)
                                        <a href="tel:{{ $drv->user->phone }}" class="mt-4 flex h-9 items-center justify-center gap-1.5 rounded-xl border border-slate-700 bg-slate-900 text-xs font-bold text-slate-200 transition hover:bg-slate-800 hover:text-white">
                                            <x-icon name="phone" class="size-3.5" /> Hubungi Driver
                                        </a>
                                    @endif
                                </div>
                            @empty
                                <div class="col-span-3 py-10 text-center">
                                    <x-empty-state
                                        icon="truck"
                                        title="Belum ada data driver"
                                        description="Tidak ada driver ambulans yang terdaftar di rumah sakit ini."
                                    />
                                </div>
                            @endforelse
                        </div>
                    </div>

                    {{-- Form Input Driver Baru --}}
                    <div class="rounded-2xl border border-slate-800 bg-slate-900/90 p-5 shadow-xs">
                        <div class="border-b border-slate-800 pb-3 mb-4">
                            <h3 class="flex items-center gap-2 text-base font-bold text-white">
                                <x-icon name="user" class="size-5 text-emerald-400" /> Input Driver Ambulans Baru
                            </h3>
                            <p class="text-xs text-slate-400 mt-0.5">Daftarkan akun supir ambulans baru di bawah naungan faskes {{ $hospital->name }}</p>
                        </div>

                        <form method="POST" action="{{ route('dashboard.hospital.drivers.store') }}" class="space-y-4">
                            @csrf
                            @if($user->hasRole('administrator'))
                                <input type="hidden" name="hospital_id" value="{{ $hospital->id }}">
                            @endif

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <x-input name="full_name" label="Nama Lengkap Driver" icon="user" placeholder="Contoh: Supriyadi" required :error="$errors->first('full_name')" />
                                <x-input name="phone" type="tel" label="Nomor Telepon / WhatsApp" icon="phone" placeholder="081234567890" required :error="$errors->first('phone')" />
                            </div>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <x-input name="email" type="email" label="Alamat Email (Login Aplikasi Driver)" icon="mail" placeholder="driver@faskes.id" required :error="$errors->first('email')" />
                                <x-input name="license_number" label="Nomor SIM Driver" icon="shield" placeholder="Contoh: SIM-B1-889900" required :error="$errors->first('license_number')" />
                            </div>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <x-input name="password" type="password" label="Kata Sandi Akun" icon="lock" placeholder="Minimal 8 karakter" required :error="$errors->first('password')" />
                                <div>
                                    <label for="ambulance_id" class="mb-1.5 block text-xs font-bold text-ink">Tugaskan ke Unit Ambulans (Opsional)</label>
                                    <select id="ambulance_id" name="ambulance_id" class="h-10 w-full rounded-xl border border-line bg-card px-3 text-xs text-ink focus:border-primary focus:outline-none">
                                        <option value="">-- Belum Ditugaskan / Pasangkan Nanti --</option>
                                        @foreach($ambulances as $ambOption)
                                            <option value="{{ $ambOption->id }}">
                                                {{ $ambOption->plate_number }} ({{ $ambOption->model_type ?? 'Ambulans' }})
                                                @if($ambOption->driver_profile_id) - Akan menggantikan driver lama @endif
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('ambulance_id')
                                        <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <x-input name="alamat" label="Alamat Domisili Driver (Opsional)" icon="map" placeholder="Alamat tempat tinggal pengemudi..." :value="old('alamat')" :error="$errors->first('alamat')" />

                            <div class="flex justify-end pt-2">
                                <button type="submit" class="flex h-10 items-center gap-2 rounded-xl bg-primary px-5 text-xs font-bold text-white shadow-sm transition hover:bg-primary/90 active:scale-98 cursor-pointer">
                                    <x-icon name="user" class="size-4" /> Daftarkan Driver Ambulans
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                {{-- TAB 4: Info & Gerbang Masuk IGD --}}
                <div data-tab-content="faskes" class="space-y-4 hidden">
                    <div class="grid gap-5 md:grid-cols-2">
                        {{-- Hospital Geo & Entrance Info --}}
                        <div class="rounded-2xl border border-slate-800 bg-slate-900/90 p-5 shadow-xs space-y-4">
                            <div class="border-b border-slate-800 pb-3">
                                <h3 class="text-base font-bold text-white flex items-center gap-2">
                                    <x-icon name="hospital" class="size-5 text-sky-400" /> Informasi Titik Masuk Faskes
                                </h3>
                                <p class="text-xs text-slate-400 mt-0.5">Panduan titik rujukan fisik dan jalur evakuasi ambulans</p>
                            </div>

                            <div class="space-y-3.5 text-xs">
                                <div>
                                    <span class="block text-[11px] font-bold text-slate-400 uppercase">Nama Rumah Sakit</span>
                                    <p class="text-sm font-bold text-white mt-0.5">{{ $hospital->name }}</p>
                                </div>

                                <div>
                                    <span class="block text-[11px] font-bold text-slate-400 uppercase">Alamat Lengkap</span>
                                    <p class="text-slate-300 mt-0.5 leading-relaxed">{{ $hospital->address }}</p>
                                </div>

                                <div class="rounded-xl border border-rose-500/30 bg-rose-500/10 p-3.5">
                                    <span class="block text-[11px] font-bold text-rose-400 uppercase tracking-wide">Titik Masuk Ambulans (Ambulance Entrance)</span>
                                    <p class="mt-1 text-sm font-black text-white">
                                        {{ $hospital->ambulance_entrance ?? 'Pintu Masuk Khusus Evakuasi & IGD' }}
                                    </p>
                                    <p class="mt-1 text-[11px] text-slate-300">
                                        Jalur bebas hambatan untuk supir ambulans membawa pasien rujukan langsung ke ruang resusitasi IGD.
                                    </p>
                                </div>

                                <div>
                                    <span class="block text-[11px] font-bold text-slate-400 uppercase">Koordinat Geografis (GPS)</span>
                                    <p class="font-mono text-slate-200 mt-0.5">
                                        Lat: {{ $hospital->latitude ?? '-' }}, Long: {{ $hospital->longitude ?? '-' }}
                                    </p>
                                </div>

                                @if($hospital->latitude && $hospital->longitude)
                                    <div class="pt-2">
                                        <a
                                            href="https://www.google.com/maps/search/?api=1&query={{ $hospital->latitude }},{{ $hospital->longitude }}"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="inline-flex h-9 items-center gap-2 rounded-xl bg-primary px-4 text-xs font-bold text-white transition hover:bg-primary/90"
                                        >
                                            <x-icon name="map" class="size-4" /> Buka di Google Maps
                                        </a>
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- Emergency Contacts & Triage SOP --}}
                        <div class="rounded-2xl border border-slate-800 bg-slate-900/90 p-5 shadow-xs space-y-4">
                            <div class="border-b border-slate-800 pb-3">
                                <h3 class="text-base font-bold text-white flex items-center gap-2">
                                    <x-icon name="phone" class="size-5 text-rose-400" /> Kontak Darurat & SOP Penerimaan IGD
                                </h3>
                                <p class="text-xs text-slate-400 mt-0.5">Saluran koordinasi cepat antara posko 119, supir, dan IGD</p>
                            </div>

                            <div class="space-y-3">
                                <div class="rounded-xl border border-slate-800 bg-slate-950/70 p-3.5">
                                    <span class="block text-[10px] font-bold text-slate-400 uppercase">Hotline IGD Faskes</span>
                                    @if($hospital->emergency_phone)
                                        <a href="tel:{{ $hospital->emergency_phone }}" class="mt-1 flex items-center gap-1.5 text-base font-extrabold text-rose-400 hover:underline font-mono">
                                            <x-icon name="phone" class="size-4" /> {{ $hospital->emergency_phone }}
                                        </a>
                                    @else
                                        <p class="text-xs text-slate-500 mt-1">Belum diatur</p>
                                    @endif
                                </div>

                                <div class="rounded-xl border border-slate-800 bg-slate-950/70 p-3.5">
                                    <span class="block text-[10px] font-bold text-slate-400 uppercase">Pusat Bantuan Evakuasi Darurat Nasional</span>
                                    <a href="tel:119" class="mt-1 flex items-center gap-1.5 text-base font-extrabold text-sky-400 hover:underline font-mono">
                                        <x-icon name="sos" class="size-4" /> 119 (Sistem Penanggulangan Gawat Darurat Terpadu)
                                    </a>
                                </div>

                                <div class="rounded-xl border border-slate-800 bg-slate-950/40 p-3.5 text-xs text-slate-300 space-y-2">
                                    <span class="block text-[10px] font-bold text-white uppercase tracking-wide">SOP Penerimaan Rujukan Pasien:</span>
                                    <ul class="space-y-1.5 pl-4 list-disc text-[11px] text-slate-400">
                                        <li>Petugas IGD memverifikasi notifikasi rujukan dari aplikasi Cari Ambulan.</li>
                                        <li>Menyiapkan tim resusitasi atau ruang isolasi sesuai kondisi triage pasien.</li>
                                        <li>Melakukan serah terima pasien dari pengemudi ambulans dan mencatat log waktu.</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Form Perbarui Informasi Rumah Sakit --}}
                    <div class="rounded-2xl border border-line bg-card p-6 shadow-sm">
                        <div class="border-b border-line pb-3">
                            <h3 class="flex items-center gap-2 text-base font-bold text-ink">
                                <x-icon name="edit" class="size-5 text-primary" /> Perbarui Informasi Fasilitas Rumah Sakit
                            </h3>
                            <p class="text-xs text-ink/60">Sesuaikan nama, alamat, nomor darurat, dan pintu masuk ambulans faskes</p>
                        </div>

                        <form method="POST" action="{{ route('dashboard.hospital.profile') }}" class="mt-4 space-y-4">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="hospital_id" value="{{ $hospital->id }}">

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <x-input name="name" label="Nama Rumah Sakit" icon="hospital" :value="old('name', $hospital->name)" required :error="$errors->first('name')" />
                                <x-input name="emergency_phone" type="tel" label="Hotline IGD / Darurat" icon="phone" :value="old('emergency_phone', $hospital->emergency_phone)" required :error="$errors->first('emergency_phone')" />
                            </div>

                            <x-input name="address" label="Alamat Lengkap" icon="map" :value="old('address', $hospital->address)" required :error="$errors->first('address')" />

                            @php
                                $healthFacilityOptions = [
                                    'IGD 24 Jam',
                                    'ICU',
                                    'NICU',
                                    'PICU',
                                    'Ruang Operasi',
                                    'Radiologi',
                                    'Laboratorium',
                                    'Farmasi',
                                    'Rawat Inap',
                                    'Hemodialisis',
                                ];
                                $selectedHealthFacilities = old('health_facilities', $hospital->health_facilities ?? []);
                            @endphp

                            <fieldset class="rounded-xl border border-line bg-surface/60 p-4">
                                <legend class="px-1 text-xs font-bold text-ink">Fasilitas Kesehatan (Faskes) Tersedia</legend>
                                <p class="mb-3 text-[11px] text-ink/60">Pilih fasilitas yang tersedia untuk mendukung rujukan &amp; penanganan darurat:</p>
                                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                    @foreach($healthFacilityOptions as $facility)
                                        <label class="flex items-center gap-2 rounded-lg border border-line bg-card px-3 py-2 text-xs font-semibold text-ink/80 transition hover:border-primary/40 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                name="health_facilities[]"
                                                value="{{ $facility }}"
                                                @checked(in_array($facility, $selectedHealthFacilities, true))
                                                class="size-4 rounded border-line text-primary focus:ring-primary/40"
                                            >
                                            <span>{{ $facility }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                @error('health_facilities')
                                    <p class="mt-2 text-xs font-medium text-danger">{{ $message }}</p>
                                @enderror
                            </fieldset>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                <div class="sm:col-span-1">
                                    <x-input name="ambulance_entrance" label="Jalur / Pintu Masuk Ambulans" icon="ambulance" :value="old('ambulance_entrance', $hospital->ambulance_entrance)" :error="$errors->first('ambulance_entrance')" />
                                </div>
                                <div>
                                    <x-input name="latitude" type="number" step="any" label="Latitude (GPS)" icon="map" :value="old('latitude', $hospital->latitude)" :error="$errors->first('latitude')" />
                                </div>
                                <div>
                                    <x-input name="longitude" type="number" step="any" label="Longitude (GPS)" icon="map" :value="old('longitude', $hospital->longitude)" :error="$errors->first('longitude')" />
                                </div>
                            </div>

                            <div class="flex justify-end pt-2">
                                <button type="submit" class="flex h-10 items-center gap-2 rounded-xl bg-primary px-5 text-xs font-bold text-white shadow-sm transition hover:bg-primary/90">
                                    Simpan Perubahan Fasilitas
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const setupMapContainer = document.getElementById('setupHospitalMap');
        if (setupMapContainer) {
            const latInput = document.getElementById('setup_latitude');
            const lngInput = document.getElementById('setup_longitude');
            const btnGetLocation = document.getElementById('btnGetSetupLocation');
            const gpsBadge = document.getElementById('setupGpsBadge');

            let initialLat = parseFloat(latInput.value) || -6.2088;
            let initialLng = parseFloat(lngInput.value) || 106.8456;

            const hospitalIcon = L.divIcon({
                className: 'custom-hospital-pin',
                html: `
                    <div style="background-color: #ef4444; color: white; width: 32px; height: 32px; border-radius: 50% 50% 50% 0; transform: rotate(-45deg); display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 8px rgba(239,68,68,0.4); border: 2px solid white;">
                        <span style="transform: rotate(45deg); font-size: 13px; font-weight: bold;">+</span>
                    </div>
                `,
                iconSize: [32, 32],
                iconAnchor: [16, 32]
            });

            const setupMap = L.map('setupHospitalMap', {
                zoomControl: true,
                attributionControl: false
            }).setView([initialLat, initialLng], 14);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19
            }).addTo(setupMap);

            const marker = L.marker([initialLat, initialLng], {
                icon: hospitalIcon,
                draggable: true
            }).addTo(setupMap);

            const searchInput = document.getElementById('setupMapSearchInput');
            const btnSearchLocation = document.getElementById('btnSetupSearchLocation');
            const btnSyncFromForm = document.getElementById('btnSetupSyncFromForm');
            const httpNotice = document.getElementById('setupHttpGpsNotice');
            const hospitalNameInput = document.querySelector('input[name="name"]');
            const hospitalAddressInput = document.querySelector('input[name="address"]');

            function updateSetupCoords(lat, lng, detail = '') {
                latInput.value = Number(lat).toFixed(8);
                lngInput.value = Number(lng).toFixed(8);
                marker.setLatLng([lat, lng]);
                gpsBadge.innerHTML = `<span class="text-success font-bold">✓ Koordinat Statis Terkunci: ${Number(lat).toFixed(5)}, ${Number(lng).toFixed(5)}${detail ? ' (' + detail + ')' : ''}</span>`;
            }

            marker.on('dragend', function (e) {
                const pos = e.target.getLatLng();
                updateSetupCoords(pos.lat, pos.lng, 'Disetel Manual via Pin');
            });

            setupMap.on('click', function (e) {
                updateSetupCoords(e.latlng.lat, e.latlng.lng, 'Disetel Manual via Klik Peta');
            });

            // 1. Geocoding via OpenStreetMap Nominatim
            function searchLocation(query) {
                if (!query || query.trim().length < 3) {
                    gpsBadge.innerHTML = `<span class="text-warning font-medium">Ketik minimal 3 karakter untuk mencari.</span>`;
                    return;
                }

                if (btnSearchLocation) {
                    btnSearchLocation.disabled = true;
                    btnSearchLocation.innerHTML = `<span class="animate-spin inline-block size-3 border-2 border-white border-t-transparent rounded-full"></span> Mencari...`;
                }
                gpsBadge.innerHTML = `<span class="text-primary font-medium">Mencari lokasi "${query}" di peta...</span>`;

                const url = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=1&countrycodes=id`;

                fetch(url)
                    .then(res => res.json())
                    .then(data => {
                        if (data && data.length > 0) {
                            const result = data[0];
                            const lat = parseFloat(result.lat);
                            const lng = parseFloat(result.lon);
                            const displayName = result.display_name.split(',').slice(0, 3).join(',');

                            updateSetupCoords(lat, lng, displayName);
                            setupMap.setView([lat, lng], 17);
                        } else {
                            gpsBadge.innerHTML = `<span class="text-warning font-medium">Lokasi tidak ditemukan. Coba geser pin merah langsung ke titik IGD.</span>`;
                        }
                    })
                    .catch(() => {
                        gpsBadge.innerHTML = `<span class="text-warning font-medium">Pencarian online terkendala. Silakan klik langsung pada peta.</span>`;
                    })
                    .finally(() => {
                        if (btnSearchLocation) {
                            btnSearchLocation.disabled = false;
                            btnSearchLocation.innerHTML = `<svg class="size-3.5 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg> Cari di Peta`;
                        }
                    });
            }

            if (btnSearchLocation && searchInput) {
                btnSearchLocation.addEventListener('click', function () {
                    searchLocation(searchInput.value.trim());
                });

                searchInput.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        searchLocation(searchInput.value.trim());
                    }
                });
            }

            if (btnSyncFromForm) {
                btnSyncFromForm.addEventListener('click', function () {
                    const name = hospitalNameInput ? hospitalNameInput.value.trim() : '';
                    const address = hospitalAddressInput ? hospitalAddressInput.value.trim() : '';
                    const query = [name, address].filter(Boolean).join(', ');
                    if (!query) {
                        gpsBadge.innerHTML = `<span class="text-warning font-medium">Isi Nama Rumah Sakit atau Alamat pada form terlebih dahulu.</span>`;
                        return;
                    }
                    if (searchInput) searchInput.value = query;
                    searchLocation(query);
                });
            }

            // 2. Hardware GPS Geolocation dengan HTTP Safety Check
            if (btnGetLocation) {
                btnGetLocation.addEventListener('click', function () {
                    const isSecure = window.isSecureContext || location.hostname === 'localhost' || location.hostname === '127.0.0.1';

                    if (!isSecure) {
                        if (httpNotice) httpNotice.classList.remove('hidden');

                        const name = hospitalNameInput ? hospitalNameInput.value.trim() : '';
                        const address = hospitalAddressInput ? hospitalAddressInput.value.trim() : '';
                        const query = [name, address].filter(Boolean).join(', ');

                        if (query) {
                            if (searchInput) searchInput.value = query;
                            searchLocation(query);
                        } else {
                            gpsBadge.innerHTML = `<span class="text-warning font-medium">Koneksi HTTP: Gunakan kotak cari di atas atau klik langsung di peta.</span>`;
                            if (searchInput) searchInput.focus();
                        }
                        return;
                    }

                    if (!navigator.geolocation) {
                        gpsBadge.innerHTML = `<span class="text-danger font-medium">Browser tidak mendukung geolokasi GPS. Silakan klik langsung pada peta.</span>`;
                        return;
                    }

                    btnGetLocation.disabled = true;
                    const origHtml = btnGetLocation.innerHTML;
                    btnGetLocation.innerHTML = `<span class="animate-spin inline-block size-3 border-2 border-primary border-t-transparent rounded-full"></span> Mendeteksi...`;
                    gpsBadge.innerHTML = `<span class="text-primary font-medium">Mencari sinyal GPS perangkat...</span>`;

                    navigator.geolocation.getCurrentPosition(
                        function (position) {
                            const lat = position.coords.latitude;
                            const lng = position.coords.longitude;
                            const acc = Math.round(position.coords.accuracy);

                            updateSetupCoords(lat, lng, `Akurasi ±${acc}m`);
                            setupMap.setView([lat, lng], 17);

                            btnGetLocation.disabled = false;
                            btnGetLocation.innerHTML = origHtml;
                        },
                        function (err) {
                            btnGetLocation.disabled = false;
                            btnGetLocation.innerHTML = origHtml;

                            if (httpNotice) httpNotice.classList.remove('hidden');

                            const name = hospitalNameInput ? hospitalNameInput.value.trim() : '';
                            const address = hospitalAddressInput ? hospitalAddressInput.value.trim() : '';
                            const query = [name, address].filter(Boolean).join(', ');

                            if (query) {
                                if (searchInput) searchInput.value = query;
                                searchLocation(query);
                            } else {
                                gpsBadge.innerHTML = `<span class="text-warning font-medium">Akses lokasi tidak diizinkan. Silakan gunakan pencarian nama RS atau klik di peta.</span>`;
                            }
                        },
                        { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
                    );
                });
            }
        }
    });
</script>
@endpush
