<?php
$showSidebar = true;
$baseUrl     = config('app.url', 'http://localhost/rpl/public');

// ── Helper badge ─────────────────────────────────────────────────────────────
$waBadge   = $waConf && $waConn ? 'success' : ($waConf ? 'warning' : 'danger');
$waStatus  = $waConf && $waConn ? 'Terhubung' : ($waConf ? 'Siap / Belum Terhubung' : 'Belum Dikonfigurasi');
$dbBadge   = $dbStatus === 'Online' ? 'success' : 'danger';
$waSmsSent = (int)($waStats['sent'] ?? 0);
$waSmsFail = (int)($waStats['failed'] ?? 0);
?>

<div class="container-fluid">

    <!-- ══════════════════════════════════════════════════════════════════════
         HEADER
    ══════════════════════════════════════════════════════════════════════ -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-start pb-3 mb-4 border-bottom">
        <div>
            <div class="d-flex flex-wrap gap-2 mb-2">
                <span class="badge bg-primary px-3 py-1 fw-semibold rounded-pill">
                    <i class="fa-solid fa-gear me-1"></i> Pusat Konfigurasi Terpadu
                </span>
                <span class="badge bg-success px-3 py-1 fw-semibold rounded-pill">
                    <i class="fa-solid fa-shield-halved me-1"></i> Mode Aman &amp; RBAC Admin
                </span>
                <?php if (!empty($store['name'])): ?>
                    <span class="badge bg-info text-dark px-3 py-1 fw-semibold rounded-pill">
                        <i class="fa-solid fa-shop me-1"></i> <?= htmlspecialchars($store['name']) ?>
                    </span>
                <?php endif; ?>
            </div>
            <h2 class="fw-bold text-dark mb-1">⚙️ Pengaturan Sistem &amp; Integrasi</h2>
            <p class="text-muted mb-0">Kelola AI Financial Assistant, WhatsApp Gateway (FONNTE), dan spesifikasi lingkungan runtime dalam satu pusat kendali.</p>
        </div>
        <div class="mt-3 mt-md-0 d-flex flex-wrap gap-2">
            <a href="<?= $baseUrl ?>/profile" class="btn btn-sm btn-outline-primary rounded-pill">
                <i class="fa-solid fa-user-gear me-1"></i> Profil Administrator
            </a>
            <a href="<?= $baseUrl ?>/admin/whatsapp" class="btn btn-sm btn-outline-secondary rounded-pill">
                <i class="fa-solid fa-clock-rotate-left me-1"></i> Log Audit Keamanan
            </a>
            <?php if ($isGlobalAdmin): ?>
                <a href="<?= $baseUrl ?>/dashboard/owner" class="btn btn-sm btn-outline-dark rounded-pill">
                    <i class="fa-solid fa-arrow-left me-1"></i> Kembali ke Dashboard
                </a>
            <?php else: ?>
                <a href="<?= $baseUrl ?>/dashboard/admin-toko" class="btn btn-sm btn-outline-dark rounded-pill">
                    <i class="fa-solid fa-arrow-left me-1"></i> Kembali ke Dashboard
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════════
         STATUS SUMMARY — 4 CARD
    ══════════════════════════════════════════════════════════════════════ -->
    <div class="row g-3 mb-4">

        <!-- Card 1: Status Aplikasi -->
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 h-100">
                <div class="d-flex align-items-center gap-3">
                    <div class="icon-box bg-primary bg-opacity-10 text-primary">
                        <i class="fa-solid fa-server"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="text-muted small fw-semibold text-uppercase mb-1">Status Aplikasi</div>
                        <div class="fw-bold text-dark"><?= htmlspecialchars($appName) ?></div>
                        <small class="text-success fw-semibold"><i class="fa-solid fa-circle-check me-1"></i>Berjalan Normal</small>
                        <div class="text-muted" style="font-size:0.72rem; margin-top:2px;">
                            Versi: <?= htmlspecialchars($appVer ?: '1.0.0') ?> &nbsp;·&nbsp; <?= htmlspecialchars(ucfirst($appEnv)) ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card 2: Basis Data -->
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 h-100">
                <div class="d-flex align-items-center gap-3">
                    <div class="icon-box bg-<?= $dbBadge ?> bg-opacity-10 text-<?= $dbBadge ?>">
                        <i class="fa-solid fa-database"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="text-muted small fw-semibold text-uppercase mb-1">Basis Data MySQL</div>
                        <div class="fw-bold text-dark"><?= htmlspecialchars($dbName) ?></div>
                        <small class="text-<?= $dbBadge ?> fw-semibold">
                            <i class="fa-solid fa-circle-<?= $dbStatus === 'Online' ? 'check' : 'xmark' ?> me-1"></i><?= htmlspecialchars($dbStatus) ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card 3: AI Assistant -->
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 h-100">
                <div class="d-flex align-items-center gap-3">
                    <div class="icon-box bg-info bg-opacity-10 text-info">
                        <i class="fa-solid fa-robot"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="text-muted small fw-semibold text-uppercase mb-1">AI Assistant</div>
                        <div class="fw-bold text-dark"><?= htmlspecialchars($aiProvider) ?></div>
                        <small class="text-info fw-semibold"><i class="fa-solid fa-circle-check me-1"></i>Layanan Aktif</small>
                        <div class="text-muted" style="font-size:0.72rem; margin-top:2px;">
                            Model: <?= htmlspecialchars($aiModel ?: 'gemini-2.0-flash') ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card 4: WhatsApp Gateway -->
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 h-100">
                <div class="d-flex align-items-center gap-3">
                    <div class="icon-box bg-<?= $waBadge ?> bg-opacity-10 text-<?= $waBadge ?>">
                        <i class="fa-brands fa-whatsapp"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="text-muted small fw-semibold text-uppercase mb-1">WhatsApp Gateway</div>
                        <div class="fw-bold text-dark text-capitalize"><?= htmlspecialchars($waProvider) ?></div>
                        <small class="text-<?= $waBadge ?> fw-semibold">
                            <i class="fa-solid fa-circle-<?= $waConf && $waConn ? 'check' : ($waConf ? 'exclamation' : 'xmark') ?> me-1"></i><?= htmlspecialchars($waStatus) ?>
                        </small>
                        <?php if ($waPhone !== '—'): ?>
                            <div class="text-muted" style="font-size:0.72rem; margin-top:2px;">
                                <i class="fa-solid fa-phone me-1"></i><code><?= htmlspecialchars($waPhone) ?></code>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    </div><!-- /status summary -->

    <!-- ══════════════════════════════════════════════════════════════════════
         TABS
    ══════════════════════════════════════════════════════════════════════ -->
    <ul class="nav nav-tabs mb-0 border-0" id="configTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active fw-semibold" id="tab-whatsapp" data-bs-toggle="tab" data-bs-target="#panel-whatsapp" type="button" role="tab">
                <i class="fa-brands fa-whatsapp text-success me-1"></i> WhatsApp Gateway
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-semibold" id="tab-ai" data-bs-toggle="tab" data-bs-target="#panel-ai" type="button" role="tab">
                <i class="fa-solid fa-robot text-info me-1"></i> AI Financial Assistant
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-semibold" id="tab-system" data-bs-toggle="tab" data-bs-target="#panel-system" type="button" role="tab">
                <i class="fa-solid fa-shield-halved text-warning me-1"></i> Sistem &amp; Keamanan
            </button>
        </li>
    </ul>

    <div class="tab-content border border-top-0 rounded-bottom-4 shadow-sm bg-white p-4 mb-4" id="configTabsContent">

        <!-- ══════════════════════════════════════════════════════════════
             TAB: WhatsApp Gateway
        ══════════════════════════════════════════════════════════════════ -->
        <div class="tab-pane fade show active" id="panel-whatsapp" role="tabpanel">

            <div class="row g-4">

                <!-- Panel Konfigurasi WA -->
                <div class="col-12 col-lg-7">
                    <div class="card border-0 bg-light rounded-4 p-4 h-100">
                        <div class="d-flex align-items-center gap-3 mb-4">
                            <div class="icon-box bg-success bg-opacity-10 text-success" style="width:44px;height:44px;font-size:1.2rem;">
                                <i class="fa-brands fa-whatsapp"></i>
                            </div>
                            <div>
                                <h5 class="fw-bold mb-0">Konfigurasi WhatsApp Gateway</h5>
                                <small class="text-muted">Provider: <strong>Fonnte</strong></small>
                            </div>
                            <span class="ms-auto badge bg-<?= $waBadge ?> rounded-pill px-3"><?= htmlspecialchars($waStatus) ?></span>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-sm-6">
                                <label class="form-label small fw-semibold text-muted text-uppercase">Status Layanan</label>
                                <div class="form-control-plaintext fw-bold text-<?= $waBadge ?>">
                                    <i class="fa-solid fa-circle-<?= $waConf && $waConn ? 'check' : ($waConf ? 'exclamation' : 'xmark') ?> me-1"></i>
                                    <?= htmlspecialchars($waStatus) ?>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small fw-semibold text-muted text-uppercase">Provider</label>
                                <div class="form-control-plaintext fw-bold text-capitalize">
                                    <i class="fa-brands fa-whatsapp text-success me-1"></i>
                                    <?= htmlspecialchars($waProvider) ?>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small fw-semibold text-muted text-uppercase">API URL</label>
                                <div class="form-control-plaintext fw-semibold small font-monospace text-muted">
                                    https://api.fonnte.com/send
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small fw-semibold text-muted text-uppercase">API Token</label>
                                <div class="d-flex align-items-center gap-2">
                                    <?php if (!empty($maskedToken)): ?>
                                        <code class="text-muted"><?= htmlspecialchars($maskedToken) ?></code>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle small">Tersimpan</span>
                                    <?php else: ?>
                                        <span class="text-danger small"><i class="fa-solid fa-triangle-exclamation me-1"></i>Belum dikonfigurasi</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small fw-semibold text-muted text-uppercase">Nomor WhatsApp</label>
                                <div class="form-control-plaintext fw-bold">
                                    <?php if ($waPhone !== '—'): ?>
                                        <i class="fa-solid fa-phone text-success me-1"></i><code><?= htmlspecialchars($waPhone) ?></code>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small fw-semibold text-muted text-uppercase">Mode Pengiriman</label>
                                <div class="form-control-plaintext">
                                    <?php $mode = $waSettings['mode'] ?? 'TEST'; ?>
                                    <span class="badge bg-<?= $mode === 'PRODUCTION' ? 'danger' : 'warning text-dark' ?> rounded-pill px-3">
                                        <?= htmlspecialchars($mode) ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Statistik pesan -->
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <div class="p-2 bg-success bg-opacity-10 rounded-3 text-center">
                                    <div class="fw-bold fs-5 text-success"><?= number_format($waSmsSent) ?></div>
                                    <div class="text-muted small">Terkirim</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-2 bg-danger bg-opacity-10 rounded-3 text-center">
                                    <div class="fw-bold fs-5 text-danger"><?= number_format($waSmsFail) ?></div>
                                    <div class="text-muted small">Gagal</div>
                                </div>
                            </div>
                        </div>

                        <!-- Tombol Aksi -->
                        <div class="d-flex flex-wrap gap-2">
                            <?php if ($isGlobalAdmin): ?>
                                <a href="<?= $baseUrl ?>/admin/whatsapp" class="btn btn-sm btn-primary rounded-pill">
                                    <i class="fa-solid fa-gear me-1"></i> Kelola Konfigurasi
                                </a>
                            <?php endif; ?>
                            <a href="<?= $baseUrl ?>/admin/whatsapp#testSendSection" class="btn btn-sm btn-outline-success rounded-pill">
                                <i class="fa-solid fa-paper-plane me-1"></i> Kirim Test WhatsApp
                            </a>
                            <a href="<?= $baseUrl ?>/admin/whatsapp" class="btn btn-sm btn-outline-secondary rounded-pill" id="btnCheckDevice">
                                <i class="fa-solid fa-plug me-1"></i> Test Koneksi
                            </a>
                            <a href="<?= $baseUrl ?>/admin/whatsapp" class="btn btn-sm btn-outline-primary rounded-pill">
                                <i class="fa-solid fa-clock-rotate-left me-1"></i> Riwayat Pesan
                            </a>
                        </div>

                        <?php if (!$isGlobalAdmin): ?>
                            <div class="mt-3 alert alert-info rounded-3 py-2 small mb-0">
                                <i class="fa-solid fa-circle-info me-1"></i>
                                Sebagai Admin Toko, Anda dapat test kirim dan melihat riwayat. Untuk mengubah konfigurasi global, hubungi Owner.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Panel Chat Bot WA -->
                <div class="col-12 col-lg-5">
                    <div class="card border-0 bg-light rounded-4 p-4 h-100">
                        <div class="d-flex align-items-center gap-3 mb-4">
                            <div class="icon-box bg-info bg-opacity-10 text-info" style="width:44px;height:44px;font-size:1.2rem;">
                                <i class="fa-solid fa-robot"></i>
                            </div>
                            <div>
                                <h5 class="fw-bold mb-0">Chat Bot WhatsApp</h5>
                                <small class="text-muted">Balas otomatis pesan masuk pelanggan</small>
                            </div>
                            <span class="ms-auto badge bg-<?= !empty($botEnabled) ? 'success' : 'secondary' ?> rounded-pill px-3">
                                <?= !empty($botEnabled) ? 'Aktif' : 'Nonaktif' ?>
                            </span>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <div class="p-3 bg-info bg-opacity-10 rounded-3 text-center">
                                    <div class="fw-bold fs-4 text-info"><?= number_format($activeKwCount ?? 0) ?></div>
                                    <div class="text-muted small">Keyword Aktif</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-3 bg-secondary bg-opacity-10 rounded-3 text-center">
                                    <div class="fw-bold fs-4 text-secondary"><?= number_format($totalKwCount ?? 0) ?></div>
                                    <div class="text-muted small">Respons Tersimpan</div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="d-flex align-items-center gap-2 text-muted small">
                                <i class="fa-solid fa-circle-<?= !empty($botEnabled) ? 'check text-success' : 'xmark text-secondary' ?>"></i>
                                <span>Status Bot Toko: <strong><?= !empty($botEnabled) ? 'Aktif Menjawab' : 'Nonaktif' ?></strong></span>
                            </div>
                            <div class="d-flex align-items-center gap-2 text-muted small mt-1">
                                <i class="fa-solid fa-circle-info text-info"></i>
                                <span>Kelola kata kunci pemicu dan jawaban balasan otomatis pesan WhatsApp.</span>
                            </div>
                        </div>

                        <div class="d-flex flex-wrap gap-2">
                            <a href="<?= $baseUrl ?>/admin/whatsapp-bot" class="btn btn-sm btn-primary rounded-pill px-3 shadow-sm">
                                <i class="fa-solid fa-robot me-1"></i> Kelola Chat Bot Toko
                            </a>
                        </div>
                    </div>
                </div>

            </div><!-- /row wa panels -->
        </div><!-- /tab-pane whatsapp -->

        <!-- ══════════════════════════════════════════════════════════════
             TAB: AI Financial Assistant (placeholder)
        ══════════════════════════════════════════════════════════════════ -->
        <div class="tab-pane fade" id="panel-ai" role="tabpanel">
            <div class="text-center py-5">
                <div class="icon-box bg-info bg-opacity-10 text-info mx-auto mb-3" style="width:64px;height:64px;font-size:1.8rem;">
                    <i class="fa-solid fa-robot"></i>
                </div>
                <h5 class="fw-bold mb-2">AI Financial Assistant</h5>
                <p class="text-muted mb-3">
                    Provider: <strong><?= htmlspecialchars($aiProvider) ?></strong> &nbsp;·&nbsp;
                    Model: <strong><?= htmlspecialchars($aiModel ?: 'gemini-2.0-flash') ?></strong>
                </p>
                <?php if ($isGlobalAdmin): ?>
                    <a href="<?= $baseUrl ?>/admin/ai-settings" class="btn btn-primary rounded-pill px-4">
                        <i class="fa-solid fa-gear me-1"></i> Buka Pengaturan AI
                    </a>
                <?php else: ?>
                    <div class="alert alert-info d-inline-block rounded-3 px-4 py-2 small">
                        <i class="fa-solid fa-circle-info me-1"></i>
                        Pengaturan AI dikelola oleh Owner Platform. Hubungi Owner untuk mengubah konfigurasi AI.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ══════════════════════════════════════════════════════════════
             TAB: Sistem & Keamanan (placeholder)
        ══════════════════════════════════════════════════════════════════ -->
        <div class="tab-pane fade" id="panel-system" role="tabpanel">
            <div class="row g-4 py-2">
                <div class="col-md-6">
                    <div class="card border-0 bg-light rounded-4 p-4">
                        <h6 class="fw-bold mb-3"><i class="fa-solid fa-circle-info text-primary me-2"></i>Informasi Sistem</h6>
                        <table class="table table-sm mb-0">
                            <tr><td class="text-muted">Aplikasi</td><td class="fw-semibold"><?= htmlspecialchars($appName) ?></td></tr>
                            <tr><td class="text-muted">Environment</td><td><span class="badge bg-<?= $appEnv === 'production' ? 'danger' : 'warning text-dark' ?> rounded-pill"><?= htmlspecialchars(ucfirst($appEnv)) ?></span></td></tr>
                            <tr><td class="text-muted">Database</td><td class="fw-semibold"><?= htmlspecialchars($dbName) ?></td></tr>
                            <tr><td class="text-muted">Status DB</td><td><span class="badge bg-<?= $dbBadge ?> rounded-pill"><?= htmlspecialchars($dbStatus) ?></span></td></tr>
                            <tr><td class="text-muted">Role Aktif</td><td><span class="badge bg-primary rounded-pill"><?= htmlspecialchars($role) ?></span></td></tr>
                            <?php if (!empty($store['name'])): ?>
                                <tr><td class="text-muted">Toko</td><td class="fw-semibold"><?= htmlspecialchars($store['name']) ?></td></tr>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card border-0 bg-light rounded-4 p-4">
                        <h6 class="fw-bold mb-3"><i class="fa-solid fa-shield-halved text-warning me-2"></i>Keamanan RBAC</h6>
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <i class="fa-solid fa-circle-check text-success"></i>
                            <span class="small">Autentikasi Server-Side Aktif</span>
                        </div>
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <i class="fa-solid fa-circle-check text-success"></i>
                            <span class="small">Role-Based Access Control (RBAC) Aktif</span>
                        </div>
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <i class="fa-solid fa-circle-check text-success"></i>
                            <span class="small">Token API dienkripsi (tidak ditampilkan plaintext)</span>
                        </div>
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <i class="fa-solid fa-circle-check text-success"></i>
                            <span class="small">CSRF Protection Aktif</span>
                        </div>
                        <?php if ($isGlobalAdmin): ?>
                            <div class="mt-3">
                                <a href="<?= $baseUrl ?>/admin/whatsapp" class="btn btn-sm btn-outline-warning rounded-pill">
                                    <i class="fa-solid fa-clock-rotate-left me-1"></i> Lihat Log Audit
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    </div><!-- /tab-content -->

</div><!-- /container-fluid -->

