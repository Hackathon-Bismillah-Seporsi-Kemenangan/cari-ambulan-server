<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title') — Cari Ambulan</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="min-h-dvh bg-card">
<header class="border-b border-line">
    <div class="mx-auto flex h-14 max-w-6xl items-center justify-between px-5">
        <a href="{{ route('login') }}" class="flex items-center gap-2.5">
            <span class="flex size-8 items-center justify-center rounded-lg bg-danger text-white">
                <x-icon name="sos" class="size-4" />
            </span>
            <span class="text-sm font-bold">Cari Ambulan</span>
        </a>
        <a href="{{ route('login') }}" class="text-sm text-ink/70 hover:text-ink">
            Sudah terdaftar? <span class="font-semibold text-primary">Masuk</span>
        </a>
    </div>
</header>
<main class="mx-auto max-w-6xl px-5 py-10 lg:py-14">
    @yield('content')
</main>
@stack('scripts')
</body>
</html>
