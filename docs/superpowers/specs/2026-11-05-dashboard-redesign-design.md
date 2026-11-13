# Spesifikasi Desain Redesign UI Administrator & Hospital Admin

**Tanggal:** 5 November 2026  
**Status:** Disetujui (Approved)  
**Tujuan:** Merancang ulang antarmuka dasbor Administrator dan Hospital Admin di platform CariAmbulan dengan pendekatan *Interactive Card & Tab Layout* berbasis client-side tabs instan dengan sinkronisasi URL hash.

---

## 1. Latar Belakang & Masalah Saat Ini

Sebelumnya, dasbor Administrator (`/dashboard/admin`) dan Hospital Admin (`/dashboard/hospital`) menyajikan seluruh metrik, tabel pengguna, tabel role, armada ambulans, tim supir, dan antrean rujukan IGD dalam satu halaman panjang yang bertumpuk ke bawah (*vertical stack*). 

Kelemahan pendekatan lama:
1. **Scrolling Fatigue:** Pengguna harus melakukan scroll panjang untuk mencapai bagian supir atau antrean panggilan IGD.
2. **Kepadatan Informasi Tanpa Fokus:** Administrator kesulitan membedakan dengan cepat antara ringkasan operasional dan fungsi manajemen pengguna/role.
3. **Kurangnya Pemisahan Konteks Operasional IGD:** Tim faskes membutuhkan pemisahan tegas antara pemantauan rujukan darurat aktif (*live dispatch*) dengan data inventaris armada ambulans dan tim supir.

---

## 2. Arsitektur Solusi & Komponen UI

Solusi yang diterapkan adalah **Unified Command Hub dengan 4 Tab Operasional Tersegmen**, didukung oleh komponen tab interaktif berbasis client-side Alpine.js dengan sinkronisasi URL hash.

### 2.1 Komponen Tab Interaktif (`x-tabs` & Segmented Pill Bar)
- **Teknologi:** Komponen Blade dan script Alpine.js ringan terintegrasi:
  ```html
  <div x-data="{ activeTab: window.location.hash ? window.location.hash.substring(1) : 'default_tab' }"
       x-init="window.addEventListener('hashchange', () => { activeTab = window.location.hash.substring(1) || 'default_tab' })">
  ```
- **Sinkronisasi URL Hash:** Saat tab diklik, `window.location.hash` diperbarui secara mulus tanpa me-reload halaman (*zero page reload*). Jika pengguna me-refresh atau membuka link langsung (misal: `/dashboard/hospital#driver`), halaman langsung membuka tab yang tepat.
- **Tampilan Tab Bar:** *Segmented Pill Slider* berlatar abu-abu netral (`bg-surface/80 p-1.5 rounded-2xl border border-line flex flex-wrap gap-1`), di mana tab aktif memiliki kontras tinggi (`bg-card text-ink font-bold shadow-sm border border-line/60`) dan badge penghitung data dinamis.

### 2.2 Visual Token & Indikator Status (Tailwind CSS v4)
- Menggunakan palet token Tailwind v4 yang sudah didefinisikan dalam `app.css` (`primary`, `danger`, `success`, `warning`, `card`, `surface`, `line`, `ink`, `muted`).
- **Live Pulse Dots:**
  - `online` / `siap`: Hijau terang dengan animasi pulse lembut (`bg-success animate-pulse`).
  - `busy` / `bertugas`: Kuning amber peringatan aktif (`bg-warning`).
  - `offline`: Abu-abu netral (`bg-muted/50`).
  - `kritis / darurat`: Merah aksen (`bg-danger`).

---

## 3. Spesifikasi Tampilan Hospital Admin (`/dashboard/hospital`)

### 3.1 Header Rumah Sakit & Bar Metrik Cepat
1. **Banner Profil Faskes:**
   - Nama rumah sakit mitra, alamat lengkap, dan badge gerbang ambulans (*e.g., Gerbang B - Jalur Evakuasi Cepat*).
   - Hotline darurat IGD dengan tautan panggilan langsung (`tel:`).
   - Indikator kesiapan: Pill total ambulans terhubung dan jumlah supir standby.
2. **Selector Rumah Sakit (Khusus Administrator):**
   - Jika akun yang melihat adalah role `administrator`, dropdown pemilih rumah sakit ditampilkan di topbar/header untuk berpindah faskes dengan cepat.
3. **Pita Metrik KPI Cepat:**
   - 4 kartu stat ringkas di atas tab: Total Armada, Supir Siap, Panggilan Aktif Menuju IGD, dan Rujukan Selesai.

### 3.2 Struktur 4 Tab Hospital Admin

```
[ Tab 1: Antrean & Rujukan IGD ] [ Tab 2: Armada Ambulans ] [ Tab 3: Tim Driver ] [ Tab 4: Info & Gerbang IGD ]
```

