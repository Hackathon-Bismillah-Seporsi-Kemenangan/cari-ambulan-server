@extends('layouts.dashboard')

@section('title', 'Manajemen Role')
@section('page-title', 'Manajemen Role')

@section('nav-items')
    <x-admin-nav active="roles" />
@endsection

@section('topbar-actions')
    <a href="{{ route('admin.roles.create') }}" class="flex h-10 items-center gap-1.5 rounded-xl bg-primary px-3 text-xs font-bold text-white transition hover:bg-primary/90">
        <x-icon name="check" class="size-4" /> Buat Role
    </a>
@endsection

@section('content')
    <div class="space-y-4">
        {{-- Breadcrumb Navigation --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <nav class="flex items-center gap-2 text-xs text-slate-400">
                <a href="{{ route('dashboard.admin') }}" class="inline-flex items-center gap-1 font-semibold text-sky-400 hover:text-sky-300 transition">
                    <x-icon name="meters" class="size-3.5" /> Pusat Komando
                </a>
                <span class="text-slate-600">/</span>
                <span class="text-white font-bold">Manajemen Role</span>
            </nav>
            <div class="text-xs text-slate-400">
                Total <span class="font-bold text-white font-mono">{{ $roles->count() }}</span> Peran / Hak Akses Terdaftar
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse($roles as $role)
            <div class="flex flex-col rounded-xl border border-line bg-card p-5">
                <div class="flex items-start justify-between">
                    <span class="flex size-11 items-center justify-center rounded-xl bg-danger-50 text-danger">
                        <x-icon name="shield" class="size-5" />
                    </span>
                    <div class="flex gap-1.5">
                        <a href="{{ route('admin.roles.edit', $role) }}" class="flex size-8 items-center justify-center rounded-lg border border-line text-ink/60 transition hover:border-primary hover:text-primary">
                            <x-icon name="meters" class="size-3.5" />
                        </a>
                        <form method="POST" action="{{ route('admin.roles.destroy', $role) }}"
                              onsubmit="return confirm('Hapus role \'{{ $role->name }}\'? Semua user akan terlepas dari role ini.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="flex size-8 items-center justify-center rounded-lg border border-line text-ink/60 transition hover:border-danger hover:text-danger">
                                <x-icon name="x" class="size-3.5" />
                            </button>
                        </form>
                    </div>
                </div>
                <h3 class="mt-3 text-base font-extrabold">{{ $role->name }}</h3>
                <p class="text-xs text-muted">{{ $role->users_count }} pengguna</p>
                <a href="{{ route('admin.roles.show', $role) }}" class="mt-4 flex h-10 items-center justify-center gap-2 rounded-xl border border-line text-xs font-semibold text-ink/70 transition hover:border-primary hover:text-primary">
                    <x-icon name="users" class="size-4" /> Lihat pengguna
                </a>
            </div>
        @empty
            <x-empty-state icon="shield" title="Belum ada role terdaftar" class="col-span-full">
                <a href="{{ route('admin.roles.create') }}" class="flex h-11 items-center rounded-xl bg-primary px-4 text-sm font-bold text-white transition hover:bg-primary/90">Buat role pertama</a>
            </x-empty-state>
        @endforelse
        </div>
    </div>
@endsection
