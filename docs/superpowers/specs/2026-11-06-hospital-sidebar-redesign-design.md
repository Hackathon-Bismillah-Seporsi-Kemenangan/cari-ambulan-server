# Spesifikasi Desain Redesign UI Sidebar Dashboard Hospital

**Tanggal:** 6 November 2026  
**Status:** Disetujui (Approved)  
**Referensi:** `https://hosptal.mantrakshdevs.com/dashboard/admin-dashboard/?theme=dark` (dengan arahan khusus: **kurangi glow/neon**)  
**Tujuan:** Mengeliminasi redundansi visual antara Sidebar kiri dan Tab Bar di tengah dasbor Rumah Sakit (`/dashboard/hospital`), serta mengadopsi estetika *medical command center* modern (dark slate pro, crisp contrast, zero excessive glow).

---

## 1. Latar Belakang & Analisis Masalah

Sebelumnya, dasbor Rumah Sakit (`/dashboard/hospital`) menyajikan dua set kontrol navigasi yang persis sama pada satu layar:
1. **Sidebar Kiri**: Memuat link `Beranda`, `Antrean IGD (#permintaan)`, `Armada Ambulan (#armada)`, `Tim Driver (#driver)`, dan `Info & Gerbang IGD (#faskes)`.
2. **Pill Tab Bar di Tengah**: Tepat di sebelah kanan sidebar, terdapat tombol segmented horizontal dengan 4 item identik (`Antrean & Rujukan IGD`, `Armada Ambulans`, `Tim Driver`, `Info & Gerbang IGD`).

Kelemahan pendekatan lama:
- **Redundansi Visual yang Membingungkan**: Pengguna disajikan dua kontrol paralel untuk hal yang sama di viewport yang sama.
- **Pemborosan Ruang Layar (*Screen Real Estate*)**: Baris tab di tengah memakan ruang vertikal yang seharusnya dapat digunakan langsung untuk tabel antrean atau metrik KPI operasional.
- **Tampilan Kurang Modern**: Sidebar terasa polos dan tidak memberikan identitas faskes sekilas pandang (*at-a-glance*).

---

## 2. Arahan Desain & Referensi (Dark Slate Medical Pro — No Glow)

Mengacu pada referensi `https://hosptal.mantrakshdevs.com/dashboard/admin-dashboard/?theme=dark` dengan penyesuaian khusus:
- **Dark Slate Background**: Menggunakan palet `bg-slate-900` (`#0F172A`) dengan border tipis tegas `border-slate-800` pada sidebar desktop.
- **Kurangi Efek Glow / Neon**: 
  - Tidak menggunakan `box-shadow` berpendar/neon (`shadow-primary/50`, `blur-xl`, dsb).
  - Menggantinya dengan **crisp borders** (`border border-slate-700/60`), **subtle micro-shadows** (`shadow-xs` / `shadow-sm`), dan **solid/translucent badges beresolusi tajam**.
- **Kontras Tinggi & Keterbacaan Medis**:
  - Teks utama berwarna putih cerah (`text-white`) dan slate lembut (`text-slate-300`, `text-slate-400`).
  - Indikator aktif tegas (*crisp solid fill* `bg-primary text-white font-bold`).
  - Badge angka yang kontras dan informatif (*realtime counter*).

---

## 3. Spesifikasi Tampilan & Komponen

### 3.1 Struktur Sidebar Rumah Sakit (`resources/views/layouts/dashboard.blade.php` & `hospital.blade.php`)

```
+------------------------------------------+
| [CariAmbulan Logo] Hospital Command      |
|                                          |
| [Card Profil Faskes Mini (Dark Slate)]   |
| 🏥 RS Medika                             |
| ● IGD Standby 24 Jam | Hotline 0215550119|
+------------------------------------------+
| OPERASIONAL IGD                          |
| 🔴 Antrean & Rujukan IGD            [ 2 ]| <- Active state (Crisp fill, no glow)
| 🚑 Armada Ambulans                [ 1/1 ]|
| 🚚 Tim Driver                     [ 1 On]|
| 🏥 Info & Gerbang Masuk          [Grb B] |
+------------------------------------------+
| BANTUAN & INTEGRASI                      |
| 📞 Hubungi SPGDT 119                     |
| ⚙️ Kembali ke Super Admin (jika admin)   |
+------------------------------------------+
| [Profil User Dark Slate] Admin RS Medika |
| [Tombol Keluar Border Slate]             |
+------------------------------------------+
```

