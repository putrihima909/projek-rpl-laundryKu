<?php
$showSidebar = true;
$baseUrl = config('app.url', 'http://localhost/rpl/public');
?>

<div class="container-fluid">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center pb-3 mb-4 border-bottom">
        <div>
            <?php if (!empty($store)): ?>
                <div class="mb-1">
                    <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1.5 rounded-pill fw-semibold">
                        <i class="fa-solid fa-shop me-1"></i> Outlet: <?= htmlspecialchars($store['name']) ?>
                    </span>
                    <span class="badge bg-light text-muted border px-2 py-1 rounded-pill small ms-1">
                        <i class="fa-solid fa-location-dot text-danger me-1"></i><?= htmlspecialchars($store['address'] ?? '') ?>
                    </span>
                </div>
            <?php else: ?>
                <span class="badge bg-warning text-dark px-3 py-1 mb-2 fw-bold"><i class="fa-solid fa-shop me-1"></i> OWNER TOKO LAUNDRY</span>
            <?php endif; ?>
            <h2 class="fw-bold text-dark mb-1">Selamat Datang, <?= htmlspecialchars($user['name'] ?? 'Owner Toko') ?></h2>
            <p class="text-muted mb-0">Kelola Usaha Toko Laundry, Tarif Layanan, dan Kinerja Pesanan Anda secara Real-Time</p>
        </div>
        <div class="mt-3 mt-md-0 d-flex gap-2">
            <a href="<?= $baseUrl ?>/services" class="btn btn-primary rounded-pill btn-sm fw-semibold shadow-sm">
                <i class="fa-solid fa-tags me-1"></i> Kelola Layanan
            </a>
            <a href="<?= $baseUrl ?>/store/orders" class="btn btn-outline-primary rounded-pill btn-sm fw-semibold">
                <i class="fa-solid fa-basket-shopping me-1"></i> Pesanan Masuk
            </a>
            <a href="<?= $baseUrl ?>/logout" class="btn btn-outline-danger rounded-pill btn-sm">
                <i class="fa-solid fa-right-from-bracket me-1"></i> Logout
            </a>
        </div>
    </div>

    <?php require __DIR__ . '/_role_bar.php'; ?>

    <?php if (empty($store)): ?>
        <div class="alert alert-warning rounded-4 shadow-sm mb-4">
            <div class="d-flex align-items-center">
                <i class="fa-solid fa-triangle-exclamation fs-3 me-3 text-warning"></i>
                <div>
                    <h5 class="fw-bold mb-1">Toko Belum Terdaftar</h5>
                    <p class="mb-0 small">Akun Anda belum dikaitkan dengan toko laundry manapun. Silakan hubungi Platform Owner untuk mendaftarkan toko Anda.</p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="row g-4 mb-4">
        <?php foreach ($stats as $stat): 
            $hasLink = !empty($stat['link']);
            $cardTag = $hasLink ? 'a' : 'div';
            $hrefAttr = $hasLink ? 'href="' . $baseUrl . htmlspecialchars($stat['link']) . '" style="text-decoration: none;"' : '';
        ?>
            <div class="col-sm-6 col-xl-3">
                <<?= $cardTag ?> <?= $hrefAttr ?> class="card stat-card h-100 p-3 shadow-sm border-0 rounded-4 <?= $hasLink ? 'hover-shadow transition-all' : '' ?>">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted small fw-semibold text-uppercase d-block mb-1"><?= htmlspecialchars($stat['title']) ?></span>
                            <h4 class="fw-bold text-dark mb-1"><?= htmlspecialchars($stat['value']) ?></h4>
                            <small class="text-<?= $stat['color'] ?> fw-semibold d-block mb-1"><i class="fa-solid fa-arrow-trend-up me-1"></i> <?= htmlspecialchars($stat['trend']) ?></small>
                            <?php if (!empty($stat['sub_info'])): ?>
                                <small class="text-muted d-block mt-0.5" style="font-size: 0.75rem;">
                                    <i class="fa-solid fa-clock-rotate-left me-1"></i> <?= htmlspecialchars($stat['sub_info']) ?>
                                </small>
                            <?php endif; ?>
                            <?php if (!empty($stat['link'])): ?>
                                <div class="mt-2">
                                    <span class="badge bg-<?= $stat['color'] ?>-subtle text-<?= $stat['color'] ?> border border-<?= $stat['color'] ?>-subtle rounded-pill small">
                                        Lihat Detail Omset <i class="fa-solid fa-arrow-right ms-1"></i>
                                    </span>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($stat['has_transparency'])): ?>
                                <button type="button" class="btn btn-xs btn-outline-info rounded-pill py-0 px-2 fw-semibold mt-1" style="font-size: 0.75rem;" data-bs-toggle="modal" data-bs-target="#omzetDetailModal" onclick="event.stopPropagation();">
                                    <i class="fa-solid fa-circle-info me-1"></i> Lihat Rincian Perhitungan
                                </button>
                            <?php endif; ?>
                        </div>
                        <div class="icon-box bg-<?= $stat['color'] ?> bg-opacity-10 text-<?= $stat['color'] ?>">
                            <i class="fa-solid <?= $stat['icon'] ?>"></i>
                        </div>
                    </div>
                </<?= $cardTag ?>>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require __DIR__ . '/_omzet_transparency_modal.php'; ?>
