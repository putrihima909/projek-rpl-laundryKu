# LAPORAN TUGAS BESAR / PENGUMPULAN AKHIR UAS
## REKAYASA PERANGKAT LUNAK (RPL)

---

# SISTEM INFORMASI MARKETPLACE LAUNDRY MULTI-TENANT BERBASIS WEB DENGAN INTEGRASI ARTIFICIAL INTELLIGENCE, DYNAMIC QRIS, DAN LIVE TRACKING GPS (LAUNDRYKU)

---

**Disusun Oleh:**

| Nama Mahasiswa | NIM | Peran dalam Proyek |
|:---|:---:|:---|
| **[NAMA MAHASISWA 1] — [PERLU DIISI]** | **[NIM 1] — [PERLU DIISI]** | Project Lead & Full-Stack Developer |
| **[NAMA MAHASISWA 2] — [PERLU DIISI]** | **[NIM 2] — [PERLU DIISI]** | Backend & Database Architect |
| **[NAMA MAHASISWA 3] — [PERLU DIISI]** | **[NIM 3] — [PERLU DIISI]** | UI/UX Designer & QA Tester |

<br>

**KELOMPOK:** [KELOMPOK] — [PERLU DIISI]  
**KELAS:** [KELAS] — [PERLU DIISI]  
**DOSEN PENGAMPU:** [NAMA DOSEN PENGAMPU] — [PERLU DIISI]  

<br>

**PROGRAM STUDI [PROGRAM STUDI]**  
**FAKULTAS [FAKULTAS]**  
**UNIVERSITAS / INSTITUSI [UNIVERSITAS / INSTITUSI] — [PERLU DIISI]**  
**TAHUN AKADEMIK 2026/2027**

---

## KATA PENGANTAR

Puji syukur kami panjatkan ke hadirat Tuhan Yang Maha Esa atas rahmat dan karunia-Nya, sehingga laporan akhir proyek Rekayasa Perangkat Lunak (RPL) dengan judul **"Sistem Informasi Marketplace Laundry Multi-Tenant Berbasis Web dengan Integrasi Artificial Intelligence, Dynamic QRIS, dan Live Tracking GPS (LaundryKu)"** dapat diselesaikan dengan baik dan tepat waktu.

Laporan ini disusun sebagai salah satu syarat penilaian Ujian Akhir Semester (UAS) mata kuliah Rekayasa Perangkat Lunak. Proyek ini memadukan konsep rekayasa perangkat lunak modern, mulai dari pemodelan proses bisnis, *Product Requirements Document (PRD)*, *Software Design Description (SDD)*, implementasi arsitektur murni *Model-View-Controller (MVC)* dengan keamanan berlapis, hingga pengujian otomatis dan *black-box testing*.

Kami menyampaikan terima kasih yang sebesar-besarnya kepada:
1. Dosen Pengampu mata kuliah Rekayasa Perangkat Lunak atas bimbingan dan arahan akademiknya.
2. Rekan-rekan anggota kelompok atas dedikasi dan kerja sama dalam menyelesaikan proyek ini.
3. Seluruh pihak yang telah memberikan dukungan moral maupun teknis selama pengembangan sistem.

Kami menyadari bahwa laporan ini masih memiliki ruang untuk penyempurnaan. Oleh karena itu, kritik dan saran yang konstruktif sangat kami harapkan guna perbaikan di masa mendatang.

Kota [KOTA], 16 November 2026  
<br>
**Tim Pengembang LaundryKu**

---

## DAFTAR ISI

- **KATA PENGANTAR**
- **DAFTAR ISI**
- **BAB I PENDAHULUAN**
  - 1.1 Latar Belakang Masalah
  - 1.2 Identifikasi dan Rumusan Masalah
  - 1.3 Tujuan Pengembangan
  - 1.4 Manfaat Proyek
