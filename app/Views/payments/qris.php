<?php
$showSidebar = true;
$baseUrl = config('app.url', 'http://localhost/rpl/public');
$isPaid = ($payment['status'] ?? '') === \App\Models\Payment::STATUS_PAID || ($order['payment_status'] ?? '') === 'paid';
$isExpired = ($payment['status'] ?? '') === \App\Models\Payment::STATUS_EXPIRED || ($remainingSeconds <= 0 && !$isPaid);
?>

<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center pb-3 mb-4 border-bottom">
        <div>
            <div class="d-flex align-items-center gap-2 mb-2">
                <span class="badge bg-danger text-white px-3 py-1 fw-bold"><i class="fa-solid fa-qrcode me-1"></i> QRIS ONLINE PAYMENT</span>
                <?php if ($isPaid): ?>
                    <span class="badge bg-success rounded-pill px-3 py-1 fw-bold"><i class="fa-solid fa-circle-check me-1"></i> LUNAS TERVERIFIKASI</span>
                <?php elseif ($isExpired): ?>
                    <span class="badge bg-danger rounded-pill px-3 py-1 fw-bold"><i class="fa-solid fa-circle-xmark me-1"></i> KADALUARSA</span>
                <?php else: ?>
                    <span class="badge bg-warning text-dark rounded-pill px-3 py-1 fw-bold"><i class="fa-solid fa-hourglass-half me-1"></i> MENUNGGU PEMBAYARAN</span>
                <?php endif; ?>
            </div>
            <h2 class="fw-bold text-dark mb-1">Pembayaran Pesanan #<?= htmlspecialchars($order['order_code']) ?></h2>
            <p class="text-muted mb-0">Selesaikan transaksi menggunakan QRIS (Quick Response Code Indonesian Standard).</p>
        </div>
        <div class="mt-3 mt-md-0 d-flex gap-2">
            <a href="<?= $baseUrl ?>/orders/detail?id=<?= $order['id'] ?>" class="btn btn-outline-secondary rounded-pill btn-sm">
                <i class="fa-solid fa-arrow-left me-1"></i> Kembali ke Detail Pesanan
            </a>
        </div>
    </div>

    <!-- Success Banner if already paid -->
    <?php if ($isPaid): ?>
        <div class="alert alert-success border-0 shadow-sm rounded-4 p-4 mb-4 d-flex align-items-center gap-3">
            <div class="bg-success text-white p-3 rounded-circle fs-2">
                <i class="fa-solid fa-circle-check"></i>
            </div>
            <div>
                <h4 class="fw-bold mb-1">Pembayaran Telah Dikonfirmasi Lunas!</h4>
                <p class="mb-0 text-muted">
                    Transaksi dengan kode referensi <code><?= htmlspecialchars($payment['gateway_reference'] ?? '-') ?></code> berhasil diverifikasi via Payment Gateway Webhook.
                    Cucian Anda sekarang dalam proses pengerjaan.
                </p>
                <div class="mt-2">
                    <a href="<?= $baseUrl ?>/orders/detail?id=<?= $order['id'] ?>" class="btn btn-sm btn-success rounded-pill px-3 fw-bold">
                        <i class="fa-solid fa-file-invoice me-1"></i> Lihat Status Pesanan
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="row g-4 justify-content-center">
        <!-- Left Column: Official QRIS Stand Display -->
        <div class="col-lg-5 col-xl-4">
            <div class="card border-0 shadow-sm rounded-4 text-center overflow-hidden position-relative">
                <!-- QRIS Official Header Style -->
                <div class="bg-white p-3 border-bottom d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-danger text-white fs-6 px-2 py-1 fw-black" style="letter-spacing: 1px;">QRIS</span>
                        <small class="text-muted fw-bold" style="font-size: 0.75rem;">PEMBAYARAN NASIONAL</small>
                    </div>
                    <span class="badge bg-light text-dark border font-monospace" style="font-size: 0.7rem;">GPN / ASPI</span>
                </div>

                <div class="card-body p-4 bg-light bg-opacity-50">
                    <h5 class="fw-bold text-dark mb-0">LaundryKu</h5>
                    <small class="text-muted d-block mb-3">NMID: <code>ID1020304050607</code></small>

                    <!-- QR Code Frame Container -->
                    <div class="p-3 bg-white rounded-4 shadow-sm d-inline-block border position-relative" style="min-width: 250px; min-height: 250px;">
                        <div id="qris-qrcode" class="d-flex justify-content-center align-items-center" style="width: 220px; height: 220px; margin: 0 auto;"></div>

                        <?php if ($isPaid): ?>
                            <div class="position-absolute top-0 start-0 w-100 h-100 bg-white bg-opacity-90 rounded-4 d-flex flex-column align-items-center justify-content-center">
                                <i class="fa-solid fa-circle-check text-success display-3 mb-2"></i>
                                <span class="badge bg-success fs-6 px-3 py-1 rounded-pill fw-bold">LUNAS</span>
                            </div>
                        <?php elseif ($isExpired): ?>
                            <div class="position-absolute top-0 start-0 w-100 h-100 bg-white bg-opacity-90 rounded-4 d-flex flex-column align-items-center justify-content-center">
                                <i class="fa-solid fa-triangle-exclamation text-danger display-4 mb-2"></i>
                                <span class="badge bg-danger fs-6 px-3 py-1 rounded-pill fw-bold">KADALUARSA</span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Countdown Expiration Timer -->
                    <div class="mt-3 p-2 bg-white rounded-pill border d-inline-flex align-items-center gap-2 px-3 shadow-sm">
                        <i class="fa-solid fa-stopwatch text-danger"></i>
                        <small class="text-muted fw-bold">Sisa Waktu:</small>
                        <span id="countdown-timer" class="fw-bold text-danger font-monospace fs-6">00:00</span>
                    </div>

                    <!-- Total Amount Display -->
                    <div class="mt-3">
                        <small class="text-muted d-block fw-bold text-uppercase">TOTAL PEMBAYARAN</small>
                        <h2 class="fw-bold text-primary mb-0 font-monospace">
                            Rp <?= number_format($payment['amount'], 0, ',', '.') ?>
                        </h2>
                    </div>

                    <div class="mt-3 pt-3 border-top small text-muted">
                        <div>Ref: <code><?= htmlspecialchars($payment['gateway_reference'] ?? 'MOCK-QRIS') ?></code></div>
                        <div class="text-secondary" style="font-size: 0.75rem;">
                            Batas waktu: <?= date('d M Y, H:i', strtotime($payment['expired_at'])) ?> WIB
                        </div>
                    </div>

                    <?php if ($isExpired && !$isPaid): ?>
                        <div class="mt-3">
                            <a href="<?= $baseUrl ?>/orders/payment?order_id=<?= $order['id'] ?>&refresh=1" class="btn btn-warning rounded-pill px-4 fw-bold shadow-sm btn-sm">
                                <i class="fa-solid fa-rotate-right me-1"></i> Buat Ulang QRIS Baru
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="card-footer bg-white py-2.5 border-top">
                    <small class="text-muted d-flex align-items-center justify-content-center gap-1" style="font-size: 0.75rem;">
                        <i class="fa-solid fa-shield-halved text-success me-1"></i> Terenkripsi & Terverifikasi Otomatis Webhook
                    </small>
                </div>
            </div>
        </div>

        <!-- Right Column: Instructions, Order Breakdown & Tester Simulator -->
        <div class="col-lg-7 col-xl-6">
            <!-- Order Breakdown Card -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white py-3 border-0">
                    <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-receipt text-primary me-2"></i> Rincian Tagihan Laundry</h5>
                </div>
                <div class="card-body p-4 pt-0">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <tbody>
                                <tr>
                                    <td class="text-muted">Nomor Pesanan</td>
                                    <td class="text-end fw-bold font-monospace"><?= htmlspecialchars($order['order_code']) ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Toko Laundry</td>
                                    <td class="text-end fw-bold"><?= htmlspecialchars($order['store_name']) ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Layanan</td>
                                    <td class="text-end"><?= htmlspecialchars($order['service_name']) ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Berat Aktual Cucian</td>
                                    <td class="text-end font-monospace"><?= $order['actual_weight'] ?: $order['estimated_weight'] ?> kg</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Tarif Satuan</td>
                                    <td class="text-end font-monospace">Rp <?= number_format($order['service_price'], 0, ',', '.') ?> / <?= $order['service_unit'] ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Subtotal Cucian</td>
                                    <td class="text-end font-monospace">Rp <?= number_format($order['subtotal'] ?? $order['estimated_amount'], 0, ',', '.') ?></td>
                                </tr>
                                <?php if (!empty($order['additional_fee']) && (float)$order['additional_fee'] > 0): ?>
                                    <tr>
                                        <td class="text-muted">Biaya Tambahan (Treatment/Parfum)</td>
                                        <td class="text-end font-monospace text-danger">+ Rp <?= number_format($order['additional_fee'], 0, ',', '.') ?></td>
                                    </tr>
                                <?php endif; ?>
                                <?php if (!empty($order['discount']) && (float)$order['discount'] > 0): ?>
                                    <tr>
                                        <td class="text-muted">Potongan Diskon Toko</td>
                                        <td class="text-end font-monospace text-success">- Rp <?= number_format($order['discount'], 0, ',', '.') ?></td>
                                    </tr>
                                <?php endif; ?>
                                <tr class="table-primary fw-bold">
                                    <td class="fs-6 py-2">TOTAL AKHIR PEMBAYARAN</td>
                                    <td class="text-end fs-5 text-primary font-monospace py-2">
                                        Rp <?= number_format($payment['amount'], 0, ',', '.') ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- How to Pay Accordion -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white py-3 border-0">
                    <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-mobile-screen-button text-success me-2"></i> Cara Membayar dengan QRIS</h5>
                </div>
                <div class="card-body p-4 pt-0">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="p-3 bg-light rounded-3 h-100 text-center">
                                <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width: 40px; height: 40px;">
                                    <strong>1</strong>
                                </div>
                                <h6 class="fw-bold mb-1">Buka Aplikasi</h6>
                                <small class="text-muted">Buka m-Banking (BCA, Mandiri, BRI, BNI) atau E-Wallet (GoPay, OVO, DANA, ShopeePay).</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-3 bg-light rounded-3 h-100 text-center">
                                <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width: 40px; height: 40px;">
                                    <strong>2</strong>
                                </div>
                                <h6 class="fw-bold mb-1">Scan QR Code</h6>
                                <small class="text-muted">Pilih menu <strong>Bayar / QRIS</strong>, lalu arahkan kamera ke kode QR di layar.</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-3 bg-light rounded-3 h-100 text-center">
                                <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width: 40px; height: 40px;">
                                    <strong>3</strong>
                                </div>
                                <h6 class="fw-bold mb-1">Konfirmasi</h6>
                                <small class="text-muted">Periksa nama merchant dan nominal, lalu masukkan PIN. Halaman ini akan mendeteksi otomatis!</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Mock Payment Gateway Simulator (Developer & Testing Sandbox) -->
            <div class="card border-0 shadow-sm rounded-4 mb-4 border-start border-4 border-warning bg-white">
                <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                    <div>
                        <span class="badge bg-warning text-dark px-2.5 py-1 fw-bold mb-1">DEVELOPMENT / TESTER SANDBOX</span>
                        <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-flask-vial text-warning me-2"></i> Simulator Webhook Payment Gateway</h5>
                    </div>
                    <span class="badge bg-light text-secondary border">Behavior Menyerupai Gateway Nyata</span>
                </div>
                <div class="card-body p-4 pt-1">
                    <p class="small text-muted mb-3">
                        Gunakan tombol di bawah untuk menguji integrasi Webhook server-side sesuai skenario pengujian yang diwajibkan:
                    </p>

                    <div class="row g-2">
                        <div class="col-sm-6">
                            <button type="button" class="btn btn-outline-success btn-sm w-100 rounded-pill fw-bold text-start p-2.5" onclick="triggerSimulator('success')">
                                <i class="fa-solid fa-circle-check text-success me-1"></i> 1. Webhook Sukses (Valid HMAC)
                            </button>
                        </div>
                        <div class="col-sm-6">
                            <button type="button" class="btn btn-outline-danger btn-sm w-100 rounded-pill fw-bold text-start p-2.5" onclick="triggerSimulator('failed')">
                                <i class="fa-solid fa-circle-xmark text-danger me-1"></i> 2. Webhook Gagal (Status Failed)
                            </button>
                        </div>
                        <div class="col-sm-6">
                            <button type="button" class="btn btn-outline-secondary btn-sm w-100 rounded-pill fw-bold text-start p-2.5" onclick="triggerSimulator('expired')">
                                <i class="fa-solid fa-stopwatch text-secondary me-1"></i> 3. Webhook Kadaluarsa (Expired)
                            </button>
                        </div>
                        <div class="col-sm-6">
                            <button type="button" class="btn btn-outline-warning text-dark btn-sm w-100 rounded-pill fw-bold text-start p-2.5" onclick="triggerSimulator('duplicate')">
                                <i class="fa-solid fa-copy text-warning me-1"></i> 4. Webhook Duplikat (Idempotency)
                            </button>
                        </div>
                        <div class="col-sm-6">
                            <button type="button" class="btn btn-outline-danger btn-sm w-100 rounded-pill fw-bold text-start p-2.5" onclick="triggerSimulator('amount_mismatch')">
                                <i class="fa-solid fa-scale-unbalanced text-danger me-1"></i> 5. Beda Nominal (Amount Mismatch 400)
                            </button>
                        </div>
                        <div class="col-sm-6">
                            <button type="button" class="btn btn-outline-dark btn-sm w-100 rounded-pill fw-bold text-start p-2.5" onclick="triggerSimulator('invalid_signature')">
                                <i class="fa-solid fa-shield-virus text-danger me-1"></i> 6. Signature Salah (Unauthorized 401)
                            </button>
                        </div>
                    </div>

                    <!-- Simulator Live Console Output -->
                    <div id="sim-result-box" class="mt-3 p-3 bg-dark text-light rounded-3 font-monospace small" style="display: none; max-height: 200px; overflow-y: auto;">
                        <div class="d-flex justify-content-between text-muted border-bottom pb-1 mb-2">
                            <span><i class="fa-solid fa-terminal me-1"></i> Simulator Log Output</span>
                            <span id="sim-status-code" class="badge bg-secondary">HTTP ---</span>
                        </div>
                        <pre id="sim-output-content" class="mb-0 text-info" style="white-space: pre-wrap; font-size: 0.75rem;"></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Pop-up Pembayaran Berhasil -->
