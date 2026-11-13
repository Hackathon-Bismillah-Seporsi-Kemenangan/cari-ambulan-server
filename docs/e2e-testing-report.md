# Laporan Pengujian End-to-End (E2E) 7 Fitur API CariAmbulan

**Tanggal Pengujian:** 5 November 2026  
**Cakupan:** 7 Fitur Utama API Mobile (User App & Driver App)  
**Status Akhir:** ✅ **126/126 Tests Passed (585 assertions)**, Code Style Formatted (Pint)

---

## 1. Ringkasan Eksekutif

Pengujian End-to-End (E2E) menyeluruh telah dilakukan untuk memvalidasi interaksi nyata antara aplikasi pengguna (User App / Civilian) dan aplikasi pengemudi ambulans (Driver App) melalui server API CariAmbulan.

Pengujian mensimulasikan skenario lengkap mulai dari:
1. Konfigurasi profil pengguna, sinkronisasi Rekam Medis (Medical ID), dan manajemen alamat darurat.
2. Siklus hidup panggilan darurat (*emergency request*): pembatasan radius pencarian awal 3 km, tangga ekspansi otomatis 90 detik hingga 25 km, penawaran ke supir terdekat, penerimaan order, dan pelacakan real-time ambulans.
3. Alur pembatalan oleh pasien saat ambulans sedang menuju lokasi penjemputan (*en route to pickup*) dengan alasan, reset ketersediaan driver menjadi online, dan penutupan otomatis layar supir melalui polling `GET /api/v1/driver/trip`.
4. Validasi pencegahan pembatalan setelah ambulans tiba di lokasi penjemputan (*arrive at pickup*) dengan kode respons 409 Conflict.
5. Mekanisme kehabisan driver (*search exhausted*) pada radius maksimal 25 km dan pengujian tombol *"Cari Lagi"* (`POST /emergency-requests/{id}/retry`) yang mengatur ulang status penolakan supir terdahulu.

---

## 2. Temuan Bug & Perbaikan Logika (Bug & Logic Fixes)

Selama proses pengujian E2E, ditemukan 2 bug/anomali logika yang berpotensi menyebabkan error di lingkungan produksi. Seluruhnya telah diperbaiki langsung dan dikonfirmasi lulus uji regresi:

| No | Lokasi | Masalah / Bug | Dampak | Perbaikan yang Diterapkan |
|---|---|---|---|---|
| **1** | `app/Http/Controllers/Api/V1/UserAddressController.php` | Type-hint `UserAddress $userAddress` pada method `destroy` belum mengimpor namespace `App\Models\UserAddress`. | Terjadi PHP `ReflectionException: Class "App\Http\Controllers\Api\V1\UserAddress" does not exist` saat rute DELETE dipanggil (HTTP 500). | Menambahkan pernyataan impor `use App\Models\UserAddress;` pada controller. |
| **2** | `app/Http/Controllers/Api/V1/MedicalProfileController.php` | Pengambilan profil medis menggunakan dynamic property `$request->user()->medicalProfile`. Eloquent melakukan *caching in-memory* terhadap relasi yang bernilai `null`. | Jika user memeriksa profil medis yang masih kosong lalu menyimpannya dalam sesi yang sama, pemanggilan GET berikutnya tetap mengembalikan `null` akibat cache relasi model. | Mengubah pemanggilan menjadi `$request->user()->medicalProfile()->first()` untuk menjamin query selalu mengambil data terbaru dari database. |

---

## 3. Hasil Pengujian Rinci per Tugas (7 Tasks)

### Task 1: Detail Driver, Ambulans, RS, dan ETA pada Search Response
- **Endpoint:** `GET /api/v1/emergency-requests/{id}` dan `GET /api/v1/emergency-requests/current`
- **Hasil Pengujian:**
  - Saat request masih berstatus `searching_driver`, field `driver`, `ambulance`, `hospital`, dan `eta` bernilai `null`.
  - Begitu order diterima supir (`driver_profile_id` terisi), response langsung memuat:
    - `driver`: nama, nomor telepon, foto, rating, dan jumlah trip.
    - `ambulance`: nomor plat, tipe armada (*ALS/BLS*), dan koordinat latitude & longitude supir terkini tanpa pembulatan untuk pelacakan peta.
    - `hospital`: nama rumah sakit rujukan.
    - `eta`: estimasi waktu tempuh dalam menit (`DispatchLog::drivingMinutes()`) dan jarak tempuh dalam km (`DriverProfile::distanceKm()`).

### Task 2: Pembatalan Setelah Supir Menerima Order (dengan Alasan)
- **Endpoint:** `POST /api/v1/emergency-requests/{id}/cancel`
- **Hasil Pengujian:**
  - Pasien dapat membatalkan pesanan saat status `searching_driver`, `driver_assigned`, atau `en_route_to_pickup` dengan menyertakan payload `reason` atau `cancel_reason`.
  - Kolom `cancel_reason` dan `cancelled_at` tersimpan rapi pada database.
  - Supir yang sebelumnya berstatus `busy` otomatis dikembalikan ke status `online` (`DriverAvailability::Online`) agar dapat menerima panggilan lain.
  - **Integrasi Layar Driver:** Pemanggilan `GET /api/v1/driver/trip` oleh supir yang ordernya baru dibatalkan mengembalikan objek request berstatus `cancelled` lengkap dengan `cancel_reason`. Hal ini memungkinkan aplikasi supir menutup layar navigasi dan menampilkan pemberitahuan kepada pengemudi.
  - Begitu status mencapai `arrive_at_pickup` atau `en_route_to_hospital`, upaya pembatalan ditolak tegas dengan kode **HTTP 409 Conflict**.

