<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Cari Ambulan — Platform darurat ambulan Indonesia">
    <title>@yield('title', 'Masuk') — Cari Ambulan</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="min-h-dvh">
<div class="grid min-h-dvh lg:grid-cols-2">
    <aside class="relative hidden flex-col justify-between overflow-hidden bg-linear-to-br from-ink via-[#1e2436] to-danger/70 p-10 text-white lg:flex">
        <div class="flex items-center gap-3">
            <div class="flex size-11 items-center justify-center rounded-xl bg-danger shadow-lg shadow-danger/30">
                <x-icon name="sos" class="size-6" />
            </div>
            <div>
                <p class="text-base font-extrabold">Cari Ambulan</p>
                <p class="text-xs text-white/60">Emergency Response System</p>
            </div>
        </div>

        <div>
            <p class="text-[11px] font-bold uppercase tracking-widest text-danger-50/80">CARI AMBULAN &bull; Layanan darurat 24/7</p>
            <h2 class="mt-2 text-4xl font-extrabold leading-tight">Bantuan darurat,<br>dalam satu ketukan.</h2>
            <p class="mt-4 max-w-sm text-sm leading-6 text-white/70">Tekan satu tombol — sistem mencarikan ambulans terdekat, kirim laporan medis, dan pantau perjalanan sampai tiba di IGD.</p>

            <div class="mt-8 flex flex-col gap-2.5 max-w-sm">
                <div class="flex items-center gap-3 rounded-xl bg-white/10 px-4 py-3 backdrop-blur">
                    <span class="flex size-8 items-center justify-center rounded-lg bg-white/15"><x-icon name="check" class="size-4" /></span>
                    <span class="text-xs font-medium">Pencarian ambulans otomatis</span>
                </div>
                <div class="flex items-center gap-3 rounded-xl bg-white/10 px-4 py-3 backdrop-blur">
                    <span class="flex size-8 items-center justify-center rounded-lg bg-white/15"><x-icon name="check" class="size-4" /></span>
                    <span class="text-xs font-medium">Medical ID terkirim ke petugas</span>
                </div>
                <div class="flex items-center gap-3 rounded-xl bg-white/10 px-4 py-3 backdrop-blur">
                    <span class="flex size-8 items-center justify-center rounded-lg bg-white/15"><x-icon name="check" class="size-4" /></span>
                    <span class="text-xs font-medium">Tracking rumah sakit tujuan</span>
                </div>
            </div>
        </div>

        <p class="text-xs text-white/50">&copy; {{ date('Y') }} Cari Ambulan Indonesia &bull; Hak Cipta Dilindungi</p>
    </aside>

    <main class="flex flex-col items-center justify-center px-5 py-10 sm:px-10">
        <div class="mb-8 flex items-center gap-3 lg:hidden">
            <div class="flex size-10 items-center justify-center rounded-xl bg-danger text-white">
                <x-icon name="sos" class="size-5" />
            </div>
            <div>
                <p class="text-base font-extrabold">Cari Ambulan</p>
                <p class="text-xs text-muted">Emergency Response System</p>
            </div>
        </div>
        {{ $slot ?? '' }}
        @yield('content')
    </main>
</div>
@stack('scripts')
</body>
</html>