1. **Card Faskes Mini (Header Sidebar)**:
   - Terletak di bawah logo CariAmbulan.
   - Nama Rumah Sakit mitra aktif (`{{ $hospital->name }}`) berhuruf tebal putih.
   - Indikator kesiapan IGD: Titik hijau dengan animasi pulse lembut (`bg-emerald-500 animate-pulse`) bertuliskan `IGD Standby 24 Jam`.
   - Tombol klik-untuk-menelepon hotline IGD faskes (`tel:{{ $hospital->emergency_phone }}`) beraksen merah tajam tanpa glow.
2. **Grup Menu Operasional Faskes**:
   - `Antrean & Rujukan IGD` (`#permintaan`):
     - Ikon `sos`.
     - Active state: `bg-primary text-white font-bold shadow-xs` (tanpa blur shadow).
     - Badge counter: `bg-rose-500/20 text-rose-300 border border-rose-500/30` dinamis berisi jumlah rujukan aktif.
   - `Armada Ambulans` (`#armada`):
     - Ikon `ambulance`.
     - Badge rasio unit siap (`{{ $activeAmbulances }}/{{ $totalAmbulances }}`).
   - `Tim Driver` (`#driver`):
     - Ikon `truck`.
     - Badge jumlah supir online standby (`{{ $availableDrivers->count() }} On`).
   - `Info & Gerbang IGD` (`#faskes`):
     - Ikon `hospital`.
     - Badge titik masuk ambulans (`{{ $hospital->ambulance_entrance ?? 'IGD' }}`).
3. **Grup Bantuan & Jalur Cepat**:
   - `Panggilan Darurat SPGDT 119` (`tel:119`): Tautan langsung darurat nasional beraksen merah/slate lembut.
   - `Kembali ke Admin` (`route('dashboard.admin')`): Ditampilkan hanya jika user memiliki role `administrator`.
4. **Profil Akun di Bawah Sidebar**:
   - Card profil bernuansa `bg-slate-800/80 border border-slate-700/60`, teks inisial avatar tajam, dan tombol "Keluar" beraksen slate-300 yang beralih ke rose saat hover.

---

### 3.2 Penyesuaian Area Konten Utama (`resources/views/dashboard/hospital.blade.php`)

1. **Eliminasi Baris Tab di Tengah**:
   - Menghapus kontainer segmented button horizontal di tengah halaman yang sebelumnya menduplikasi link sidebar.
2. **Pembersihan Glow pada Kartu & Banner**:
   - Menghapus efek `shadow-primary/30`, `shadow-danger/25`, dsb.
   - Menggunakan border bersih (`border border-line` atau `border border-slate-200`) dan bayangan mikro tajam (`shadow-xs`).
3. **Panel Konten Terisolasi**:
   - Modul `data-tab-content="permintaan"`, `data-tab-content="armada"`, `data-tab-content="driver"`, dan `data-tab-content="faskes"` tampil langsung di bawah grid KPI.
   - Panel aktif ditampilkan, panel lainnya diberi kelas `hidden`.

---

### 3.3 Penyesuaian Script Interaksi Client-Side (`resources/js/app.js`)

1. **Sinkronisasi Navigasi Sidebar**:
   - Memperluas listener tab di `resources/js/app.js` agar elemen navigasi sidebar (`data-tab-target` atau tautan anchor `#permintaan`, `#armada`, dll.) secara otomatis:
     - Mengubah panel konten yang terlihat (`[data-tab-content]`).
     - Memperbarui kelas visual aktif pada link sidebar (`bg-primary text-white shadow-xs` vs `text-slate-300 hover:text-white hover:bg-slate-800/80`).
     - Memperbarui URL hash tanpa me-reload halaman (`history.replaceState`).
2. **Mendukung Direct Deep-linking & Hashchange**:
   - Jika pengguna mengakses `/dashboard/hospital#driver`, script langsung mengaktifkan modul Driver dan menandai link Driver di sidebar sebagai aktif.

---

## 4. Rencana Pengujian & Verifikasi

1. **Uji Fungsional Navigasi (Pest Test)**:
   - Memastikan rute `/dashboard/hospital` merender dengan status HTTP 200 untuk akun `hospital_admin` dan `administrator`.
   - Memastikan tidak ada lagi baris tab duplikat di area tengah.
   - Memastikan elemen sidebar memuat nama faskes, indikator kesiapan, dan 4 link modul operasional dengan badge yang sesuai.
2. **Uji Real-time WebSocket (Reverb / Echo)**:
   - Memastikan listener WebSocket real-time pada tabel `#requests-table` tetap berjalan normal saat status rujukan diperbarui.
3. **Pint Code Formatter**:
   - Seluruh file Blade, JS, dan PHP wajib lolos audit format PSR-12 via `vendor/bin/pint --dirty --format agent`.
4. **Kompilasi Aset Frontend**:
   - Menjalankan `npm run build` dan memastikan tidak ada error kompilasi Tailwind v4 / Vite.
