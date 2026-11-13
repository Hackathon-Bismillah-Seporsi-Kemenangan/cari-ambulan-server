@extends('layouts.guest')

@section('title', 'Masuk')

@section('content')
<div class="w-full max-w-sm">
    <h1 class="text-2xl font-extrabold">Portal Masuk</h1>
    <p class="mt-1 text-sm text-ink/60">Khusus Administrator Sistem &amp; Mitra Rumah Sakit.</p>

    <form method="POST" action="{{ route('login.attempt') }}" class="mt-6" id="loginForm">
        @csrf

        @if(session('success'))
            <x-alert-banner tone="success" class="mb-4">{{ session('success') }}</x-alert-banner>
        @endif
        @if($errors->has('email') && ! old('email'))
            <x-alert-banner tone="danger" class="mb-4">{{ $errors->first('email') }}</x-alert-banner>
        @endif

        <x-input name="email" type="email" label="Alamat Email" icon="mail" placeholder="nama@cariambulan.id" :autofocus="true" autocomplete="email" required :error="$errors->first('email')" />

        <div class="mb-1.5 flex items-center justify-between">
            <label for="password" class="text-sm font-semibold text-ink">Kata Sandi</label>
            <button type="button" id="togglePasswordBtn" class="text-xs font-semibold text-primary transition hover:underline">Lihat</button>
        </div>
        <x-input name="password" type="password" icon="lock" placeholder="••••••••" autocomplete="current-password" :error="$errors->first('password')" required />

        <label class="mb-5 mt-1 flex cursor-pointer items-center gap-2.5 select-none">
            <input type="checkbox" name="remember" value="1" {{ old('remember') ? 'checked' : '' }} class="size-4 accent-primary">
            <span class="text-sm text-ink/70">Ingat saya di perangkat ini</span>
        </label>

        <button type="submit" class="flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-primary text-sm font-bold text-white shadow-sm transition hover:bg-primary/90 active:scale-[0.98]">
            Masuk ke Dashboard <x-icon name="arrow-right" class="size-4" />
        </button>
    </form>

    <div class="mt-5 rounded-xl border border-warning/30 bg-warning-50 p-4">
        <div class="flex items-center justify-between gap-2">
            <span class="text-xs font-bold text-warning">Akun demo sistem</span>
            <div class="flex items-center gap-1.5">
                <button type="button" id="autofillAdminBtn" class="rounded-lg border border-warning/40 bg-card px-2 py-0.5 text-[11px] font-bold text-warning transition hover:bg-warning/10">
                    Admin
                </button>
                <button type="button" id="autofillHospitalBtn" class="rounded-lg border border-warning/40 bg-card px-2 py-0.5 text-[11px] font-bold text-warning transition hover:bg-warning/10">
                    RS Admin
                </button>
            </div>
        </div>
        <div class="mt-2 space-y-1 font-mono text-[11px] text-ink/70">
            <p>Admin: <span class="font-bold text-ink">admin@cariambulan.id</span> (Admin@123)</p>
            <p>RS Admin: <span class="font-bold text-ink">hospital@cariambulan.id</span> (Hospital@123)</p>
        </div>
    </div>

    <p class="mt-6 text-center text-sm text-ink/60">
        Rumah sakit belum bermitra?
        <a href="{{ route('register') }}" class="font-bold text-primary hover:underline">Daftar Rumah Sakit</a>
    </p>

    <div class="mt-4 rounded-xl border border-line bg-surface p-3 text-center text-[11px] text-ink/60">
        Driver ambulans &amp; pasien darurat silakan masuk melalui aplikasi mobile <span class="font-bold text-ink">Cari Ambulan</span>.
    </div>

    <p class="mt-6 text-center text-xs text-muted">&copy; {{ date('Y') }} Cari Ambulan Indonesia</p>
</div>

<script>
    const toggleBtn = document.getElementById('togglePasswordBtn');
    const passwordInput = document.getElementById('password');

    if (toggleBtn && passwordInput) {
        toggleBtn.addEventListener('click', () => {
            const isPassword = passwordInput.getAttribute('type') === 'password';
            passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
            toggleBtn.textContent = isPassword ? 'Sembunyikan' : 'Lihat';
        });
    }

    const autofillBtn = document.getElementById('autofillAdminBtn');
    if (autofillBtn) {
        autofillBtn.addEventListener('click', () => {
            document.getElementById('email').value = 'admin@cariambulan.id';
            passwordInput.value = 'Admin@123';
        });
    }

    const autofillHospitalBtn = document.getElementById('autofillHospitalBtn');
    if (autofillHospitalBtn) {
        autofillHospitalBtn.addEventListener('click', () => {
            document.getElementById('email').value = 'hospital@cariambulan.id';
            passwordInput.value = 'Hospital@123';
        });
    }
</script>
@endsection