1. **Tab 1: `permintaan` (Antrean & Rujukan Pasien IGD)**
   - **Filter Status Cepat:** Tombol filter pill: `Semua`, `Menuju IGD (Aktif)`, `Selesai`, `Dibatalkan`.
   - **Tabel Panggilan Darurat Interaktif:**
     - Kode Request, Nama & Telepon Pasien, Tipe Darurat (badge warna keparahan), Driver & Plat Ambulans, Status Panggilan, dan Waktu Masuk.
     - **Panel Info Triage & Persiapan IGD:** Menampilkan catatan kondisi pasien (*patient notes*) dan instruksi persiapan tim IGD otomatis (`igdPreparation()`, e.g. *"IGD menyiapkan tim trauma"*).
2. **Tab 2: `armada` (Armada Ambulans Rumah Sakit)**
   - Grid kartu unit ambulans:
     - Nomor plat dalam tipografi monospace tebal.
     - Tipe unit (*Ambulans ALS / BLS / Gawat Darurat*).
     - Status ketersediaan (*Siap Bertugas* vs *Sedang Di Lapangan*).
     - Nama & kontak supir yang ditugaskan memegang armada tersebut.
3. **Tab 3: `driver` (Tim Pengemudi Ambulans)**
   - Grid kartu tim supir terhubung faskes:
     - Inisial avatar profil & nama lengkap.
     - Indikator ketersediaan real-time (*Online*, *Busy*, *Offline*).
     - Nomor SIM supir dan plat ambulans yang terhubung.
     - Tombol aksi *"Hubungi Driver"* (`tel:`).
4. **Tab 4: `faskes` (Lokasi & Titik Masuk Faskes)**
   - Koordinat GPS (Latitude & Longitude) rumah sakit.
   - Panduan titik masuk ambulans (*Gerbang Masuk IGD / Ambulance Entrance*).
   - Hotline darurat 119 dan nomor kontak langsung rumah sakit.

---

## 4. Spesifikasi Tampilan Administrator (`/dashboard/admin` & Manajemen)

### 4.1 Header Komando & Bar Metrik KPI Sistem
1. **Header Komando:**
   - Judul Dasbor Administrator dengan status operasional sistem.
   - Tombol aksi cepat: `+ Tambah User` dan `+ Buat Role`.
2. **4 Kartu Metrik KPI Utama:**
   - Total Pengguna (dengan sebaran role).
   - Total Faskes & RS Mitra Terhubung.
   - Kesiapan Armada Ambulans Nasional (Siap vs Bertugas).
   - Total Panggilan Darurat Sistem.

### 4.2 Struktur 4 Tab Administrator

```
[ Tab 1: Overview Sistem ] [ Tab 2: Kelola Pengguna ] [ Tab 3: Kelola Role & Akses ] [ Tab 4: RS Mitra ]
```

1. **Tab 1: `overview` (Ringkasan Sistem & Panggilan Terkini)**
   - Feed panggilan darurat aktif terkini lintas rumah sakit.
   - Ringkasan faskes mitra dengan armada terbanyak.
   - Quick launchpads menuju halaman manajemen teknis.
2. **Tab 2: `users` (Manajemen Pengguna Terintegrasi)**
   - Input pencarian instan (nama, email, telepon).
   - Filter role cepat (*Administrator, Driver, User, Hospital Admin*).
   - Tabel pengguna: Avatar inisial & nama, email, telepon, alamat, badge role tunggal (1 user = 1 role), tanggal bergabung, dan tombol aksi "Kelola".
   - Tombol shortcut `+ Tambah User Baru`.
3. **Tab 3: `roles` (Manajemen Peran & Akses)**
   - Kartu peran sistem dengan ikon shield/key.
   - Jumlah pengguna aktif per role.
   - Tombol aksi `Detail Role` dan `Edit Permissions`.
   - Tombol `+ Buat Role Baru`.
4. **Tab 4: `hospitals` (Jaringan Rumah Sakit Mitra)**
   - Kartu faskes mitra se-Indonesia:
     - Nama faskes, alamat, nomor darurat IGD, titik masuk ambulans.
     - Badge jumlah ambulans dan jumlah supir terdaftar.
     - **Tombol Aksi Utama:** Tombol *"Buka Dashboard RS"* yang langsung mengarahkan administrator ke dasbor operasional rumah sakit yang bersangkutan (`/dashboard/hospital?hospital_id={id}`).

---

## 5. Rencana Pengujian & Verifikasi (Verification Plan)

1. **Pest Feature & Browser Verification:**
   - Verifikasi respons HTTP 200 untuk seluruh rute dasbor: `/dashboard/admin`, `/dashboard/hospital`, `/admin/users`, `/admin/roles`.
   - Verifikasi bahwa parameter hash atau query parameter `hospital_id` dapat diakses dan merender data yang sesuai.
2. **Uji Fungsional Navigasi Tab:**
   - Memastikan tab default terbuka saat pertama kali diakses.
   - Memastikan hash URL (`#armada`, `#driver`, `#permintaan`, dll) membuka tab yang bersangkutan.
3. **Pint Code Formatter:**
   - Memastikan seluruh file Blade dan PHP diformat rapi dengan `vendor/bin/pint --dirty --format agent`.

