@extends('layouts.dashboard')

@section('title', 'Edit Role — ' . $role->name)
@section('page-title', 'Edit Role')

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
        <h3 class="mb-5 text-sm font-bold">Edit role: <span class="text-primary">{{ $role->name }}</span></h3>
        <form method="POST" action="{{ route('admin.roles.update', $role) }}">
            @csrf
            @method('PUT')
            <x-input name="name" label="Nama Role" value="{{ old('name', $role->name) }}" required :error="$errors->first('name')" />
            <button type="submit" class="flex h-12 w-full items-center justify-center rounded-xl bg-primary text-sm font-bold text-white transition hover:bg-primary/90">
                Perbarui Role
            </button>
        </form>
    </div>
@endsection
