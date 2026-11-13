@extends('layouts.dashboard')

@section('title', 'Buat Role Baru')
@section('page-title', 'Buat Role Baru')

@section('nav-items')
    <x-admin-nav active="roles" />
@endsection

@section('topbar-actions')
    <a href="{{ route('admin.roles.index') }}" class="flex h-10 items-center gap-1.5 rounded-xl border border-line bg-card px-3 text-xs font-semibold text-ink/70 transition hover:border-primary hover:text-primary">
        <x-icon name="arrow-left" class="size-3.5" /> Kembali
    </a>
@endsection

@section('content')
    <div class="mx-auto max-w-md rounded-xl border border-line bg-card p-6">
        <h3 class="mb-1 text-sm font-bold">Informasi role</h3>
        <p class="mb-5 text-xs text-muted">Gunakan format snake_case, contoh: hospital_admin, senior_driver</p>
        <form method="POST" action="{{ route('admin.roles.store') }}">
            @csrf
            <x-input name="name" label="Nama Role" value="{{ old('name') }}" placeholder="dispatcher" required :error="$errors->first('name')" />
            <button type="submit" class="flex h-12 w-full items-center justify-center rounded-xl bg-primary text-sm font-bold text-white transition hover:bg-primary/90">
                Simpan Role
            </button>
        </form>
    </div>
@endsection
