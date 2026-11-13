<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Cari Ambulan — Platform darurat ambulan Indonesia">
    <title>@yield('title', 'Dashboard') — Cari Ambulan</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="min-h-dvh theme-dashboard bg-surface text-ink antialiased">
<div class="md:flex md:min-h-dvh">
    <nav class="fixed inset-x-0 bottom-0 z-40 flex items-stretch justify-around gap-1 border-t bg-slate-900 border-slate-800 px-1 pt-1 pb-[max(0.25rem,env(safe-area-inset-bottom))] text-slate-200 md:sticky md:top-0 md:h-dvh md:w-64 md:shrink-0 md:flex-col md:items-stretch md:justify-start md:gap-1 md:border-r md:border-t-0 md:border-slate-800 md:px-4 md:py-6 md:overflow-y-auto">
        <div class="hidden md:flex items-center gap-3 px-3 pb-4 shrink-0">
            <div class="flex size-10 items-center justify-center rounded-xl bg-danger text-white">
                <x-icon name="sos" class="size-5" />
            </div>
            <div class="min-w-0">
                <h1 class="truncate text-sm font-extrabold text-white">Cari Ambulan</h1>
                <span class="block truncate text-[10px] font-medium text-slate-400">Emergency Response</span>
            </div>
        </div>

        <div class="contents md:block md:space-y-1 md:flex-1 md:overflow-y-auto">
            @yield('nav-items')
        </div>

        <div class="mt-auto hidden border-t border-slate-800 pt-4 md:block shrink-0">
            @auth
                <div class="flex items-center gap-3 rounded-xl bg-slate-800/80 border border-slate-700/60 p-3">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-bold text-white">
                        {{ substr(auth()->user()->full_name, 0, 1) }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-xs font-bold text-white">{{ auth()->user()->full_name }}</p>
                        <p class="truncate text-[10px] text-slate-400">{{ auth()->user()->roles->first()?->name ?? '' }}</p>
                    </div>
                </div>
                <form method="POST" action="{{ route('logout') }}" class="mt-3">
                    @csrf
                    <button type="submit" class="flex h-11 w-full items-center justify-center gap-2 rounded-xl border border-slate-800 bg-slate-800/40 text-slate-300 hover:border-danger hover:bg-danger/10 hover:text-danger text-sm font-semibold transition">
                        Keluar
                    </button>
                </form>
            @endauth
        </div>
    </nav>

    <div class="min-w-0 flex-1">
        <header class="sticky top-0 z-30 border-b border-line bg-card/90 px-4 py-3 backdrop-blur md:px-8 md:py-5">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-3">
                <h2 class="truncate text-sm font-extrabold md:text-lg">@yield('page-title', 'Dashboard')</h2>
                <div class="flex items-center gap-2">
                    @yield('topbar-actions')
                    @auth
                        <div class="md:hidden">
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="flex size-10 items-center justify-center rounded-full bg-primary text-sm font-bold text-white" aria-label="Keluar">
                                    {{ substr(auth()->user()->full_name, 0, 1) }}
                                </button>
                            </form>
                        </div>
                    @endauth
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-7xl px-4 pb-28 pt-4 md:px-8 md:pb-10 md:pt-6">
            @if(session('success'))
                <x-alert-banner tone="success" class="mb-4">{{ session('success') }}</x-alert-banner>
            @endif
            @if($errors->any())
                <x-alert-banner tone="danger" class="mb-4">
                    {{ $errors->first() }}
                </x-alert-banner>
            @endif
            {{ $slot ?? '' }}
            @yield('content')
        </main>
    </div>
</div>
@stack('scripts')
</body>
</html>