<div class="modal fade" id="paymentSuccessModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 text-center p-4">
            <div class="my-3">
                <div class="bg-success text-white rounded-circle d-inline-flex align-items-center justify-content-center shadow" style="width: 80px; height: 80px;">
                    <i class="fa-solid fa-check fs-1"></i>
                </div>
            </div>
            <h3 class="fw-bold text-dark mb-1">Pembayaran Berhasil!</h3>
            <p class="text-muted mb-4">
                Pembayaran QRIS sebesar <strong>Rp <?= number_format($payment['amount'], 0, ',', '.') ?></strong> telah berhasil diverifikasi oleh sistem. Cucian Anda sedang diproses oleh toko.
            </p>
            <div>
                <a href="<?= $baseUrl ?>/orders/detail?id=<?= $order['id'] ?>" class="btn btn-success rounded-pill px-5 fw-bold shadow-sm">
                    Lihat Status Cucian <i class="fa-solid fa-arrow-right ms-1"></i>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Load QRCode.js library for rendering authentic QRIS string -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<script>
const ORDER_ID = <?= (int)$order['id'] ?>;
const BASE_URL = '<?= $baseUrl ?>';
const QR_STRING = <?= json_encode($payment['qr_string'] ?? '00020101021226630014ID.GO.QRIS.WWW01189360099900000100010215ID10203040506075204599953033605802ID5909LAUNDRYKU6015JAKARTA SELATAN6304') ?>;
let remainingSeconds = <?= (int)$remainingSeconds ?>;
let isAlreadyPaid = <?= $isPaid ? 'true' : 'false' ?>;

