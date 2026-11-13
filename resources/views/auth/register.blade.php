@extends('layouts.wide')

@section('title', 'Pendaftaran Mitra Rumah Sakit')

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
<style>
    .leaflet-container { font-family: inherit; }
    .igd-pin {
        width: 22px; height: 22px; border-radius: 50%;
        background: #D92D2F; border: 3px solid #fff;
        box-shadow: 0 0 0 1px rgba(23,27,39,.25), 0 2px 6px rgba(23,27,39,.35);
    }
</style>
@endpush

@section('content')
@php
    $field = 'h-11 w-full rounded-lg border bg-card px-3.5 text-sm text-ink placeholder:text-muted/80 focus:outline-none focus:ring-2 focus:ring-primary/30';
    $hasLocation = filled(old('latitude')) && filled(old('longitude'));
@endphp

<div class="max-w-2xl">
    <h1 class="text-2xl font-bold tracking-tight">Daftarkan rumah sakit Anda</h1>
    <p class="mt-2 text-sm leading-6 text-ink/65">
        Setelah terdaftar, IGD Anda muncul sebagai tujuan rujukan dan dapat memantau ambulans yang sedang menuju rumah sakit.
        Pendaftaran memakan waktu sekitar dua menit.
    </p>
</div>

<form method="POST" action="{{ route('register.attempt') }}" id="registerForm" novalidate class="mt-10">
    @csrf

    @if($errors->any())
        <x-alert-banner tone="danger" class="mb-8" role="alert">
            Data belum lengkap. Periksa {{ $errors->count() }} isian yang ditandai di bawah.
        </x-alert-banner>
    @endif

    <div class="grid gap-x-14 gap-y-10 lg:grid-cols-2">

        <div class="space-y-10">
            {{-- 1. Akun --}}
            <section>
                <h2 class="text-sm font-bold">1. Akun pengelola</h2>
                <p class="mt-1 text-sm text-ink/60">Dipakai untuk masuk ke dashboard rumah sakit.</p>

                <div class="mt-5 space-y-4">
                    <div>
                        <label for="full_name" class="mb-1.5 block text-sm font-medium">Nama lengkap</label>
                        <input id="full_name" name="full_name" value="{{ old('full_name') }}" autocomplete="name" required
                               class="{{ $field }} {{ $errors->has('full_name') ? 'border-danger' : 'border-line focus:border-primary' }}">
                        @error('full_name')<p class="mt-1.5 text-xs text-danger">{{ $message }}</p>@enderror
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="email" class="mb-1.5 block text-sm font-medium">Email</label>
                            <input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required
                                   class="{{ $field }} {{ $errors->has('email') ? 'border-danger' : 'border-line focus:border-primary' }}">
                            @error('email')<p class="mt-1.5 text-xs text-danger">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="phone" class="mb-1.5 block text-sm font-medium">No. HP / WhatsApp</label>
                            <input id="phone" type="tel" inputmode="tel" name="phone" value="{{ old('phone') }}" placeholder="0812 3456 7890" autocomplete="tel" required
                                   class="{{ $field }} {{ $errors->has('phone') ? 'border-danger' : 'border-line focus:border-primary' }}">
                            @error('phone')<p class="mt-1.5 text-xs text-danger">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="password" class="mb-1.5 block text-sm font-medium">Kata sandi</label>
                            <div class="relative">
                                <input id="password" type="password" name="password" autocomplete="new-password" required minlength="8"
                                       class="{{ $field }} pr-16 {{ $errors->has('password') ? 'border-danger' : 'border-line focus:border-primary' }}">
                                <button type="button" id="togglePassword" class="absolute inset-y-0 right-0 px-3.5 text-xs font-semibold text-primary">Lihat</button>
                            </div>
                            @error('password')
                                <p class="mt-1.5 text-xs text-danger">{{ $message }}</p>
                            @else
                                <p class="mt-1.5 text-xs text-ink/50">Minimal 8 karakter.</p>
                            @enderror
                        </div>
                        <div>
                            <label for="password_confirmation" class="mb-1.5 block text-sm font-medium">Ulangi kata sandi</label>
                            <input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" required
                                   class="{{ $field }} border-line focus:border-primary">
                        </div>
                    </div>
                </div>
            </section>

            {{-- 2. Rumah sakit --}}
            <section>
                <h2 class="text-sm font-bold">2. Data rumah sakit</h2>
                <p class="mt-1 text-sm text-ink/60">Ditampilkan kepada pasien dan sopir ambulans.</p>

                <div class="mt-5 space-y-4">
                    <div>
                        <label for="hospital_name" class="mb-1.5 block text-sm font-medium">Nama rumah sakit</label>
                        <input id="hospital_name" name="hospital_name" value="{{ old('hospital_name') }}" placeholder="RSUD Contoh Sehat" required
                               class="{{ $field }} {{ $errors->has('hospital_name') ? 'border-danger' : 'border-line focus:border-primary' }}">
                        @error('hospital_name')<p class="mt-1.5 text-xs text-danger">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="hospital_address" class="mb-1.5 block text-sm font-medium">Alamat</label>
                        <input id="hospital_address" name="hospital_address" value="{{ old('hospital_address') }}" placeholder="Jl. Kesehatan No. 10, Kecamatan, Kota" required
                               class="{{ $field }} {{ $errors->has('hospital_address') ? 'border-danger' : 'border-line focus:border-primary' }}">
                        @error('hospital_address')<p class="mt-1.5 text-xs text-danger">{{ $message }}</p>@enderror
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="emergency_phone" class="mb-1.5 block text-sm font-medium">Telepon IGD (24 jam)</label>
                            <input id="emergency_phone" type="tel" inputmode="tel" name="emergency_phone" value="{{ old('emergency_phone') }}" placeholder="021 555 0119" required
                                   class="{{ $field }} {{ $errors->has('emergency_phone') ? 'border-danger' : 'border-line focus:border-primary' }}">
                            @error('emergency_phone')<p class="mt-1.5 text-xs text-danger">{{ $message }}</p>@enderror

                {{-- Fasilitas Kesehatan (Faskes) Tersedia --}}
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
                    $selectedFacilities = old('health_facilities', ['IGD 24 Jam']);
                @endphp
                <div class="rounded-xl border border-line bg-surface/60 p-4">
                    <label class="block text-xs font-bold text-ink">Fasilitas Kesehatan (Faskes) Tersedia</label>
                    <p class="mb-3 text-[11px] text-ink/60">Pilih fasilitas yang tersedia untuk mendukung kesiapan rujukan dan IGD:</p>
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                        @foreach($healthFacilityOptions as $fac)
                            <label class="flex items-center gap-2 rounded-lg border border-line bg-card px-3 py-2 text-xs font-semibold text-ink/80 transition hover:border-primary/40 cursor-pointer">
                                <input
                                    type="checkbox"
                                    name="health_facilities[]"
                                    value="{{ $fac }}"
                                    @checked(in_array($fac, $selectedFacilities, true))
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
                        </div>
                        <div>
                            <label for="ambulance_entrance" class="mb-1.5 block text-sm font-medium">Pintu masuk ambulans <span class="font-normal text-ink/50">(opsional)</span></label>
                            <input id="ambulance_entrance" name="ambulance_entrance" value="{{ old('ambulance_entrance') }}" placeholder="Gerbang IGD sisi timur"
                                   class="{{ $field }} {{ $errors->has('ambulance_entrance') ? 'border-danger' : 'border-line focus:border-primary' }}">
                            @error('ambulance_entrance')<p class="mt-1.5 text-xs text-danger">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </div>
            </section>
        </div>

        {{-- 3. Lokasi --}}
        <section class="lg:sticky lg:top-6 lg:self-start">
            <h2 class="text-sm font-bold">3. Lokasi IGD</h2>
            <p class="mt-1 text-sm text-ink/60">
                Tandai tepat di pintu IGD. Titik ini menjadi tujuan rute ambulans dan dipakai untuk menghitung rumah sakit terdekat.
            </p>

            <div class="mt-5">
                <div class="relative">
                    <label for="mapSearch" class="sr-only">Cari lokasi</label>
                    <input id="mapSearch" type="search" autocomplete="off" placeholder="Cari nama rumah sakit atau alamat"
                           class="{{ $field }} border-line pr-24 focus:border-primary">
                    <button type="button" id="btnSearch" class="absolute inset-y-1 right-1 rounded-md bg-ink px-3 text-xs font-semibold text-white transition hover:bg-ink/85">Cari</button>
                </div>
                <ul id="searchResults" class="mt-2 hidden divide-y divide-line overflow-hidden rounded-lg border border-line text-sm"></ul>
            </div>

            <div class="mt-3 overflow-hidden rounded-lg border {{ $errors->has('latitude') || $errors->has('longitude') ? 'border-danger' : 'border-line' }}">
                <div id="map" class="h-72 w-full bg-surface lg:h-80"></div>
            </div>

            <div class="mt-3 flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
                <p id="locationStatus" class="text-sm {{ $hasLocation ? 'text-ink' : 'text-ink/55' }}" aria-live="polite">
                    {{ $hasLocation ? 'Titik dipilih.' : 'Belum ada titik. Klik peta atau cari lokasi.' }}
                </p>
                <button type="button" id="btnGps" class="text-sm font-semibold text-primary hover:underline">Gunakan lokasi saya</button>
            </div>
            <p id="locationError" class="mt-1.5 text-xs text-danger {{ $errors->has('latitude') || $errors->has('longitude') ? '' : 'hidden' }}">
                {{ $errors->first('latitude') ?: $errors->first('longitude') ?: 'Tentukan lokasi IGD pada peta.' }}
            </p>

            <input type="hidden" id="latitude" name="latitude" value="{{ old('latitude') }}">
            <input type="hidden" id="longitude" name="longitude" value="{{ old('longitude') }}">
        </section>
    </div>

    <div class="mt-12 flex flex-col-reverse items-stretch gap-4 border-t border-line pt-6 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-xs text-ink/55 sm:max-w-sm">
            Sopir ambulans dan pasien tidak mendaftar di sini, mereka masuk lewat aplikasi seluler.
        </p>
        <button type="submit" id="submitBtn" class="h-11 rounded-lg bg-primary px-7 text-sm font-semibold text-white transition hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-primary/40 focus:ring-offset-2 disabled:opacity-60">
            Daftarkan rumah sakit
        </button>
    </div>
