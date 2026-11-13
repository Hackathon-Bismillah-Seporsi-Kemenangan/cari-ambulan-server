# Dashboard Redesign — Cari Ambulan Design System

Tanggal: 2026-11-03
Status: Disetujui (brainstorming selesai, menunggu review spec)

## Tujuan

Merombak total tampilan 4 dashboard (User, Driver, Hospital, Admin) dari layout
web sidebar tradisional menjadi **mobile-first** mengikuti design system
Cari Ambulan v1.0: calm but urgent, map-first, large readable text, touch target
minimum 48px, spacing grid 8px, ditambah **redesign register + login +
welcome page** dan **pembuatan fitur register baru** (tidak ada sebelumnya).
Controller dashboard tidak diubah; controller auth ditambah untuk register.

## Keputusan Tambahan (Bagian 6-9, disetujui 2026-11-03)

- **Register**: fungsional lengkap — route `GET /register` (`register`) +
  `POST /register` (`register.attempt`), controller baru
  `app/Http/Controllers/Auth/RegisterController.php` (`showRegistrationForm`,
  `store`). Validasi: `full_name` required max 200; `email` required email
  unique; `phone` required max 20; `password` required min 8 confirmed.
  Sukses → assign role `user` via mekanisme relasi Role yang sudah ada
  (attach di pivot roles — dibuat eksplisit saat implementasi), auto-login,
  redirect `dashboard.user` + flash "Selamat datang!". User table sudah punya
  kolom `full_name` & `phone` sehingga tanpa migrasi.
- **Shell guest**: layout baru `layouts/guest.blade.php` untuk auth & welcome —
  desktop split (panel brand merah-biru kiri, kartu form kanan di `surface`),
  mobile satu kolom.
- **Komponen form**: `x-input` (label, ikon opsional, error, min-height 48px)
  dan `x-alert-banner` (bagian 3) dipakai login & register.
- **Login**: dirombak ke shell guest; pertahankan JS toggle password,
  remember-me, dan demo admin + "Isi Otomatis"; error per-field via `x-input`.
- **Welcome**: starter-kit boilerplate diganti landing Cari Ambulan — nav atas
  (logo + Masuk/Daftar, "Dashboard" bila auth), hero judul + sos-button
  (klik → `login`), CTA Masuk/Buat Akun, blok fitur 3 kolom memakai
  `x-action-card` (User 9, Shared 7, Driver 7, kode layar U/D/O/S), footer
  kredit design system. Konten fitur statis.
- **Test register**: Pest baru — sukses, validasi (email duplikat, sandi
  pendek), redirect pasca-register. Suite lama tetap harus hijau.

## Keputusan yang Disetujui

- **Cakupan**: keempat dashboard (user, driver, hospital, admin) + halaman CRUD admin (`admin/users/*`, `admin/roles/*`) ikut di-refresh token biar konsisten.
- **Layout**: mobile-first seperti referensi (bottom nav di mobile, konten kartu besar), BECAUSE pengguna konfirmasi.
- **Styling**: Tailwind utility classes (tailwindcss ^4.0.0 sudah terpasang), CSS custom lama dihapus bertahap per file yang dirombak.
- **Pendekatan**: layout baru + Blade components reusable (bukan satu template generik) karena komposisi tiap role berbeda signifikan.
- **Scope data**: visual-only, variabel dari controller tetap dipakai.

## 1. Design Tokens (Tailwind v4 `@theme` di `resources/css/app.css`)

| Token | Nilai | Kelas Tailwind |
|---|---|---|
| `danger` (Emergency Red) | `#D92D2F` | `bg-danger`, `text-danger` |
| `danger-light` (Red Light) | `#FDECEA` | `bg-danger-50` |
| `primary` (Trust Blue) | `#1D66C0` | `bg-primary` |
| `primary-light` (Blue Light) | `#E9F2FD` | `bg-primary-50` |
| `success` | `#22C732` | `bg-success` |
| `warning` | `#F88A25` | `bg-warning` |
| `surface` | `#F5F8F9` | background body |
| `ink` (Text) | `#171B27` | `text-ink` |
| `muted` | `#A8B2B0` | `text-muted` |
| `line` (Border) | `#E5E8EB` | `border-line` |

Aturan tambahan: font Inter (sudah dimuat); radius kartu 12–16px; spacing
kelipatan 8px; semua tombol & nav item berukuran min 48px tinggi; ikon SVG
inline stroke (lucide-style path) — tidak ada emoji.

Catatan penamaan: nama token Tailwind v4 memengaruhi nama warna bawaan; gunakan
`--color-*` khusus dan hindari bentrok dengan scale bawaan (`50/100/...`).
Detail naming final ditentukan saat implementasi (lihat plan).

## 2. Layout Shell — `resources/views/layouts/dashboard.blade.php`

Rombak total file ini:

