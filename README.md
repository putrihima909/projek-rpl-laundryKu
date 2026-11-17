# LaundryKu - Marketplace Laundry Berbasis Web dengan Integrasi Multi-Model AI

[![PHP Version](https://img.shields.io/badge/PHP-8.1%20%7C%208.2-777bb4.svg?style=flat-square&logo=php)](https://www.php.net/)
[![Database](https://img.shields.io/badge/MySQL-8.0%20%2F%20MariaDB-4479a1.svg?style=flat-square&logo=mysql)](https://www.mysql.com/)
[![Architecture](https://img.shields.io/badge/Architecture-Pure%20MVC%20PSR--4-orange.svg?style=flat-square)](https://www.php-fig.org/psr/psr-4/)
[![CSS Framework](https://img.shields.io/badge/UI-Bootstrap%205.3-7952b3.svg?style=flat-square&logo=bootstrap)](https://getbootstrap.com/)
[![Test Suite](https://img.shields.io/badge/Automated%20Tests-981%20Passing-brightgreen.svg?style=flat-square)](#-eksekusi-pengujian-otomatis-automated-testing)
[![License](https://img.shields.io/badge/License-Academic%20Use%20Only-blue.svg?style=flat-square)](#19-catatan--lisensi-akademik)

> **Repository Resmi Pengumpulan Tugas Akhir / Ujian Akhir Semester (UAS) Rekayasa Perangkat Lunak (RPL).**

---

## 📋 DAFTAR ISI
1. [Nama Project](#1-nama-project)
2. [Identitas Anggota Kelompok](#2-identitas-anggota-kelompok)
3. [Nomor Induk Mahasiswa (NIM)](#3-nomor-induk-mahasiswa-nim)
4. [Kelompok & Kelas](#4-kelompok--kelas)
5. [Tema Project](#5-tema-project)
6. [Deskripsi Aplikasi](#6-deskripsi-aplikasi)
7. [Aktor / Pengguna Sistem (6 Role RBAC)](#7-aktor--pengguna-sistem-6-role-rbac)
8. [Fitur Utama Aplikasi](#8-fitur-utama-aplikasi)
9. [Fitur AI (SmartBot Assistant & Failover Pool)](#9-fitur-ai-smartbot-assistant--failover-pool)
10. [Teknologi yang Digunakan](#10-teknologi-yang-digunakan)
11. [Basis Data & Skema Relasional](#11-basis-data--skema-relasional)
12. [Panduan Instalasi Project](#12-panduan-instalasi-project)
13. [Konfigurasi Lingkungan (.env)](#13-konfigurasi-lingkungan-env)
14. [Cara Menjalankan Aplikasi](#14-cara-menjalankan-aplikasi)
15. [Akun Demo Pengujian](#15-akun-demo-pengujian)
16. [Video Demo Aplikasi (YouTube)](#16-video-demo-aplikasi-youtube)
17. [Struktur Folder & Direktori](#17-struktur-folder--direktori)
18. [Tangkapan Layar & Demo Visual (Screenshot)](#18-tangkapan-layar--demo-visual-screenshot)
19. [Catatan & Lisensi Akademik](#19-catatan--lisensi-akademik)

---

## 1. Nama Project
* **Nama Resmi Aplikasi:** **LaundryKu**
* **Nama Komersial / Tampilan Antarmuka:** **SmartLaundry Pro / WashMart**
* **Kategori Perangkat Lunak:** Multi-Tenant Web Marketplace Laundry & Logistics Management System with Artificial Intelligence Integration.

---

## 2. Identitas Anggota Kelompok
* **Anggota 1:** [NAMA MAHASISWA 1] — **[PERLU DIISI]**
* **Anggota 2:** [NAMA MAHASISWA 2] — **[PERLU DIISI]**
* **Anggota 3:** [NAMA MAHASISWA 3] — **[PERLU DIISI]**

---

## 3. Nomor Induk Mahasiswa (NIM)
* **NIM Anggota 1:** [NIM MAHASISWA 1] — **[PERLU DIISI]**
* **NIM Anggota 2:** [NIM MAHASISWA 2] — **[PERLU DIISI]**
* **NIM Anggota 3:** [NIM MAHASISWA 3] — **[PERLU DIISI]**

---

## 4. Kelompok & Kelas
* **Nama / Nomor Kelompok:** [KELOMPOK] — **[PERLU DIISI]**
* **Kelas:** [KELAS] — **[PERLU DIISI]**
* **Program Studi:** [PROGRAM STUDI] — **[PERLU DIISI]**
* **Fakultas / Jurusan:** [FAKULTAS] — **[PERLU DIISI]**
* **Institusi / Perguruan Tinggi:** [UNIVERSITAS] — **[PERLU DIISI]**
* **Dosen Pengampu:** [NAMA DOSEN PENGAMPU] — **[PERLU DIISI]**

---

## 5. Tema Project
> **Marketplace Laundry Berbasis Web dengan Integrasi Multi-Model AI, Multi-Tenancy Store Isolation, Live Tracking Kurir, WhatsApp Notification Gateway, dan Dynamic Payment QRIS.**

---

## 6. Deskripsi Aplikasi

### A. Latar Belakang
Usaha binatu (*laundry*) skala UMKM menghadapi tantangan klasik: antrean penimbangan manual yang lambat, kesulitan pelanggan mengetahui estimasi waktu pengerjaan dan lokasi kurir, ketiadaan notifikasi otomatis, serta ketidakjelasan perhitungan omzet kasir harian. Pelanggan juga kerap kesulitan memilih outlet terdekat dengan harga dan reputasi yang terpercaya.

### B. Masalah yang Diselesaikan
1. **Bagi Pelanggan:** Mengeliminasi kebutuhan datang langsung ke outlet hanya untuk cek harga atau antrean; pelanggan dapat memilih gerai terdekat berdasarkan geolokasi GPS, memesan layanan kiloan/satuan, membayar instan via QRIS, dan melacak cucian secara real-time.
2. **Bagi Pengelola Toko (Kasir & Owner Toko):** Menggantikan buku nota kertas dengan sistem digital: kasir menimbang cucian dan tagihan terhitung otomatis, notifikasi WhatsApp terkirim ke pelanggan tanpa ketik manual, dan omzet terhitung presisi tanpa risiko kebocoran data antar-toko (*store isolation*).
3. **Bagi Kurir:** Mengatur penugasan penjemputan (*pickup*) dan pengantaran (*delivery*) terintegrasi peta GPS Leaflet OSM dengan toggle dinas online/offline yang responsif di ponsel.
4. **Bagi Administrator Platform:** Menyediakan dashboard analitik terpadu untuk memantau performa mitra, mengontrol komisi platform, mengelola klaim *refund*, serta memusatkan konfigurasi AI multi-model.

### C. Tujuan Aplikasi
Membangun platform perangkat lunak berbasis web arsitektur *Pure MVC Clean Code* yang aman, andal, mudah digunakan, dan terintegrasi dengan kecerdasan buatan (LLM) untuk mendampingi pelanggan serta staf operasional selama 24 jam penuh.

### D. Target Pengguna
* **Pelanggan Umum / Rumah Tangga / Mahasiswa (`customer`)**
* **Operator Kasir Gerai Laundry (`admin_toko`)**
* **Pemilik Usaha Gerai Laundry Mitra (`owner_toko`)**
* **Petugas Penjemput & Pengantar Pakaian (`kurir`)**
* **Staf Manajemen Operasional Platform (`admin_owner`)**
* **Super Administrator / Pemilik Platform (`owner`)**

### E. Cara Kerja Aplikasi
1. Pelanggan membuka web, mencari outlet terdekat melalui pencarian berbasis formula Haversine, lalu membuat pesanan (*order*).
2. Kasir toko menerima order dan menugaskan kurir standby. Kurir menerima rincian alamat GPS dan menjemput pakaian kotor.
3. Pakaian tiba di outlet, kasir menimbang berat aktual cucian pada sistem, dan invoice tagihan diterbitkan.
4. Notifikasi WhatsApp otomatis terkirim ke nomor pelanggan disertai tautan bayar QRIS dinamis.
5. Pelanggan memindai QRIS SVG di layar. Webhook gateway memvalidasi pembayaran dan mengubah status menjadi `paid`.
6. Cucian diproses melalui tahapan cuci (*washing*), pengeringan (*drying*), dan setrika (*ironing*).
7. Kurir mengantarkan cucian bersih ke rumah pelanggan, mengunggah bukti serah terima, dan menyelesaikan pesanan (*delivered*).
8. Pelanggan dapat bertanya mengenai tips merawat kain atau mengecek status cucian kapan saja melalui asisten cerdas **SmartBot AI**.

### F. Manfaat Aplikasi
* Transparansi penuh status cucian dan penghitungan harga berdasarkan timbangan riil.
* Efisiensi waktu dan peningkatan produktivitas operasional outlet.
* Mencegah kecurangan kasir dengan pencatatan audit log permanen.
* Menyediakan customer service 24/7 otomatis bertenaga AI tanpa beban biaya staf manual.

---

## 7. Aktor / Pengguna Sistem (6 Role RBAC)

Aplikasi menerapkan **Role-Based Access Control (RBAC)** berbasis server-side middleware (`AuthMiddleware.php`) dengan 6 peran pengguna:

| No | Role Key | Nama Peran | Deskripsi Wewenang & Tanggung Jawab | Dashboard URI |
|:--:|:---|:---|:---|:---|
| 1 | `customer` | **Pelanggan (Customer)** | Mencari toko laundry terdekat, memilih layanan (kiloan/satuan), membuat pesanan, membayar via QRIS, melacak kurir via GPS, berkonsultasi dengan SmartBot AI, memberi rating/review, dan mengajukan refund. | `/dashboard/customer` |
| 2 | `admin_toko` | **Kasir / Operator Outlet** | Menerima pesanan masuk, menimbang cucian fisik dan menginput berat ke sistem, mengubah tahapan cuci, menugaskan kurir, mengelola operasional siap ambil/antar, dan monitoring omzet kasir harian. | `/dashboard/admin-toko` |
| 3 | `owner_toko` | **Pemilik Mitra Toko** | Memantau grafik omzet toko, mengelola katalog layanan dan harga cabang, mengelola akun staf kasir, mengirim rekap omzet ke WhatsApp pemilik, dan mengatur chatbot outlet. | `/dashboard/owner-toko` |
| 4 | `kurir` | **Kurir Logistik** | Mengaktifkan status dinas (Online/Offline), menerima tugas penjemputan dan pengantaran, navigasi peta GPS Leaflet, konfirmasi ambil cucian, dan upload foto bukti penyerahan. | `/dashboard/kurir` |
| 5 | `admin_owner` | **Staf Operasional Platform**| Verifikasi toko mitra baru, audit transaksi pengguna, peninjauan dan persetujuan klaim pengembalian dana (*refund*), serta pengawasan gateway notifikasi massal. | `/dashboard/admin-owner` |
| 6 | `owner` | **Super Administrator Platform**| Memantau analitik omzet global seluruh cabang di Indonesia, pengaturan komisi platform, pusat konfigurasi multi-model AI, pengelolaan API key rahasia, dan audit keamanan sistem. | `/dashboard/owner` |

---

## 8. Fitur Utama Aplikasi

### A. Marketplace Multi-Outlet & Geolocation Haversine
* Pencarian toko berbasis radius jarak real-time dari titik koordinat GPS pelanggan menggunakan kalkulasi matematika **Formula Haversine**.
* Filter katalog berdasarkan rating tertinggi, layanan kiloan/satuan, durasi express, dan status buka/tutup toko.

### B. Siklus Hidup Pesanan 12 Status (Order State Machine)
Alur status pesanan terstruktur ketat mematuhi mesin status (*state machine*) dengan pencatatan audit log di tabel `order_status_logs`:
$$\text{Pending Pickup} \rightarrow \text{Courier Assigned} \rightarrow \text{Picked Up} \rightarrow \text{At Store} \rightarrow \text{Weighed} \rightarrow \text{Waiting Payment} \rightarrow \text{Paid} \rightarrow \text{Washing} \rightarrow \text{Drying} \rightarrow \text{Ironing} \rightarrow \text{Ready Delivery} \rightarrow \text{Delivered}$$

### C. Pembayaran Digital QRIS Dinamis & Webhook Gateway
* Pembuatan gambar visual **QRIS Standar Resmi Indonesia (GPN/BI)** berbasis SVG dinamis untuk setiap kode invoice.
* Countdown timer batas pembayaran 15 menit.
* Endpoint Webhook `/webhook/payment` dengan verifikasi tanda tangan digital **HMAC SHA-256** dan proteksi **idempotensi** (mencegah *double-charge*).
* Tombol simulasi pembayaran instan untuk pengujian lokal di lingkungan XAMPP.

### D. Modul Logistik Kurir & Live Tracking Peta GPS
* Sakelar toggle dinas Online/Offline kurir.
* Integrasi peta interaktif **Leaflet.js & OpenStreetMap** tanpa biaya API pihak ketiga.
* Endpoint pembaruan lokasi kurir `/courier/update-location` yang dipolling secara asynchronous.

### E. Manajemen Transparansi Keuangan & Omzet Kasir
* Transparansi rumus omzet kasir terbayar:
  $$\text{Omzet Bersih} = \sum (\text{Pesanan Lunas}) - \text{Refund Disetujui}$$
* Modal transparansi asal-usul data dan tombol kirim laporan omzet otomatis ke nomor WhatsApp pemilik toko.

### F. Keamanan Transaksi & Isolasi Multi-Tenant
* **Store Isolation:** Admin/Kasir Toko A dijamin tidak dapat melihat atau mengubah transaksi, omzet, maupun pengaturan milik Toko B (*Anti-IDOR*).
* **Proteksi Lengkap:** Token CSRF di seluruh form POST, Prepared Statements PDO (bebas SQL Injection), dan sanitasi input XSS.

---

## 9. Fitur AI (SmartBot Assistant & Failover Pool)

Modul kecerdasan buatan dikembangkan menggunakan arsitektur adapter service berstandar `LlmClientInterface` yang adaptif dan aman:

| Komponen AI | Penjelasan Teknis & Implementasi Aktual |
|:---|:---|
| **Nama Asisten** | **SmartBot AI Virtual Assistant** |
| **Penyedia AI yang Didukung** | 1. **Google Gemini API** (`gemini-2.5-flash`, `gemini-3.5-flash-lite`, `gemini-2.5-pro`)<br>2. **GroqCloud API** (`llama-3.3-70b-versatile`, `mixtral-8x7b-32768`)<br>3. **OpenAI / OpenAI-Compatible API** (Custom Base URL / Proxy)<br>4. **Mock AI Client** (Pengujian luring/offline tanpa koneksi internet) |
| **Dynamic Context Injection** | Sistem secara dinamis menginjeksi konteks ke dalam prompt sistem: profil pengguna yang sedang login, katalog layanan toko aktif, status pesanan berjalan pelanggan (*read-only*), dan data operasional outlet. |
| **Transaction Denial Guardrail** | **AI dilarang keras mengeksekusi pembatalan pesanan atau manipulasi tagihan secara langsung**. AI wajib mengarahkan pelanggan ke tombol UI resmi di halaman aplikasi. |
| **Anti-Hallucination & Privacy** | AI dipagari agar tidak membocorkan data pribadi pelanggan lain atau omzet toko tetangga. Pertanyaan di luar ranah laundry dijawab secara santun. |
| **Prompt Injection Resistance** | Deteksi instruksi jahat (*jailbreak* / *system prompt override*) untuk melindungi data sensitif platform. |
| **Rate Limiting AI** | Pembatasan konsumsi kuota maksimal 20 pesan per pengguna per jam (mengembalikan HTTP 429 jika melebihi batas). |
| **Multi-Model Failover Pool** | Pengelola dapat mendaftarkan beberapa model cadangan di tabel `ai_model_pool`. Jika model utama mengalami gangguan kuota (HTTP 429), sistem dapat dialihkan dengan satu klik. |
| **Enkripsi Kunci API AES-256-GCM** | Kunci API disimpan dalam database dalam bentuk terenkripsi kuat **AES-256-GCM** menggunakan master key `APP_KEY`. |
| **Password-Protected Reveal Key** | Tombol mata pada form konfigurasi AI tidak menampilkan teks secara langsung; pengguna wajib memasukkan password login akun mereka. Kunci hanya tampil di layar selama **30 detik** lalu terhapus otomatis dari memori browser, dengan pembatasan maksimal 5 kali percobaan salah per 10 menit. |

---

## 10. Teknologi yang Digunakan

### Backend (Server-Side)
* **Bahasa Pemrograman:** PHP 8.1 / PHP 8.2 murni (*Pure PHP Clean Architecture*).
* **Pola Desain:** **Model-View-Controller (MVC)** dengan PSR-4 Autoloading via Composer.
* **Database Driver:** PHP Data Objects (PDO) dengan *Prepared Statements*.
* **Keamanan Kriptografi:** `openssl_encrypt` (AES-256-GCM), `password_hash` (BCRYPT), dan `hash_hmac` (SHA-256).

### Frontend (Client-Side)
* **HTML5 & CSS3:** Semantic markup, responsif mobile-first.
* **CSS Framework:** **Bootstrap 5.3.2** (Grid 12-kolom, modal, alert, dropdown).
* **Ikonografi:** **FontAwesome 6.4.0 Free Icons**.
* **Peta Digital:** **Leaflet.js 1.9.4** terintegrasi tileset **OpenStreetMap (OSM)**.
* **JavaScript:** Native Vanilla JavaScript ES6+ (AJAX Fetch API, Dynamic Stepper, Timer Countdown, DOM sanitizer).

### Layanan & Integrasi Pihak Ketiga (Third-Party Services)
* **Google Gemini AI Studio REST API:** `https://generativelanguage.googleapis.com/v1beta`
* **GroqCloud LLM REST API:** `https://api.groq.com/openai/v1`
* **Fonnte WhatsApp API Gateway:** `https://api.fonnte.com/send` & Webhook Inbound
* **Google OAuth 2.0 API:** Otentikasi single sign-on (SSO) Google Login.

---

## 11. Basis Data & Skema Relasional

Aplikasi menggunakan sistem manajemen basis data **MySQL 8.0 / MariaDB 10.4** bernama `marketplace_laundry` dengan **25 tabel relasional**:

```
                       SKEMA DATABASE RELASIONAL (25 TABEL)
┌─────────────────────────────────────────────────────────────────────────────────┐
│ [users] ────────┬────────────> [orders] ───────────┬─────────> [payments]       │
│                 │                 │                │               │            │
│                 ▼                 ▼                ▼               ▼            │
│             [stores] ───> [order_items]   [deliveries]     [payment_refunds]    │
│                 │                                  │                            │
│                 ▼                                  ▼                            │
│            [services]                       [delivery_logs]                     │
└─────────────────────────────────────────────────────────────────────────────────┘
```

1. **`users`**: Master akun seluruh peran (customer, admin_toko, owner_toko, kurir, admin_owner, owner).
2. **`stores`**: Data gerai outlet laundry mitra (nama, alamat, koordinat lat/lng, jam buka, rating).
3. **`services`**: Katalog paket layanan cuci (kiloan, satuan, dry clean, express, setrika).
4. **`service_categories`**: Kategori pengelompokan layanan binatu.
5. **`orders`**: Transaksi pemesanan cucian (nomor invoice, tanggal, estimasi & aktual berat, status).
6. **`order_items`**: Rincian pakaian satuan di dalam pesanan.
7. **`order_status_logs`**: Rekaman riwayat audit perpindahan status pesanan beserta user pengubah.
8. **`payments`**: Transaksi pembayaran digital (metode QRIS, nominal, gateway reference, status).
9. **`payment_refunds`**: Pencatatan permohonan dan audit pengembalian dana transaksi batal.
10. **`deliveries`**: Data penugasan kurir untuk penjemputan (*pickup*) dan pengantaran (*delivery*).
11. **`delivery_logs`**: Riwayat pelacakan koordinat posisi kurir saat bertugas di jalan.
12. **`reviews`**: Ulasan bintang 1–5 dan komentar pelanggan terhadap gerai laundry.
13. **`review_replies`**: Tanggapan resmi dari pemilik gerai toko atas ulasan pelanggan.
14. **`coupons`**: Master voucher diskon potongan harga (persentase / nominal rupiah).
15. **`notifications`**: Notifikasi in-app untuk pengguna di dalam sistem.
16. **`system_settings`**: Konfigurasi global platform (nama web, komisi platform, nomor kontak).
17. **`ai_conversations`**: Sesi percakapan antara pengguna dengan SmartBot AI.
18. **`ai_messages`**: Riwayat isi pesan percakapan chatbot untuk memori konteks AI.
19. **`ai_settings_audit`**: Log audit keamanan perubahan konfigurasi AI dan peristiwa pembukaan API key.
20. **`ai_model_pool`**: Daftar pool model cadangan untuk failover otomatis.
21. **`ai_key_reveal_attempts`**: Rate limiting mitigasi percobaan brute-force password pembukaan API key.
22. **`login_attempts`**: Mitigasi brute force serangan kredensial login akun.
23. **`whatsapp_logs`**: Riwayat pengiriman pesan notifikasi Fonnte WhatsApp.
24. **`whatsapp_webhook_logs`**: Catatan muatan (*payload*) pesan masuk dari webhook WhatsApp.
25. **`user_addresses`**: Buku alamat multi-lokasi pelanggan terintegrasi titik koordinat GPS.

---

## 12. Panduan Instalasi Project

### Prasyarat Sistem Minimum
* **Sistem Operasi:** Windows 10/11, Linux (Ubuntu 20.04+), atau macOS.
* **PHP:** Versi **8.1** atau **8.2** (disertakan dalam paket XAMPP).
* **Ekstensi PHP Wajib:** `pdo_mysql`, `curl`, `mbstring`, `openssl`, `json`.
* **Database Server:** MySQL 8.0+ atau MariaDB 10.4+.
* **Web Server:** Apache (XAMPP) atau PHP Built-in Server.

### Langkah-Langkah Instalasi (1 s/d 6)

#### Langkah 1: Unduh / Clone Repository
Salin repository ke direktori web server lokal Anda (`htdocs` pada XAMPP):
```powershell
cd C:\xampp\htdocs
git clone [URL_REPOSITORY_GITHUB] rpl
cd rpl
```

#### Langkah 2: Salin File Konfigurasi Lingkungan (`.env`)
Salin template konfigurasi `.env.example` menjadi `.env`:
```powershell
copy .env.example .env
```

#### Langkah 3: Setup Basis Data MySQL
1. Buka aplikasi **XAMPP Control Panel** dan klik **Start** pada modul **Apache** dan **MySQL**.
2. Buka antarmuka **phpMyAdmin** di browser (`http://localhost/phpmyadmin/`) atau buka terminal MySQL.
3. Buat basis data baru bernama **`marketplace_laundry`**:
   ```sql
   CREATE DATABASE IF NOT EXISTS marketplace_laundry CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
4. Impor skema tabel dan data awal (*seeds*):
   ```powershell
   & "C:\xampp\mysql\bin\mysql.exe" -u root marketplace_laundry < database/schema.sql
   ```

#### Langkah 4: Sesuaikan File Konfigurasi `.env`
Buka berkas `.env` menggunakan teks editor (VS Code, Notepad, dll.) dan sesuaikan kredensial database lokal Anda (lihat Bab 13).

#### Langkah 5: Instalasi Dependensi (Opsional)
Proyek ini telah dilengkapi autoloader PSR-4 bawaan. Jika Anda ingin meregenerasi autoload:
```powershell
composer dump-autoload
```

#### Langkah 6: Akses Aplikasi di Browser
Buka peramban web dan akses URL:
```
http://localhost/rpl/public/
```

---

## 13. Konfigurasi Lingkungan (.env)

Berikut adalah contoh konfigurasi variabel lingkungan yang aman pada berkas [`.env`](file:///c:/xampp/htdocs/rpl/.env). 

> [!WARNING]
> Jangan pernah memublikasikan API Key asli, token WhatsApp, atau APP_KEY ke repository publik GitHub!

```env
# ==============================================================================
# KONFIGURASI APLIKASI
# ==============================================================================
APP_NAME="LaundryKu"
APP_ENV=development
APP_DEBUG=true
APP_URL="http://localhost/rpl/public"

# Kunci master enkripsi 32 karakter (Wajib diisi untuk enkripsi AES-256-GCM)
APP_KEY="base64_random_32_characters_key_here"

# ==============================================================================
# KONFIGURASI DATABASE MYSQL
# ==============================================================================
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=marketplace_laundry
DB_USERNAME=root
DB_PASSWORD=

# ==============================================================================
# KONFIGURASI ASISTEN AI (SMARTBOT)
# Provider yang didukung: "gemini", "groqcloud", "openai", atau "mock"
# ==============================================================================
AI_PROVIDER="mock"
GEMINI_API_KEY=""
AI_MODEL="gemini-3.5-flash-lite"
AI_TEMPERATURE=0.7
AI_RATE_LIMIT=20

# Opsi GroqCloud / OpenAI Compatible (Opsional)
GROQ_API_KEY=""
AI_BASE_URL="https://api.groq.com/openai/v1"

# ==============================================================================
# KONFIGURASI NOTIFIKASI WHATSAPP GATEWAY (FONNTE)
# Provider yang didukung: "fonnte" atau "mock" (untuk pengujian lokal)
# ==============================================================================
WHATSAPP_ENABLED=true
WHATSAPP_PROVIDER=mock
WHATSAPP_API_TOKEN=""

# ==============================================================================
# KONFIGURASI PAYMENT GATEWAY & GOOGLE OAUTH
# ==============================================================================
PAYMENT_GATEWAY_SECRET="payment_mock_secret_key"
GOOGLE_CLIENT_ID=""
GOOGLE_CLIENT_SECRET=""
GOOGLE_REDIRECT_URI="http://localhost/rpl/public/auth/google/callback"
```

---

## 14. Cara Menjalankan Aplikasi

### Metode 1: Menggunakan Apache XAMPP (Sangat Direkomendasikan)
1. Letakkan folder proyek di `C:\xampp\htdocs\rpl`.
2. Buka XAMPP Control Panel, pastikan service **Apache** dan **MySQL** dalam status hijau (*Running*).
3. Buka peramban (Chrome, Edge, Firefox) dan navigasikan ke alamat:
   ```
   http://localhost/rpl/public/
   ```

### Metode 2: Menggunakan PHP Built-in Web Server
Jika tidak menggunakan Apache, Anda dapat menjalankan server internal PHP dari terminal PowerShell di dalam direktori `c:\xampp\htdocs\rpl`:
```powershell
& "C:\xampp\php\php.exe" -S localhost:8000 -t public
```
Akses di browser melalui: `http://localhost:8000`

---

## 🧪 Eksekusi Pengujian Otomatis (*Automated Testing*)

Proyek ini memiliki master runner pengujian mandiri untuk memvalidasi kesehatan seluruh modul:
```powershell
& "C:\xampp\php\php.exe" tests/run_all_tests.php
```
*Hasil eksekusi: 27 test suite dengan akumulasi **981 PASS**.*

Untuk menguji modul spesifik:
```powershell
# 1. Uji Fitur AI Assistant, Guardrail & Failover
& "C:\xampp\php\php.exe" tests/test_ai_assistant_module.php

# 2. Uji Proteksi API Key & Password Verification Reveal
& "C:\xampp\php\php.exe" tests/test_ai_key_reveal.php

# 3. Uji Modul Pembayaran QRIS & Webhook
& "C:\xampp\php\php.exe" tests/test_payment_module.php

# 4. Uji Notifikasi WhatsApp & Idempotensi
& "C:\xampp\php\php.exe" tests/test_whatsapp_notifications.php

# 5. Uji Keamanan Akun & Multi-Tenancy Store Isolation
& "C:\xampp\php\php.exe" tests/test_customer_security.php
& "C:\xampp\php\php.exe" tests/test_store_ai_settings_feature.php
```

---

## 15. Akun Demo Pengujian

Seluruh akun demo di bawah ini telah disematkan (*seeded*) pada database dan siap digunakan untuk pengujian peran (RBAC) dengan password seragam: **`password123`**.

| No | Peran / Role | Alamat Email Login | Kata Sandi | Dashboard Akses | Hak Istimewa Utama |
|:--:|:---|:---|:---:|:---|:---|
| 1 | **Customer** | `customer@laundry.test` | `password123` | `/dashboard/customer` | Pesan cuci, bayar QRIS, lacak kurir, chat AI SmartBot. |
| 2 | **Admin Toko** | `admin.toko@laundry.test` | `password123` | `/dashboard/admin-toko` | Input berat timbangan, update cucian, tugaskan kurir. |
| 3 | **Owner Toko** | `owner.toko@laundry.test` | `password123` | `/dashboard/owner-toko` | Laporan omzet cabang, kelola harga layanan cuci. |
| 4 | **Kurir** | `kurir@laundry.test` | `password123` | `/dashboard/kurir` | Toggle dinas online/offline, peta GPS, upload bukti serah. |
| 5 | **Admin Owner** | `admin.owner@laundry.test` | `password123` | `/dashboard/admin-owner` | Verifikasi mitra baru, persetujuan klaim refund. |
| 6 | **Owner Platform** | `owner@laundry.test` | `password123` | `/dashboard/owner` | Analitik omzet global, konfigurasi AI failover pool. |

---

## 16. Video Demo Aplikasi (YouTube)

Tautan video presentasi demonstrasi alur sistem aplikasi berdurasi 10–15 menit:

* **Tautan YouTube:** `[LINK YOUTUBE: URL Video Demo UAS RPL]` — **[PERLU DIISI]**
* **Naskah & Panduan Narasi Video:** Panduan alur presentasi lengkap per menit (00:00 s/d 15:00) tersedia pada dokumen [**`docs/VIDEO_DEMO.md`**](file:///c:/xampp/htdocs/rpl/docs/VIDEO_DEMO.md).

---

## 17. Struktur Folder & Direktori

```text
c:\xampp\htdocs\rpl/
├── app/
│   ├── Controllers/             # 18 Controller (Auth, Order, Courier, Payment, AI, WhatsApp, dll.)
│   ├── Core/                    # Router kustom, Controller dasar, Model dasar, Database Singleton, Middleware
│   ├── Helpers/                 # Auth, Csrf, Session, Validator, Logger, PhoneHelper, GoogleOAuth
│   ├── Models/                  # 21 Model relasional Active Record (User, Store, Service, Order, Payment, dll.)
│   ├── Services/
│   │   ├── Ai/                  # AiAssistantService, GeminiAiClient, OpenAiCompatibleClient, AiSettings
│   │   ├── Finance/             # OmzetTransparencyService
│   │   ├── Payment/             # PaymentService, MockPaymentGateway, PaymentGatewayInterface
│   │   ├── WhatsApp/            # WhatsAppNotificationService, FonnteClient, WhatsAppTemplate
│   │   └── DistanceCalculator.php # Kalkulator Geometri Haversine & Distance Matrix
│   └── Views/                   # Template antarmuka pengguna (Dashboard 6 role, Layouts, Orders, dll.)
├── config/                      # Berkas konfigurasi sistem (app, database, ai, whatsapp, payment)
├── database/                    # Skema SQL komprehensif (schema.sql), migrasi, dan seeders
├── docs/                        # DOKUMENTASI LENGKAP PENGUMPULAN UAS RPL
│   ├── PRD.md                   # Product Requirements Document (25 Bab Standar Industri)
│   ├── SDD.md                   # Software Design Description (25 Bab Spesifikasi Teknis)
│   ├── LAPORAN_PENGUJIAN.md     # Laporan Black Box Testing (7 Kategori Pengujian)
│   ├── UIUX.md                  # Spesifikasi Desain Antarmuka & Figma Ready Design
│   ├── SCREENSHOT_CHECKLIST.md  # Panduan 36 Titik Screenshot Evidence Pengujian
│   ├── VIDEO_DEMO.md            # Naskah Presentasi & Timeline Video Demo YouTube
│   ├── DOKUMEN_FINAL_UAS.md     # Kompilasi Laporan Akhir Formal Siap Cetak/PDF
│   ├── figma-assets/            # Aset Vektor SVG Komponen Figma Siap Drag-and-Drop
│   └── DIAGRAM/                 # 12 Berkas Diagram Teknis (Mermaid Syntax)
│       ├── use-case/            # Use Case Diagram sistem (6 aktor, 23 use case)
│       ├── activity/            # 4 Activity Diagram (Customer, Admin Toko, Kurir, Owner)
│       ├── sequence/            # 4 Sequence Diagram (Pesanan, Pembayaran, AI, WhatsApp)
│       ├── class/               # Class Diagram arsitektur berorientasi objek
│       ├── erd/                 # Entity Relationship Diagram (25 tabel relasional)
│       └── architecture/        # Diagram arsitektur berlapis 6-tier
├── public/                      # Public Web Document Root (index.php, css/, js/, images/, .htaccess)
├── storage/                     # Penyimpanan log audit (app.log) dan backup SQL otomatis
├── tests/                       # 27 Berkas Pengujian Otomatis & Master Runner (981 Assertions Pass)
├── .env.example                 # Template variabel lingkungan aman untuk repository
├── .gitignore                   # Proteksi pengabaian file kredensial rahasia Git
├── composer.json                # Konfigurasi PSR-4 autoloading proyek
└── README.md                    # Dokumentasi Utama Repository GitHub
```

---

## 18. Tangkapan Layar & Demo Visual (Screenshot)

Panduan detail pengambilan 36 tangkapan layar untuk laporan pengujian tersedia di berkas [**`docs/SCREENSHOT_CHECKLIST.md`**](file:///c:/xampp/htdocs/rpl/docs/SCREENSHOT_CHECKLIST.md).

Berikut adalah gambaran antarmuka utama sistem LaundryKu:

| No | Tampilan Modul / Halaman | URL Halaman | Role Pengguna | Elemen Utama yang Ditampilkan |
|:--:|:---|:---|:---:|:---|
| 1 | **Landing Page Utama** | `/` atau `/public/` | Publik (Guest) | Hero section promosi, slogan AI, katalog gerai terpopuler, navbar. |
| 2 | **Dashboard Customer** | `/dashboard/customer` | Customer | Kartu metrik pesanan aktif, shortcut order baru, floating widget SmartBot. |
| 3 | **Katalog & Jarak Toko (Haversine)** | `/stores` | Customer | Grid toko, foto outlet, rating bintang, dan kalkulasi jarak km terdekat. |
| 4 | **Form Order & Peta Penjemputan** | `/orders/create` | Customer | Stepper form, pilihan layanan cuci, input estimasi berat kg, peta koordinat GPS. |
| 5 | **Stand Pembayaran QRIS Dinamis** | `/payments/qris?order_id=X` | Customer | Kode visual QRIS SVG resmi, total tagihan, countdown waktu bayar 15 menit. |
| 6 | **Timeline Pelacakan Status 12 Tahap**| `/orders/detail?id=X` | Customer | Stepper progress vertikal pesanan, info kurir, tombol hubungi WhatsApp. |
| 7 | **Interaksi Asisten Cerdas (SmartBot)** | Floating Chat Widget | Customer / Staf | Percakapan konsultasi perawatan kain sensitif, rekomendasi paket express. |
| 8 | **Dashboard Admin Toko (Kasir)** | `/dashboard/admin-toko` | Admin Toko | Antrean cucian masuk, tombol input timbangan kg, tombol penugasan kurir. |
| 9 | **Dashboard Kurir & Peta Navigasi** | `/dashboard/kurir` | Kurir | Toggle switch dinas Online hijau, rute jalan ke rumah pelanggan, tombol ambil. |
| 10| **Pengaturan Multi-Model AI & Pool** | `/admin/store-ai-settings` | Admin Toko / Owner | Form AI utama, tombol tes latensi, modal reveal API key dengan timer 30 detik. |
| 11| **Eksekusi Test Runner CLI (981 PASS)**| Terminal PowerShell | Tester / Dev | Hasil eksekusi `php tests/run_all_tests.php` menunjukkan keberhasilan pengujian. |

---

## 19. Catatan & Lisensi Akademik

### Catatan Keamanan Repository GitHub
* Berkas rahasia [`.env`](file:///c:/xampp/htdocs/rpl/.env), berkas log di `storage/logs/`, dan berkas backup database di `storage/backups/` telah dilindungi oleh [`.gitignore`](file:///c:/xampp/htdocs/rpl/.gitignore).
* Jangan pernah menghapus tanda pagar atau memasukkan token kredensial produksi pada file `.env.example`.
* Pada lingkungan pengujian lokal (*localhost*), WhatsApp Gateway dan Payment Gateway dapat dijalankan menggunakan mode simulasi (`mock`) yang sudah tersedia secara *built-in* tanpa biaya langganan.

### Lisensi Akademik
Proyek perangkat lunak ini dikembangkan secara orisinal untuk memenuhi evaluasi capaian pembelajaran pada mata kuliah:
* **Mata Kuliah:** Rekayasa Perangkat Lunak (RPL)
* **Tahun Akademik:** 2026 / 2027
* **Lisensi Penggunaan:** *Academic Free License (AFL 3.0) / Educational Purpose Only*. Kode sumber dilindungi untuk keperluan penilaian akademik dan pembelajaran rekayasa sistem perangkat lunak.
