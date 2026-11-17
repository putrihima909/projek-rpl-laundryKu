# ACTIVITY DIAGRAM: OWNER & ADMIN OWNER FLOW

Diagram aktivitas ini menggambarkan proses pengawasan eksekutif, audit keuangan, dan manajemen konfigurasi platform oleh **Owner** dan **Admin Owner**.

```mermaid
flowchart TD
    Start([Mulai: Owner / Admin Owner Login]) --> Dashboard[Buka Dashboard Owner /dashboard/owner]
    Dashboard --> SelectAction{Pilih Modul Pengawasan}

    SelectAction -- Analitik Omzet & Performa --> ViewOmzet[Lihat Kartu Metrik Global: Total Omzet, Pesanan, Toko Aktif]
    ViewOmzet --> OmzetDrillDown[Buka Detail Transparansi Omzet /dashboard/omzet-detail]
    OmzetDrillDown --> AuditFormula[Periksa Komponen: Pesanan Lunas dikurangi Refund]

    SelectAction -- Audit Refund Finansial --> OpenRefunds[Buka Menu Manajemen Refund /refunds]
    OpenRefunds --> ReviewRefund{Tinjau Permohonan Refund}
    ReviewRefund -- Tolak --> RejectRefund[Klik Tolak & Tulis Catatan Alasan]
    ReviewRefund -- Setujui --> ApproveRefund[Klik Setujui: Event Tercatat Tak Terhapus di payment_events]

    SelectAction -- Manajemen Mitra Toko --> StoreMgmt[Buka Kelola Toko /stores/manage]
    StoreMgmt --> VerifyStore[Verifikasi Outlet Baru / Toggle Status Aktif Gerai]

    SelectAction -- Konfigurasi AI Platform --> AiSettings[Buka Pengaturan AI /admin/ai-settings]
    AiSettings --> MultiModelPool[Kelola Model AI di Failover Pool: Groq / Gemini / OpenAI]
    MultiModelPool --> TestLatency[Uji Latensi Tes Koneksi AI]
    MultiModelPool --> RevealApiKey[Lihat Kunci API Tersimpan dengan Verifikasi Password Akun]

    RejectRefund --> End([Selesai])
    ApproveRefund --> End
    VerifyStore --> End
    RevealApiKey --> End
```
