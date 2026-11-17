<?php
$showSidebar = true;
$baseUrl = config('app.url', 'http://localhost/rpl/public');
?>

<div class="container-fluid">
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
            <?php else: ?>
                <span class="badge bg-primary text-white px-3 py-1 mb-2 fw-bold"><i class="fa-solid fa-cash-register me-1"></i> ADMIN / KASIR TOKO</span>
            <?php endif; ?>
            <h2 class="fw-bold text-dark mb-1">Selamat Datang, <?= htmlspecialchars($user['name'] ?? 'Admin Toko') ?></h2>
            <p class="text-muted mb-0">Panel Operasional Harian Kasir &amp; Pemrosesan Cucian Toko</p>
        </div>
        <div class="mt-3 mt-md-0 d-flex flex-wrap gap-2">
            <a href="<?= $baseUrl ?>/orders/create" class="btn btn-success rounded-pill btn-sm fw-semibold shadow-sm">
                <i class="fa-solid fa-cart-plus me-1"></i> Input Pesanan Baru
            </a>
            <a href="<?= $baseUrl ?>/store/orders" class="btn btn-primary rounded-pill btn-sm fw-semibold shadow-sm">
                <i class="fa-solid fa-basket-shopping me-1"></i> Daftar Pesanan Toko
            </a>
            <a href="<?= $baseUrl ?>/services" class="btn btn-outline-secondary rounded-pill btn-sm">
                <i class="fa-solid fa-tags me-1"></i> Layanan Toko
            </a>
            <a href="<?= $baseUrl ?>/admin/store-ai-settings" class="btn btn-outline-primary rounded-pill btn-sm fw-semibold shadow-sm">
                <i class="fa-solid fa-robot me-1"></i> Konfigurasi API Chatbot
            </a>
            <a href="<?= $baseUrl ?>/admin/system-config" class="btn btn-outline-secondary rounded-pill btn-sm fw-semibold shadow-sm">
                <i class="fa-solid fa-gear me-1"></i> Konfigurasi Sistem &amp; Integrasi
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
                    <h5 class="fw-bold mb-1">Toko Belum Ditugaskan</h5>
                    <p class="mb-0 small">Akun Anda belum dikaitkan dengan cabang toko laundry manapun. Silakan hubungi Platform Owner.</p>
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
                            <small class="text-<?= $stat['color'] ?> fw-semibold d-block">
                                <i class="fa-solid fa-circle-check me-1"></i> <?= htmlspecialchars($stat['trend']) ?>
                            </small>
                            <?php if (!empty($stat['sub_info'])): ?>
                                <small class="text-muted d-block mt-0.5" style="font-size: 0.75rem;">
                                    <i class="fa-solid fa-clock-rotate-left me-1"></i> <?= htmlspecialchars($stat['sub_info']) ?>
                                </small>
                            <?php endif; ?>
                            <?php if (!empty($stat['link'])): ?>
                                <div class="mt-2">
                                    <span class="badge bg-<?= $stat['color'] ?>-subtle text-<?= $stat['color'] ?> border border-<?= $stat['color'] ?>-subtle rounded-pill small">
                                        <?= str_contains(strtolower($stat['title']), 'omset') ? 'Lihat Detail Omset' : 'Buka Operasional' ?> <i class="fa-solid fa-arrow-right ms-1"></i>
                                    </span>
                                </div>
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

    <!-- =====================================================
         CARD 1: Konfigurasi WhatsApp (Admin Toko)
         Memanfaatkan $waStats yang sudah dikirim oleh DashboardController::adminToko()
         ===================================================== -->
    <?php
    $wa         = $waStats ?? [];
    $waConf     = !empty($wa['is_configured']);
    $waConn     = !empty($wa['device']['connected'] ?? false);
    $waSent     = (int)($wa['sent']   ?? 0);
    $waFailed   = (int)($wa['failed'] ?? 0);
    $waProvider = htmlspecialchars($wa['provider'] ?? 'Fonnte');
    $waPhone    = htmlspecialchars($wa['device']['device'] ?? '—');
    if ($waConf && $waConn)       { $waBadge = 'success'; $waStatus = 'Terhubung (Connected)'; }
    elseif ($waConf && !$waConn)  { $waBadge = 'warning'; $waStatus = 'Siap / Belum Terhubung'; }
    else                          { $waBadge = 'danger';  $waStatus = 'Belum Dikonfigurasi'; }
    ?>
    <div class="row g-4 mb-4">

        <!-- CARD 1: Konfigurasi WhatsApp -->
        <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 p-4 h-100">
                <!-- Header -->
                <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
                    <div class="icon-box bg-success bg-opacity-10 text-success" style="width:48px;height:48px;font-size:1.3rem;">
                        <i class="fa-brands fa-whatsapp"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h5 class="fw-bold mb-0 text-dark">📱 Konfigurasi WhatsApp</h5>
                        <small class="text-muted">
                            Provider: <strong><?= $waProvider ?></strong>
                            <?php if ($waPhone !== '—'): ?>
                                &nbsp;·&nbsp; Nomor: <code><?= $waPhone ?></code>
                            <?php endif; ?>
                            <?php if (!empty($store['name'])): ?>
                                &nbsp;·&nbsp; Toko: <strong><?= htmlspecialchars($store['name']) ?></strong>
                            <?php endif; ?>
                        </small>
                    </div>
                    <span class="badge bg-<?= $waBadge ?> rounded-pill px-3"><?= htmlspecialchars($waStatus) ?></span>
                </div>

                <!-- Statistik pesan -->
                <div class="row g-3 mb-3">
                    <div class="col-6">
                        <div class="p-3 bg-success bg-opacity-10 rounded-3 text-center">
                            <div class="fw-bold fs-5 text-success"><?= number_format($waSent) ?></div>
                            <div class="text-muted small">Pesan Terkirim</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 bg-danger bg-opacity-10 rounded-3 text-center">
                            <div class="fw-bold fs-5 text-danger"><?= number_format($waFailed) ?></div>
                            <div class="text-muted small">Pesan Gagal</div>
                        </div>
                    </div>
                </div>

                <!-- Status konfigurasi -->
                <div class="mb-3">
                    <?php if ($waConf): ?>
                        <div class="d-flex align-items-center gap-2 text-success small">
                            <i class="fa-solid fa-circle-check"></i>
                            <span>Konfigurasi API tersedia</span>
                        </div>
                    <?php else: ?>
                        <div class="d-flex align-items-center gap-2 text-danger small">
                            <i class="fa-solid fa-circle-xmark"></i>
                            <span>Konfigurasi API belum disetel (hubungi Owner)</span>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Tombol aksi -->
                <div class="d-flex flex-wrap gap-2">
                    <a href="<?= $baseUrl ?>/admin/whatsapp" class="btn btn-sm btn-primary rounded-pill">
                        <i class="fa-solid fa-gear me-1"></i> Kelola WhatsApp
                    </a>
                    <a href="<?= $baseUrl ?>/admin/whatsapp#testSendSection" class="btn btn-sm btn-outline-success rounded-pill">
                        <i class="fa-solid fa-paper-plane me-1"></i> Test Kirim
                    </a>
                    <a href="<?= $baseUrl ?>/admin/whatsapp#omzetReportSection" class="btn btn-sm btn-outline-primary rounded-pill">
                        <i class="fa-solid fa-file-invoice-dollar me-1"></i> Laporan Omzet
                    </a>
                </div>
            </div>
        </div>

        <!-- CARD 2: Chat Bot WhatsApp -->
        <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 p-4 h-100">
                <!-- Header -->
                <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
                    <div class="icon-box bg-info bg-opacity-10 text-info" style="width:48px;height:48px;font-size:1.3rem;">
                        <i class="fa-solid fa-robot"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h5 class="fw-bold mb-0 text-dark">🤖 Chat Bot WhatsApp</h5>
                        <small class="text-muted">Balas otomatis pesan masuk dari pelanggan toko Anda</small>
                    </div>
                    <span class="badge bg-secondary rounded-pill px-3">Belum Dikonfigurasi</span>
                </div>

                <!-- Statistik bot (sementara, statis sampai tabel chatbot dibuat) -->
                <div class="row g-3 mb-3">
                    <div class="col-6">
                        <div class="p-3 bg-info bg-opacity-10 rounded-3 text-center">
                            <div class="fw-bold fs-5 text-info">0</div>
                            <div class="text-muted small">Keyword Aktif</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 bg-secondary bg-opacity-10 rounded-3 text-center">
                            <div class="fw-bold fs-5 text-secondary">0</div>
                            <div class="text-muted small">Respons Tersimpan</div>
                        </div>
                    </div>
                </div>

                <!-- Status bot -->
                <div class="mb-3">
                    <div class="d-flex align-items-center gap-2 text-secondary small">
                        <i class="fa-solid fa-circle-info"></i>
                        <span>Status Bot: <strong>Nonaktif</strong> — fitur sedang disiapkan</span>
                    </div>
                </div>

                <!-- Tombol aksi -->
                <div class="d-flex flex-wrap gap-2">
                    <a href="<?= $baseUrl ?>/admin/store-ai-settings" class="btn btn-sm btn-primary rounded-pill">
                        <i class="fa-solid fa-robot me-1"></i> Konfigurasi API Chatbot
                    </a>
                </div>
            </div>
        </div>

    </div><!-- /row WhatsApp cards -->

</div>
