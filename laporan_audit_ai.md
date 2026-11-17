# LAPORAN PERBAIKAN KONFIGURASI & KECERDASAN AI ASSISTANT
**Platform LaundryKu (Marketplace Laundry)**  
*Dokumen Laporan Akhir Pasca-Implementasi & Verifikasi*

---

## 1. Kondisi Awal & Ringkasan Temuan
Pada audit awal, ditemukan bahwa asisten AI:
1. **Katalog Statis & Halusinasi Layanan**: Menggunakan teks statis hardcoded pada `AiAssistantService.php` (baris 219-224) tanpa menyertakan varian harga riil toko dari tabel `services`. Jika ditanya harga spesifik toko, AI tidak memiliki data harga sebenarnya.
2. **Cakupan Tabel Terbatas**: Belum menghubungkan tabel `services` (katalog harga riil per toko), ringkasan `whatsapp_logs` (monitoring notifikasi staf), dan `notifications` (notifikasi in-app pengguna login).
3. **Inkonsistensi Zona Waktu**: Definisi rentang waktu "hari ini" berbeda antara `AiDataProvider` (`CURDATE()`) dan `MetricsService` (`DateTime` timezone `Asia/Jakarta`).

---

## 2. Audit Cakupan Database (`marketplace_laundry`)
Berdasarkan query schema `INFORMATION_SCHEMA.COLUMNS`:

| Nama Tabel | Status Akses AI | Klasifikasi Sensitivitas | Filter & Mekanisme Pengamanan |
| :--- | :---: | :---: | :--- |
| **`services`** | **Terhubung** | Publik / Non-Sensitif | Allowlist kolom: `id`, `name`, `price`, `unit`, `duration_hours`, `store_name`. Scoped: `store_id` untuk staf toko / toko terkait, platform untuk owner/admin_owner. |
| **`whatsapp_logs`** | **Terhubung (Ringkasan)** | **Sensitif Parsial** | Hanya untuk `owner`, `admin_owner`, dan `admin_toko` (`admin_toko` difilter `store_id`). **Eksklusi ketat**: `provider_response` mentah dan nomor telepon privat. Hanya menyajikan agregasi status (`sent`, `failed`, `test_sent`) & 5 jenis event terakhir. Ditolak untuk customer/kurir. |
| **`notifications`** | **Terhubung** | **Sensitif User** | Hanya membaca notifikasi milik user yang sedang login (`WHERE user_id = :uid`). Allowlist: `title`, `message`, `is_read`, `type`, `created_at`. |
| **`orders`** | Terhubung | Operasional Bisnis | Scoped role & `store_id`. Prepared statement. Bebas kolom credentials. |
| **`order_items`** | Terhubung via Relasi | Operasional Bisnis | Rincian item pesanan customer terangkum dalam pesanan aktif. |
| **`order_status_histories`** | Terhubung via Model | Operasional Bisnis | Riwayat status pesanan aktif. |
| **`stores`** | Terhubung | Publik / Agregat | Nama toko, rating, dan status aktif. Scoped `store_id` untuk toko. |
| **`users`** | Terhubung (Allowlist) | **Sangat Sensitif** | Eksklusi ketat: `password`, `google_id`, `remember_token`, `otp`. Hanya nama, role, email, dan alamat user yang login. |
| **`roles`** | Terhubung | Publik Sistem | Penerjemahan role user. |
| **`refunds`** | Terhubung | Keuangan / Operasional | Agregasi refund pending & completed. Scoped role & `store_id`. |
| **`payments`** | Terhubung | Keuangan / Operasional | Jumlah pembayaran kedaluwarsa hari ini & pesanan belum lunas. |
| **`payment_events` / `logs`** | **Tidak Terhubung** | **Sangat Sensitif** | Dikecualikan sepenuhnya demi privasi payload gateway & IP. |
| **`whatsapp_settings`** | **Tidak Terhubung** | **Sangat Sensitif** | Dikecualikan sepenuhnya (memuat API token/credentials Fonnte). |
| **`courier_locations`** | Terhubung via Status | Operasional Kurir | Hanya dibaca melalui status tugas kurir aktif (pickup/delivery). |
| **`addresses`** | Terhubung | Sensitif User | Hanya alamat utama milik user yang login. |
| **`ai_chat_messages`** | Terhubung | Riwayat Percakapan | Scoped `WHERE user_id = :uid`. |

---

## 3. Daftar File yang Diubah & Alasannya

Sebelum pengeditan, file asli telah dicadangkan ke direktori `backup_ai_fix/`.