// 1. Render QR Code
function renderQrCode() {
    const container = document.getElementById('qris-qrcode');
    if (!container) return;
    container.innerHTML = '';

    if (typeof QRCode !== 'undefined') {
        new QRCode(container, {
            text: QR_STRING,
            width: 210,
            height: 210,
            colorDark: "#111827",
            colorLight: "#ffffff",
            correctLevel: QRCode.CorrectLevel.M
        });
    } else {
        // Fallback generator using visual standard SVG
        container.innerHTML = `<img src="https://api.qrserver.com/v1/create-qr-code/?size=210x210&data=${encodeURIComponent(QR_STRING)}" alt="QRIS QR Code" class="img-fluid rounded-3 border" style="width: 210px; height: 210px;">`;
    }
}

// 2. Countdown Timer
function updateTimer() {
    const timerElem = document.getElementById('countdown-timer');
    if (!timerElem) return;

    if (isAlreadyPaid) {
        timerElem.innerText = 'LUNAS';
        timerElem.className = 'fw-bold text-success font-monospace fs-6';
        return;
    }

    if (remainingSeconds <= 0) {
        timerElem.innerText = '00:00 (HABIS)';
        timerElem.className = 'fw-bold text-danger font-monospace fs-6';
        return;
    }

    const minutes = Math.floor(remainingSeconds / 60);
    const seconds = remainingSeconds % 60;
    timerElem.innerText = String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');

    if (remainingSeconds < 300) {
        timerElem.className = 'fw-bold text-danger font-monospace fs-6 animate-pulse';
    } else if (remainingSeconds < 900) {
        timerElem.className = 'fw-bold text-warning font-monospace fs-6';
    }

    remainingSeconds--;
}

