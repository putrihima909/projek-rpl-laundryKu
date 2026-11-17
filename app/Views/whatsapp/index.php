<?php
$showSidebar = true;
$baseUrl = config('app.url', 'http://localhost/rpl/public');
$isConfigured = !empty($stats['is_configured']);
$device = $deviceInfo ?? ($stats['device'] ?? null);
$isConnected = !empty($device['connected']);
$mode = strtoupper($settings['mode'] ?? 'TEST');
$isTestMode = ($mode === 'TEST');
?>

<div class="container-fluid pb-5">
    <!-- Banner Khusus Admin Toko (Store-Level Isolation) -->
    <?php if (!empty($activeStoreId) && !empty($storeInfo)): ?>
        <div class="alert alert-info border-0 shadow-sm rounded-4 mb-4 p-3 d-flex align-items-center">
            <i class="fa-solid fa-store fa-2x me-3 text-info"></i>
            <div>
                <h6 class="fw-bold mb-0 text-dark">Data Dibatasi untuk Toko: <?= htmlspecialchars($storeInfo['name']) ?></h6>
                <small class="text-secondary">Anda login sebagai <strong>Admin Toko</strong>. Riwayat pengiriman, statistik, dan laporan omset di bawah hanya menampilkan aktivitas untuk toko Anda.</small>
            </div>
        </div>
    <?php endif; ?>

    <!-- Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center pb-3 mb-4 border-bottom">
        <div>
            <div class="d-flex align-items-center gap-2 mb-2">
                <span class="badge bg-success text-white px-3 py-1 fw-bold">
                    <i class="fa-brands fa-whatsapp me-1"></i> WHATSAPP GATEWAY & CLOUD API
                </span>
                <?php if ($isTestMode && !empty($isGlobalAdmin)): ?>
                    <span class="badge bg-warning text-dark border border-warning px-2.5 py-1 rounded-pill small fw-semibold">
                        <i class="fa-solid fa-vial me-1"></i> Mode: TEST
                    </span>
                <?php endif; ?>
            </div>
            <h2 class="fw-bold text-dark mb-1">📱 WhatsApp Manager & Notifikasi</h2>
            <p class="text-muted mb-0">Kelola gateway WhatsApp, kredensial Cloud API, test mode, laporan omset, dan riwayat transmisi.</p>
        </div>
        <div class="mt-3 mt-md-0 d-flex gap-2">
            <a href="#omzetReportSection" class="btn btn-outline-success rounded-pill px-3 shadow-sm fw-semibold">
                <i class="fa-solid fa-file-invoice-dollar me-1"></i> Laporan Omset WA
            </a>
            <a href="#configSection" class="btn btn-outline-primary rounded-pill px-3 shadow-sm fw-semibold">
                <i class="fa-solid fa-gear me-1"></i> Konfigurasi API
            </a>
            <a href="#testSendSection" class="btn btn-success rounded-pill px-4 shadow-sm fw-bold">
                <i class="fa-solid fa-paper-plane me-1"></i> Test Kirim WhatsApp
            </a>
        </div>
    </div>

    <!-- Alert Flash Messages -->
    <?php if ($flashSuccess = \App\Helpers\Session::flash('success')): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-4 shadow-sm" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i> <?= htmlspecialchars($flashSuccess) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($flashError = \App\Helpers\Session::flash('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-4 shadow-sm" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i> <?= htmlspecialchars($flashError) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- 1. STATUS RINGKASAN WHATSAPP SESUAI SPESIFIKASI -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-light">
        <div class="card-body p-4">
            <div class="row g-3 align-items-center">
                <div class="col-12 col-md-3 border-end-md">
                    <small class="text-muted text-uppercase fw-semibold d-block mb-1" style="font-size: 0.75rem;">Status WhatsApp</small>
                    <?php if ($isTestMode): ?>
                        <span class="badge bg-warning text-dark fs-6 px-3 py-2 rounded-pill"><i class="fa-solid fa-vial me-1"></i> TEST MODE</span>
                    <?php elseif ($isConfigured && $isConnected): ?>
                        <span class="badge bg-success fs-6 px-3 py-2 rounded-pill"><i class="fa-solid fa-link me-1"></i> CONNECTED</span>
                    <?php elseif ($isConfigured): ?>
                        <span class="badge bg-info text-dark fs-6 px-3 py-2 rounded-pill"><i class="fa-solid fa-circle-check me-1"></i> READY / STANDBY</span>
                    <?php else: ?>
                        <span class="badge bg-secondary fs-6 px-3 py-2 rounded-pill"><i class="fa-solid fa-plug-circle-xmark me-1"></i> NOT CONFIGURED</span>
                    <?php endif; ?>
                </div>
                <div class="col-12 col-md-3 border-end-md">
                    <small class="text-muted text-uppercase fw-semibold d-block mb-1" style="font-size: 0.75rem;">Nomor WhatsApp</small>
                    <h6 class="fw-bold text-dark mb-0 font-monospace">
                        <i class="fa-solid fa-phone text-success me-1"></i>
                        <?= htmlspecialchars($settings['phone_number'] ?? ($device['device'] ?? '081234567890')) ?>
                    </h6>
                </div>
                <div class="col-12 col-md-3 border-end-md">
                    <small class="text-muted text-uppercase fw-semibold d-block mb-1" style="font-size: 0.75rem;">Fonnte API Token</small>
                    <h6 class="fw-bold text-dark mb-0 font-monospace">
                        <?= !empty($maskedToken) ? htmlspecialchars($maskedToken) : '<span class="text-danger small">Belum diisi di .env</span>' ?>
                    </h6>
                </div>
                <div class="col-12 col-md-3 text-md-end">
                    <small class="text-muted text-uppercase fw-semibold d-block mb-1" style="font-size: 0.75rem;">Status API Gateway</small>
                    <span class="badge <?= $isTestMode ? 'bg-secondary' : 'bg-success' ?> rounded-pill px-3 py-1.5 fw-semibold">
                        <i class="fa-solid fa-server me-1"></i> <?= $isTestMode ? 'Simulasi Test Aktif' : 'Gateway Live Aktif' ?>
                    </span>
                    <div class="mt-2">
                        <a href="#testSendSection" class="btn btn-sm btn-success rounded-pill px-3 fw-bold">
                            <i class="fa-solid fa-paper-plane me-1"></i> Test Kirim WhatsApp
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Metrics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle p-3 bg-success-subtle text-success me-3">
                        <i class="fa-solid fa-circle-check fa-xl"></i>
                    </div>
                    <div>
                        <small class="text-muted text-uppercase fw-semibold" style="font-size: 0.72rem;">Pesan Terkirim (Sent)</small>
                        <h4 class="fw-bold mb-0 text-dark"><?= number_format($stats['sent'] ?? 0) ?></h4>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle p-3 bg-warning-subtle text-warning me-3">
                        <i class="fa-solid fa-vial fa-xl"></i>
                    </div>
                    <div>
                        <small class="text-muted text-uppercase fw-semibold" style="font-size: 0.72rem;">Simulasi Test (Test Sent)</small>
                        <?php
                            $testCount = 0;
                            foreach ($logs as $l) {
                                if (($l['status'] ?? '') === 'test_sent') $testCount++;
                            }
                        ?>
                        <h4 class="fw-bold mb-0 text-dark"><?= number_format($testCount) ?></h4>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle p-3 bg-danger-subtle text-danger me-3">
                        <i class="fa-solid fa-circle-xmark fa-xl"></i>
                    </div>
                    <div>
                        <small class="text-muted text-uppercase fw-semibold" style="font-size: 0.72rem;">Pesan Gagal (Failed)</small>
                        <h4 class="fw-bold mb-0 text-dark"><?= number_format($stats['failed'] ?? 0) ?></h4>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle p-3 bg-primary-subtle text-primary me-3">
                        <i class="fa-solid fa-clock-rotate-left fa-xl"></i>
                    </div>
                    <div>
                        <small class="text-muted text-uppercase fw-semibold" style="font-size: 0.72rem;">Kirim Terakhir</small>
                        <div class="fw-bold text-dark" style="font-size: 0.85rem;">
                            <?= !empty($stats['last_sent_at']) ? htmlspecialchars($stats['last_sent_at']) : '<span class="text-muted">Belum ada pengiriman</span>' ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. BAGIAN KONFIGURASI WHATSAPP FONNTE API -->
    <div class="card border-0 shadow-sm rounded-4 mb-4" id="configSection">
        <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-sliders text-success me-2"></i> Konfigurasi API Fonnte Gateway</h5>
            <span class="badge bg-light text-dark border">Provider Utama: FONNTE</span>
        </div>
        <div class="card-body p-4">
            <?php if (!empty($isGlobalAdmin)): ?>
            <form action="<?= $baseUrl ?>/admin/whatsapp/settings" method="POST">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

                <div class="row g-3">
                    <div class="col-12 col-md-4">
                        <label class="form-label fw-bold text-dark small">Mode Operasional <span class="text-danger">*</span></label>
                        <select class="form-select" name="mode" required>
                            <option value="TEST" <?= $isTestMode ? 'selected' : '' ?>>TEST (Simulasi Log TEST_SENT Tanpa Kuota)</option>
                            <option value="PRODUCTION" <?= !$isTestMode ? 'selected' : '' ?>>PRODUCTION (Kirim Nyata via Fonnte API)</option>
                        </select>
                        <small class="text-muted" style="font-size: 0.72rem;">Pada mode TEST, pesan dicatat sebagai <code>TEST_SENT</code> di database.</small>
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label fw-bold text-dark small">Nomor WhatsApp Pengirim / Device</label>
                        <input type="text" class="form-control font-monospace" name="phone_number" 
                               value="<?= htmlspecialchars($settings['phone_number'] ?? ($device['device'] ?? '081234567890')) ?>" 
                               placeholder="Contoh: 081234567890">
                        <small class="text-muted" style="font-size: 0.72rem;">Nomor perangkat WhatsApp yang tertaut di Fonnte.</small>
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label fw-bold text-dark small">Fonnte API Endpoint</label>
                        <input type="text" class="form-control font-monospace bg-light" readonly
                               value="<?= htmlspecialchars(config('whatsapp.fonnte.api_url', 'https://api.fonnte.com/send')) ?>">
                        <small class="text-muted" style="font-size: 0.72rem;">Endpoint resmi pengiriman pesan Fonnte.</small>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-bold text-dark small">Fonnte API Token</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="fa-solid fa-key text-muted"></i></span>
                            <input type="password" class="form-control font-monospace" name="access_token" id="accessTokenInput"
                                   value="<?= htmlspecialchars($maskedToken) ?>" 
                                   placeholder="Masukkan Token Fonnte baru jika ingin mengganti...">
                            <button class="btn btn-outline-secondary" type="button" onclick="toggleTokenVisibility()">
                                <i class="fa-solid fa-eye" id="toggleTokenIcon"></i>
                            </button>
                        </div>
                        <small class="text-muted" style="font-size: 0.72rem;">
                            <i class="fa-solid fa-shield-halved text-success me-1"></i> Token dibaca dari <code>.env (WHATSAPP_API_TOKEN)</code> dan disimpan terenkripsi/masked demi keamanan.
                        </small>
                    </div>

                    <div class="col-12 text-end pt-2">
                        <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm">
                            <i class="fa-solid fa-floppy-disk me-1"></i> Simpan Pengaturan
                        </button>
                    </div>
                </div>
            </form>
            <?php else: ?>
                <!-- Read-only view for Admin Toko -->
                <div class="alert alert-secondary border-0 rounded-4 p-3 mb-3 d-flex align-items-center">
                    <i class="fa-solid fa-lock text-muted fs-4 me-3"></i>
                    <div>
                        <strong class="text-dark">Pengaturan API Dikelola oleh Platform Owner</strong>
                        <p class="mb-0 small text-muted">Sebagai Admin Toko, Anda memiliki hak penuh untuk melakukan uji coba kirim pesan dan mengirim laporan omset toko, namun tidak dapat mengubah token atau mode global sistem.</p>
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-12 col-md-4">
                        <label class="form-label fw-bold text-muted small">Mode Aktif Sistem</label>
                        <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($settings['mode'] ?? 'TEST') ?>" readonly>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label fw-bold text-muted small">Nomor WhatsApp Pengirim</label>
                        <input type="text" class="form-control bg-light font-monospace" value="<?= htmlspecialchars($settings['phone_number'] ?? '081234567890') ?>" readonly>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label fw-bold text-muted small">Provider Gateway</label>
                        <input type="text" class="form-control bg-light" value="Fonnte API (Resmi)" readonly>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 3. FITUR LAPORAN OMSET WHATSAPP (HARIAN / MINGGUAN / BULANAN) -->
    <div class="card border-0 shadow-sm rounded-4 mb-4" id="omzetReportSection">
        <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
            <div>
                <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-file-invoice-dollar text-success me-2"></i> Laporan Omset WhatsApp</h5>
                <small class="text-muted">Kirimkan ringkasan keuangan tervalidasi langsung ke nomor WhatsApp Owner / Admin Owner.</small>
            </div>
            <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1 rounded-pill fw-semibold">
                Khusus Owner & Admin Owner
            </span>
        </div>
        <div class="card-body p-4">
            <div class="row g-4">
                <!-- Preview Kartu Omset -->
                <div class="col-12 col-lg-8">
                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <div class="p-3 bg-light rounded-4 border">
                                <span class="badge bg-primary mb-2">Harian (Hari Ini)</span>
                                <h5 class="fw-bold text-dark mb-1">Rp <?= number_format($omzetSummary['today']['total_omset'] ?? 0, 0, ',', '.') ?></h5>
                                <small class="text-muted d-block"><?= number_format($omzetSummary['today']['total_transaksi'] ?? 0) ?> Transaksi Terbayar</small>
                            </div>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="p-3 bg-light rounded-4 border">
                                <span class="badge bg-info text-dark mb-2">Mingguan (7 Hari)</span>
                                <h5 class="fw-bold text-dark mb-1">Rp <?= number_format($omzetSummary['week']['total_omset'] ?? 0, 0, ',', '.') ?></h5>
                                <small class="text-muted d-block"><?= number_format($omzetSummary['week']['total_transaksi'] ?? 0) ?> Transaksi Terbayar</small>
                            </div>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="p-3 bg-light rounded-4 border">
                                <span class="badge bg-success mb-2">Bulanan (Bulan Ini)</span>
                                <h5 class="fw-bold text-dark mb-1">Rp <?= number_format($omzetSummary['month']['total_omset'] ?? 0, 0, ',', '.') ?></h5>
                                <small class="text-muted d-block"><?= number_format($omzetSummary['month']['total_transaksi'] ?? 0) ?> Transaksi Terbayar</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Form Trigger Kirim Laporan -->
                <div class="col-12 col-lg-4 border-start-lg">
                    <form action="<?= $baseUrl ?>/admin/whatsapp/send-omzet-report" method="POST">
                        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark small">Pilihan Periode Laporan</label>
                            <select class="form-select form-select-sm" name="period" required>
                                <option value="today">Laporan Harian (Hari Ini)</option>
                                <option value="week">Laporan Mingguan (7 Hari Terakhir)</option>
                                <option value="month">Laporan Bulanan (Bulan Ini)</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark small">Nomor WhatsApp Tujuan</label>
                            <input type="text" class="form-control form-control-sm font-monospace" name="recipient_phone" 
                                   value="<?= htmlspecialchars($user['phone'] ?? '081234567890') ?>" required>
                            <small class="text-muted" style="font-size: 0.7rem;">Default ke nomor akun Anda saat ini.</small>
                        </div>

                        <button type="submit" class="btn btn-outline-success btn-sm w-100 rounded-pill fw-bold py-2 shadow-sm">
                            <i class="fa-solid fa-paper-plane me-1"></i> 📤 Kirim Laporan Omset via WhatsApp
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- 4. TEST SEND PANEL -->
    <div class="card border-0 shadow-sm rounded-4 mb-4" id="testSendSection">
        <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-dark">
                <i class="fa-solid fa-paper-plane text-success me-2"></i> Uji Coba Pengiriman Pesan (Test Kirim WhatsApp)
            </h5>
            <span class="badge <?= $isTestMode ? 'bg-warning text-dark' : 'bg-danger text-white' ?>">
                <i class="fa-solid <?= $isTestMode ? 'fa-vial' : 'fa-bolt' ?> me-1"></i> <?= $isTestMode ? 'Simulasi Test Mode Aktif' : 'Pesan Nyata (Live Production)' ?>
            </span>
        </div>
        <div class="card-body p-4">
            <form action="<?= $baseUrl ?>/admin/whatsapp/test-send" method="POST" id="formTestSend">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

                <div class="row g-3 mb-3">
                    <div class="col-12 col-md-4">
                        <label class="form-label fw-bold text-dark small">Nomor WhatsApp Penerima <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted"><i class="fa-solid fa-phone"></i></span>
                            <input type="text" class="form-control" name="phone" id="testPhoneInput" 
                                   placeholder="Contoh: 081234567890 atau 628123456789" required>
                        </div>
                        <small class="text-muted" style="font-size: 0.72rem;">Nomor otomatis dinormalisasi ke format internasional (+62).</small>
                    </div>

                    <div class="col-12 col-md-8">
                        <label class="form-label fw-bold text-dark small">Template Pesan Otomatis</label>
                        <select class="form-select" id="templateSelect" onchange="applyTemplate()">
                            <option value="">-- Pilih Template Pesan Siap Pakai --</option>
                            <?php if (!empty($quickTemplates)): ?>
                                <?php foreach ($quickTemplates as $k => $tpl): ?>
                                    <option value="<?= htmlspecialchars($k) ?>"><?= htmlspecialchars($tpl['label']) ?></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                        <small class="text-muted" style="font-size: 0.72rem;">Pilih template untuk mengisi pesan secara otomatis. Anda tetap dapat mengedit teksnya.</small>
                    </div>
                </div>

                <!-- Parameter Opsional Placeholder Template -->
                <div class="p-3 bg-light rounded-4 border mb-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <small class="fw-bold text-dark"><i class="fa-solid fa-wand-magic-sparkles text-primary me-1"></i> Parameter Placeholder Template (Opsional)</small>
                        <small class="text-muted" style="font-size: 0.72rem;">Otomatis menggantikan <code>{nama_pelanggan}</code>, <code>{kode_pesanan}</code>, <code>{nama_toko}</code>, <code>{total}</code>, <code>{tanggal}</code></small>
                    </div>
                    <div class="row g-2">
                        <div class="col-6 col-md-3">
                            <input type="text" class="form-control form-control-sm" name="nama_pelanggan" id="paramCustomer" 
                                   placeholder="Nama Pelanggan" oninput="updatePlaceholderPreview()">
                        </div>
                        <div class="col-6 col-md-3">
                            <input type="text" class="form-control form-control-sm" name="kode_pesanan" id="paramOrderCode" 
                                   placeholder="Kode Pesanan (mis. ORD001)" oninput="updatePlaceholderPreview()">
                        </div>
                        <div class="col-6 col-md-3">
                            <input type="text" class="form-control form-control-sm" name="total" id="paramTotal" 
                                   placeholder="Total Biaya (mis. Rp 35.000)" oninput="updatePlaceholderPreview()">
                        </div>
                        <div class="col-6 col-md-3">
                            <?php if (!empty($storeInfo)): ?>
                                <!-- Admin Toko: Nama Toko dikunci ke toko miliknya -->
                                <input type="text" class="form-control form-control-sm bg-white" name="store_name" id="paramStoreName" 
                                       value="<?= htmlspecialchars($storeInfo['name']) ?>" readonly title="Toko Anda">
                            <?php elseif (!empty($allStores)): ?>
                                <!-- Platform Owner: Bisa memilih toko atau mengetik -->
                                <select class="form-select form-select-sm" name="store_name" id="paramStoreName" onchange="updatePlaceholderPreview()">
                                    <?php foreach ($allStores as $s): ?>
                                        <option value="<?= htmlspecialchars($s['name']) ?>"><?= htmlspecialchars($s['name']) ?></option>
                                    <?php endforeach; ?>
                                    <option value="LaundryKu">LaundryKu Platform</option>
                                </select>
                            <?php else: ?>
                                <input type="text" class="form-control form-control-sm" name="store_name" id="paramStoreName" 
                                       value="LaundryKu" placeholder="Nama Toko" oninput="updatePlaceholderPreview()">
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-bold text-dark small">Isi Pesan WhatsApp</label>
                        <textarea class="form-control font-monospace" name="message" id="testMessageInput" rows="4" 
                                  placeholder="Pilih template di atas atau ketik pesan manual di sini..." oninput="updateCharCount()"></textarea>
                        <div class="d-flex justify-content-between align-items-center mt-1">
                            <small class="text-muted" style="font-size: 0.72rem;">
                                <i class="fa-solid fa-circle-info me-1"></i> Teks di atas akan dikirim persis apa adanya setelah placeholder diganti.
                            </small>
                            <span class="badge bg-light text-muted border font-monospace" id="charCountBadge" style="font-size: 0.75rem;">
                                0 / 2000 karakter
                            </span>
                        </div>
                    </div>

                    <div class="col-12 d-flex flex-wrap align-items-center justify-content-between gap-2 pt-2">
                        <div class="form-check mb-0">
                            <input class="form-check-input" type="checkbox" name="force_send" value="1" id="chkForceSend"
                                <?= $isTestMode ? '' : 'checked' ?>>
                            <label class="form-check-label small fw-semibold" for="chkForceSend">
                                🔥 Kirim Langsung ke Fonnte API
                                <span class="text-muted fw-normal">(abaikan TEST MODE — gunakan kuota API nyata)</span>
                            </label>
                        </div>
                        <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm" id="btnSubmitTest">
                            <i class="fa-solid fa-paper-plane me-1"></i> Test Kirim WhatsApp
                        </button>
                    </div>
                </div>
            </form>
            <?php if ($isTestMode): ?>
            <div class="alert alert-warning border-0 rounded-3 mt-3 py-2 px-3 small mb-0">
                <i class="fa-solid fa-vial me-1"></i>
                <strong>Mode aktif: TEST.</strong>
                Tanpa centang "Kirim Langsung ke Fonnte API", pesan hanya disimulasikan (log <code>TEST_SENT</code>).
                Centang opsi tersebut untuk pengiriman nyata ke nomor WhatsApp tujuan via Fonnte.
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 5. RIWAYAT NOTIFIKASI WHATSAPP SESUAI SPESIFIKASI -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
            <div>
                <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-clock-rotate-left text-primary me-2"></i> Riwayat Notifikasi WhatsApp</h5>
                <small class="text-muted">Menampilkan daftar pengiriman notifikasi dari tabel <code>whatsapp_logs</code>.</small>
            </div>
            <span class="badge bg-light text-dark border">
                Total Riwayat: <?= number_format($stats['total'] ?? 0) ?>
            </span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4" style="width: 140px;">Tanggal</th>
                        <th>Nomor</th>
                        <th>Jenis Notifikasi</th>
                        <th>Pesan</th>
                        <th>Status</th>
                        <th>Response/Error</th>
                        <th class="text-end pe-4">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-inbox fa-2x mb-2 d-block text-secondary"></i>
                                Belum ada riwayat notifikasi WhatsApp tercatat.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <?php
                                $statusUpper = strtoupper($log['status'] ?? 'PENDING');
                                if ($statusUpper === 'TEST_SENT') {
                                    $statusBadge = 'bg-secondary text-white'; // Abu-abu untuk simulasi test mode
                                } elseif ($statusUpper === 'SENT') {
                                    $statusBadge = 'bg-success text-white';   // Hijau untuk pengiriman nyata sukses
                                } elseif ($statusUpper === 'FAILED') {
                                    $statusBadge = 'bg-danger text-white';    // Merah untuk pengiriman gagal
                                } elseif ($statusUpper === 'SKIPPED') {
                                    $statusBadge = 'bg-secondary text-white';
                                } else {
                                    $statusBadge = 'bg-info text-dark';
                                }

                                $respJson = is_array($log['provider_response'] ?? null) 
                                    ? json_encode($log['provider_response']) 
                                    : (string)($log['provider_response'] ?? '-');
                            ?>
                            <tr>
                                <td class="ps-4 small text-muted text-nowrap">
                                    <?= htmlspecialchars($log['sent_at'] ?? $log['created_at']) ?>
                                </td>
                                <td>
                                    <div class="fw-bold text-nowrap font-monospace"><?= htmlspecialchars(\App\Helpers\PhoneHelper::formatNational($log['recipient_phone']) ?? $log['recipient_phone']) ?></div>
                                    <small class="text-muted d-block" style="font-size: 0.72rem;"><?= htmlspecialchars($log['user_name'] ?? 'Role: ' . ($log['recipient_role'] ?? '-')) ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border font-monospace" style="font-size: 0.72rem;">
                                        <?= htmlspecialchars($log['event_type']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="text-truncate" style="max-width: 250px;" title="<?= htmlspecialchars($log['message']) ?>">
                                        <?= htmlspecialchars($log['message']) ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge <?= $statusBadge ?> fw-semibold" style="font-size: 0.72rem;">
                                        <?= htmlspecialchars($statusUpper) ?>
                                    </span>
                                    <?php if ((int)$log['attempts'] > 1): ?>
                                        <small class="text-muted d-block" style="font-size: 0.65rem;">(<?= $log['attempts'] ?>x coba)</small>
                                    <?php endif; ?>
                                </td>
                                <td class="font-monospace small text-truncate" style="max-width: 200px;" title="<?= htmlspecialchars($respJson) ?>">
                                    <?= htmlspecialchars($respJson) ?>
                                </td>
                                <td class="text-end pe-4 text-nowrap">
                                    <?php if ($log['status'] === 'failed'): ?>
                                        <form action="<?= $baseUrl ?>/admin/whatsapp/retry" method="POST" class="d-inline">
                                            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                            <input type="hidden" name="log_id" value="<?= (int)$log['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-2 py-0" title="Coba Kirim Ulang">
                                                <i class="fa-solid fa-rotate-right me-1"></i> Retry
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted" style="font-size: 0.75rem;">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
const rawTemplates = <?= json_encode($quickTemplates ?? [], JSON_UNESCAPED_UNICODE) ?>;

function getPlaceholderValues() {
    const customerEl = document.getElementById('paramCustomer');
    const orderCodeEl = document.getElementById('paramOrderCode');
    const totalEl = document.getElementById('paramTotal');
    const storeEl = document.getElementById('paramStoreName');

    const customer = (customerEl && customerEl.value.trim()) ? customerEl.value.trim() : 'Pelanggan';
    const orderCode = (orderCodeEl && orderCodeEl.value.trim()) ? orderCodeEl.value.trim() : '-';
    const total = (totalEl && totalEl.value.trim()) ? totalEl.value.trim() : '-';
    const storeName = (storeEl && storeEl.value.trim()) ? storeEl.value.trim() : 'LaundryKu';

    const now = new Date();
    const day = String(now.getDate()).padStart(2, '0');
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const year = now.getFullYear();
    const dateFormatted = `${day}/${month}/${year}`;

    return {
        '{nama_pelanggan}': customer,
        '{kode_pesanan}': orderCode,
        '{total}': total,
        '{nama_toko}': storeName,
        '{tanggal}': dateFormatted
    };
}

let activeRawTemplateText = "";

function applyTemplate() {
    const select = document.getElementById('templateSelect');
    const val = select.value;
    const textarea = document.getElementById('testMessageInput');

    if (!val || !rawTemplates[val]) {
        activeRawTemplateText = "";
        return;
    }

    activeRawTemplateText = rawTemplates[val].text || "";
    renderTemplateToTextarea();
}

function updatePlaceholderPreview() {
    if (activeRawTemplateText) {
        renderTemplateToTextarea();
    }
}

function renderTemplateToTextarea() {
    const textarea = document.getElementById('testMessageInput');
    if (!textarea) return;

    let text = activeRawTemplateText;
    const values = getPlaceholderValues();

    for (const [placeholder, val] of Object.entries(values)) {
        text = text.replaceAll(placeholder, val);
    }

    textarea.value = text;
    updateCharCount();
}

function updateCharCount() {
    const textarea = document.getElementById('testMessageInput');
    const badge = document.getElementById('charCountBadge');
    if (!textarea || !badge) return;

    const count = textarea.value.length;
    badge.textContent = `${count} / 2000 karakter`;

    if (count > 2000) {
        badge.classList.remove('bg-light', 'text-muted');
        badge.classList.add('bg-danger', 'text-white');
    } else {
        badge.classList.remove('bg-danger', 'text-white');
        badge.classList.add('bg-light', 'text-muted');
    }
}

// Intercept form submit to ensure no raw placeholder remains
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('formTestSend');
    if (form) {
        form.addEventListener('submit', function() {
            const textarea = document.getElementById('testMessageInput');
            if (textarea && textarea.value) {
                let text = textarea.value;
                const values = getPlaceholderValues();
                for (const [placeholder, val] of Object.entries(values)) {
                    text = text.replaceAll(placeholder, val);
                }
                textarea.value = text;
            }
        });
    }
    updateCharCount();
});

function toggleTokenVisibility() {
    const input = document.getElementById('accessTokenInput');
    const icon = document.getElementById('toggleTokenIcon');
    if (!input || !icon) return;
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}
</script>