- **BAB II PRODUCT REQUIREMENTS DOCUMENT (PRD)**
  - 2.1 Latar Belakang Produk
  - 2.2 Tujuan Produk
  - 2.3 Target & Persona Pengguna
  - 2.4 Scope & Out of Scope
  - 2.5 Kebutuhan Fungsional (Functional Requirements)
  - 2.6 Kebutuhan Non-Fungsional (Non-Functional Requirements)
  - 2.7 Daftar Fitur Sistem
  - 2.8 Spesifikasi Fitur Kecerdasan Buatan (AI)
- **BAB III SOFTWARE DESIGN DESCRIPTION (SDD)**
  - 3.1 Arsitektur Sistem (3-Tier Architecture)
  - 3.2 Tumpukan Teknologi (Technology Stack)
  - 3.3 Struktur Direktori Project
  - 3.4 Desain Basis Data (25 Tabel Relational)
  - 3.5 Entity Relationship Diagram (ERD)
  - 3.6 Use Case Diagram
  - 3.7 Activity Diagram
  - 3.8 Sequence Diagram (Workflow, QRIS & AI)
  - 3.9 Class Diagram
  - 3.10 Desain Keamanan (CSRF, AES-256-GCM, BCRYPT & RBAC)
- **BAB IV IMPLEMENTASI SISTEM**
  - 4.1 Antarmuka Pengguna & Design System
  - 4.2 Modul Pelanggan (Customer)
  - 4.3 Modul Admin Toko (Kasir Outlet)
  - 4.4 Modul Owner Toko & Owner Platform
  - 4.5 Modul Kurir & Tracking GPS
  - 4.6 Modul Asisten Virtual AI (SmartBot)
  - 4.7 Modul Pembayaran Online & QRIS
  - 4.8 Modul WhatsApp Gateway & Webhook Bot
  - 4.9 Modul Transparansi Finansial & Omzet Kasir
- **BAB V PENGUJIAN PERANGKAT LUNAK (TESTING)**
  - 5.1 Metode Pengujian (Automated & Black Box Testing)
  - 5.2 Skenario Pengujian Fungsional
  - 5.3 Hasil Pengujian Black Box
  - 5.4 Pengujian Khusus Asisten AI & Guardrail
  - 5.5 Matriks Pengujian Otorisasi RBAC (6 Peran)
  - 5.6 Rekapitulasi Hasil Eksekusi Uji Otomatis
  - 5.7 Panduan Bukti Screenshot Sistem
- **BAB VI PENUTUP**
  - 6.1 Kesimpulan
  - 6.2 Saran dan Pengembangan Lanjutan
- **DAFTAR PUSTAKA**
- **LAMPIRAN**

---

## BAB I PENDAHULUAN

### 1.1 Latar Belakang Masalah
Di era transformasi digital saat ini, efisiensi operasional dan kenyamanan pelanggan merupakan faktor penentu keberhasilan industri jasa binatu (*laundry*). Sebagian besar UMKM laundry di Indonesia masih mengandalkan sistem manual: nota kertas yang rentan hilang, ketiadaan kepastian jadwal penjemputan, serta pencatatan pendapatan kasir yang rentan manipulasi atau salah hitung. Dari sisi konsumen, kesibukan harian menuntut adanya layanan laundry jemput-antar yang dapat dipantau statusnya secara real-time dari ponsel pintar.

Untuk menjawab kebutuhan tersebut, dibangunlah sistem **LaundryKu**, sebuah platform marketplace laundry multi-tenant berbasis web yang menghubungkan seluruh pemangku kepentingan dalam satu ekosistem terpadu.

### 1.2 Identifikasi dan Rumusan Masalah
Berdasarkan analisis kebutuhan lapangan, dirumuskan masalah sebagai berikut:
1. Bagaimana merancang arsitektur sistem marketplace yang menjamin isolasi data transaksi antar-toko (*multi-tenancy store isolation*)?
2. Bagaimana mengelola siklus pesanan laundry yang melibatkan peran pelanggan, kasir, kurir, dan pemilik melalui *state machine* 12 status pesanan?
3. Bagaimana mengintegrasikan asisten virtual AI berbasis LLM yang cerdas namun memiliki *guardrail* ketat sehingga tidak mengarang data transaksi finansial?
4. Bagaimana mewujudkan transparansi pembukuan omzet kasir harian dengan mempertimbangkan pemotongan refund yang sah?

