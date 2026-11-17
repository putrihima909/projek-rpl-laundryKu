# NASKAH & STRUKTUR REKAMAN VIDEO DEMO APLIKASI (YOUTUBE)
## LaundryKu - Marketplace Laundry Berbasis Web dengan Integrasi AI

---

## 🔗 Informasi Tautan Video
- **Link Video YouTube**: `[LINK YOUTUBE]` — [PERLU DIISI]
- **Durasi Video Rekomendasi**: 10 – 15 Menit
- **Resolusi Video**: 1080p Full HD (60 fps disarankan)

---

## 🎬 Susunan Timeline & Panduan Naskah Presentasi

| Bagian | Durasi | Tampilan Layar (Screen Recording) | Panduan Naskah Suara (Voice-Over / Presenter) |
|:---|:---:|:---|:---|
| **1. Opening** | 00:00 - 00:30 | Bumper / Slide Judul Proyek "LaundryKu: Marketplace Laundry Berbasis Web dengan Integrasi AI" | *"Halo semuanya! Selamat datang di video presentasi dan demonstrasi proyek Rekayasa Perangkat Lunak kami yang berjudul LaundryKu: Marketplace Laundry Berbasis Web dengan Integrasi AI."* |
| **2. Identitas Kelompok** | 00:30 - 01:00 | Slide nama, NIM, foto anggota, kelompok, dan kelas mahasiswa | *"Proyek ini dikembangkan oleh kelompok kami yang beranggotakan: 1. [NAMA 1] - [NIM 1], 2. [NAMA 2] - [NIM 2], dan 3. [NAMA 3] - [NIM 3] dari kelas [KELAS] program studi [PRODI] [UNIVERSITAS]."* |
| **3. Penjelasan Masalah** | 01:00 - 02:00 | Ilustrasi nota hilang, antrean laundry menumpuk, kurir tersesat | *"Kami mengangkat permasalahan umum pada UMKM laundry konvensional, di mana pencatatan masih menggunakan nota manual, pelanggan tidak tahu status cucian dan posisi kurir, serta kasir terbebani menghitung omzet secara manual."* |
| **4. Penjelasan Aplikasi** | 02:00 - 03:00 | Tampilan Landing Page interaktif LaundryKu | *"LaundryKu hadir sebagai solusi marketplace multi-tenant yang mendigitalisasi alur bisnis laundry dari order, timbangan, pembayaran QRIS, GPS tracking kurir, notifikasi WhatsApp otomatis, hingga asisten AI SmartBot."* |
| **5. Login & Google OAuth** | 03:00 - 04:00 | Halaman `/login` & klik tombol login Google | *"Sistem kami mendukung 6 role pengguna dengan Role-Based Access Control ketat, serta kemudahan masuk menggunakan Google OAuth 2.0 untuk pelanggan."* |
| **6. Dashboard Overview** | 04:00 - 05:00 | Menampilkan variasi dashboard per role | *"Setiap role memiliki dashboard yang terisolasi. Pelanggan melihat riwayat pesanannya, kasir melihat antrean cucian, kurir melihat tugas pickup, dan pemilik melihat ringkasan omzet."* |
| **7. Customer Flow & Order** | 05:00 - 06:30 | Buka `/stores`, pilih toko CleanMaster, isi form order | *"Mari kita simulasikan pelanggan yang memesan jasa cuci express. Pelanggan memilih alamat GPS penjemputan dan memilih paket layanan kiloan."* |
| **8. Courier Pickup & Peta** | 06:30 - 07:30 | Login kurir, toggle online, peta Leaflet live GPS | *"Kurir Budi menerima tugas pickup. Dengan panduan peta Leaflet, kurir menuju lokasi pelanggan dan mengubah status menjadi 'Cucian Dijemput' hingga 'Tiba di Gerai'."* |
| **9. Timbangan Kasir & Invoice**| 07:30 - 08:30 | Login kasir, buka pesanan, input berat 4.5 kg | *"Setelah pakaian tiba di gerai, kasir menimbang cucian dan memasukkan berat aktual. Total tagihan terhitung otomatis dan invoice diterbitkan seketika."* |
| **10. Pembayaran QRIS Dinamis** | 08:30 - 09:30 | Buka halaman QRIS SVG, klik simulasi bayar | *"Pelanggan membuka invoice dan melakukan pembayaran via QRIS dinamis. Webhook secara instan mengonfirmasi pembayaran lunas (paid) tanpa perlu upload bukti transfer manual."* |
| **11. SmartBot AI Demo** | 09:30 - 11:00 | Buka chat widget, tanya bahan sutra, cek status pesanan, dan buka panel model pool | *"Inilah fitur unggulan kami: SmartBot AI. Pelanggan dapat menanyakan cara merawat bahan sutra atau menanyakan status pesanannya. AI dibekali guardrail ketat sehingga tidak dapat membatalkan pesanan fiktif. Di panel admin, tersedia Multi-Model Failover Pool yang mendukung Groq dan Gemini dengan API key terenkripsi AES-256."* |
| **12. Cuci Selesai & Delivery** | 11:00 - 12:00 | Kasir klik ready, kurir klik delivered | *"Setelah pakaian selesai dicuci, kasir menandai status Ready. Kurir mengantarkan pakaian bersih kembali ke rumah pelanggan hingga pesanan tuntas (Delivered)."* |
| **13. Detail Omzet & Transparansi**| 12:00 - 13:00 | Buka `/dashboard/omzet-detail` & modal rumus | *"Kasir dan pemilik toko dapat melihat rincian omzet kasir terbayar hari ini, lengkap dengan modal transparansi formula matematika omzet dan tombol kirim laporan ke WhatsApp pemilik."* |
| **14. Owner Platform & Refund**| 13:00 - 14:00 | Buka `/dashboard/owner` dan menu `/refunds` | *"Pemilik platform dapat memantau performa seluruh gerai laundry mitra di seluruh Indonesia dan menyetujui pengembalian dana refund dengan pencatatan audit tak terhapus."* |
| **15. Kesimpulan & Penutup** | 14:00 - 15:00 | Slide kesimpulan & ucapan terima kasih | *"Demikian demonstrasi proyek UAS Rekayasa Perangkat Lunak aplikasi LaundryKu. Sistem ini terbukti handal, aman, dan siap menjawab tantangan digitalisasi UMKM laundry. Terima kasih!"* |

---

## 📋 Checklist Persiapan Sebelum Merekam Layar
1. Pastikan server Apache dan MySQL di XAMPP sudah berjalan (*Running*).
2. Bersihkan tab browser yang tidak relevan agar tampilan video rapi dan profesional.
3. Siapkan microphone dengan kualitas audio jernih tanpa noise latar belakang.
4. Gunakan software perekam layar seperti **OBS Studio** atau **Camtasia**.
5. Upload video ke YouTube dengan visibilitas **Public** atau **Unlisted**, lalu salin link URL-nya ke berkas `README.md` dan `docs/VIDEO_DEMO.md`.