- **Mobile**: topbar tipis (logo + judul halaman), konten, **bottom nav tetap
  di bawah** — item ikon SVG + label, item aktif = pill `bg-primary-50 text-primary`.
- **Desktop (≥1024px)**: bottom nav berubah menjadi **rail kiri**; konten
  centered `max-w-3xl` untuk user/driver, `max-w-6xl` untuk admin/hospital
  (yang memuat tabel).
- Sidebar/backdrop JS lama dihapus. Tiap dashboard view memberikan array item
  nav (`label`, `route`, `icon`) langsung pada komponen `x-nav-bar`
  (`<x-nav-bar :items="[...]" />`) tanpa section khusus.
- Kartu user + logout pindah ke dropdown avatar di topbar, dengan satu script
  kecil toggle (tanpa Alpine).
- Flash `session('success')` dirender via `x-alert-banner`.

## 3. Komponen Blade Baru (`resources/views/components/`)

| Komponen | Fungsi |
|---|---|
| `status-badge.blade.php` | Pill status berwarna (info/success/danger/warning) via prop `tone` |
| `stat-card.blade.php` | Angka besar + label + aksen warna |
| `action-card.blade.php` | Kartu list: ikon, judul, deskripsi, kode layar (mis. U2) |
| `sos-button.blade.php` | Lingkaran merah besar + halo pulse + teks "CARI AMBULANS" |
| `trip-timeline.blade.php` | Stepper vertikal 5 langkah: mencari → diterima → di jalan → RS → selesai |
| `driver-card.blade.php` | Avatar, nama, rating, kode order, tombol Telepon/Chat |
| `alert-banner.blade.php` | Info/warning/success/error banner dengan tombol tutup |
| `empty-state.blade.php` | Ikon + judul + deskripsi |

## 4. Komposisi per Dashboard

Data yang tersedia per role (tidak diubah):

- `dashboard.user`: `$user`, `$totalRequestsCount`, `$completedRequestsCount`,
  `$activeRequest` (+`hospital`, `driverProfile.user`, `pickup_address`),
  `$requestHistory`.
- `dashboard.driver`: `$activeRequest` (+`user`, `hospital`, `ambulance`),
  `$pendingDispatchOffers`, `$tripHistory`, `$totalTrips`, `$completedTrips`.
- `dashboard.hospital`: `$totalAmbulances`, `$activeAmbulances`,
  `$busyAmbulances`, `$recentRequests`, `$totalDrivers`, `$totalRequests`,
  `$completedRequests`.
- `dashboard.admin`: `$totalUsers`, `$totalHospitals`, `$totalAmbulances`,
  `$totalEmergencyRequests`, `$usersByRole`, `$requestsByStatus`,
  `$recentEmergencyRequests`, `$recentDispatchLogs` (+active/busy ambulances).

Komposisi:

- **User**: sambutan singkat; **sos-button** dominan bila tak ada permintaan
  aktif; jika ada `$activeRequest` → **trip-timeline** + **driver-card**.
  Stat mini (Total, Selesai) di atas. Riwayat sebagai daftar `action-card` +
  `status-badge` (bukan tabel).
- **Driver**: kartu order aktif + **trip-timeline**; daftar `$pendingDispatchOffers`
  sebagai `driver-card`; stat mini (total trip & trip selesai).
- **Hospital**: stat mini (ambulances/drivers/requests); daftar `$recentRequests`
  sebagai `action-card` + `status-badge`.
- **Admin**: stat-card grid (users, hospitals, ambulances, requests,
  active/busy ambulances); tabel ringkasan permintaan & dispatch logs tetap
  format tabel (styled Tailwind, `hover:bg-surface`); halaman
  CRUD (`admin/users`, `admin/roles`) hanya di-refresh kelas-kelasnya dengan
  token baru.

## 5. Error Handling, Testing, Verifikasi

- Flash messages lewat `x-alert-banner`.
- Tidak ada perubahan logika — pengujian: aktifkan suite yang ada
  (`php artisan test --compact`) untuk mengonfirmasi tak ada regresi; jalankan
  `vendor/bin/pint --dirty --format agent` setelah menyentuh file PHP.
- Build frontend: `npm run build` setelah mengubah `app.css` agar manifest Vite
  valid.
- Verifikasi manual per role: `/dashboard/user`, `/dashboard/driver`,
  `/dashboard/hospital`, `/dashboard/admin`.

## Out of Scope

- Penambahan fitur backend selain register (wiring order countdown, toggle
  online driver, dsb).
- API mobile / Blade untuk aplikasi mobile.
- Penghapusan CSS custom klasik yang dipakai oleh view yang tidak dirombak
  (mis. `welcome.blade.php`, `auth/login.blade.php` tetap berfungsi).
- Chat realis (Telepon/Chat button pada driver-card disiapkan tapi belum
  terhubung route).
