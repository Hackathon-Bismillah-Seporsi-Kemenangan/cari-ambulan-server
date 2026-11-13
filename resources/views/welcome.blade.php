<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Cari Ambulan — Platform Terintegrasi Respons Ambulans Gawat Darurat & IGD Rumah Sakit Indonesia">
    <title>Cari Ambulan — Platform Terintegrasi Respons Ambulans & IGD Rumah Sakit</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-dvh bg-surface font-sans text-ink antialiased selection:bg-primary-100 selection:text-primary">

    {{-- Top Navigation Bar --}}
    <header class="sticky top-0 z-50 border-b border-line bg-card/90 backdrop-blur">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3 sm:px-6 lg:px-8">
            <a href="{{ url('/') }}" class="flex items-center gap-2.5">
                <span class="flex size-10 items-center justify-center rounded-xl bg-danger text-white shadow-md shadow-danger/20">
                    <x-icon name="ambulance" class="size-5" />
                </span>
                <div>
                    <span class="block text-base font-extrabold tracking-tight leading-none text-ink">Cari Ambulan</span>
                    <span class="block text-[10px] font-semibold tracking-wider text-muted uppercase">Emergency Response Server</span>
                </div>
            </a>

            <nav class="hidden md:flex items-center gap-7 text-xs font-semibold text-ink/70">
                <a href="#solusi" class="transition hover:text-primary">Solusi Ekosistem</a>
                <a href="#alur-kerja" class="transition hover:text-primary">Alur Penyelamatan</a>
                <a href="#teknologi" class="transition hover:text-primary">Teknologi &amp; API</a>
                <a href="#rumah-sakit" class="transition hover:text-primary">Mitra Rumah Sakit</a>
            </nav>

            <div class="flex items-center gap-2.5">
                @auth
                    <a href="{{ route('dashboard') }}" class="flex h-10 items-center gap-1.5 rounded-xl bg-primary px-4 text-xs font-bold text-white shadow-sm transition hover:bg-primary/90">
                        Buka Dashboard <x-icon name="arrow-right" class="size-3.5" />
                    </a>
                @else
                    <a href="{{ route('login') }}" class="flex h-10 items-center rounded-xl px-3.5 text-xs font-bold text-ink/80 transition hover:text-primary">
                        Masuk Portal
                    </a>
                    <a href="{{ route('register') }}" class="flex h-10 items-center gap-1.5 rounded-xl bg-primary px-4 text-xs font-bold text-white shadow-sm transition hover:bg-primary/90 active:scale-[0.98]">
                        Daftar Akun
                    </a>
                @endauth
            </div>
        </div>
    </header>

    {{-- Hero Section --}}
    <section class="relative overflow-hidden border-b border-line bg-gradient-to-b from-card via-card to-surface py-16 sm:py-24">
        {{-- Background decorative blurs --}}
        <div class="pointer-events-none absolute -top-40 left-1/2 -z-10 h-96 w-96 -translate-x-1/2 rounded-full bg-primary/10 blur-3xl"></div>
        <div class="pointer-events-none absolute top-40 right-10 -z-10 h-72 w-72 rounded-full bg-danger/10 blur-3xl"></div>

        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-3xl text-center">
                <div class="inline-flex items-center gap-2 rounded-full border border-primary/20 bg-primary-50 px-3.5 py-1 text-xs font-bold text-primary">
                    <span class="size-1.5 rounded-full bg-primary animate-pulse"></span>
                    Platform Backend &amp; Dispatch Server Terpadu Indonesia
                </div>

                <h1 class="mt-6 text-4xl font-extrabold tracking-tight text-ink sm:text-5xl lg:text-6xl lg:leading-[1.15]">
                    Menghubungkan Pasien, Armada Ambulans, dan <span class="text-primary">IGD Rumah Sakit</span>
                </h1>

                <p class="mt-6 text-base leading-relaxed text-ink/70 sm:text-lg">
                    Cari Ambulan adalah infrastruktur backend respons darurat medis yang mengintegrasikan panggilan gawat darurat warga, sistem dispatch pengemudi berbasis GPS, dan kesiapan instalasi gawat darurat (IGD) secara real-time.
                </p>

                <div class="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
                    @auth
                        <a href="{{ route('dashboard') }}" class="flex h-12 w-full sm:w-auto items-center justify-center gap-2 rounded-xl bg-primary px-7 text-sm font-bold text-white shadow-md shadow-primary/25 transition hover:bg-primary/90 active:scale-[0.98]">
                            Masuk ke Dashboard Sistem <x-icon name="arrow-right" class="size-4" />
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="flex h-12 w-full sm:w-auto items-center justify-center gap-2 rounded-xl bg-primary px-7 text-sm font-bold text-white shadow-md shadow-primary/25 transition hover:bg-primary/90 active:scale-[0.98]">
                            Akses Portal Manajemen <x-icon name="arrow-right" class="size-4" />
                        </a>
                        <a href="{{ route('register') }}" class="flex h-12 w-full sm:w-auto items-center justify-center rounded-xl border border-line bg-card px-6 text-sm font-bold text-ink/75 transition hover:border-primary hover:text-primary">
                            Registrasi Akun Baru
                        </a>
                    @endauth
                    <a href="#solusi" class="flex h-12 w-full sm:w-auto items-center justify-center rounded-xl border border-line bg-surface px-5 text-sm font-semibold text-ink/70 transition hover:bg-card">
                        Pelajari Fitur Platform
                    </a>
                </div>
            </div>

            {{-- Stat Metrics Bar --}}
            <div class="mt-16 grid grid-cols-2 gap-3 rounded-2xl border border-line bg-card p-5 shadow-sm sm:grid-cols-4 lg:p-6">
                <div class="border-b border-line pb-3 text-center sm:border-b-0 sm:border-r sm:pb-0">
                    <p class="text-3xl font-black text-danger">24/7</p>
                    <p class="mt-1 text-xs font-semibold text-ink/60">Kesiagaan Sistem Non-Stop</p>
                </div>
                <div class="border-b border-line pb-3 text-center sm:border-b-0 sm:border-r sm:pb-0">
                    <p class="text-3xl font-black text-primary">&lt; 30 Detik</p>
                    <p class="mt-1 text-xs font-semibold text-ink/60">Auto-Dispatch Driver Terdekat</p>
                </div>
                <div class="text-center sm:border-r sm:pb-0">
                    <p class="text-3xl font-black text-success">100%</p>
                    <p class="mt-1 text-xs font-semibold text-ink/60">Koordinasi Terintegrasi IGD</p>
                </div>
                <div class="text-center">
                    <p class="text-3xl font-black text-ink">REST API</p>
                    <p class="mt-1 text-xs font-semibold text-ink/60">Siap untuk Mobile Apps Pasien &amp; Driver</p>
                </div>
            </div>
        </div>
    </section>

    {{-- Ecosystem & Solutions Section --}}
    <section id="solusi" class="py-16 sm:py-24">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-2xl text-center">
                <span class="rounded-full bg-primary-50 px-3 py-1 text-xs font-bold text-primary uppercase tracking-wider">
                    Solusi Lengkap
                </span>
                <h2 class="mt-3 text-3xl font-extrabold text-ink sm:text-4xl">
                    Satu Ekosistem Terpadu untuk Semua Peran
                </h2>
                <p class="mt-3 text-sm text-ink/60">
                    Cari Ambulan menghubungkan seluruh pihak yang terlibat dalam penyelamatan gawat darurat melalui hak akses dan portal khusus.
                </p>
            </div>

            <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                {{-- Hospital Card --}}
                <div class="flex flex-col justify-between rounded-2xl border border-line bg-card p-6 shadow-sm transition hover:border-primary/50 hover:shadow-md">
                    <div>
                        <span class="flex size-12 items-center justify-center rounded-xl bg-primary-50 text-primary">
                            <x-icon name="hospital" class="size-6" />
                        </span>
                        <h3 class="mt-5 text-lg font-bold text-ink">Rumah Sakit &amp; IGD</h3>
                        <p class="mt-2 text-xs leading-relaxed text-ink/60">
                            Pusat pemantauan pasien rujukan gawat darurat yang sedang menuju IGD. Dilengkapi notifikasi tipe trauma (jantung, persalinan, kecelakaan) agar dokter dan perawat siap siaga sebelum ambulans tiba.
                        </p>
                    </div>
                    <ul class="mt-5 space-y-2 border-t border-line pt-4 text-xs font-medium text-ink/80">
                        <li class="flex items-center gap-2"><x-icon name="check" class="size-3.5 text-success" /> Pemantauan armada ambulans RS</li>
                        <li class="flex items-center gap-2"><x-icon name="check" class="size-3.5 text-success" /> Notifikasi dini pasien IGD</li>
                        <li class="flex items-center gap-2"><x-icon name="check" class="size-3.5 text-success" /> Manajemen driver ambulans faskes</li>
                    </ul>
                </div>

                {{-- Driver Card --}}
                <div class="flex flex-col justify-between rounded-2xl border border-line bg-card p-6 shadow-sm transition hover:border-primary/50 hover:shadow-md">
                    <div>
                        <span class="flex size-12 items-center justify-center rounded-xl bg-success-50 text-success">
                            <x-icon name="truck" class="size-6" />
                        </span>
                        <h3 class="mt-5 text-lg font-bold text-ink">Pengemudi Ambulans</h3>
                        <p class="mt-2 text-xs leading-relaxed text-ink/60">
                            Aplikasi dan antarmuka khusus driver ambulans untuk menerima tawaran penjemputan terdekat, melihat rute evakuasi, dan meninjau informasi riwayat medis pasien di jalan.
                        </p>
                    </div>
                    <ul class="mt-5 space-y-2 border-t border-line pt-4 text-xs font-medium text-ink/80">
                        <li class="flex items-center gap-2"><x-icon name="check" class="size-3.5 text-success" /> Penerimaan order berbasis GPS</li>
                        <li class="flex items-center gap-2"><x-icon name="check" class="size-3.5 text-success" /> Akses Medical ID darurat pasien</li>
                        <li class="flex items-center gap-2"><x-icon name="check" class="size-3.5 text-success" /> Update tahapan trip hingga ke IGD</li>
                    </ul>
                </div>

                {{-- Civilian User Card --}}
                <div class="flex flex-col justify-between rounded-2xl border border-line bg-card p-6 shadow-sm transition hover:border-primary/50 hover:shadow-md">
                    <div>
                        <span class="flex size-12 items-center justify-center rounded-xl bg-danger-50 text-danger">
                            <x-icon name="sos" class="size-6" />
                        </span>
                        <h3 class="mt-5 text-lg font-bold text-ink">Masyarakat / Pasien</h3>
                        <p class="mt-2 text-xs leading-relaxed text-ink/60">
                            Aplikasi seluler warga untuk meminta ambulans gawat darurat dengan cepat, otomatis menyertakan golongan darah dan riwayat alergi, serta memantau posisi ambulans secara langsung.
                        </p>
                    </div>
                    <ul class="mt-5 space-y-2 border-t border-line pt-4 text-xs font-medium text-ink/80">
                        <li class="flex items-center gap-2"><x-icon name="check" class="size-3.5 text-success" /> Panggilan darurat satu sentuhan</li>
                        <li class="flex items-center gap-2"><x-icon name="check" class="size-3.5 text-success" /> Profil medis &amp; kontak darurat</li>
                        <li class="flex items-center gap-2"><x-icon name="check" class="size-3.5 text-success" /> Tracking posisi ambulans di peta</li>
                    </ul>
                </div>

                {{-- Administrator Card --}}
                <div class="flex flex-col justify-between rounded-2xl border border-line bg-card p-6 shadow-sm transition hover:border-primary/50 hover:shadow-md">
                    <div>
                        <span class="flex size-12 items-center justify-center rounded-xl bg-primary-50 text-primary">
                            <x-icon name="shield" class="size-6" />
                        </span>
                        <h3 class="mt-5 text-lg font-bold text-ink">Pusat Kendali Admin</h3>
                        <p class="mt-2 text-xs leading-relaxed text-ink/60">
                            Pusat kontrol operasional untuk mengelola akun pengguna, hak akses peran (RBAC), pendaftaran rumah sakit mitra, serta analitik komprehensif seluruh data panggilan darurat.
                        </p>
                    </div>
                    <ul class="mt-5 space-y-2 border-t border-line pt-4 text-xs font-medium text-ink/80">
                        <li class="flex items-center gap-2"><x-icon name="check" class="size-3.5 text-success" /> Manajemen Pengguna &amp; Peran</li>
                        <li class="flex items-center gap-2"><x-icon name="check" class="size-3.5 text-success" /> Database Rumah Sakit &amp; Faskes</li>
                        <li class="flex items-center gap-2"><x-icon name="check" class="size-3.5 text-success" /> Pemantauan seluruh status panggilan</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    {{-- Workflow Section --}}
    <section id="alur-kerja" class="border-y border-line bg-card py-16 sm:py-24">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-2xl text-center">
                <span class="rounded-full bg-success-50 px-3 py-1 text-xs font-bold text-success uppercase tracking-wider">
                    Alur Penyelamatan
                </span>
                <h2 class="mt-3 text-3xl font-extrabold text-ink sm:text-4xl">
                    Bagaimana Sistem Bekerja Saat Detik Menentukan?
                </h2>
                <p class="mt-3 text-sm text-ink/60">
                    Arsitektur backend Cari Ambulan dirancang dengan latensi minimal untuk memastikan pertolongan pertama tiba secepat mungkin.
                </p>
            </div>

            <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                <div class="relative rounded-2xl border border-line bg-surface/50 p-6">
                    <span class="text-3xl font-black text-danger/30">01</span>
                    <h3 class="mt-2 text-base font-bold text-ink">Permintaan Masuk</h3>
                    <p class="mt-1 text-xs leading-relaxed text-ink/60">
                        Warga memicu panggilan darurat dari aplikasi. Koordinat lintang/bujur dan data rekam medis darurat (Medical ID) langsung dikirimkan ke server backend.
                    </p>
                </div>

                <div class="relative rounded-2xl border border-line bg-surface/50 p-6">
                    <span class="text-3xl font-black text-warning/30">02</span>
                    <h3 class="mt-2 text-base font-bold text-ink">Smart Auto-Dispatch</h3>
                    <p class="mt-1 text-xs leading-relaxed text-ink/60">
                        Algoritma backend mengkalkulasi jarak dan langsung menawarkan panggilan ke unit ambulans siaga terdekat dengan batas waktu konfirmasi otomatis.
                    </p>
                </div>

                <div class="relative rounded-2xl border border-line bg-surface/50 p-6">
                    <span class="text-3xl font-black text-primary/30">03</span>
                    <h3 class="mt-2 text-base font-bold text-ink">Navigasi &amp; Notifikasi IGD</h3>
                    <p class="mt-1 text-xs leading-relaxed text-ink/60">
                        Driver menerima penugasan dan bergegas ke lokasi penjemputan. Secara paralel, IGD rumah sakit tujuan menerima pemberitahuan kondisi pasien agar tim medis bersiap.
                    </p>
                </div>

                <div class="relative rounded-2xl border border-line bg-surface/50 p-6">
                    <span class="text-3xl font-black text-success/30">04</span>
                    <h3 class="mt-2 text-base font-bold text-ink">Serah Terima di IGD</h3>
                    <p class="mt-1 text-xs leading-relaxed text-ink/60">
                        Pasien tiba di rumah sakit tujuan dan langsung ditangani di ruang gawat darurat tanpa birokrasi berbelit. Unit ambulan kembali siap bertugas.
                    </p>
                </div>
            </div>
        </div>
    </section>

    {{-- Technology & API Section --}}
    <section id="teknologi" class="py-16 sm:py-24">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid gap-12 lg:grid-cols-2 lg:items-center">
                <div>
                    <span class="rounded-full bg-primary-50 px-3 py-1 text-xs font-bold text-primary uppercase tracking-wider">
                        Teknologi Backend
                    </span>
                    <h2 class="mt-3 text-3xl font-extrabold text-ink sm:text-4xl">
                        Infrastruktur Handal &amp; REST API Siap Integrasi
                    </h2>
                    <p class="mt-4 text-sm leading-relaxed text-ink/70">
                        Server Cari Ambulan dibangun dengan standar modern berkinerja tinggi, menyediakan API komprehensif bagi developer aplikasi seluler pasien dan aplikasi pengemudi ambulans.
                    </p>

                    <div class="mt-6 space-y-4">
                        <div class="flex items-start gap-3.5">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-primary">
                                <x-icon name="lock" class="size-4" />
                            </span>
                            <div>
                                <h4 class="text-sm font-bold text-ink">Autentikasi Token Aman (Sanctum)</h4>
                                <p class="text-xs text-ink/60">Mendukung multi-device token login untuk aplikasi Android dan iOS pengemudi serta pasien.</p>
                            </div>
                        </div>

                        <div class="flex items-start gap-3.5">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-primary">
                                <x-icon name="map" class="size-4" />
                            </span>
                            <div>
                                <h4 class="text-sm font-bold text-ink">Real-time Location &amp; Dispatch State Machine</h4>
                                <p class="text-xs text-ink/60">Pelacakan posisi pengemudi, pembaruan status per detik, dan redispatch otomatis jika panggilan tidak terjawab.</p>
                            </div>
                        </div>

                        <div class="flex items-start gap-3.5">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-primary">
                                <x-icon name="shield" class="size-4" />
                            </span>
                            <div>
                                <h4 class="text-sm font-bold text-ink">Role-Based Access Control (RBAC)</h4>
                                <p class="text-xs text-ink/60">Pemisahan hak akses yang ketat antara Administrator, Admin RS, Pengemudi, dan Pengguna Umum.</p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Code / API Mockup Card --}}
                <div class="rounded-2xl border border-line bg-card p-6 shadow-xl">
                    <div class="flex items-center justify-between border-b border-line pb-3">
                        <div class="flex items-center gap-2">
                            <span class="size-3 rounded-full bg-danger"></span>
                            <span class="size-3 rounded-full bg-warning"></span>
                            <span class="size-3 rounded-full bg-success"></span>
                            <span class="ml-2 font-mono text-xs font-semibold text-muted">Cari Ambulan REST API v1</span>
                        </div>
                        <span class="rounded bg-surface px-2 py-0.5 text-[10px] font-bold text-primary">POST /api/v1/emergency-requests</span>
                    </div>

                    <div class="mt-4 overflow-x-auto font-mono text-xs text-ink/80">
