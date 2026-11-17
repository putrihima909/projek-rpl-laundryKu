<?php
$showSidebar = true;
$baseUrl = config('app.url', 'http://localhost/rpl/public');
?>

<div class="container-fluid">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center pb-3 mb-4 border-bottom">
        <div>
            <span class="badge bg-info text-dark px-3 py-1 mb-2 fw-bold"><i class="fa-solid fa-user me-1"></i> CUSTOMER</span>
            <h2 class="fw-bold text-dark mb-1">Selamat Datang, <?= htmlspecialchars($user['name'] ?? 'Customer') ?></h2>
            <p class="text-muted mb-0">Pantau Status Cuci, Pesan Layanan Baru, dan Kelola Alamat Anda</p>
        </div>
        <div class="mt-3 mt-md-0 d-flex gap-2">
            <a href="<?= $baseUrl ?>/logout" class="btn btn-outline-danger rounded-pill btn-sm">
                <i class="fa-solid fa-right-from-bracket me-1"></i> Logout
            </a>
        </div>
    </div>

    <?php require __DIR__ . '/_role_bar.php'; ?>

    <div class="row g-4 mb-4">
        <?php foreach ($stats as $stat): ?>
            <div class="col-sm-6 col-xl-3">
                <div class="card stat-card h-100 p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted small fw-semibold text-uppercase d-block mb-1"><?= htmlspecialchars($stat['title']) ?></span>
                            <h4 class="fw-bold text-dark mb-1"><?= htmlspecialchars($stat['value']) ?></h4>
                            <small class="text-<?= $stat['color'] ?> fw-semibold"><i class="fa-solid fa-soap me-1"></i> <?= htmlspecialchars($stat['trend']) ?></small>
                        </div>
                        <div class="icon-box bg-<?= $stat['color'] ?> bg-opacity-10 text-<?= $stat['color'] ?>">
                            <i class="fa-solid <?= $stat['icon'] ?>"></i>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
