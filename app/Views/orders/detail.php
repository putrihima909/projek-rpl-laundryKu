<?php
$showSidebar = true;
$baseUrl = config('app.url', 'http://localhost/rpl/public');
$currentStatus = $order['status'] ?? 'pending';
$stInfo = $statusLabels[$currentStatus] ?? ['label' => $currentStatus, 'badge' => 'secondary'];
?>

<div class="container-fluid">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center pb-3 mb-4 border-bottom">
        <div>
            <div class="d-flex align-items-center gap-2 mb-2">
                <span class="badge bg-primary text-white px-3 py-1 fw-bold">ORDER DETAIL</span>
                <span class="badge bg-<?= $stInfo['badge'] ?> rounded-pill px-3 py-1 fw-bold"><i class="fa-solid fa-spinner me-1"></i> STATUS: <?= htmlspecialchars($stInfo['label']) ?></span>
            </div>
            <h2 class="fw-bold text-dark mb-1">Pesanan #<?= htmlspecialchars($order['order_code']) ?></h2>
            <p class="text-muted mb-0">Dibuat pada <?= date('d F Y, H:i', strtotime($order['created_at'])) ?> WIB</p>
        </div>
        <div class="mt-3 mt-md-0 d-flex gap-2">
            <a href="<?= $baseUrl ?>/orders" class="btn btn-outline-secondary rounded-pill btn-sm">
                <i class="fa-solid fa-arrow-left me-1"></i> Kembali ke Daftar Pesanan
            </a>
        </div>
    </div>

    <div class="row g-4">
        <!-- Left Column: Order Summary & Courier Info Widget -->
        <div class="col-lg-7">
            <!-- Courier Info Widget -->
            <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white border-start border-4 border-info">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-truck-fast text-info me-2"></i> Informasi Kurir Penjemput (Pickup Courier)</h5>
                        <?php if (in_array($role, [\App\Helpers\Auth::ROLE_OWNER_TOKO, \App\Helpers\Auth::ROLE_ADMIN_TOKO, \App\Helpers\Auth::ROLE_OWNER, \App\Helpers\Auth::ROLE_ADMIN_OWNER])): ?>
                            <button class="btn btn-sm btn-outline-info rounded-pill px-3 fw-bold" data-bs-toggle="modal" data-bs-target="#assignCourierModal">
                                <i class="fa-solid fa-user-gear me-1"></i> Tugaskan Kurir
                            </button>
                        <?php endif; ?>
                    </div>

                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-info bg-opacity-10 text-info p-3 rounded-circle fs-3">
                            <i class="fa-solid fa-motorcycle"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="fw-bold text-dark mb-1"><?= htmlspecialchars($order['courier_name'] ?? 'Kurir Belum Ditugaskan') ?></h6>
                            <?php if (!empty($order['courier_phone'])): ?>
                                <small class="text-success d-block fw-bold mb-1">
                                    <i class="fa-solid fa-phone me-1"></i> WhatsApp: 
                                    <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $order['courier_phone']) ?>" target="_blank" class="text-success text-decoration-none">
                                        <?= htmlspecialchars($order['courier_phone']) ?>
                                    </a>
                                </small>
                            <?php endif; ?>
                            <small class="text-muted d-block">
                                <i class="fa-solid fa-location-crosshairs text-primary me-1"></i> GPS Kurir: 
                                <code>Lat: <?= htmlspecialchars($order['courier_lat'] ?? '-6.24430000') ?>, Lng: <?= htmlspecialchars($order['courier_lng'] ?? '106.79300000') ?></code>
                            </small>
                        </div>
                        <div>
                            <span class="badge bg-<?= ($order['courier_duty_status'] ?? 'available') === 'available' ? 'success' : 'warning' ?> rounded-pill px-3 py-1 fw-bold">
                                <?= strtoupper($order['courier_duty_status'] ?? 'available') ?>
                            </span>
                        </div>
                    </div>

                    <!-- Courier Action Button for Courier User -->
                    <?php if ($role === \App\Helpers\Auth::ROLE_KURIR && (int)$order['courier_id'] === \App\Helpers\Auth::id()): ?>
                        <div class="mt-3 pt-3 border-top d-flex gap-2">
                            <?php if ($currentStatus === \App\Models\Order::STATUS_ASSIGNED_TO_COURIER): ?>
                                <form action="<?= $baseUrl ?>/courier/update-pickup-status" method="POST" class="w-100">
                                    <?= \App\Helpers\Csrf::field() ?>
                                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                    <input type="hidden" name="status" value="<?= \App\Models\Order::STATUS_COURIER_ON_THE_WAY ?>">
                                    <input type="hidden" name="redirect_to" value="/orders/detail?id=<?= $order['id'] ?>">
                                    <button type="submit" class="btn btn-info text-white w-100 rounded-pill fw-bold">
                                        <i class="fa-solid fa-motorcycle me-1"></i> Terima Tugas & Menuju Customer
                                    </button>
                                </form>
                            <?php elseif ($currentStatus === \App\Models\Order::STATUS_COURIER_ON_THE_WAY): ?>
                                <form action="<?= $baseUrl ?>/courier/update-pickup-status" method="POST" class="w-100">
                                    <?= \App\Helpers\Csrf::field() ?>
                                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                    <input type="hidden" name="status" value="<?= \App\Models\Order::STATUS_PICKED_UP ?>">
                                    <input type="hidden" name="redirect_to" value="/orders/detail?id=<?= $order['id'] ?>">
                                    <button type="submit" class="btn btn-primary w-100 rounded-pill fw-bold">
                                        <i class="fa-solid fa-box-archive me-1"></i> Confirm: Laundry Dijemput dari Customer
                                    </button>
                                </form>
                            <?php elseif ($currentStatus === \App\Models\Order::STATUS_PICKED_UP): ?>
                                <form action="<?= $baseUrl ?>/courier/update-pickup-status" method="POST" class="w-100">
                                    <?= \App\Helpers\Csrf::field() ?>
                                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                    <input type="hidden" name="status" value="<?= \App\Models\Order::STATUS_ARRIVED_AT_STORE ?>">
                                    <input type="hidden" name="redirect_to" value="/orders/detail?id=<?= $order['id'] ?>">
                                    <button type="submit" class="btn btn-success w-100 rounded-pill fw-bold">
                                        <i class="fa-solid fa-store me-1"></i> Confirm: Sampai & Serahkan ke Toko
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Visual Map Tracking Component (Tahap 8 - Leaflet OSM + Real-time Polling) -->
            <?php 
            $isTrackingActive = in_array($currentStatus, [
                \App\Models\Order::STATUS_COURIER_ON_THE_WAY,
                \App\Models\Order::STATUS_OUT_FOR_DELIVERY
            ], true);
            ?>
            <?php if ($isTrackingActive): ?>
                <!-- Live Polling Status Header -->
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-2 px-1 gap-2">
                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 rounded-pill px-3 py-1.5 fw-bold">
                        <i class="fa-solid fa-satellite-dish fa-beat me-1"></i> Live Real-Time Tracking Kurir
                    </span>
                    <small class="text-muted" id="pollingStatusIndicator">
                        <i class="fa-solid fa-clock-rotate-left me-1"></i> Terakhir diperbarui: <span id="timeAgoText" class="fw-bold text-dark">baru saja</span>
                    </small>
                </div>

                <?php
                $mapMarkers = [
                    [
                        'lat' => (float)($order['store_lat'] ?? -6.22974650),
                        'lng' => (float)($order['store_lng'] ?? 106.81644900),
                        'label' => 'Toko: ' . ($order['store_name'] ?? 'Toko Laundry'),
                        'type' => 'store',
                        'info' => $order['store_address'] ?? 'Alamat Toko Laundry'
                    ],
                    [
                        'lat' => (float)($order['courier_lat'] ?? -6.24430000),
                        'lng' => (float)($order['courier_lng'] ?? 106.79300000),
                        'label' => 'Kurir: ' . ($order['courier_name'] ?? 'Petugas Kurir'),
                        'type' => 'courier',
                        'info' => 'Posisi Terkini Kurir ' . (!empty($order['courier_loc_updated_at']) ? '(' . date('H:i', strtotime($order['courier_loc_updated_at'])) . ' WIB)' : '')
                    ]
                ];

                if (!empty($order['latitude']) && !empty($order['longitude'])) {
                    $mapMarkers[] = [
                        'lat' => (float)$order['latitude'],
                        'lng' => (float)$order['longitude'],
                        'label' => 'Lokasi Anda (' . ($order['address_title'] ?? 'Customer') . ')',
                        'type' => 'customer',
                        'info' => $order['address_line'] ?? ''
                    ];
                }

                $cardTitle = ($currentStatus === \App\Models\Order::STATUS_COURIER_ON_THE_WAY) 
                    ? 'Peta Visual: Kurir Menuju Alamat Penjemputan' 
                    : 'Peta Visual: Kurir Sedang Mengantarkan Pakaian Bersih';

                $cardSubtitle = ($currentStatus === \App\Models\Order::STATUS_COURIER_ON_THE_WAY)
                    ? 'Pantau lokasi penjemputan pakaian secara visual dan pergerakan kurir secara live.'
                    : 'Pantau pengantaran cucian bersih kembali ke alamat Anda.';

                $mapId = 'orderDetailTrackingMap';
                $mapHeight = '350px';
                $markers = $mapMarkers;
                require dirname(__DIR__) . '/partials/map.php';
                ?>

                <!-- Real-Time Polling JavaScript Engine -->
                <script>
                (function() {
                    const orderId = <?= (int)$order['id'] ?>;
                    const baseUrl = '<?= $baseUrl ?>';
                    let lastUpdateTime = Date.now();
                    let pollInterval = null;
                    let timeAgoInterval = null;

                    function updateTimeAgo() {
                        const secondsAgo = Math.max(0, Math.floor((Date.now() - lastUpdateTime) / 1000));
                        const textEl = document.getElementById('timeAgoText');
                        if (!textEl) return;
                        if (secondsAgo < 5) {
                            textEl.innerText = 'baru saja';
                        } else if (secondsAgo < 60) {
                            textEl.innerText = `${secondsAgo} detik lalu`;
                        } else {
                            const mins = Math.floor(secondsAgo / 60);
                            textEl.innerText = `${mins} menit lalu`;
                        }
                    }

                    async function fetchCourierLocation() {
                        try {
                            const response = await fetch(`${baseUrl}/orders/${orderId}/courier-location`, {
                                headers: {
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'Accept': 'application/json'
                                }
                            });

                            if (!response.ok) {
                                if (response.status === 403) {
                                    clearInterval(pollInterval);
                                    clearInterval(timeAgoInterval);
                                }
                                return;
                            }

                            const result = await response.json();
                            if (result.status === 'success' && result.data) {
                                const data = result.data;

                                // Update posisi marker kurir di Leaflet tanpa reload
                                if (window.activeTrackingMapHelper && typeof window.activeTrackingMapHelper.updateCourier === 'function') {
                                    window.activeTrackingMapHelper.updateCourier(
                                        data.latitude,
                                        data.longitude,
                                        `Posisi Terkini Kurir (${data.updated_at ? data.updated_at.substring(11, 16) : 'Live'} WIB)`,
                                        data.courier_name
                                    );
                                }

                                lastUpdateTime = Date.now();
                                updateTimeAgo();

                                // Hentikan polling otomatis jika status order berubah menjadi selesai/non-tracking
                                if (!data.is_tracking_active) {
                                    clearInterval(pollInterval);
                                    clearInterval(timeAgoInterval);
                                    const indicator = document.getElementById('pollingStatusIndicator');
                                    if (indicator) {
                                        indicator.innerHTML = '<span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-1"><i class="fa-solid fa-circle-check me-1"></i> Perjalanan kurir selesai</span>';
                                    }
                                }
                            }
                        } catch (err) {
                            console.warn('[Live Tracking Polling]', err);
                        }
                    }

                    // Polling posisi kurir setiap 12 detik
                    pollInterval = setInterval(fetchCourierLocation, 12000);
                    timeAgoInterval = setInterval(updateTimeAgo, 1000);
                })();
                </script>
            <?php endif; ?>

            <!-- Order Details Card -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-file-invoice text-primary me-2"></i> Ringkasan Pesanan</h5>
                    <span class="badge bg-light text-dark border">Metode Bayar: <strong><?= strtoupper($order['payment_method']) ?></strong></span>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <small class="text-muted d-block fw-bold text-uppercase">Pelanggan / Customer</small>
                            <span class="fw-bold text-dark"><?= htmlspecialchars($order['customer_name']) ?></span>
                            <small class="text-muted d-block"><i class="fa-solid fa-phone text-success me-1"></i> <?= htmlspecialchars($order['customer_phone']) ?></small>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted d-block fw-bold text-uppercase">Toko Laundry</small>
                            <span class="fw-bold text-dark"><?= htmlspecialchars($order['store_name']) ?></span>
                            <small class="text-muted d-block"><i class="fa-solid fa-store text-primary me-1"></i> <?= htmlspecialchars($order['store_address']) ?></small>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted d-block fw-bold text-uppercase">Layanan Laundry</small>
                            <span class="fw-bold text-primary fs-6"><?= htmlspecialchars($order['service_name']) ?></span>
                            <small class="text-muted d-block">Rp <?= number_format($order['service_price'], 0, ',', '.') ?> / <?= $order['service_unit'] ?></small>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted d-block fw-bold text-uppercase">Kurir Penjemput / Pengantar</small>
                            <span class="fw-bold text-dark"><i class="fa-solid fa-truck-fast me-1 text-info"></i> <?= htmlspecialchars($order['courier_name'] ?? 'Petugas Kurir') ?></span>
                        </div>
                    </div>

                    <hr>

                    <!-- Pickup Address Details -->
                    <div class="mb-3">
                        <small class="text-muted d-block fw-bold text-uppercase mb-1"><i class="fa-solid fa-location-dot text-danger me-1"></i> Alamat Penjemputan (Pickup Address)</small>
                        <div class="p-3 bg-light rounded-3 border">
                            <div class="fw-bold text-dark mb-1">[<?= htmlspecialchars($order['address_title']) ?>] <?= htmlspecialchars($order['address_line']) ?></div>
                            <small class="text-muted d-block">
                                Kel. <?= htmlspecialchars($order['village']) ?>, RT <?= htmlspecialchars($order['rt']) ?> / RW <?= htmlspecialchars($order['rw']) ?>, Kec. <?= htmlspecialchars($order['district']) ?>, <?= htmlspecialchars($order['city']) ?>, <?= htmlspecialchars($order['province']) ?>
                            </small>
                            <?php if (!empty($order['detail_address'])): ?>
                                <small class="text-secondary d-block mt-1"><i class="fa-solid fa-info-circle me-1"></i> Patokan: <?= htmlspecialchars($order['detail_address']) ?></small>
                            <?php endif; ?>
                            <?php if (!empty($distanceEstimate)): ?>
                                <div class="mt-2 pt-2 border-top d-flex flex-wrap align-items-center justify-content-between gap-2 small">
                                    <span class="text-primary fw-bold">
                                        <i class="fa-solid fa-route me-1"></i> Jarak ke Toko: <?= htmlspecialchars($distanceEstimate['formatted_distance']) ?>
                                    </span>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2.5 py-1">
                                        <i class="fa-solid fa-motorcycle me-1"></i> Estimasi Kurir: ±<?= htmlspecialchars($distanceEstimate['formatted_duration']) ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (!empty($order['notes'])): ?>
                        <div class="mb-3">
                            <small class="text-muted d-block fw-bold text-uppercase mb-1">Catatan Khusus</small>
                            <div class="p-2.5 bg-warning bg-opacity-10 text-dark rounded-3 border border-warning border-opacity-25 small">
                                <i class="fa-solid fa-note-sticky text-warning me-1"></i> <?= htmlspecialchars($order['notes']) ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Pricing & Weighing Breakdown Card -->
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-calculator text-success me-2"></i> Perhitungan Penimbangan & Harga Final</h5>
                    <?php if (in_array($role, [\App\Helpers\Auth::ROLE_OWNER_TOKO, \App\Helpers\Auth::ROLE_ADMIN_TOKO, \App\Helpers\Auth::ROLE_OWNER])): ?>
                        <button class="btn btn-sm btn-primary rounded-pill px-3 fw-bold" data-bs-toggle="modal" data-bs-target="#weighingModal">
                            <i class="fa-solid fa-scale-balanced me-1"></i> Input Penimbangan Toko
                        </button>
                    <?php endif; ?>
                </div>
                <div class="card-body p-4">
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Komponen Perhitungan</th>
                                    <th class="text-end">Rincian Nilai</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Berat Estimasi (Customer)</td>
                                    <td class="text-end font-monospace"><?= $order['estimated_weight'] ?> kg</td>
                                </tr>
                                <tr>
                                    <td>Berat Aktual (Penimbangan Toko)</td>
                                    <td class="text-end font-monospace fw-bold text-primary">
                                        <?= $order['actual_weight'] ? $order['actual_weight'] . ' kg' : '<span class="text-warning">Belum ditimbang</span>' ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Harga Layanan per <?= $order['service_unit'] ?></td>
                                    <td class="text-end font-monospace">Rp <?= number_format($order['service_price'], 0, ',', '.') ?></td>
                                </tr>
                                <tr>
                                    <td>Subtotal (Berat Aktual × Tarif)</td>
                                    <td class="text-end font-monospace fw-semibold">
                                        Rp <?= number_format($order['subtotal'] ?? $order['estimated_amount'], 0, ',', '.') ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Biaya Tambahan (Parfum / Treatment)</td>
                                    <td class="text-end font-monospace text-danger">+ Rp <?= number_format($order['additional_fee'] ?? 0, 0, ',', '.') ?></td>
                                </tr>
                                <tr>
                                    <td>Potongan Diskon Toko</td>
                                    <td class="text-end font-monospace text-success">- Rp <?= number_format($order['discount'] ?? 0, 0, ',', '.') ?></td>
                                </tr>
                                <tr class="table-primary fw-bold">
                                    <td class="fs-6">TOTAL FINAL PEMBAYARAN</td>
                                    <td class="text-end fs-5 text-primary font-monospace">
                                        Rp <?= number_format($order['total_amount'], 0, ',', '.') ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Payment Action Box -->
                    <div class="mt-4 p-3 bg-light rounded-3 d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
                        <div>
                            <span class="text-muted small d-block">Status Pembayaran:</span>
                            <span class="badge bg-<?= $order['payment_status'] === 'paid' ? 'success' : 'warning' ?> rounded-pill px-3 py-1.5 fs-6">
                                <?= strtoupper($order['payment_status']) ?>
                            </span>
                            <?php if (!empty($payment['gateway_reference'])): ?>
                                <small class="text-muted d-block mt-1">Ref: <code><?= htmlspecialchars($payment['gateway_reference']) ?></code></small>
                            <?php endif; ?>
                        </div>
                        <?php if ($order['payment_status'] !== 'paid'): ?>
                            <?php if ($order['payment_method'] === 'online'): ?>
                                <a href="<?= $baseUrl ?>/orders/payment?order_id=<?= $order['id'] ?>" class="btn btn-danger rounded-pill px-4 fw-bold shadow-sm">
                                    <i class="fa-solid fa-qrcode me-2"></i> Bayar Sekarang via QRIS
                                </a>
                            <?php else: ?>
                                <span class="badge bg-secondary rounded-pill px-3 py-2 text-white">
                                    <i class="fa-solid fa-money-bill-wave me-1"></i> Bayar Tunai ke Kurir/Toko
                                </span>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="text-end">
                                <span class="text-success fw-bold d-block"><i class="fa-solid fa-circle-check me-1"></i> Lunas Terverifikasi Webhook</span>
                                <?php if (!empty($payment['paid_at'])): ?>
                                    <small class="text-muted"><?= date('d M Y, H:i', strtotime($payment['paid_at'])) ?> WIB</small>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Order Status Timeline Component -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-clock-rotate-left text-primary me-2"></i> Order Status Timeline</h5>
                    <?php if (!in_array($role, [\App\Helpers\Auth::ROLE_CUSTOMER], true)): ?>
                    <button class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-bold" data-bs-toggle="modal" data-bs-target="#statusUpdateModal">
                        <i class="fa-solid fa-sliders me-1"></i> Update Status
                    </button>
                    <?php endif; ?>
                </div>

                <!-- Timeline Component -->
                <div class="timeline border-start border-3 border-primary ps-3 my-3 ms-2">
                    <?php foreach ($timeline as $tl): ?>
                        <?php 
                            $lbl = $statusLabels[$tl['status']] ?? ['label' => $tl['status'], 'badge' => 'secondary'];
                        ?>
                        <div class="timeline-item mb-4 position-relative">
                            <div class="position-absolute start-0 translate-middle-x bg-white p-1 rounded-circle ms-n3 text-primary">
                                <i class="fa-solid fa-circle-dot fs-5"></i>
                            </div>
                            <div class="ps-3">
                                <span class="badge bg-<?= $lbl['badge'] ?> rounded-pill px-3 py-1 fw-bold mb-1">
                                    <?= htmlspecialchars($lbl['label']) ?>
                                </span>
                                <p class="mb-1 text-dark fw-semibold small"><?= htmlspecialchars($tl['notes']) ?></p>
                                <small class="text-muted" style="font-size: 0.75rem;">
                                    <i class="fa-solid fa-clock me-1"></i> <?= date('d M Y, H:i', strtotime($tl['created_at'])) ?>
                                    <?php if (!empty($tl['actor_name'])): ?>
                                        | <i class="fa-solid fa-user me-1"></i> <?= htmlspecialchars($tl['actor_name']) ?>
                                    <?php endif; ?>
                                </small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="mt-auto pt-3 border-top text-center text-muted small">
                    <i class="fa-solid fa-shield-halved text-success me-1"></i> Perubahan status dicatat di <code>order_status_histories</code>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal 1: Penugasan Kurir oleh Admin -->
