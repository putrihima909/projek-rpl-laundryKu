<?php
$showSidebar = true;
$baseUrl = config('app.url', 'http://localhost/rpl/public');
$currentTab = $activeTab ?? 'pickup';
?>

<div class="container-fluid py-2">
    <!-- Header Page -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center pb-3 mb-4 border-bottom">
        <div>
            <?php if (!empty($store)): ?>
                <div class="mb-1">
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1.5 rounded-pill fw-semibold">
                        <i class="fa-solid fa-shop me-1"></i> Outlet: <?= htmlspecialchars($store['name']) ?>
                    </span>
                    <span class="badge bg-light text-muted border px-2 py-1 rounded-pill small ms-1">
                        <i class="fa-solid fa-location-dot text-danger me-1"></i><?= htmlspecialchars($store['address'] ?? '') ?>
                    </span>
                </div>
            <?php endif; ?>
            <h2 class="fw-bold text-dark mb-1">
                <i class="fa-solid fa-boxes-packing text-info me-2"></i>Operasional: Siap Diambil / Diantar
            </h2>
            <p class="text-muted mb-0">Manajemen penyerahan cucian bersih kepada customer di toko atau pemberangkatan kurir pengantaran.</p>
        </div>
        <div class="mt-3 mt-md-0 d-flex gap-2">
            <a href="<?= $baseUrl ?>/dashboard/admin-toko" class="btn btn-outline-secondary rounded-pill btn-sm px-3">
                <i class="fa-solid fa-arrow-left me-1"></i> Dashboard Admin
            </a>
            <a href="<?= $baseUrl ?>/store/orders" class="btn btn-outline-primary rounded-pill btn-sm px-3">
                <i class="fa-solid fa-list-check me-1"></i> Seluruh Pesanan Toko
            </a>
            <a href="<?= $baseUrl ?>/orders/create" class="btn btn-success rounded-pill btn-sm px-3 fw-semibold shadow-sm">
                <i class="fa-solid fa-cart-plus me-1"></i> Pesanan Baru
            </a>
        </div>
    </div>

    <!-- Alert Notifications -->
    <?php if (\App\Helpers\Session::has('success')): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-4 shadow-sm mb-4" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i><?= htmlspecialchars(\App\Helpers\Session::flash('success')) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (\App\Helpers\Session::has('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-4 shadow-sm mb-4" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i><?= htmlspecialchars(\App\Helpers\Session::flash('error')) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Summary Statistics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-4">
            <div class="card border-0 shadow-sm rounded-4 p-3 <?= $currentTab === 'pickup' ? 'border-start border-4 border-warning bg-warning bg-opacity-10' : 'bg-white' ?>">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase d-block mb-1">Siap Diambil di Toko</span>
                        <h3 class="fw-bold text-dark mb-0"><?= (int)($readyCounts['ready_pickup'] ?? 0) ?> <span class="fs-6 fw-normal text-muted">Paket</span></h3>
                        <small class="text-warning fw-semibold"><i class="fa-solid fa-clock me-1"></i> Menunggu customer mengambil</small>
                    </div>
                    <div class="icon-box bg-warning bg-opacity-25 text-warning p-3 rounded-circle fs-4">
                        <i class="fa-solid fa-store"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-4">
            <div class="card border-0 shadow-sm rounded-4 p-3 <?= $currentTab === 'delivery' ? 'border-start border-4 border-info bg-info bg-opacity-10' : 'bg-white' ?>">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase d-block mb-1">Siap Diantar Kurir</span>
                        <h3 class="fw-bold text-dark mb-0"><?= (int)($readyCounts['ready_delivery'] ?? 0) ?> <span class="fs-6 fw-normal text-muted">Paket</span></h3>
                        <small class="text-info fw-semibold">
                            <i class="fa-solid fa-truck-fast me-1"></i> <?= (int)($readyCounts['delivering'] ?? 0) ?> paket sedang di jalan
                        </small>
                    </div>
                    <div class="icon-box bg-info bg-opacity-25 text-info p-3 rounded-circle fs-4">
                        <i class="fa-solid fa-truck-arrow-right"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-12 col-xl-4">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase d-block mb-1">Total Paket Siap</span>
                        <h3 class="fw-bold text-primary mb-0"><?= (int)($readyCounts['total'] ?? 0) ?> <span class="fs-6 fw-normal text-muted">Paket Cucian</span></h3>
                        <small class="text-success fw-semibold"><i class="fa-solid fa-circle-check me-1"></i> Selesai dicuci oleh outlet</small>
                    </div>
                    <div class="icon-box bg-primary bg-opacity-10 text-primary p-3 rounded-circle fs-4">
                        <i class="fa-solid fa-box-open"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Navigation Tabs -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-white border-bottom p-3">
            <ul class="nav nav-pills card-header-pills gap-2" id="readyTabs">
                <li class="nav-item">
                    <a class="nav-link rounded-pill px-4 fw-semibold <?= $currentTab === 'pickup' ? 'active bg-warning text-dark' : 'text-muted' ?>" 
                       href="<?= $baseUrl ?>/store/orders/ready?tab=pickup">
                        <i class="fa-solid fa-store me-2"></i> 1. Siap Diambil di Toko
                        <span class="badge <?= $currentTab === 'pickup' ? 'bg-dark text-white' : 'bg-warning-subtle text-warning' ?> rounded-pill ms-2">
                            <?= (int)($readyCounts['ready_pickup'] ?? 0) ?>
                        </span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link rounded-pill px-4 fw-semibold <?= $currentTab === 'delivery' ? 'active bg-info text-white' : 'text-muted' ?>" 
                       href="<?= $baseUrl ?>/store/orders/ready?tab=delivery">
                        <i class="fa-solid fa-truck-fast me-2"></i> 2. Siap Diantar Kurir
                        <span class="badge <?= $currentTab === 'delivery' ? 'bg-white text-info' : 'bg-info-subtle text-info' ?> rounded-pill ms-2">
                            <?= (int)($readyCounts['ready_delivery'] ?? 0) ?>
                        </span>
                    </a>
                </li>
            </ul>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="card-body p-3 bg-light border-bottom">
            <form method="GET" action="<?= $baseUrl ?>/store/orders/ready" class="row g-2 align-items-center">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($currentTab) ?>">

                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="fa-solid fa-magnifying-glass"></i></span>
                        <input type="text" name="search" class="form-control bg-white border-start-0" 
                               placeholder="Cari Kode Pesanan, Nama Customer, No. WhatsApp..." 
                               value="<?= htmlspecialchars($filters['search'] ?? '') ?>">
                    </div>
                </div>

                <div class="col-md-3">
                    <select name="status" class="form-select bg-white">
                        <option value="">-- Semua Status --</option>
                        <option value="ready" <?= ($filters['status'] ?? '') === 'ready' ? 'selected' : '' ?>>Siap (Ready)</option>
                        <?php if ($currentTab === 'delivery'): ?>
                            <option value="out_for_delivery" <?= ($filters['status'] ?? '') === 'out_for_delivery' ? 'selected' : '' ?>>Dalam Pengantaran Kurir</option>
                        <?php endif; ?>
                    </select>
                </div>

                <?php if ($currentTab === 'delivery'): ?>
                    <div class="col-md-2">
                        <select name="courier_assigned" class="form-select bg-white">
                            <option value="">-- Status Kurir --</option>
                            <option value="no" <?= ($filters['courier_assigned'] ?? '') === 'no' ? 'selected' : '' ?>>Belum Ditugaskan</option>
                            <option value="yes" <?= ($filters['courier_assigned'] ?? '') === 'yes' ? 'selected' : '' ?>>Sudah Ditugaskan</option>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="col-auto d-flex gap-2">
                    <button type="submit" class="btn btn-primary rounded-pill px-3">
                        <i class="fa-solid fa-filter me-1"></i> Filter
                    </button>
                    <?php if (!empty($filters['search']) || !empty($filters['status']) || !empty($filters['courier_assigned'])): ?>
                        <a href="<?= $baseUrl ?>/store/orders/ready?tab=<?= htmlspecialchars($currentTab) ?>" class="btn btn-outline-secondary rounded-pill px-3">
                            <i class="fa-solid fa-rotate-left me-1"></i> Reset
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Orders Table / List Content -->
        <div class="card-body p-0">
            <?php if (empty($orders)): ?>
                <div class="text-center py-5 px-3">
                    <div class="mb-3 text-muted">
                        <i class="fa-solid fa-box-open fs-1 text-secondary opacity-50"></i>
                    </div>
                    <h5 class="fw-bold text-dark">Tidak ada pesanan yang siap <?= $currentTab === 'pickup' ? 'diambil' : 'diantar' ?>.</h5>
                    <p class="text-muted small mb-3">
                        <?= !empty($filters['search']) || !empty($filters['status']) 
                            ? 'Tidak ada pesanan yang cocok dengan kriteria filter saat ini.' 
                            : ($currentTab === 'pickup' ? 'Saat ini seluruh cucian ambil di toko belum ada yang berstatus Siap (ready).' : 'Saat ini belum ada cucian siap antar yang perlu ditugaskan atau dikirim.') ?>
                    </p>
                    <a href="<?= $baseUrl ?>/store/orders" class="btn btn-outline-primary btn-sm rounded-pill px-4">
                        <i class="fa-solid fa-basket-shopping me-1"></i> Lihat Semua Pesanan Toko
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light text-muted small text-uppercase">
                            <tr>
                                <th class="ps-3 py-3" style="width: 170px;">Kode & Waktu</th>
                                <th>Customer & WhatsApp</th>
                                <th>Layanan & Berat</th>
                                <th>Total & Bayar</th>
                                <?php if ($currentTab === 'delivery'): ?>
                                    <th>Alamat & Kurir</th>
                                <?php endif; ?>
                                <th>Lama Menunggu</th>
                                <th class="text-end pe-3" style="min-width: 220px;">Aksi Operasional</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $o): 
                                $waRaw = $o['customer_phone'] ?? '';
                                $cleanPhone = preg_replace('/\D/', '', $waRaw);
                                if (str_starts_with($cleanPhone, '0')) {
                                    $waIntl = '62' . substr($cleanPhone, 1);
                                } elseif (str_starts_with($cleanPhone, '62')) {
                                    $waIntl = $cleanPhone;
                                } else {
                                    $waIntl = '62' . $cleanPhone;
                                }

                                $hoursWait = (int)($o['hours_waiting'] ?? 0);
                                $daysWait = (int)($o['days_waiting'] ?? 0);
                                if ($daysWait > 0) {
                                    $waitingText = "{$daysWait} hari lalu";
                                    $waitingBadgeClass = $daysWait >= 3 ? 'bg-danger text-white' : 'bg-warning text-dark';
                                } elseif ($hoursWait > 0) {
                                    $waitingText = "{$hoursWait} jam lalu";
                                    $waitingBadgeClass = $hoursWait >= 24 ? 'bg-warning text-dark' : 'bg-info-subtle text-info';
                                } else {
                                    $waitingText = "Baru saja";
                                    $waitingBadgeClass = 'bg-success-subtle text-success';
                                }

                                $paymentBadge = match($o['payment_status'] ?? 'unpaid') {
                                    'paid'     => 'bg-success text-white',
                                    'refunded' => 'bg-secondary text-white',
                                    default    => 'bg-danger text-white',
                                };
                                $paymentLabel = match($o['payment_status'] ?? 'unpaid') {
                                    'paid'     => 'LUNAS',
                                    'refunded' => 'REFUND',
                                    default    => 'BELUM LUNAS',
                                };

                                $statusBadge = match($o['status'] ?? 'ready') {
                                    'ready'            => 'bg-info-subtle text-info border border-info-subtle',
                                    'out_for_delivery' => 'bg-primary-subtle text-primary border border-primary-subtle',
                                    'delivered'        => 'bg-success-subtle text-success border border-success-subtle',
                                    default            => 'bg-light text-muted border',
                                };
                                $statusLabel = match($o['status'] ?? 'ready') {
                                    'ready'            => 'Siap di Toko',
                                    'out_for_delivery' => 'Kurir Mengantar',
                                    'delivered'        => 'Selesai Diterima',
                                    default            => ucfirst($o['status'] ?? ''),
                                };
                            ?>
                                <tr>
                                    <!-- Kode & Waktu -->
                                    <td class="ps-3">
                                        <div class="fw-bold text-dark font-monospace">#<?= htmlspecialchars($o['order_code']) ?></div>
                                        <small class="text-muted d-block" style="font-size: 0.75rem;">
                                            <?= date('d M Y, H:i', strtotime($o['created_at'])) ?>
                                        </small>
                                        <span class="badge <?= $statusBadge ?> rounded-pill small mt-1">
                                            <?= $statusLabel ?>
                                        </span>
                                    </td>

                                    <!-- Customer & WhatsApp -->
                                    <td>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($o['customer_name'] ?? 'Pelanggan') ?></div>
                                        <?php if (!empty($cleanPhone)): ?>
                                            <div class="small text-muted font-monospace mt-0.5">
                                                <i class="fa-solid fa-phone text-secondary me-1"></i><?= htmlspecialchars($waRaw) ?>
                                            </div>
                                        <?php else: ?>
                                            <small class="text-muted fst-italic">Tanpa no. telepon</small>
                                        <?php endif; ?>
                                        <?php if (!empty($o['notes'])): ?>
                                            <div class="small text-muted fst-italic mt-1" style="max-width: 250px;">
                                                <i class="fa-solid fa-note-sticky text-warning me-1"></i>"<?= htmlspecialchars($o['notes']) ?>"
                                            </div>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Layanan & Berat -->
                                    <td>
                                        <div class="fw-semibold text-dark"><?= htmlspecialchars($o['service_name'] ?? '-') ?></div>
                                        <small class="text-muted">
                                            Berat: <strong class="text-dark"><?= htmlspecialchars($o['actual_weight'] ?: $o['estimated_weight']) ?> <?= htmlspecialchars($o['service_unit'] ?? 'kg') ?></strong>
                                        </small>
                                    </td>

                                    <!-- Total & Status Bayar -->
                                    <td>
                                        <div class="fw-bold text-dark">Rp <?= number_format((float)($o['total_amount'] ?? 0), 0, ',', '.') ?></div>
                                        <span class="badge <?= $paymentBadge ?> rounded-pill small">
                                            <?= $paymentLabel ?>
                                        </span>
                                    </td>

                                    <!-- Alamat & Kurir (Untuk Delivery Tab) -->
                                    <?php if ($currentTab === 'delivery'): ?>
                                        <td>
                                            <div class="small text-dark fw-semibold" style="max-width: 220px;">
                                                <i class="fa-solid fa-location-dot text-danger me-1"></i>
                                                <?= htmlspecialchars($o['address_line'] ?? 'Alamat pelanggan') ?>
                                            </div>
                                            <div class="mt-1 small">
                                                <?php if (!empty($o['courier_name'])): ?>
                                                    <span class="badge bg-light text-dark border rounded-pill">
                                                        <i class="fa-solid fa-motorcycle text-primary me-1"></i>
                                                        <?= htmlspecialchars($o['courier_name']) ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill">
                                                        <i class="fa-solid fa-user-xmark me-1"></i> Belum Ada Kurir
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    <?php endif; ?>

                                    <!-- Lama Menunggu -->
                                    <td>
                                        <span class="badge <?= $waitingBadgeClass ?> rounded-pill small px-2 py-1">
                                            <?= $waitingText ?>
                                        </span>
                                        <small class="text-muted d-block mt-1" style="font-size: 0.7rem;">
                                            Selesai: <?= date('d/m H:i', strtotime($o['updated_at'])) ?>
                                        </small>
                                    </td>

                                    <!-- Aksi Operasional -->
                                    <td class="text-end pe-3">
                                        <div class="d-flex flex-wrap justify-content-end gap-1">
                                            <!-- 1. Lihat Detail -->
                                            <a href="<?= $baseUrl ?>/orders/detail?id=<?= $o['id'] ?>" 
                                               class="btn btn-outline-secondary btn-sm rounded-pill px-2.5" 
                                               title="Buka Halaman Detail Lengkap">
                                                <i class="fa-solid fa-eye me-1"></i> Detail
                                            </a>

                                            <!-- 2. Hubungi Customer via WhatsApp Resmi -->
                                            <?php if (!empty($cleanPhone)): 
                                                $waText = rawurlencode("Halo Kak " . ($o['customer_name'] ?? '') . ",\n\nKami dari *" . ($store['name'] ?? 'SMART LAUNDRY') . "* menginformasikan bahwa pesanan laundry #" . $o['order_code'] . " sudah selesai dan siap " . ($currentTab === 'pickup' ? 'diambil di outlet kami' : 'diantarkan ke alamat Anda') . ".\n\nTotal: Rp " . number_format((float)$o['total_amount'], 0, ',', '.') . " (" . $paymentLabel . ").\n\nTerima kasih! 😊");
                                            ?>
                                                <a href="https://wa.me/<?= $waIntl ?>?text=<?= $waText ?>" 
                                                   target="_blank" 
                                                   rel="noopener noreferrer"
                                                   class="btn btn-outline-success btn-sm rounded-pill px-2.5" 
                                                   title="Hubungi nomor WhatsApp customer">
                                                    <i class="fa-brands fa-whatsapp text-success me-1"></i> Hubungi
                                                </a>
                                            <?php endif; ?>

                                            <!-- 3. Aksi Khusus Tab Siap Diambil -->
                                            <?php if ($currentTab === 'pickup' && $o['status'] === 'ready'): ?>
                                                <button type="button" 
                                                        class="btn btn-success btn-sm rounded-pill px-3 fw-semibold shadow-sm"
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#confirmPickupModal<?= $o['id'] ?>">
                                                    <i class="fa-solid fa-hand-holding-hand me-1"></i> Tandai Sudah Diambil
                                                </button>

                                                <!-- Modal Konfirmasi Ambil di Toko -->
                                                <div class="modal fade" id="confirmPickupModal<?= $o['id'] ?>" tabindex="-1">
                                                    <div class="modal-dialog modal-dialog-centered">
                                                        <div class="modal-content rounded-4 border-0">
                                                            <div class="modal-header bg-success text-white rounded-top-4">
                                                                <h5 class="modal-title fw-bold">
                                                                    <i class="fa-solid fa-circle-check me-2"></i>Konfirmasi Penyerahan Cucian
                                                                </h5>
                                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <form action="<?= $baseUrl ?>/store/orders/mark-picked-up" method="POST">
                                                                <?= \App\Helpers\Csrf::field() ?>
                                                                <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                                                                <input type="hidden" name="store_id" value="<?= $store['id'] ?>">

                                                                <div class="modal-body p-4 text-start">
                                                                    <div class="alert alert-light border rounded-3 p-3 mb-3">
                                                                        <div class="row g-2 small">
                                                                            <div class="col-4 text-muted">Kode Order:</div>
                                                                            <div class="col-8 fw-bold">#<?= htmlspecialchars($o['order_code']) ?></div>
                                                                            <div class="col-4 text-muted">Customer:</div>
                                                                            <div class="col-8 fw-bold"><?= htmlspecialchars($o['customer_name']) ?></div>
                                                                            <div class="col-4 text-muted">Total Biaya:</div>
                                                                            <div class="col-8 fw-bold text-primary">Rp <?= number_format((float)$o['total_amount'], 0, ',', '.') ?> (<?= $paymentLabel ?>)</div>
                                                                        </div>
                                                                    </div>
                                                                    <p class="mb-0 text-dark">
                                                                        Apakah Anda yakin pakaian bersih telah diserahkan dan diterima langsung oleh customer di toko?
                                                                    </p>
                                                                    <small class="text-muted d-block mt-2">
                                                                        <i class="fa-solid fa-info-circle text-info me-1"></i>
                                                                        Status pesanan akan diperbarui menjadi <strong>Selesai (delivered)</strong> dan riwayat penyerahan akan dicatat oleh sistem.
                                                                    </small>
                                                                </div>
                                                                <div class="modal-footer bg-light rounded-bottom-4">
                                                                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                                                                    <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold">
                                                                        <i class="fa-solid fa-check me-1"></i> Ya, Sudah Diambil Customer
                                                                    </button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>

                                            <!-- 4. Aksi Khusus Tab Siap Diantar -->
                                            <?php if ($currentTab === 'delivery'): ?>
                                                <?php if (empty($o['courier_id']) && $o['status'] === 'ready'): ?>
                                                    <!-- Tombol Tugaskan Kurir -->
                                                    <button type="button" 
                                                            class="btn btn-info text-white btn-sm rounded-pill px-3 fw-semibold shadow-sm"
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#assignCourierModal<?= $o['id'] ?>">
                                                        <i class="fa-solid fa-user-plus me-1"></i> Tugaskan Kurir
                                                    </button>
                                                <?php elseif (!empty($o['courier_id']) && $o['status'] === 'ready'): ?>
                                                    <!-- Tombol Ganti Kurir & Mulai Pengantaran -->
                                                    <button type="button" 
                                                            class="btn btn-outline-info btn-sm rounded-pill px-2"
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#assignCourierModal<?= $o['id'] ?>"
                                                            title="Ganti Kurir Pengantar">
                                                        <i class="fa-solid fa-user-pen"></i>
                                                    </button>

                                                    <form action="<?= $baseUrl ?>/store/orders/start-delivery" method="POST" class="d-inline" onsubmit="return confirm('Mulai proses pengantaran kurir untuk pesanan #<?= htmlspecialchars($o['order_code']) ?>?');">
                                                        <?= \App\Helpers\Csrf::field() ?>
                                                        <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                                                        <input type="hidden" name="store_id" value="<?= $store['id'] ?>">
                                                        <button type="submit" class="btn btn-primary btn-sm rounded-pill px-3 fw-semibold shadow-sm">
                                                            <i class="fa-solid fa-truck-fast me-1"></i> Mulai Pengantaran
                                                        </button>
                                                    </form>
                                                <?php elseif ($o['status'] === 'out_for_delivery'): ?>
                                                    <span class="badge bg-primary rounded-pill px-3 py-1.5 small">
                                                        <i class="fa-solid fa-road me-1"></i> Sedang Diantar Kurir
                                                    </span>
                                                <?php endif; ?>

                                                <!-- Modal Penugasan Kurir Pengantaran -->
                                                <div class="modal fade" id="assignCourierModal<?= $o['id'] ?>" tabindex="-1">
                                                    <div class="modal-dialog modal-dialog-centered">
                                                        <div class="modal-content rounded-4 border-0">
                                                            <div class="modal-header bg-info text-white rounded-top-4">
                                                                <h5 class="modal-title fw-bold">
                                                                    <i class="fa-solid fa-motorcycle me-2"></i>Tugaskan Kurir Pengantaran
                                                                </h5>
                                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <form action="<?= $baseUrl ?>/store/orders/assign-delivery-courier" method="POST">
                                                                <?= \App\Helpers\Csrf::field() ?>
                                                                <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                                                                <input type="hidden" name="store_id" value="<?= $store['id'] ?>">

                                                                <div class="modal-body p-4 text-start">
                                                                    <div class="alert alert-light border rounded-3 p-3 mb-3 small">
                                                                        <div><strong>Pesanan:</strong> #<?= htmlspecialchars($o['order_code']) ?> (<?= htmlspecialchars($o['customer_name']) ?>)</div>
                                                                        <div class="text-truncate"><strong>Tujuan:</strong> <?= htmlspecialchars($o['address_line'] ?? '-') ?></div>
                                                                    </div>

                                                                    <div class="mb-3">
                                                                        <label for="courier_id_<?= $o['id'] ?>" class="form-label fw-bold">Pilih Kurir Yang Tersedia <span class="text-danger">*</span></label>
                                                                        <select class="form-select form-select-lg" id="courier_id_<?= $o['id'] ?>" name="courier_id" required>
                                                                            <option value="">-- Pilih Kurir Aktif --</option>
                                                                            <?php foreach ($availableCouriers as $cr): ?>
                                                                                <option value="<?= $cr['id'] ?>" <?= (int)($o['courier_id'] ?? 0) === (int)$cr['id'] ? 'selected' : '' ?>>
                                                                                    <?= htmlspecialchars($cr['name']) ?> (<?= htmlspecialchars($cr['phone']) ?>) — <?= strtoupper($cr['duty_status'] ?? 'AVAILABLE') ?>
                                                                                </option>
                                                                            <?php endforeach; ?>
                                                                        </select>
                                                                        <small class="text-muted mt-1 d-block">
                                                                            <i class="fa-solid fa-circle-info text-info me-1"></i>
                                                                            Kurir yang ditugaskan akan menerima notifikasi tugas pengantaran ini di dashboard kurir.
                                                                        </small>
                                                                    </div>
                                                                </div>

                                                                <div class="modal-footer bg-light rounded-bottom-4">
                                                                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                                                                    <button type="submit" class="btn btn-info text-white rounded-pill px-4 fw-bold">
                                                                        <i class="fa-solid fa-check me-1"></i> Simpan Penugasan Kurir
                                                                    </button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