### 1.3 Tujuan Pengembangan
1. Mengembangkan aplikasi web marketplace laundry dengan arsitektur Pure PHP MVC yang modular, aman, dan berkinerja tinggi.
2. Mengimplementasikan 6 level peran pengguna dengan hak akses (*RBAC*) server-side yang ketat.
3. Mengintegrasikan teknologi live GPS tracking kurir menggunakan Leaflet.js dan formula perhitungan jarak Haversine.
4. Menerapkan asisten virtual AI multi-model pool dengan proteksi enkripsi AES-256-GCM.
5. Membangun transparansi omzet kasir dan integrasi notifikasi transaksional WhatsApp otomatis.

### 1.4 Manfaat Proyek
- **Bagi Pengguna (Pelanggan)**: Kemudahan memesan laundry, kepastian biaya timbangan, dan fleksibilitas bayar QRIS.
- **Bagi Gerai Laundry**: Otomasi alur kerja kasir, penghapusan nota fisik, dan rekonsiliasi omzet instan.
- **Bagi Kurir**: Efisiensi rute penjemputan dan kemudahan pembaruan status tugas lapangan.
- **Bagi Pemilik Platform**: Kemampuan pengawasan eksekutif terhadap performa seluruh mitra cabang di Indonesia.

---

## BAB II PRODUCT REQUIREMENTS DOCUMENT (PRD)

*(Ringkasan Spesifikasi Produk dari dokumen `/docs/PRD.md`)*

### 2.1 Latar Belakang & Tujuan Produk
LaundryKu dirancang sebagai platform penghubung antara konsumen binatu dengan pemilik gerai dan kurir, mempermudah alur pemesanan, penimbangan, pembayaran digital, hingga pengantaran kembali pakaian bersih.

### 2.2 Target & Persona Pengguna
1. **Customer (Neila Barsha)**: Pengguna yang memerlukan kemudahan penjemputan cucian dan konsultasi perawatan bahan sensitif.
2. **Kasir Toko (Rina Melati)**: Operator outlet yang memerlukan kemudahan input berat timbangan dan cetak invoice tagihan digital.
3. **Kurir (Budi Santoso)**: Petugas lapangan yang memerlukan kejelasan titik penjemputan GPS.
4. **Owner Toko & Owner Platform**: Eksekutif yang memerlukan transparansi omzet dan monitoring performa bisnis.

### 2.3 Scope & Out of Scope
- **Scope**: Multi-role RBAC, pemesanan online, 12-state order machine, QRIS dinamis, pelacakan live GPS kurir, notifikasi WhatsApp gateway, asisten SmartBot AI, dan pelaporan omzet kasir.
- **Out of Scope**: Perangkat keras POS thermal printer khusus, integrasi kurir instan pihak ketiga (Gojek/Grab API), dan aplikasi native mobile store.

### 2.4 Kebutuhan Fungsional & Non-Fungsional
- **Fungsional**: Autentikasi multi-role, CRUD alamat & geocoding GPS, otomasi transisi status pesanan, pembayaran QRIS dengan webhook HMAC, permohonan refund, notifikasi WhatsApp, asisten AI chat, dan multi-model pool.
- **Non-Fungsional**: Keamanan berlapis (CSRF, AES-256-GCM, BCRYPT), waktu respons rata-rata $\le 1.2$ detik, ketersediaan 24/7, dan antarmuka responsif Bootstrap 5.3.

---

## BAB III SOFTWARE DESIGN DESCRIPTION (SDD)

*(Ringkasan Desain Arsitektur Teknis dari dokumen `/docs/SDD.md`)*

### 3.1 Arsitektur Aplikasi
Menggunakan arsitektur **3-Tier Pure PHP MVC**:
- **Presentation Tier**: Blade/PHP Views, Bootstrap 5.3, FontAwesome 6, Leaflet.js.
- **Application Tier**: Front Controller (`public/index.php`), Router Core, AuthMiddleware (RBAC), Controller, dan Service Layer.
- **Data Tier**: MySQL Database `marketplace_laundry` (25 tabel relasional dengan InnoDB engine).

