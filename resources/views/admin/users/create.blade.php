@extends('layouts.dashboard')

@section('title', 'Tambah User Baru')
@section('page-title', 'Tambah User Baru')

@section('nav-items')
    <x-admin-nav active="users" />
@endsection

@section('topbar-actions')
    <a href="{{ route('admin.users.index') }}" class="flex h-9 items-center gap-1.5 rounded-xl border border-line bg-card px-3 text-xs font-semibold text-ink/70 transition hover:border-primary hover:text-primary">
        <x-icon name="arrow-left" class="size-3.5" /> Kembali ke Daftar User
    </a>
@endsection

@section('content')
    <div class="mx-auto max-w-2xl space-y-4">
        {{-- Breadcrumb Navigation --}}
        <nav class="flex items-center gap-2 text-xs text-slate-400">
            <a href="{{ route('dashboard.admin') }}" class="inline-flex items-center gap-1 font-semibold text-sky-400 hover:text-sky-300 transition">
                <x-icon name="meters" class="size-3.5" /> Pusat Komando
            </a>
            <span class="text-slate-600">/</span>
            <a href="{{ route('admin.users.index') }}" class="font-semibold text-sky-400 hover:text-sky-300 transition">
                Manajemen User
            </a>
            <span class="text-slate-600">/</span>
            <span class="text-white font-bold">Tambah Baru</span>
        </nav>

        <div class="rounded-xl border border-line bg-card p-6 shadow-sm">
            <div class="mb-6 border-b border-line pb-4">
                <h3 class="text-lg font-bold text-ink">Informasi Akun Pengguna</h3>
                <p class="mt-1 text-xs text-ink/60">Lengkapi data pengguna baru untuk didaftarkan ke dalam sistem Cari Ambulan.</p>
            </div>

            <form method="POST" action="{{ route('admin.users.store') }}">
                @csrf

                <x-input
                    name="full_name"
                    label="Nama Lengkap"
                    icon="user"
                    placeholder="Contoh: Budi Santoso"
                    :value="old('full_name')"
                    :autofocus="true"
                    required
                    :error="$errors->first('full_name')"
                />

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-input
                        name="email"
                        type="email"
                        label="Alamat Email"
                        icon="mail"
                        placeholder="nama@cariambulan.id"
                        :value="old('email')"
                        autocomplete="email"
                        required
                        :error="$errors->first('email')"
                    />

                    <x-input
                        name="phone"
                        type="tel"
                        label="Nomor Telepon"
                        icon="phone"
                        placeholder="Contoh: 081234567890"
                        :value="old('phone')"
                        autocomplete="tel"
                        required
                        :error="$errors->first('phone')"
                    />
                </div>

                <div class="mb-4">
                    <label for="alamat" class="mb-1.5 block text-sm font-semibold text-ink">
                        Alamat Lengkap <span class="text-danger">*</span>
                    </label>
                    <textarea
                        id="alamat"
                        name="alamat"
                        rows="3"
                        required
                        placeholder="Alamat tempat tinggal / domisili pengguna..."
                        class="w-full rounded-xl border bg-card p-3.5 text-sm text-ink placeholder:text-muted focus:outline-none focus:ring-2 focus:ring-primary/40 {{ $errors->has('alamat') ? 'border-danger' : 'border-line focus:border-primary' }}"
                    >{{ old('alamat') }}</textarea>
                    @error('alamat')
                        <p class="mt-1.5 text-xs font-medium text-danger">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-input
                        name="password"
                        type="password"
                        label="Kata Sandi"
                        icon="lock"
                        placeholder="Minimal 8 karakter"
                        autocomplete="new-password"
                        required
                        :error="$errors->first('password')"
                    />

                    <x-input
                        name="password_confirmation"
                        type="password"
                        label="Konfirmasi Kata Sandi"
                        icon="lock"
                        placeholder="Ulangi kata sandi"
                        autocomplete="new-password"
                        required
                    />
                </div>

                {{-- Role Selection (1 User = 1 Role) --}}
                <div class="mt-2 mb-6 rounded-xl border border-line bg-surface/60 p-4">
                    <div class="mb-3">
                        <label class="block text-sm font-bold text-ink">Penetapan Role (1 Pengguna = 1 Role)</label>
                        <p class="text-xs text-ink/60">Pilih satu peran yang ditetapkan untuk pengguna ini:</p>
                    </div>

                    <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                        @forelse($roles as $role)
                            <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-line bg-card p-3 transition hover:border-primary">
                                <input
                                    type="radio"
                                    name="role_id"
                                    value="{{ $role->id }}"
                                    class="size-4 accent-primary"
                                    {{ (old('role_id') ?? (is_array(old('roles')) ? old('roles')[0] : old('roles'))) === $role->id ? 'checked' : '' }}
                                >
                                <div class="min-w-0 flex-1">
                                    <span class="block text-sm font-semibold text-ink capitalize">{{ $role->name }}</span>
                                </div>
                            </label>
                        @empty
                            <p class="col-span-2 text-xs text-muted">Belum ada role yang tersedia.</p>
                        @endforelse
                    </div>
                    @error('role_id')
                        <p class="mt-2 text-xs font-medium text-danger">{{ $message }}</p>
                    @enderror
                    @error('roles')
                        <p class="mt-2 text-xs font-medium text-danger">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Detail Tambahan Khusus Driver --}}
                <div class="mb-6 rounded-xl border border-line bg-surface/60 p-4 space-y-3">
                    <div>
                        <span class="block text-sm font-bold text-ink">Informasi Tambahan Pengemudi (Khusus Role Driver)</span>
                        <p class="text-xs text-ink/60">Isi data lisensi dan afiliasi faskes jika pengguna ini bertugas sebagai supir ambulans:</p>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-input
                            name="license_number"
                            label="Nomor SIM Driver"
                            icon="shield"
                            placeholder="Contoh: SIM-B1-123456"
                            :value="old('license_number')"
                            :error="$errors->first('license_number')"
                        />

                        <div>
                            <label for="admin_hospital_id" class="mb-1.5 block text-sm font-semibold text-ink">Afiliasi Rumah Sakit (Opsional)</label>
                            <select id="admin_hospital_id" name="hospital_id" class="h-10 w-full rounded-xl border border-line bg-card px-3 text-xs text-ink focus:border-primary focus:outline-none">
                                <option value="">-- Tanpa Faskes (Driver Lepas/Umum) --</option>
                                @foreach($hospitals ?? [] as $hosp)
                                    <option value="{{ $hosp->id }}" @selected(old('hospital_id') === $hosp->id)>{{ $hosp->name }}</option>
                                @endforeach
                            </select>
                            @error('hospital_id')
                                <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:justify-end">
                    <a href="{{ route('admin.users.index') }}" class="flex h-11 items-center justify-center rounded-xl border border-line bg-card px-5 text-sm font-semibold text-ink/70 transition hover:bg-surface">
                        Batal
                    </a>
                    <button type="submit" class="flex h-11 items-center justify-center gap-2 rounded-xl bg-primary px-6 text-sm font-bold text-white shadow-sm transition hover:bg-primary/90 active:scale-[0.98]">
                        Simpan Pengguna
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
