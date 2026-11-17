<?php
$showSidebar = true;
$baseUrl = config('app.url', 'http://localhost/rpl/public');
?>

<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center pb-3 mb-4 border-bottom">
        <div>
            <span class="badge bg-warning text-dark px-3 py-1 mb-2 fw-bold"><i class="fa-solid fa-crown me-1"></i> OWNER PLATFORM</span>
            <h2 class="fw-bold text-dark mb-1">Selamat Datang, <?= htmlspecialchars($user['name'] ?? 'Owner') ?></h2>
            <p class="text-muted mb-0">Manajemen Pusat & Kontrol Seluruh Ekosistem LaundryKu</p>
        </div>
        <div class="mt-3 mt-md-0 d-flex gap-2">
            <a href="<?= $baseUrl ?>/users" class="btn btn-primary rounded-pill btn-sm fw-semibold">
                <i class="fa-solid fa-users-gear me-1"></i> Kelola User System
            </a>
            <a href="<?= $baseUrl ?>/logout" class="btn btn-outline-danger rounded-pill btn-sm">
                <i class="fa-solid fa-right-from-bracket me-1"></i> Logout
            </a>
        </div>
    </div>

    <!-- Role Switcher Floating Bar -->
    <?php require __DIR__ . '/_role_bar.php'; ?>

    <!-- Stat Cards -->
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
                            <small class="text-<?= $stat['color'] ?> fw-semibold d-block mb-1"><i class="fa-solid fa-check-circle me-1"></i> <?= htmlspecialchars($stat['trend']) ?></small>
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

    <!-- Quick Action & RBAC Security Overview -->
    <div class="row g-4">

    <!-- WhatsApp Gateway Widget (Owner & Admin Owner only) -->
    <?php
    $wa         = $waStats ?? [];
    $waConf     = !empty($wa['is_configured']);
    $waConn     = !empty($wa['device']['connected'] ?? false);
    $waSent     = (int)($wa['sent']   ?? 0);
    $waFailed   = (int)($wa['failed'] ?? 0);
    $waProvider = htmlspecialchars($wa['provider'] ?? 'fonnte');
    $waPhone    = htmlspecialchars($wa['device']['device'] ?? '—');
    if ($waConf && $waConn)       { $waBadge = 'success'; $waStatus = 'Connected'; }
    elseif ($waConf && !$waConn)  { $waBadge = 'info';    $waStatus = 'Ready / Not Connected'; }
    else                           { $waBadge = 'danger';  $waStatus = 'Belum Dikonfigurasi'; }
    ?>
    <div class="col-12">
        <div class="card border-0 shadow-sm rounded-4 p-4 h-100">
            <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
                <div class="icon-box bg-success bg-opacity-10 text-success" style="width:48px;height:48px;font-size:1.3rem;">
                    <i class="fa-brands fa-whatsapp"></i>
                </div>
                <div>
                    <h5 class="fw-bold mb-0 text-dark">📱 WhatsApp Gateway</h5>
                    <small class="text-muted">Provider: <strong><?= $waProvider ?></strong>
                        <?php if ($waPhone !== '—'): ?> · Nomor: <code><?= $waPhone ?></code><?php endif; ?>
                    </small>
                </div>
                <span class="ms-auto badge bg-<?= $waBadge ?> rounded-pill px-3"><?= htmlspecialchars($waStatus) ?></span>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-6 col-md-3">
                    <div class="p-3 bg-success bg-opacity-10 rounded-3 text-center">
                        <div class="fw-bold fs-5 text-success"><?= number_format($waSent) ?></div>
                        <div class="text-muted small">Terkirim</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="p-3 bg-danger bg-opacity-10 rounded-3 text-center">
                        <div class="fw-bold fs-5 text-danger"><?= number_format($waFailed) ?></div>
                        <div class="text-muted small">Gagal</div>
                    </div>
                </div>
                <div class="col-12 col-md-6 d-flex align-items-center justify-content-end gap-2 flex-wrap">
                    <a href="<?= $baseUrl ?>/admin/whatsapp#testSendSection" class="btn btn-sm btn-outline-success rounded-pill">
                        <i class="fa-solid fa-paper-plane me-1"></i> Test Kirim
                    </a>
                    <a href="<?= $baseUrl ?>/admin/whatsapp#omzetReportSection" class="btn btn-sm btn-outline-primary rounded-pill">
                        <i class="fa-solid fa-file-invoice-dollar me-1"></i> Laporan Omzet
                    </a>
                    <a href="<?= $baseUrl ?>/admin/whatsapp" class="btn btn-sm btn-success rounded-pill">
                        <i class="fa-solid fa-sliders me-1"></i> Kelola WhatsApp
                    </a>
                </div>
            </div>
        </div>
    </div>

        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0"><i class="fa-solid fa-shield-halved text-primary me-2"></i> Kontrol Akses Sistem RBAC</h5>
                    <a href="<?= $baseUrl ?>/users" class="btn btn-sm btn-primary rounded-pill">Buka User Management</a>
                </div>
                <p class="text-muted small">
                    Sistem menggunakan Server-Side RBAC Enforcement. Setiap role (`owner`, `admin_owner`, `owner_toko`, `admin_toko`, `kurir`, `customer`) terisolasi secara otomatis oleh `AuthMiddleware`.
                </p>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle text-center small mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Role Slug</th>
                                <th>Nama Display</th>
                                <th>Hak Akses User Management</th>
                                <th>Rute Dashboard</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td><code>owner</code></td><td>Owner Platform</td><td><span class="badge bg-success">Full Access</span></td><td><code>/dashboard/owner</code></td></tr>
                            <tr><td><code>admin_owner</code></td><td>Admin Owner</td><td><span class="badge bg-success">Full Access</span></td><td><code>/dashboard/admin-owner</code></td></tr>
                            <tr><td><code>owner_toko</code></td><td>Owner Toko</td><td><span class="badge bg-secondary">Restricted</span></td><td><code>/dashboard/owner-toko</code></td></tr>
                            <tr><td><code>admin_toko</code></td><td>Admin Toko</td><td><span class="badge bg-secondary">Restricted</span></td><td><code>/dashboard/admin-toko</code></td></tr>
                            <tr><td><code>kurir</code></td><td>Kurir / Jasa Antar</td><td><span class="badge bg-secondary">Restricted</span></td><td><code>/dashboard/kurir</code></td></tr>
                            <tr><td><code>customer</code></td><td>Customer</td><td><span class="badge bg-secondary">Restricted</span></td><td><code>/dashboard/customer</code></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 bg-dark text-white p-4 h-100">
                <h5 class="fw-bold mb-3"><i class="fa-solid fa-crown text-warning me-2"></i> Owner Quick Menu</h5>
                <div class="d-grid gap-2">
                    <a href="<?= $baseUrl ?>/users" class="btn btn-primary rounded-3 text-start fw-semibold py-2">
                        <i class="fa-solid fa-user-plus me-2"></i> Tambah User Baru
                    </a>
                    <a href="<?= $baseUrl ?>/users" class="btn btn-outline-light rounded-3 text-start fw-semibold py-2">
                        <i class="fa-solid fa-users me-2"></i> Lihat Daftar Seluruh User
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/_omzet_transparency_modal.php'; ?>
