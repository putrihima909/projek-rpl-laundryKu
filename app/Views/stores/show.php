<?php
/**
 * Store Detail Page – Public view
 * Displays full profile of a single store, contact info, operating hours,
 * and list of services offered.
 */
$showSidebar = false;
$baseUrl = config('app.url', 'http://localhost/rpl/public');
?>

<div class="container py-5">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb bg-light rounded-pill px-3 py-2">
            <li class="breadcrumb-item"><a href="<?= $baseUrl ?>/stores" class="text-decoration-none"><i class="fa-solid fa-store me-1"></i> Marketplace</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($store['name']) ?></li>
        </ol>
    </nav>

    <div class="row g-4">
        <!-- Store Profile Card (left) -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 text-center p-4 sticky-top" style="top: 80px;">
                <div class="rounded-circle bg-primary bg-opacity-10 d-inline-flex align-items-center justify-content-center mx-auto mb-3"
                     style="width:90px;height:90px;">
                    <i class="fa-solid fa-shop text-primary" style="font-size:2.4rem;"></i>
                </div>
                <h4 class="fw-bold mb-1 text-dark"><?= htmlspecialchars($store['name']) ?></h4>
                <div class="d-flex justify-content-center align-items-center gap-2 mb-3">
                    <span class="badge bg-warning text-dark fw-bold">
                        <i class="fa-solid fa-star me-1"></i><?= number_format((float)($store['rating'] ?? 4.8), 1) ?> / 5.0
                    </span>
                    <span class="badge bg-success-subtle text-success border border-success-subtle">
                        <i class="fa-solid fa-check me-1"></i>Terverifikasi
                    </span>
                </div>

                <?php if (!empty($store['description'])): ?>
                    <p class="text-muted small mb-3 text-start"><?= htmlspecialchars($store['description']) ?></p>
                <?php endif; ?>

                <ul class="list-unstyled text-start small mb-4">
                    <li class="mb-2 d-flex align-items-start gap-2">
                        <i class="fa-solid fa-location-dot text-danger mt-1"></i>
                        <span><?= htmlspecialchars($store['address']) ?></span>
                    </li>
                    <?php if (!empty($store['phone'])): ?>
                    <li class="mb-2 d-flex align-items-center gap-2">
                        <i class="fa-solid fa-phone text-success"></i>
                        <span><?= htmlspecialchars($store['phone']) ?></span>
                    </li>
                    <?php endif; ?>
                    <li class="mb-2 d-flex align-items-center gap-2">
                        <i class="fa-regular fa-clock text-info"></i>
                        <span>Jam Buka: <?= htmlspecialchars($store['working_hours'] ?? '08:00 - 21:00') ?></span>
                    </li>
                    <?php if (!empty($distanceEstimate)): ?>
                    <li class="mb-2 d-flex align-items-center gap-2">
                        <i class="fa-solid fa-route text-warning"></i>
                        <span>Estimasi Jarak: <strong><?= htmlspecialchars($distanceEstimate['formatted_distance']) ?></strong> (±<?= htmlspecialchars($distanceEstimate['formatted_duration']) ?>)</span>
                    </li>
                    <?php endif; ?>
                </ul>

                <?php if ($isLoggedIn && isset($user['role_name']) && $user['role_name'] === 'customer'): ?>
                    <a href="<?= $baseUrl ?>/orders/create?store_id=<?= $store['id'] ?>" class="btn btn-primary rounded-pill w-100 py-2 fw-bold shadow-sm">
                        <i class="fa-solid fa-cart-plus me-2"></i> Pesan Laundry di Toko Ini
                    </a>
                <?php elseif (!$isLoggedIn): ?>
                    <a href="<?= $baseUrl ?>/login" class="btn btn-primary rounded-pill w-100 py-2 fw-bold shadow-sm">
                        <i class="fa-solid fa-right-to-bracket me-2"></i> Login untuk Memesan
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Services List (right) -->
        <div class="col-lg-8">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold text-dark mb-0"><i class="fa-solid fa-list-check text-primary me-2"></i>Daftar Layanan & Tarif</h5>
                <span class="badge bg-light text-muted border"><?= count($services) ?> Layanan Tersedia</span>
            </div>

            <?php if (empty($services)): ?>
                <div class="card border-0 shadow-sm rounded-4 p-4 text-center">
                    <p class="text-muted mb-0">Belum ada layanan yang ditambahkan oleh toko ini.</p>
                </div>
            <?php else: ?>
                <div class="d-flex flex-column gap-3">
                    <?php foreach ($services as $svc): ?>
                        <div class="card border-0 shadow-sm rounded-4 p-3">
                            <div class="card-body p-0 d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
                                <div>
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <h6 class="fw-bold mb-0 text-dark" style="font-size:1.05rem;"><?= htmlspecialchars($svc['name']) ?></h6>
                                        <span class="badge bg-info-subtle text-info border border-info-subtle small">
                                            <?= strtoupper(htmlspecialchars($svc['unit'])) ?>
                                        </span>
                                    </div>
                                    <?php if (!empty($svc['description'])): ?>
                                        <p class="text-muted small mb-2"><?= htmlspecialchars($svc['description']) ?></p>
                                    <?php endif; ?>
                                    <div class="text-muted small">
                                        <i class="fa-regular fa-clock me-1 text-primary"></i> Estimasi pengerjaan: <strong><?= $svc['duration_hours'] ?> jam</strong>
                                    </div>
                                </div>
                                <div class="text-sm-end border-top border-sm-top-0 pt-2 pt-sm-0">
                                    <div class="text-muted small" style="font-size:.75rem;">Tarif Layanan</div>
                                    <div class="fw-bold text-success" style="font-size:1.3rem;">
                                        Rp <?= number_format((float)$svc['price'], 0, ',', '.') ?>
                                        <span class="text-muted small" style="font-size:.8rem;">/ <?= htmlspecialchars($svc['unit']) ?></span>
                                    </div>
                                    <?php if ($isLoggedIn && isset($user['role_name']) && $user['role_name'] === 'customer'): ?>
                                        <a href="<?= $baseUrl ?>/orders/create?store_id=<?= $store['id'] ?>&service_id=<?= $svc['id'] ?>"
                                           class="btn btn-sm btn-outline-success rounded-pill mt-2 px-3 fw-semibold">
                                            Pilih Layanan Ini
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
