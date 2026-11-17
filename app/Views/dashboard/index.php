<?php
$showSidebar = true;
$baseUrl = config('app.url', 'http://localhost/rpl/public');
?>

<div class="container-fluid">
    <!-- Top Welcome Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center pb-3 mb-4 border-bottom">
        <div>
            <h2 class="fw-bold text-dark mb-1">
                Selamat Datang, <?= htmlspecialchars($user['name'] ?? 'User') ?>!
            </h2>
            <p class="text-muted mb-0">
                Anda masuk sebagai role <span class="badge bg-primary px-3 py-1.5"><?= htmlspecialchars($role) ?></span> pada sistem LaundryKu.
            </p>
        </div>
        <div class="mt-3 mt-md-0 d-flex gap-2">
            <a href="<?= $baseUrl ?>/" class="btn btn-outline-secondary rounded-pill btn-sm">
                <i class="fa-solid fa-globe me-1"></i> Lihat Beranda
            </a>
            <a href="<?= $baseUrl ?>/logout" class="btn btn-danger rounded-pill btn-sm">
                <i class="fa-solid fa-right-from-bracket me-1"></i> Logout
            </a>
        </div>
    </div>




    <!-- Statistics Cards Grid -->
    <div class="row g-4 mb-4">
        <?php foreach ($stats as $stat): ?>
            <div class="col-sm-6 col-xl-3">
                <div class="card stat-card h-100 p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted small fw-semibold text-uppercase d-block mb-1"><?= htmlspecialchars($stat['title']) ?></span>
                            <h4 class="fw-bold text-dark mb-1"><?= htmlspecialchars($stat['value']) ?></h4>
                            <small class="text-<?= $stat['color'] ?> fw-semibold"><i class="fa-solid fa-arrow-trend-up me-1"></i> <?= htmlspecialchars($stat['trend']) ?></small>
                        </div>
                        <div class="icon-box bg-<?= $stat['color'] ?> bg-opacity-10 text-<?= $stat['color'] ?>">
                            <i class="fa-solid <?= $stat['icon'] ?>"></i>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Main Section / Placeholder Activity -->
    <div class="row g-4">
        <!-- Recent Activities Table -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white py-3 border-0 d-flex align-items-center justify-content-between">
                    <h5 class="fw-bold mb-0"><i class="fa-solid fa-clock-rotate-left text-primary me-2"></i> Riwayat Aktivitas Terbaru</h5>
                    <button class="btn btn-sm btn-light border rounded-pill">Lihat Semua</button>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Kode Transaksi</th>
                                <th>Deskripsi Layanan</th>
                                <th>Waktu</th>
                                <th>Status Laundry</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentActivities as $act): ?>
                                <tr>
                                    <td><span class="fw-bold text-primary"><?= $act['id'] ?></span></td>
                                    <td><?= $act['title'] ?></td>
                                    <td><small class="text-muted"><?= $act['time'] ?></small></td>
                                    <td><span class="badge bg-<?= $act['badge'] ?> rounded-pill px-3 py-1.5"><?= $act['status'] ?></span></td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-primary rounded-circle"><i class="fa-solid fa-eye"></i></button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Next Step Development Info Card -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 bg-primary text-white p-4 h-100">
                <h5 class="fw-bold mb-3"><i class="fa-solid fa-cubes me-2"></i> Status Fondasi Proyek</h5>
                <p class="small opacity-90">
                    Fondasi sistem MVC PHP murni, PDO MySQL, helper autentikasi, middleware, dan skema database telah berhasil disiapkan dengan rapi.
                </p>
                <div class="bg-white bg-opacity-15 p-3 rounded-3 mb-3 text-white small">
                    <div class="fw-semibold mb-2"><i class="fa-solid fa-list-check me-1"></i> Modul Siap Dikembangkan:</div>
                    <ul class="mb-0 ps-3">
                        <li>Modul Manajemen User & Toko</li>
                        <li>Modul Katalog & Pricing Layanan</li>
                        <li>Modul Ordering & Kurir Tracking</li>
                        <li>Integrasi Midtrans / E-Wallet</li>
                        <li>Integrasi AI Recommendation</li>
                    </ul>
                </div>
                <div class="mt-auto">
                    <small class="text-white-50">Versi 1.0.0 - Siap untuk Pengembangan Modul Lanjutan</small>
                </div>
            </div>
        </div>
    </div>
</div>