1. **[app/Services/Ai/AiDataProvider.php](file:///c:/xampp/htdocs/rpl/app/Services/Ai/AiDataProvider.php)**
   - *Alasan*: 
     - Menambahkan method `getServicesCatalog(?int $storeId = null)` untuk mengambil daftar layanan aktif dan harga riil dari tabel `services`.
     - Menambahkan method `getWhatsAppLogsSummary(int $userId, string $role, ?int $storeId = null)` untuk menyajikan ringkasan notifikasi WhatsApp yang aman (tanpa `provider_response` mentah) khusus untuk owner, admin_owner, dan admin_toko.
     - Menambahkan method `getUserNotifications(int $userId, int $limit = 5)` untuk mengambil riwayat notifikasi user yang login.
     - Menyeragamkan filter tanggal `hari_ini`, `kemarin`, `terakhir_7_hari`, dan `bulan_ini` pada `getOrderStats()`, `getRevenueStats()`, dan `getTop3StoresThisMonth()` menggunakan rentang `DateTime` bersama zona waktu `Asia/Jakarta` via `MetricsService::getTodayRange()` dan `MetricsService::getMonthRange()`.
2. **[app/Services/Ai/AiAssistantService.php](file:///c:/xampp/htdocs/rpl/app/Services/Ai/AiAssistantService.php)**
   - *Alasan*:
     - Menghapus katalog layanan statis/hardcoded dan menggantinya dengan pemanggilan dinamis `getServicesCatalog()` yang difilter per toko/role di dalam blok `[KATALOG_LAYANAN_START] ... [KATALOG_LAYANAN_END]`.
     - Jika toko belum memiliki layanan, menyuntikkan keterangan *"Data layanan belum tersedia di database."*
     - Menyuntikkan blok `[NOTIFIKASI_USER_START]` untuk notifikasi user login.
     - Menyuntikkan ringkasan log WhatsApp ke dalam `[DATA_OPERASIONAL_START]` khusus staf yang berwenang.
3. **[config/ai.php](file:///c:/xampp/htdocs/rpl/config/ai.php)**
   - *Alasan*: Menambahkan aturan guardrail ketat anti-halusinasi: untuk harga, nama layanan, dan status, AI hanya boleh mengambil data resmi dari database; jika tidak ditemukan, wajib menjawab *"Data tidak ditemukan di database."*
4. **[app/Services/Ai/MockAiClient.php](file:///c:/xampp/htdocs/rpl/app/Services/Ai/MockAiClient.php)**
   - *Alasan*: Menyesuaikan deterministic mock client agar menguji dan merespons pertanyaan harga/layanan berdasarkan blok dinamis `[KATALOG_LAYANAN_START]`, mendeteksi ketiadaan layanan, dan menjawab *"Data tidak ditemukan di database"* bila layanan yang ditanyakan tidak terdaftar.

---

## 4. Lokasi File Backup
Seluruh file sebelum modifikasi tersimpan di:
- `backup_ai_fix/app/Services/Ai/AiDataProvider.php`
- `backup_ai_fix/app/Services/Ai/AiAssistantService.php`
- `backup_ai_fix/app/Services/Ai/MetricsService.php`
- `backup_ai_fix/app/Services/Ai/MockAiClient.php`
- `backup_ai_fix/config/ai.php`

---

## 5. Hasil Pengujian & Verifikasi

### A. Hasil Test Suite Baru: `tests/test_ai_expanded_intelligence.php` (22 PASS, 0 FAIL)
- `[PASS]` System prompt admin toko 1 memuat Cuci Komplit Reguler Rp 6.000
- `[PASS]` System prompt admin toko 1 memuat Dry Cleaning Jas Rp 35.000
- `[PASS]` System prompt admin toko 1 TIDAK memuat layanan toko 2 (Cuci Kering Lipat Hemat)
- `[PASS]` Query harga layanan dieksekusi sukses
- `[PASS]` Jawaban AI memuat harga 6.000 sesuai tabel services
- `[PASS]` Katalog layanan toko tanpa layanan kosong
- `[PASS]` System prompt toko kosong menyatakan data layanan belum tersedia di database
- `[PASS]` System prompt toko kosong TIDAK memuat katalog layanan hardcoded umum
- `[PASS]` Jawaban AI untuk toko tanpa layanan menyatakan data tidak ditemukan / belum tersedia di database
- `[PASS]` Admin toko 1 berhasil mendapatkan ringkasan log WA untuk tokonya
- `[PASS]` Cakupan WA summary admin toko adalah 'toko'
- `[PASS]` System prompt admin toko 1 memuat ringkasan WA toko
- `[PASS]` System prompt admin toko 1 TIDAK memuat ringkasan WA platform
- `[PASS]` Prompt tidak pernah memuat kolom provider_response
- `[PASS]` Prompt tidak pernah memuat access_token
- `[PASS]` Prompt tidak pernah memuat kolom password
- `[PASS]` Prompt tidak pernah memuat remember_token
- `[PASS]` Prompt tidak pernah memuat google_id
- `[PASS]` getWhatsAppLogsSummary mengembalikan null untuk role customer
- `[PASS]` System prompt customer TIDAK memuat blok log WhatsApp
- `[PASS]` System prompt customer TIDAK memuat data operasional
- `[PASS]` Database 100% bersih kembali tanpa menyisakan data sampah uji

### B. Hasil Pengujian Manual: `tests/manual_verification_ai.php`
Menggunakan `MockAiClient`:
1. **Q1 (Harga Layanan Cuci Reguler - Toko 1)**:  
   *Query DB*: `SELECT name, price, unit FROM services WHERE store_id = 1 AND name LIKE '%Cuci Komplit Reguler%'` -> `{"name":"Cuci Komplit Reguler","price":"6000.00","unit":"kg"}`  
   *Jawaban AI*: Memuat daftar resmi database: Cuci Komplit Reguler Rp 6.000/kg.
2. **Q2 (Omzet Toko 1 Hari Ini)**:  
   *Query DB*: `SELECT COALESCE(SUM(total_amount), 0) as omset ... WHERE store_id = 1 AND payment_status = 'paid' ...` -> `{"omset":"0.00"}`  
   *Jawaban AI*: "Omzet Terbayar Hari Ini: Rp0."
3. **Q3 (Pesanan Platform Hari Ini)**:  
   *Query DB*: `SELECT COUNT(*) as total FROM orders WHERE created_at >= CURDATE() ...` -> `{"total":0}`  
   *Jawaban AI*: "Jumlah Pesanan Hari Ini: 0 pesanan."
4. **Q4 (Toko Aktif Platform)**:  
   *Query DB*: `SELECT COUNT(*) as total FROM stores WHERE is_active = 1` -> `{"total":2}`  
   *Jawaban AI*: "Toko Laundry Aktif: 2 toko aktif."
5. **Q5 (Profil Pengguna Login - Customer)**:  
   *Query DB*: `SELECT id, name, email FROM users WHERE id = 6` -> `{"id":6,"name":"syfa","email":"customer@laundry.test"}`  
   *Jawaban AI*: "Anda terdaftar sebagai syfa, dengan peran Customer di LaundryKu."
6. **Q6 (Layanan Sengaja Tidak Ada - Cuci Sepatu)**:  
   *Query DB*: `SELECT * FROM services WHERE store_id = 1 AND name LIKE '%sepatu%'` -> `[] (0 baris)`  
   *Jawaban AI*: "Data tidak ditemukan di database untuk layanan tersebut."
7. **Q7 (Akses Dilarang - Customer tanya Omzet Platform)**:  
   *Jawaban AI*: "Mohon maaf, akun Anda tidak memiliki akses ke data pesanan atau operasional tersebut."
8. **Q8 (Toko Kosong Tanpa Layanan)**:  
   *Query DB*: `SELECT * FROM services WHERE store_id = [emptyStore]` -> `[] (0 baris)`  
   *Jawaban AI*: "Data tidak ditemukan di database. Toko ini belum memiliki daftar layanan aktif."

### C. Hasil Pengujian Non-Regresi Suite Lama
- `tests/test_ai_assistant_module.php`: **116 PASS, 0 FAIL**
- `tests/test_ai_database_intelligence.php`: **31 PASS, 0 FAIL**
- `tests/test_whatsapp_notifications.php`: **85 PASS, 0 FAIL**

---

## 6. Hal yang Harus Dilakukan Manual
1. **Pengisian Layanan Toko**: Untuk toko/mitra baru yang baru terdaftar, pastikan menginput daftar layanan di panel outlet agar AI langsung dapat membacanya.
2. **Kredensial API**: Konfigurasi token API Gemini pada `.env` tetap aman dan aktif.

---

## 7. Risiko yang Tersisa & Mitigasi
1. **Panjang Konteks Token Prompt**: Jika sebuah outlet memiliki puluhan jenis layanan, blok prompt akan semakin panjang.  
   *Mitigasi*: Layanan difilter hanya yang berstatus aktif (`is_active = 1`) dan diurutkan berdasarkan harga. Jika di masa depan melebihi 20 layanan, dapat ditambahkan pagination/limit top services.
2. **Keamanan Data**: Semua query menggunakan PDO prepared statements, allowlist kolom eksplisit, dan isolasi role/store_id yang ketat. Kolom sensitif tidak pernah diekspos ke AI prompt.