### 3.2 Desain Basis Data & ERD
Terdiri dari 25 tabel relational mencakup:
- Identitas & Autentikasi: `roles`, `users`, `addresses`
- Gerai & Katalog: `stores`, `services`
- Siklus Transaksi: `orders`, `order_items`, `order_status_histories`
- Pembayaran & Finansial: `payments`, `payment_events`, `payment_logs`, `refunds`
- Pelacakan & Notifikasi: `courier_locations`, `notifications`, `whatsapp_settings`, `whatsapp_logs`
- Kecerdasan Buatan (AI): `ai_chat_messages`, `ai_settings`, `ai_settings_audit`, `ai_model_pool`, `ai_key_reveal_attempts`, `store_ai_settings`

### 3.3 Diagram UML & Alur Proses
Sistem dirancang dengan pemodelan diagram lengkap:
- **Use Case Diagram**: Memetakan interaksi 6 aktor terhadap 23 use case utama.
- **Activity Diagram**: Menggambarkan alur pelanggan, kasir, kurir, dan owner.
- **Sequence Diagram**: Memvisualisasikan alur pemesanan 12-tahap, verifikasi pembayaran QRIS idempoten, dan pemrosesan chat AI.
- **Class Diagram**: Menggambarkan relasi antar-kelas Controller, Model, Service, dan Interface.

### 3.4 Desain Keamanan Sistem
1. **CSRF Protection**: Token unik sesi divalidasi dengan `hash_equals()`.
2. **Kredensial Terenkripsi**: API key pihak ketiga disimpan terenkripsi menggunakan AES-256-GCM.
3. **Pelihatan Kunci Terproteksi**: Verifikasi password akun dan rate limit maksimal 5 kali percobaan per 10 menit.
4. **Data Isolation**: Query database dikunci menggunakan klausa `store_id` untuk mencegah eskalasi akses horizontal antar toko.

---

## BAB IV IMPLEMENTASI SISTEM

### 4.1 Tampilan Antarmuka & Design System
Antarmuka pengguna dibangun secara konsisten menggunakan palet warna primer biru `#0d6efd`, kartu membulat modern (`rounded-4`), dan tipografi bersih berbasis font *Inter*.

### 4.2 Implementasi Fitur Utama
1. **Customer Experience**: Pelanggan dapat memilih outlet terdekat, memilih layanan cuci, memilih alamat berkoordinat GPS, melacak kurir pada peta Leaflet, dan berkonsultasi seputar cucian via SmartBot AI.
2. **Admin Toko (Kasir)**: Dilengkapi layar *Ready Delivery* untuk memisahkan cucian siap diambil mandiri vs siap antar kurir, input timbangan digital, dan modal transparansi omzet kasir.
3. **Kurir**: Antarmuka mobile-friendly dengan switch dinas Online/Offline, pembaruan koordinat GPS otomatis setiap 10 detik, dan rute navigasi Leaflet.
4. **Owner Platform & Toko**: Visualisasi metrik eksekutif, audit permohonan refund finansial, dan pengawasan multi-cabang.
5. **SmartBot AI & Multi-Model Pool**: Asisten AI pintar multi-provider (Gemini, Groq, OpenAI) dengan proteksi *transaction denial*, injeksi konteks dinamis, dan enkripsi kredensial.
6. **WhatsApp Gateway**: Pengiriman notifikasi transaksional otomatis dan balasan otomatis bot berbasis kata kunci.

---

## BAB V PENGUJIAN PERANGKAT LUNAK (TESTING)

### 5.1 Metodologi & Skenario Uji
Pengujian dilakukan menggunakan dua pilar:
1. **Black Box Testing**: Pengujian menyeluruh pada form login, pemesanan, timbangan, pembayaran QRIS, kurir GPS, refund, dan laporan omzet.
2. **Automated Unit/Integration Test Suite**: 27 berkas pengujian otomatis native PHP yang memvalidasi integritas kode tanpa dependensi pihak ketiga.

