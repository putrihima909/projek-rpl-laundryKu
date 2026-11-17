# SEQUENCE DIAGRAM: WHATSAPP NOTIFICATION & BOT WEBHOOK

Diagram sekuens ini menggambarkan pengiriman notifikasi transaksional otomatis, pelaporan omzet kasir harian ke WhatsApp pemilik toko, dan penanganan webhook pesan masuk bot.

```mermaid
sequenceDiagram
    autonumber
    actor Customer as Pelanggan / Owner
    participant App as Sistem LaundryKu
    participant WaSvc as WhatsAppNotificationService
    participant Fonnte as Fonnte WhatsApp API Gateway
    participant WebhookCtrl as WhatsAppWebhookController
    participant DB as Database MySQL

    Note over App,Fonnte: SKENARIO 1: Pengiriman Notifikasi Transaksional Otomatis
    App->>WaSvc: sendOrderNotification(order_id, event_type)
    WaSvc->>DB: SELECT phone FROM users WHERE id = customer_id
    WaSvc->>Fonnte: POST https://api.fonnte.com/send (target, message, token)
    Fonnte-->>WaSvc: HTTP 200 OK { status: true, id: "msg_123" }
    WaSvc->>DB: INSERT INTO whatsapp_logs (recipient, message, status='sent')
    Fonnte-->>Customer: Pesan WhatsApp Terkirim ke Smartphone Pelanggan

    Note over App,Fonnte: SKENARIO 2: Kirim Laporan Omzet Kasir Harian
    App->>WaSvc: sendOmzetReport(store_id, target_phone)
    WaSvc->>DB: Hitung omzet terbayar hari ini dari tabel orders
    WaSvc->>Fonnte: POST /send (Pesan rekapitulasi omzet lunas)
    Fonnte-->>Customer: WhatsApp Laporan Omzet Diterima oleh Pemilik Toko

    Note over Customer,WebhookCtrl: SKENARIO 3: Pelanggan Chat ke Nomor WA Bisnis Toko
    Customer->>Fonnte: Kirim pesan WA: "Halo, info harga cuci"
    Fonnte->>WebhookCtrl: POST /webhook/fonnte (sender, message)
    WebhookCtrl->>DB: SELECT response FROM store_whatsapp_bot_responses WHERE keyword LIKE '%harga%'
    DB-->>WebhookCtrl: Template Balasan Terdaftar
    WebhookCtrl->>Fonnte: POST /send (Balas otomatis daftar harga layanan)
    Fonnte-->>Customer: Pelanggan Menerima Balasan Otomatis Bot
```