// 3. Periodic Status Polling (Every 3 seconds)
let pollingInterval = null;
function checkPaymentStatus() {
    if (isAlreadyPaid) return;

    fetch(`${BASE_URL}/api/payment/status?order_id=${ORDER_ID}`)
        .then(res => res.json())
        .then(data => {
            if (data.is_paid) {
                isAlreadyPaid = true;
                clearInterval(pollingInterval);

                // Show Success Modal
                const modal = new bootstrap.Modal(document.getElementById('paymentSuccessModal'));
                modal.show();

                // Auto redirect after 3 seconds
                setTimeout(() => {
                    window.location.href = `${BASE_URL}/orders/detail?id=${ORDER_ID}`;
                }, 3000);
            }
        })
        .catch(err => console.log('Polling check status:', err));
}

// 4. Trigger Webhook Simulator (Sandbox Testing)
function triggerSimulator(mode) {
    const resultBox = document.getElementById('sim-result-box');
    const statusBadge = document.getElementById('sim-status-code');
    const outputElem = document.getElementById('sim-output-content');

    resultBox.style.display = 'block';
    statusBadge.className = 'badge bg-secondary';
    statusBadge.innerText = 'Sending...';
    outputElem.innerText = 'Dispatching webhook simulation to server...';

    const formData = new FormData();
    formData.append('order_id', ORDER_ID);
    formData.append('mode', mode);

    fetch(`${BASE_URL}/payment/mock-gateway/simulate`, {
        method: 'POST',
        body: formData
    })
    .then(async response => {
        const data = await response.json();
        statusBadge.innerText = 'HTTP ' + response.status;
        if (response.status >= 200 && response.status < 300) {
            statusBadge.className = 'badge bg-success';
        } else if (response.status === 400 || response.status === 404) {
            statusBadge.className = 'badge bg-warning text-dark';
        } else {
            statusBadge.className = 'badge bg-danger';
        }

        outputElem.innerText = JSON.stringify(data, null, 2);

        // If success simulation was triggered, status polling will detect it in 1-2s!
        if (mode === 'success' || mode === 'duplicate') {
            checkPaymentStatus();
        }
    })
    .catch(err => {
        statusBadge.className = 'badge bg-danger';
        statusBadge.innerText = 'ERROR';
        outputElem.innerText = String(err);
    });
}

document.addEventListener('DOMContentLoaded', () => {
    renderQrCode();
    updateTimer();
    setInterval(updateTimer, 1000);

    if (!isAlreadyPaid) {
        pollingInterval = setInterval(checkPaymentStatus, 3000);
    }
});
</script>
