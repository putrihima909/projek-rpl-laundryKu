# SEQUENCE DIAGRAM: PAYMENT & WEBHOOK GATEWAY

Diagram sekuens ini menggambarkan interaksi teknis pembuatan QRIS, simulasi pembayaran, validasi HMAC signature webhook, dan pencatatan audit finansial.

```mermaid
sequenceDiagram
    autonumber
    actor Customer as Pelanggan
    participant Browser as Browser Client
    participant PayCtrl as PaymentController
    participant PaySvc as PaymentService
    participant Gateway as MockPaymentGateway
    participant WebhookCtrl as PaymentWebhookController
    participant DB as Database MySQL

    Customer->>Browser: Klik tombol "Bayar Sekarang (QRIS)"
    Browser->>PayCtrl: GET /payments/qris?order_id=12
    PayCtrl->>PaySvc: getOrCreatePayment(order_id=12)
    PaySvc->>DB: SELECT * FROM payments WHERE order_id = 12
    PaySvc->>Gateway: createCharge(amount, order_code)
    Gateway-->>PaySvc: QRIS SVG Data & Transaction Token
    PayCtrl-->>Browser: Render Halaman QRIS & Tombol Simulasi Bayar

    Note over Customer,Browser: Pelanggan Scan QRIS / Klik Simulasi Pembayaran

    Gateway->>WebhookCtrl: POST /webhook/payment (Payload JSON + Header x-gateway-signature)
    WebhookCtrl->>PaySvc: verifyAndProcessWebhook(payload, signature)
    PaySvc->>PaySvc: Validasi HMAC-SHA256 Signature dengan PAYMENT_MOCK_SECRET
    alt Signature Tidak Cocok
        PaySvc-->>WebhookCtrl: False (Signature Invalid)
        WebhookCtrl-->>Gateway: HTTP 403 Forbidden
    else Signature Valid
        PaySvc->>DB: SELECT status FROM payments WHERE id = X FOR UPDATE
        alt Status Sudah 'paid'
            PaySvc-->>WebhookCtrl: True (Idempotent: Abaikan request duplikat)
        else Status 'pending'
            PaySvc->>DB: UPDATE payments SET status = 'paid', paid_at = NOW()
            PaySvc->>DB: UPDATE orders SET payment_status = 'paid', status = 'processing'
            PaySvc->>DB: INSERT INTO payment_events (payment_id, event_type='payment_confirmed')
            PaySvc-->>WebhookCtrl: True (Sukses Diperbarui)
        end
        WebhookCtrl-->>Gateway: HTTP 200 OK { status: 'success' }
    end

    loop Polling Status per 3 Detik
        Browser->>PayCtrl: GET /payments/check-status?payment_id=X
        PayCtrl->>DB: SELECT status FROM payments WHERE id = X
        DB-->>PayCtrl: status = 'paid'
        PayCtrl-->>Browser: JSON { is_paid: true, redirect_url: '/orders/detail?id=12' }
    end

    Browser-->>Customer: Tampilkan Halaman Pesanan Lunas & Sedang Diproses
```
