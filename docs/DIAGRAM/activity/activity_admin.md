# ACTIVITY DIAGRAM: ADMIN TOKO (KASIR) FLOW

Diagram aktivitas ini menggambarkan kegiatan operasional harian **Admin Toko (Kasir)** dalam mengelola pesanan masuk, penimbangan berat, pemisahan pakaian siap antar, dan pembukuan kasir.

```mermaid
flowchart TD
    Start([Mulai: Kasir Login]) --> Dashboard[Buka Dashboard Admin Toko /dashboard/admin-toko]
    Dashboard --> CheckOrders{Pilih Aktivitas}

    CheckOrders -- Pesanan Baru Masuk --> AssignCourier[Pilih Kurir Aktif & Tugaskan Penjemputan]
    AssignCourier --> WaitArrival[Menunggu Cucian Tiba di Outlet]

    CheckOrders -- Cucian Tiba di Gerai --> WeighedSection[Timbang Fisik Cucian]
    WeighedSection --> InputWeight[Input Berat Aktual di /orders/detail]
    InputWeight --> AutoCalc[Sistem Menghitung Total Biaya Otomatis]
    AutoCalc --> PublishInvoice[Terbitkan Tagihan & Kirim WhatsApp ke Pelanggan]

    CheckOrders -- Pembayaran Lunas --> ProcessLaundry[Mulai Pencucian / Setrika: Status Processing]
    ProcessLaundry --> FinishWashing[Cucian Selesai Diproses: Tandai Ready]

    CheckOrders -- Kelola Cucian Selesai --> ReadyScreen[Buka Menu Cucian Siap Diambil / Diantar]
    ReadyScreen --> SortReady{Jenis Delivery?}
    SortReady -- Diantar --> AssignDelivery[Tugaskan Kurir Antar ke Rumah]
    SortReady -- Ambil Sendiri --> WaitCustomer[Menunggu Pelanggan Ambil di Outlet]

    CheckOrders -- Tutup Shift / Laporan --> CheckOmzet[Buka Menu Detail Omzet Kasir]
    CheckOmzet --> ViewTransparency[Klik Modal Transparansi Rumus Omzet]
    ViewTransparency --> SendWA[Klik Kirim Laporan Omzet ke WhatsApp Owner]

    AssignDelivery --> End([Selesai])
    WaitCustomer --> End
    SendWA --> End
```