<div class="modal fade" id="assignCourierModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header bg-info text-white rounded-top-4">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-user-gear me-2"></i> Penugasan Kurir Penjemputan</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= $baseUrl ?>/courier/assign-order" method="POST">
                <?= \App\Helpers\Csrf::field() ?>
                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="courier_id" class="form-label fw-bold">Pilih Kurir Yang Tersedia <span class="text-danger">*</span></label>
                        <select class="form-select form-select-lg" id="courier_id" name="courier_id" required>
                            <?php foreach ($availableCouriers as $cr): ?>
                                <option value="<?= $cr['id'] ?>" <?= (int)($order['courier_id'] ?? 0) === (int)$cr['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cr['name']) ?> (<?= htmlspecialchars($cr['phone']) ?>) - Status: <?= strtoupper($cr['duty_status'] ?? 'available') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="modal-footer bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-info text-white rounded-pill px-4 fw-bold">Tugaskan Kurir</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 2: Penimbangan Toko -->
<div class="modal fade" id="weighingModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header bg-primary text-white rounded-top-4">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-scale-balanced me-2"></i> Penimbangan Cucian & Perhitungan Final</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= $baseUrl ?>/orders/update-weighing" method="POST">
                <?= \App\Helpers\Csrf::field() ?>
                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="actual_weight" class="form-label fw-bold">Berat / Jumlah Aktual Cucian <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" step="0.1" min="0.1" class="form-control form-control-lg" id="modal_actual_weight" name="actual_weight" required value="<?= $order['actual_weight'] ?: $order['estimated_weight'] ?>" oninput="updateModalCalc()">
                            <span class="input-group-text fw-bold"><?= $order['service_unit'] ?></span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="additional_fee" class="form-label fw-bold">Biaya Tambahan (Parfum / Treatment)</label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="number" class="form-control" id="modal_additional_fee" name="additional_fee" value="<?= (int)($order['additional_fee'] ?? 0) ?>" oninput="updateModalCalc()">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="discount" class="form-label fw-bold">Potongan Diskon Toko</label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="number" class="form-control" id="modal_discount" name="discount" value="<?= (int)($order['discount'] ?? 0) ?>" oninput="updateModalCalc()">
                        </div>
                    </div>

                    <div class="p-3 bg-light rounded-3 border">
                        <div class="d-flex justify-content-between mb-1">
                            <span>Harga Satuan:</span>
                            <strong>Rp <?= number_format($order['service_price'], 0, ',', '.') ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span>Subtotal:</span>
                            <strong id="modal_subtotal">Rp 0</strong>
                        </div>
                        <div class="d-flex justify-content-between fs-5 fw-bold text-primary pt-2 border-top">
                            <span>Total Final:</span>
                            <span id="modal_total">Rp 0</span>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">Simpan & Tetapkan Total</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 3: Update Status Laundry — HANYA UNTUK STAFF/KURIR, BUKAN CUSTOMER -->
<?php if (!in_array($role, [\App\Helpers\Auth::ROLE_CUSTOMER], true)): ?>
<div class="modal fade" id="statusUpdateModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header bg-dark text-white rounded-top-4">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-sliders me-2"></i> Update Status Laundry</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= $baseUrl ?>/orders/update-status" method="POST">
                <?= \App\Helpers\Csrf::field() ?>
                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="status" class="form-label fw-bold">Pilih Status Baru <span class="text-danger">*</span></label>
                        <select class="form-select form-select-lg" id="status" name="status" required>
                            <?php 
                            $nextList = $allowedNextStatuses ?? [];
                            if (empty($nextList)): 
                            ?>
                                <option value="" disabled selected>Pesanan telah berada pada status akhir (<?= htmlspecialchars($statusLabels[$currentStatus]['label'] ?? $currentStatus) ?>)</option>
                            <?php else: ?>
                                <option value="" disabled selected>-- Pilih Tahap Status Berikutnya --</option>
                                <?php foreach ($nextList as $nextSt): ?>
                                    <option value="<?= $nextSt ?>">
                                        <?= htmlspecialchars($statusLabels[$nextSt]['label'] ?? $nextSt) ?> (<?= $nextSt ?>)
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                        <small class="text-muted mt-1 d-block"><i class="fa-solid fa-shield-halved text-success me-1"></i> Tervalidasi otomatis oleh State-Machine Laundry.</small>
                    </div>

                    <div class="mb-3">
                        <label for="notes" class="form-label fw-bold">Catatan Perubahan Status</label>
                        <textarea class="form-control" id="status_notes" name="notes" rows="2" placeholder="Masukkan catatan atau progress laundry..."></textarea>
                    </div>
                </div>

                <div class="modal-footer bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">Update Status Sekarang</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function updateModalCalc() {
    const unitPrice = <?= (float)$order['service_price'] ?>;
    const weight = parseFloat(document.getElementById('modal_actual_weight').value || 0);
    const addFee = parseFloat(document.getElementById('modal_additional_fee').value || 0);
    const discount = parseFloat(document.getElementById('modal_discount').value || 0);

    const subtotal = weight * unitPrice;
    let total = subtotal + addFee - discount;
    if (total < 0) total = 0;

    document.getElementById('modal_subtotal').innerText = 'Rp ' + new Intl.NumberFormat('id-ID').format(subtotal);
    document.getElementById('modal_total').innerText = 'Rp ' + new Intl.NumberFormat('id-ID').format(total);
}
document.addEventListener('DOMContentLoaded', updateModalCalc);
</script>