### 5.2 Hasil Pengujian Black Box & AI
- Seluruh skenario inti pada form login, validasi CSRF, Google OAuth, timbangan, pembayaran QRIS, dan tracking kurir dinyatakan **PASS**.
- Pengujian modul AI membuktikan kepatuhan asisten terhadap *Transaction Denial Guard* (menolak transaksi fiktif) dan *Anti-Hallucination Guard* (tidak mengarang angka omzet).
- Kunci API terbukti aman dalam enkripsi AES-256-GCM dan terlindungi pembatasan rate limit 5x salah password.

### 5.3 Matriks RBAC
Pengujian hak akses pada 18 rute sensitif membuktikan bahwa seluruh pembatasan role berfungsi 100% sesuai matriks hak akses (*tidak ada pelanggaran IDOR atau unauthorized privilege escalation*).

---

## BAB VI PENUTUP

### 6.1 Kesimpulan
Proyek pengembangan platform **LaundryKu** telah berhasil diselesaikan dengan memenuhi seluruh kriteria fungsional dan teknis yang disyaratkan dalam kurikulum Rekayasa Perangkat Lunak:
1. Arsitektur Pure PHP MVC terbukti tangguh, terstruktur rapi, dan mudah dikembangkan.
2. Fitur *multi-tenancy store isolation* dan *12-state order machine* berhasil mendigitalisasi operasional gerai laundry secara teratur.
3. Integrasi inovatif **SmartBot AI Multi-Model Pool** memberikan keunggulan kompetitif modern dengan tetap mematuhi standar keamanan data perbankan dan privasi.
4. Sistem siap diuji coba dan didemonstrasikan dalam evaluasi akhir semester (UAS).

### 6.2 Saran dan Pengembangan Lanjutan
1. Integrasi hardware timbangan digital IoT berbasis Bluetooth atau serial port untuk pembacaan berat otomatis ke form web.
2. Penambahan integrasi payment gateway produksi (Xendit / Midtrans live) untuk transaksi perbankan nyata skala komersial.
3. Pengembangan Progressive Web App (PWA) untuk memberikan pengalaman instalasi layaknya aplikasi mobile native pada kurir.

---

## DAFTAR PUSTAKA

1. Pressman, R. S., & Maxim, B. R. (2020). *Software Engineering: A Practitioner's Approach* (9th ed.). McGraw-Hill Education.
2. Sommerville, I. (2016). *Software Engineering* (10th ed.). Pearson.
3. Fowler, M. (2018). *Patterns of Enterprise Application Architecture*. Addison-Wesley.
4. The PHP Group. (2026). *PHP 8.1 / 8.2 Documentation & Standard PHP Library (SPL)*. https://www.php.net/docs.php
5. Bootstrap Team. (2026). *Bootstrap v5.3 Documentation*. https://getbootstrap.com/docs/5.3/
6. Google AI Studio. (2026). *Gemini API Developer Documentation*. https://ai.google.dev/docs
7. Fonnte. (2026). *Fonnte WhatsApp API Gateway Documentation*. https://fonnte.com/docs

---

## LAMPIRAN
- **Lampiran 1**: Berkas Kriteria Penerimaan MVP (`/ACCEPTANCE_CRITERIA.md`)
- **Lampiran 2**: Product Requirements Document (`/docs/PRD.md`)
- **Lampiran 3**: Software Design Description (`/docs/SDD.md`)
- **Lampiran 4**: Laporan Pengujian Lengkap (`/docs/LAPORAN_PENGUJIAN.md`)
- **Lampiran 5**: Dokumentasi UI/UX & Figma (`/docs/UIUX.md`)
- **Lampiran 6**: Checklist Bukti Screenshot (`/docs/SCREENSHOT_CHECKLIST.md`)
- **Lampiran 7**: Naskah Video Presentasi YouTube (`/docs/VIDEO_DEMO.md`)
- **Lampiran 8**: Berkas Diagram UML & ERD (`/docs/DIAGRAM/`)
