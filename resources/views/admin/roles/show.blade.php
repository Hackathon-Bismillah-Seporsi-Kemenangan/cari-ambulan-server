@extends('layouts.dashboard')

@section('title', 'Pengguna Role — ' . $role->name)
@section('page-title', 'Role: ' . $role->name)

@section('nav-items')
    <x-admin-nav active="roles" />
@endsection

@section('topbar-actions')
    <a href="{{ route('admin.roles.index') }}" class="flex h-10 items-center gap-1.5 rounded-xl border border-line bg-card px-3 text-xs font-semibold text-ink/70 transition hover:border-primary hover:text-primary">
        <x-icon name="arrow-left" class="size-3.5" /> Kembali
    </a>
    <a href="{{ route('admin.roles.edit', $role) }}" class="flex h-10 items-center gap-1.5 rounded-xl bg-primary px-3 text-xs font-bold text-white transition hover:bg-primary/90">
        Edit Role
    </a>
@endsection

@section('content')
    <div class="overflow-x-auto rounded-xl border border-line bg-card">
        <table class="w-full text-left text-sm">
            <thead>
                <tr class="border-b border-line bg-surface text-xs uppercase tracking-wide text-muted">
                    <th class="px-4 py-3 font-semibold">Nama</th>
                    <th class="px-4 py-3 font-semibold">Email</th>
                    <th class="hidden px-4 py-3 font-semibold sm:table-cell">Telepon</th>
                    <th class="hidden px-4 py-3 font-semibold sm:table-cell">Bergabung</th>
                    <th class="px-4 py-3 font-semibold">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($role->users as $user)
                    <tr class="border-b border-line last:border-0 hover:bg-surface/60">
                        <td class="px-4 py-3 font-bold">{{ $user->full_name }}</td>
                        <td class="px-4 py-3 text-xs text-ink/60">{{ $user->email ?? '-' }}</td>
                        <td class="hidden px-4 py-3 text-xs sm:table-cell">{{ $user->phone }}</td>
                        <td class="hidden px-4 py-3 text-xs text-muted sm:table-cell">{{ $user->created_at->format('d M Y') }}</td>
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.users.show', $user) }}" class="flex h-9 items-center rounded-lg border border-line px-3 text-xs font-semibold text-ink/70 transition hover:border-primary hover:text-primary">
                                Kelola
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-12 text-center text-sm text-muted">Belum ada pengguna yang memiliki role ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