</form>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const latInput = document.getElementById('latitude');
        const lngInput = document.getElementById('longitude');
        const btnGetLocation = document.getElementById('btnGetLocation');
        const gpsStatusBadge = document.getElementById('gpsStatusBadge');
        const httpNotice = document.getElementById('httpGpsNotice');

        const searchInput = document.getElementById('mapSearchInput');
        const btnSearch = document.getElementById('btnSearchLocation');
        const btnSyncFromForm = document.getElementById('btnSyncFromForm');
        const hospitalNameInput = document.getElementById('hospital_name');
        const hospitalAddressInput = document.getElementById('hospital_address');

        let initialLat = parseFloat(latInput.value) || -6.2088;
        let initialLng = parseFloat(lngInput.value) || 106.8456;

        // Custom Red Icon for Hospital Marker
        const hospitalIcon = L.divIcon({
            className: 'custom-hospital-pin',
            html: `
                <div style="background-color: #ef4444; color: white; width: 34px; height: 34px; border-radius: 50% 50% 50% 0; transform: rotate(-45deg); display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 10px rgba(239,68,68,0.4); border: 2px solid white;">
                    <span style="transform: rotate(45deg); font-size: 14px; font-weight: bold;">+</span>
                </div>
            `,
            iconSize: [34, 34],
            iconAnchor: [17, 34],
            popupAnchor: [0, -34]
        });

        const map = L.map('hospitalMap', {
            zoomControl: true,
            attributionControl: false
        }).setView([initialLat, initialLng], 14);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19
        }).addTo(map);

    const pin = L.divIcon({ className: '', html: '<div class="igd-pin"></div>', iconSize: [22, 22], iconAnchor: [11, 11] });
    let marker = null;

    function setPoint(lat, lng, note, zoom) {
        latEl.value = Number(lat).toFixed(7);
        lngEl.value = Number(lng).toFixed(7);
        if (!marker) {
            marker = L.marker([lat, lng], { icon: pin, draggable: true }).addTo(map);
            marker.on('dragend', () => { const p = marker.getLatLng(); setPoint(p.lat, p.lng); });
        } else {
            marker.setLatLng([lat, lng]);

            const desc = label ? `: ${label}` : ` (${Number(lat).toFixed(5)}, ${Number(lng).toFixed(5)})`;
            gpsStatusBadge.innerHTML = `<span class="text-success font-bold">✓ Koordinat Statis Terkunci${desc}</span>`;
            if (httpNotice) httpNotice.classList.add('hidden');
        }

        marker.on('dragend', function (e) {
            const pos = e.target.getLatLng();
            updateCoordinates(pos.lat, pos.lng);
        });

        map.on('click', function (e) {
            updateCoordinates(e.latlng.lat, e.latlng.lng);
        });

        latInput.addEventListener('change', function () {
            const lat = parseFloat(latInput.value);
            const lng = parseFloat(lngInput.value);
            if (!isNaN(lat) && !isNaN(lng)) {
                marker.setLatLng([lat, lng]);
                map.panTo([lat, lng]);
            }
        });

        lngInput.addEventListener('change', function () {
            const lat = parseFloat(latInput.value);
            const lng = parseFloat(lngInput.value);
            if (!isNaN(lat) && !isNaN(lng)) {
                marker.setLatLng([lat, lng]);
                map.panTo([lat, lng]);
            }
        });

        // 1. Geocoding Search (Works 100% on HTTP and HTTPS without needing browser GPS permission)
        async function searchLocation(query) {
            if (!query || !query.trim()) {
                gpsStatusBadge.innerHTML = `<span class="text-warning font-medium">Ketik nama faskes atau alamat untuk mencari di peta.</span>`;
                if (searchInput) searchInput.focus();
                return;
            }

            const origHtml = btnSearch.innerHTML;
            btnSearch.disabled = true;
            btnSearch.innerHTML = `<span class="animate-spin inline-block size-3.5 border-2 border-white border-t-transparent rounded-full"></span> Mencari...`;
            gpsStatusBadge.innerHTML = `<span class="text-primary font-medium">Mencari lokasi faskes di peta...</span>`;

            try {
                const res = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=1`, {
                    headers: { 'Accept-Language': 'id,en' }
                });
                const data = await res.json();

                if (data && data.length > 0) {
                    const place = data[0];
                    const lat = parseFloat(place.lat);
                    const lon = parseFloat(place.lon);
                    updateCoordinates(lat, lon, place.name || 'Ditemukan');
                    map.setView([lat, lon], 17);
                } else {
                    gpsStatusBadge.innerHTML = `<span class="text-warning font-medium">Lokasi tidak ditemukan. Coba ketik nama kota/jalan atau klik langsung pada peta.</span>`;
                }
            } catch (err) {
                gpsStatusBadge.innerHTML = `<span class="text-danger font-medium">Pencarian offline. Silakan klik langsung pada peta.</span>`;
            } finally {
                btnSearch.disabled = false;
                btnSearch.innerHTML = origHtml;
            }
        }

        if (btnSearch) {
            btnSearch.addEventListener('click', function () {
                searchLocation(searchInput.value);
            });
        }

        if (searchInput) {
            searchInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    searchLocation(searchInput.value);
                }
            });
        }

        if (btnSyncFromForm) {
            btnSyncFromForm.addEventListener('click', function () {
                const name = hospitalNameInput ? hospitalNameInput.value.trim() : '';
                const address = hospitalAddressInput ? hospitalAddressInput.value.trim() : '';
                const query = [name, address].filter(Boolean).join(', ');
                if (!query) {
                    gpsStatusBadge.innerHTML = `<span class="text-warning font-medium">Isi Nama Rumah Sakit atau Alamat pada form di atas terlebih dahulu.</span>`;
                    return;
                }
                searchInput.value = query;
                searchLocation(query);
            });
        }

        // 2. Hardware Geolocation API (Dengan Deteksi HTTP / Secure Context)
        if (btnGetLocation) {
            btnGetLocation.addEventListener('click', function () {
                const isSecure = window.isSecureContext || location.hostname === 'localhost' || location.hostname === '127.0.0.1';

                if (!isSecure) {
                    // Modern browsers block navigator.geolocation on HTTP IP
                    if (httpNotice) httpNotice.classList.remove('hidden');

                    const name = hospitalNameInput ? hospitalNameInput.value.trim() : '';
                    const address = hospitalAddressInput ? hospitalAddressInput.value.trim() : '';
                    const query = [name, address].filter(Boolean).join(', ');

                    if (query) {
                        searchInput.value = query;
                        searchLocation(query);
                    } else {
                        gpsStatusBadge.innerHTML = `<span class="text-warning font-medium">Koneksi HTTP: Gunakan kotak cari di atas atau klik langsung di peta.</span>`;
                        searchInput.focus();
                    }
                    return;
                }

                if (!navigator.geolocation) {
                    gpsStatusBadge.innerHTML = `<span class="text-danger font-medium">Browser tidak mendukung geolokasi GPS. Silakan klik langsung pada peta.</span>`;
                    return;
                }

                btnGetLocation.disabled = true;
                const originalText = btnGetLocation.innerHTML;
                btnGetLocation.innerHTML = `<span class="animate-spin inline-block size-3.5 border-2 border-primary border-t-transparent rounded-full"></span> Mendeteksi GPS...`;
                gpsStatusBadge.innerHTML = `<span class="text-primary font-medium">Mencari sinyal GPS perangkat...</span>`;

                navigator.geolocation.getCurrentPosition(
                    function (position) {
                        const lat = position.coords.latitude;
                        const lng = position.coords.longitude;
                        const accuracy = Math.round(position.coords.accuracy);

                        updateCoordinates(lat, lng, `Akurasi ±${accuracy}m`);
                        map.setView([lat, lng], 17);

                        btnGetLocation.disabled = false;
                        btnGetLocation.innerHTML = originalText;
                    },
                    function (error) {
                        btnGetLocation.disabled = false;
                        btnGetLocation.innerHTML = originalText;

                        if (httpNotice) httpNotice.classList.remove('hidden');

                        const name = hospitalNameInput ? hospitalNameInput.value.trim() : '';
                        const address = hospitalAddressInput ? hospitalAddressInput.value.trim() : '';
                        const query = [name, address].filter(Boolean).join(', ');

                        if (query) {
                            searchInput.value = query;
                            searchLocation(query);
                        } else {
                            gpsStatusBadge.innerHTML = `<span class="text-warning font-medium">Izin GPS ditolak. Silakan gunakan pencarian nama RS atau klik di peta.</span>`;
                        }
                    },
                    {
                        enableHighAccuracy: true,
                        timeout: 10000,
                        maximumAge: 0
                    }
                );
            });
        }
    });
</script>
@endpush
