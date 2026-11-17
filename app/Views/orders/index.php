<?php
$showSidebar = true;
$baseUrl = config('app.url', 'http://localhost/rpl/public');
?>

<div class="container-fluid">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center pb-3 mb-4 border-bottom">
        <div>
            <span class="badge bg-primary text-white px-3 py-1 mb-2 fw-bold"><i class="fa-solid fa-basket-shopping me-1"></i> ORDER MANAGEMENT</span>
            <h2 class="fw-bold text-dark mb-1">Daftar Transaksi Pesanan Laundry</h2>
            <p class="text-muted mb-0">Pantau status penjemputan, penimbangan, pemrosesan, dan pengantaran cucian.</p>
        </div>
        <div class="mt-3 mt-md-0 d-flex gap-2">
            <?php if ($role === \App\Helpers\Auth::ROLE_CUSTOMER): ?>
                <a href="<?= $baseUrl ?>/orders/create" class="btn btn-success rounded-pill px-4 shadow-sm fw-bold">
                    <i class="fa-solid fa-plus me-2"></i> Pesan Laundry Baru
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-4">
        <?php if (empty($orders)): ?>
            <div class="col-12 text-center py-5 bg-white rounded-4 shadow-sm">
                <i class="fa-solid fa-box-open display-1 text-muted mb-3"></i>
                <h4 class="fw-bold">Belum Ada Transaksi Pesanan</h4>
                <p class="text-muted">Tidak ada data transaksi laundry yang dapat ditampilkan saat ini.</p>
                <?php if ($role === \App\Helpers\Auth::ROLE_CUSTOMER): ?>
                    <a href="<?= $baseUrl ?>/orders/create" class="btn btn-primary rounded-pill px-4 fw-bold">
                        <i class="fa-solid fa-plus me-2"></i> Pesan Laundry Sekarang
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php foreach ($orders as $ord): ?>
                <?php 
                    $stInfo = $statusLabels[$ord['status']] ?? ['label' => $ord['status'], 'badge' => 'secondary'];
                ?>
                <div class="col-md-6 col-xl-4">
                    <div class="card h-100 border-0 shadow-sm rounded-4 p-3 hover-shadow">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <span class="badge bg-light text-primary border fw-bold px-3 py-1"><?= htmlspecialchars($ord['order_code']) ?></span>
                            </div>
                            <span class="badge bg-<?= $stInfo['badge'] ?> rounded-pill px-3 py-1.5 fw-bold">
                                <?= htmlspecialchars($stInfo['label']) ?>
                            </span>
                        </div>

                        <div class="mb-3">
                            <h5 class="fw-bold text-dark mb-1"><?= htmlspecialchars($ord['service_name'] ?? 'Cuci Laundry') ?></h5>
                            <small class="text-muted d-block mb-2"><i class="fa-solid fa-store text-primary me-1"></i> Toko: <strong><?= htmlspecialchars($ord['store_name'] ?? 'Toko Laundry') ?></strong></small>
                            <?php if (isset($ord['customer_name'])): ?>
                                <small class="text-muted d-block"><i class="fa-solid fa-user text-secondary me-1"></i> Customer: <strong><?= htmlspecialchars($ord['customer_name']) ?></strong></small>
                            <?php endif; ?>
                        </div>

                        <div class="p-2.5 bg-light rounded-3 small mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted">Berat Estimasi / Aktual:</span>
                                <span class="fw-bold text-dark"><?= htmlspecialchars($ord['actual_weight'] ? $ord['actual_weight'] . ' kg (Aktual)' : $ord['estimated_weight'] . ' kg (Estimasi)') ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted">Metode Pembayaran:</span>
                                <span class="badge bg-<?= $ord['payment_method'] === 'cash' ? 'success' : 'primary' ?> rounded-pill px-2 py-0.5"><?= strtoupper($ord['payment_method']) ?></span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-muted">Total Harga:</span>
                                <span class="fw-bold text-primary fs-6">Rp <?= number_format($ord['total_amount'], 0, ',', '.') ?></span>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center pt-2 border-top mt-auto gap-2">
                            <small class="text-muted"><i class="fa-solid fa-clock me-1"></i> <?= date('d M Y, H:i', strtotime($ord['created_at'])) ?></small>
                            <div class="d-flex gap-1">
                                <?php if ($ord['payment_method'] === 'online' && $ord['payment_status'] !== 'paid'): ?>
                                    <a href="<?= $baseUrl ?>/orders/payment?order_id=<?= $ord['id'] ?>" class="btn btn-sm btn-danger rounded-pill px-3 fw-bold">
                                        <i class="fa-solid fa-qrcode me-1"></i> Bayar QRIS
                                    </a>
                                <?php endif; ?>
                                <a href="<?= $baseUrl ?>/orders/detail?id=<?= $ord['id'] ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-bold">
                                    Detail <i class="fa-solid fa-arrow-right ms-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