### Task 3: Tab Riwayat Pasien (`GET /api/v1/emergency-requests`)
- **Endpoint:** `GET /api/v1/emergency-requests`
- **Hasil Pengujian:**
  - Mengembalikan daftar riwayat panggilan milik pengguna yang sedang login, diurutkan dari yang paling baru (maksimal 50 entri).
  - Setiap item riwayat menyediakan field lengkap: `id`, `code`, `status`, `emergency_type`, `pickup_address`, `pickup` (object), `request_at`, `hospital` (`{ name }`), `ambulance` (`{ plate_number }`), `driver` (`{ name }`), serta `cancel_reason`.
  - Didaftarkan sebelum rute binding `{emergencyRequest}` untuk mencegah ambigu URL.

### Task 4: Sinkronisasi Profil Rekam Medis (Medical ID)
- **Endpoint:** `GET /api/v1/me/medical-profile` & `PUT /api/v1/me/medical-profile`
- **Hasil Pengujian:**
  - `GET` mengembalikan `{"data": null}` jika pengguna belum mengisi Medical ID.
  - `PUT` mendukung format array JSON maupun string dipisahkan koma (*resilient input*) untuk `allergies` dan `medical_conditions`.
  - `blood_type` kini berupa string bebas (mendukung golongan darah rhesus seperti `O+`, `A-`, `B+`, `AB-`).
  - `gender` bersifat opsional (nullable).
  - Menyimpan metrik fisik (`weight_kg`, `height_cm`), kontak darurat (`name`, `relation`, `phone`), asuransi, dan izin bagikan saat SOS (`share_on_sos`).
  - Response menyertakan kalkulasi otomatis `age` (usia dalam tahun) berdasarkan tanggal lahir.

### Task 5: Manajemen Alamat Darurat Pengguna
- **Endpoint:** `GET /api/v1/me/addresses`, `POST /api/v1/me/addresses`, `DELETE /api/v1/me/addresses/{id}`
- **Hasil Pengujian:**
  - Menyimpan lokasi penting pengguna (*Rumah*, *Kantor*, *Lainnya*) beserta alamat teks dan koordinat presisi.
  - `POST` mengembalikan status HTTP 201 Created dengan UUID alamat baru.
  - `DELETE` memungkinkan penghapusan alamat oleh pemiliknya.

### Task 6: Ubah Profil Pengguna
- **Endpoint:** `PUT /api/v1/me` (serta alias kompatibilitas `PUT /api/v1/auth/me`)
- **Hasil Pengujian:**
  - Memvalidasi dan memperbarui `full_name` dan `phone`.
  - Mengembalikan format response yang identik dengan `GET /api/v1/auth/me` (`UserResource`).

### Task 7: Pencarian Bertingkat Berdasarkan Radius (3 → 25 km) & "Cari Lagi"
- **Logika Dispatch:**
  - Permintaan baru dimulai dengan radius 3 km. Driver yang berada di luar 3 km (misal: 4.2 km) tidak akan menerima dering panggilan.
  - Setiap 90 detik saat polling terjadi melalui `redispatchWaiting()`, radius otomatis melebar: `3 km -> 5 km -> 10 km -> 15 km -> 25 km`.
  - Pasien dapat mempercepat ekspansi secara instan tanpa menunggu 90 detik melalui endpoint `POST /api/v1/emergency-requests/{id}/radius`.
  - Pada radius 25 km, jika seluruh driver di area tersebut telah menolak atau waktu habis, server menetapkan `search_exhausted = true` dan mencatat `search_exhausted_at`.
  - Tombol *"Cari Lagi"* (`POST /api/v1/emergency-requests/{id}/retry`) mereset status pencarian ke 25 km, menghapus log riwayat penolakan supir, dan langsung menawarkan kembali panggilan ke armada di area tersebut.
  - Radius tampilan ambulans pada peta pencarian dinaikkan dari 10 km menjadi 25 km (`NEARBY_RADIUS_KM = 25`).

---

## 4. File Tes Baru yang Dibuat

1. **[`tests/Feature/EndToEnd/EmergencyFlowE2ETest.php`](file:///d:/Proj/PHP/CariAmbulan-server/tests/Feature/EndToEnd/EmergencyFlowE2ETest.php)**:
   - Pengujian E2E alur profil, alamat, dan rekam medis lengkap.
   - Pengujian E2E siklus dispatch ambulans, ekspansi radius 90 detik, penerimaan tugas, pelacakan koordinat real-time, dan pembatalan dengan alasan.
   - Pengujian E2E pencegahan pembatalan pasca kedatangan dan penyelesaian rujukan ke rumah sakit.
   - Pengujian E2E kehabisan armada pada 25 km dan alur "Cari Lagi" (retry).
   - Pengujian edge cases mobile app (input string koma, alias rute `/auth/me`, dan variasi penamaan payload).
2. **[`tests/Feature/Http/Controllers/Api/V1/UserApiEnhancementsTest.php`](file:///d:/Proj/PHP/CariAmbulan-server/tests/Feature/Http/Controllers/Api/V1/UserApiEnhancementsTest.php)**:
   - 15 pengujian unit dan fitur untuk masing-masing item endpoint secara terisolasi.

---

## 5. Kesimpulan & Rekomendasi untuk Tim Aplikasi Mobile

Semua fitur backend kini telah siap 100% dan terverifikasi stabil.
- **User App**: Dapat langsung beralih dari penyimpanan lokal (*fallback*) ke pemanggilan endpoint server untuk Medical ID, Alamat Tersimpan, Edit Profil, Pembatalan Berbayar/Beralasan, Riwayat, serta tombol *Cari Lagi*.
- **Driver App**: Tangani status `cancelled` pada saat polling `GET /api/v1/driver/trip` untuk menutup layar penjemputan dan menampilkan alasan pembatalan dari pasien.