<pre class="text-xs leading-relaxed"><span class="text-primary font-bold">// Permintaan Ambulans Masuk (JSON Payload)</span>
{
  <span class="text-danger">"emergency_type"</span>: <span class="text-success">"Kecelakaan Lalu Lintas"</span>,
  <span class="text-danger">"severity_level"</span>: <span class="text-success">"critical"</span>,
  <span class="text-danger">"pickup_latitude"</span>: -6.2088,
  <span class="text-danger">"pickup_longitude"</span>: 106.8456,
  <span class="text-danger">"pickup_address"</span>: <span class="text-success">"Jl. Jenderal Sudirman No. 28, Jakarta"</span>
}

<span class="text-primary font-bold">// Respons Dispatch Otomatis (HTTP 201)</span>
{
  <span class="text-danger">"request_code"</span>: <span class="text-success">"CA-89X2KQ"</span>,
  <span class="text-danger">"status"</span>: <span class="text-success">"searching_driver"</span>,
  <span class="text-danger">"igd_prep"</span>: <span class="text-success">"IGD menyiapkan tim trauma darurat."</span>
}</pre>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Hospitals & Partners Section --}}
    <section id="rumah-sakit" class="border-t border-line bg-surface/60 py-16 sm:py-24">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-2xl text-center">
                <span class="rounded-full bg-primary-50 px-3 py-1 text-xs font-bold text-primary uppercase tracking-wider">
                    Jaringan Faskes
                </span>
                <h2 class="mt-3 text-3xl font-extrabold text-ink sm:text-4xl">
                    Kemitraan Rumah Sakit &amp; Layanan Medis
                </h2>
                <p class="mt-3 text-sm text-ink/60">
                    Terbuka untuk integrasi dengan RSUD, Rumah Sakit Swasta, Dinas Kesehatan, dan penyedia layanan ambulans di seluruh Indonesia.
                </p>
            </div>

            <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <div class="rounded-2xl border border-line bg-card p-6 shadow-sm">
                    <span class="flex size-10 items-center justify-center rounded-xl bg-primary-50 text-primary mb-4">
                        <x-icon name="hospital" class="size-5" />
                    </span>
                    <h3 class="text-base font-bold text-ink">Integrasi Sistem IGD Rumah Sakit</h3>
                    <p class="mt-2 text-xs text-ink/60">
                        Sinkronisasi data langsung dengan instalasi gawat darurat faskes untuk mempercepat penerimaan pasien kritis tanpa penundaan administrasi.
                    </p>
                </div>

                <div class="rounded-2xl border border-line bg-card p-6 shadow-sm">
                    <span class="flex size-10 items-center justify-center rounded-xl bg-success-50 text-success mb-4">
                        <x-icon name="ambulance" class="size-5" />
                    </span>
                    <h3 class="text-base font-bold text-ink">Manajemen Armada Ambulans</h3>
                    <p class="mt-2 text-xs text-ink/60">
                        Kelola ketersediaan unit kendaraan, penugasan supir ambulans, dan riwayat pemakaian dalam satu dasbor kontrol terpusat.
                    </p>
                </div>

                <div class="rounded-2xl border border-line bg-card p-6 shadow-sm">
                    <span class="flex size-10 items-center justify-center rounded-xl bg-danger-50 text-danger mb-4">
                        <x-icon name="phone" class="size-5" />
                    </span>
                    <h3 class="text-base font-bold text-ink">Hotline Tanggap Darurat 119 &amp; RS</h3>
                    <p class="mt-2 text-xs text-ink/60">
                        Koneksi cepat nomor telepon darurat faskes terdekat yang dapat diakses langsung oleh masyarakat saat dibutuhkan.
                    </p>
                </div>
            </div>
        </div>
    </section>

    {{-- Bottom Call to Action --}}
    <section class="bg-gradient-to-br from-ink via-[#171d2c] to-primary py-16 text-white sm:py-20">
        <div class="mx-auto max-w-5xl px-4 text-center sm:px-6 lg:px-8">
            <span class="rounded-full bg-white/10 px-3.5 py-1 text-xs font-bold text-white/80 uppercase tracking-widest backdrop-blur">
                Portal Manajemen Terpadu
            </span>
            <h2 class="mt-4 text-3xl font-extrabold tracking-tight sm:text-4xl lg:text-5xl">
                Siap Meningkatkan Kecepatan Respons Darurat Medis?
            </h2>
            <p class="mx-auto mt-4 max-w-xl text-sm leading-relaxed text-white/70 sm:text-base">
                Kelola armada ambulan, pantau rujukan pasien IGD, atau daftarkan akun fasilitas kesehatan Anda ke dalam jaringan Cari Ambulan sekarang.
            </p>

            <div class="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
                @auth
                    <a href="{{ route('dashboard') }}" class="flex h-12 w-full sm:w-auto items-center justify-center gap-2 rounded-xl bg-danger px-8 text-sm font-bold text-white shadow-lg shadow-danger/30 transition hover:bg-danger/90">
                        Masuk ke Dashboard <x-icon name="arrow-right" class="size-4" />
                    </a>
                @else
                    <a href="{{ route('login') }}" class="flex h-12 w-full sm:w-auto items-center justify-center gap-2 rounded-xl bg-danger px-8 text-sm font-bold text-white shadow-lg shadow-danger/30 transition hover:bg-danger/90">
                        Masuk ke Portal <x-icon name="arrow-right" class="size-4" />
                    </a>
                    <a href="{{ route('register') }}" class="flex h-12 w-full sm:w-auto items-center justify-center rounded-xl border border-white/20 bg-white/10 px-7 text-sm font-bold text-white backdrop-blur transition hover:bg-white/20">
                        Daftar Akun Baru
                    </a>
                @endauth
            </div>
        </div>
    </section>

    {{-- Footer --}}
    <footer class="border-t border-line bg-card py-10 text-xs text-ink/60">
        <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-6 px-4 sm:flex-row sm:px-6 lg:px-8">
            <div class="flex items-center gap-2.5">
                <span class="flex size-7 items-center justify-center rounded-lg bg-danger text-white">
                    <x-icon name="ambulance" class="size-3.5" />
                </span>
                <span class="font-extrabold text-ink">Cari Ambulan Indonesia</span>
                <span class="text-muted">&bull; Server &amp; Dispatch Platform</span>
            </div>

            <div class="flex items-center gap-6 text-xs">
                <a href="{{ route('login') }}" class="transition hover:text-primary">Masuk Portal</a>
                <a href="{{ route('register') }}" class="transition hover:text-primary">Daftar</a>
                <a href="#solusi" class="transition hover:text-primary">Solusi</a>
                <a href="#teknologi" class="transition hover:text-primary">API</a>
            </div>

            <p class="text-[11px] text-muted">
                &copy; {{ date('Y') }} Cari Ambulan Indonesia. Hak Cipta Dilindungi.
            </p>
        </div>
    </footer>

</body>
</html>
