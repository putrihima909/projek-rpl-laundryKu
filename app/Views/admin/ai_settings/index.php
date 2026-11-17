<?php
use App\Helpers\Auth;
use App\Helpers\Csrf;

$showSidebar = true;
$title = "Konfigurasi Sistem - SmartLaundry Pro";
$appUrl = config('app.url', 'http://localhost/rpl/public');
$currentUser = Auth::user();

// Backward compatibility defaults for test suites & controllers
$isStore = $isStore ?? false;
$store = $store ?? null;
$aiEnabled = isset($aiEnabled) ? (bool)$aiEnabled : (!empty($settings['ai_enabled']));
$provider = $provider ?? ($settings['ai_provider'] ?? 'gemini');
$model = $model ?? ($settings['ai_model'] ?? 'gemini-3.5-flash-lite');
$apiBaseUrl = $customBaseUrl ?? ($apiBaseUrl ?? ($baseUrl ?? ($settings['ai_base_url'] ?? '')));
if (str_contains($apiBaseUrl, 'localhost/rpl')) {
    $apiBaseUrl = '';
}
$canRevealKey = $canRevealKey ?? (in_array(
    Auth::role() ?? '',
    defined('AI_KEY_REVEAL_ROLES') ? AI_KEY_REVEAL_ROLES : config('ai.key_reveal_roles', ['owner', 'admin_owner', 'owner_toko', 'admin_toko']),
    true
));
$hasApiKey = isset($hasApiKey) ? (bool)$hasApiKey : (!empty($settings['has_api_key']));
$keyLast4 = $keyLast4 ?? ($settings['api_key_status']['last4'] ?? '');
$apiKeyStatus = $apiKeyStatus ?? ($hasApiKey ? 'Terisi (Aktif di Database)' : 'Kosong (Belum Diatur)');
$dbConnected = isset($dbConnected) ? (bool)$dbConnected : (!empty($health['database']['connected']));
$csrfToken = $csrfToken ?? '';
$modelPool = $modelPool ?? [];
$actionBaseUrl = $isStore ? $appUrl . '/admin/store-ai-settings' : (str_contains($_SERVER['REQUEST_URI'] ?? '', 'platform/settings') ? $appUrl . '/platform/settings' : $appUrl . '/admin/ai-settings');
?>

<!-- 1. Top Navbar Gradien Biru (Sesuai Desain Referensi SmartLaundry Pro) -->
<div class="bg-primary text-white py-2.5 px-3 px-md-4 shadow-sm mb-3 rounded-bottom-3" style="background: linear-gradient(90deg, #1d4ed8 0%, #2563eb 100%) !important;">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div class="d-flex align-items-center gap-3 gap-md-4">
            <a href="<?= $appUrl ?>/" class="d-flex align-items-center gap-2 text-white text-decoration-none fw-bold fs-5">
                <span class="rounded-circle bg-white text-primary d-inline-flex align-items-center justify-content-center shadow-sm" style="width:32px;height:32px;font-size:0.95rem;">
                    <i class="fa-solid fa-water"></i>
                </span>
                <span>SmartLaundry Pro</span>
            </a>
            <div class="d-none d-md-flex align-items-center gap-3 small fw-medium">
                <a href="<?= $appUrl ?>/" class="text-white text-opacity-75 text-decoration-none hover-white">Beranda</a>
                <a href="<?= $appUrl ?>/stores" class="text-white text-opacity-75 text-decoration-none hover-white">Cari Toko</a>
                <a href="<?= $appUrl ?>/#about" class="text-white text-opacity-75 text-decoration-none hover-white">Tentang Kami</a>
                <a href="<?= $appUrl ?>/admin/ai-settings" class="text-white text-decoration-none fw-bold d-inline-flex align-items-center gap-1.5 bg-white bg-opacity-20 px-3 py-1 rounded-pill">
                    <i class="fa-solid fa-gear"></i> Konfigurasi
                </a>
            </div>
        </div>
        <div class="d-flex align-items-center gap-3">
            <a href="<?= $appUrl ?>/notifications" class="text-white text-opacity-75 text-decoration-none position-relative p-1" title="Notifikasi">
                <i class="fa-solid fa-bell"></i>
            </a>
            <div class="d-flex align-items-center gap-2">
                <div class="rounded-circle bg-warning text-dark fw-bold d-flex align-items-center justify-content-center shadow-sm" style="width:34px;height:34px;font-size:0.85rem;">
                    <?= strtoupper(substr($currentUser['name'] ?? 'R', 0, 1)) ?>
                </div>
                <div class="d-none d-sm-block text-start lh-1">
                    <div class="fw-bold text-white small"><?= htmlspecialchars($currentUser['name'] ?? ($isStore ? 'Rina Wijaya (Admin Toko)' : 'Rina Wijaya (Admin Platform)')) ?></div>
                    <span class="badge bg-white bg-opacity-25 text-white mt-1" style="font-size:0.65rem;">
                        <?= htmlspecialchars(Auth::roleDisplay() ?: 'Admin Owner') ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid px-2 px-md-4 py-2" style="background-color: #f8fafc; min-height: 85vh;">

    <!-- Flash Notifications -->
    <?php if (!empty($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm rounded-3 border-0 mb-3" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i> <?= htmlspecialchars($_SESSION['success']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm rounded-3 border-0 mb-3" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i> <?= htmlspecialchars($_SESSION['error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['warning'])): ?>
        <div class="alert alert-warning alert-dismissible fade show shadow-sm rounded-3 border-0 mb-3" role="alert">
            <i class="fa-solid fa-circle-exclamation me-2"></i> <?= htmlspecialchars($_SESSION['warning']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['warning']); ?>
    <?php endif; ?>

    <!-- 2. Header Judul Halaman -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 mt-2">
        <div>
            <h2 class="fw-bold text-dark mb-1" style="letter-spacing: -0.5px;">Pusat Konfigurasi &amp; Pengaturan Sistem</h2>
            <p class="text-muted mb-0 small">Kelola preferensi web, gateway pembayaran, integrasi kurir, template notifikasi, dan bot AI secara terpusat.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3 fw-semibold shadow-none">
                <i class="fa-solid fa-file-export me-1"></i> Ekspor Konfigurasi
            </button>
            <a href="<?= $isStore ? $appUrl . '/dashboard/admin-toko' : $appUrl . '/dashboard/owner' ?>" class="btn btn-primary btn-sm rounded-pill px-3 fw-semibold shadow-sm">
                <i class="fa-solid fa-gauge me-1"></i> Dashboard
            </a>
        </div>
    </div>

    <!-- 3. Horizontal Pill Navigation Tabs -->
    <div class="d-flex flex-wrap align-items-center gap-2 mb-4 pb-2 border-bottom">
        <button type="button" class="btn btn-light btn-sm rounded-pill px-3 fw-medium text-muted border-0"><i class="fa-solid fa-sliders me-1"></i> Umum</button>
        <button type="button" class="btn btn-light btn-sm rounded-pill px-3 fw-medium text-muted border-0"><i class="fa-solid fa-credit-card me-1"></i> Pembayaran <i class="fa-solid fa-lock text-muted ms-1" style="font-size:0.7rem;"></i></button>
        <button type="button" class="btn btn-light btn-sm rounded-pill px-3 fw-medium text-muted border-0"><i class="fa-solid fa-arrow-rotate-left me-1"></i> Pengembalian <i class="fa-solid fa-lock text-muted ms-1" style="font-size:0.7rem;"></i></button>
        <button type="button" class="btn btn-light btn-sm rounded-pill px-3 fw-medium text-muted border-0"><i class="fa-solid fa-truck me-1"></i> Kurir &amp; Pesanan <i class="fa-solid fa-lock text-muted ms-1" style="font-size:0.7rem;"></i></button>
        <button type="button" class="btn btn-light btn-sm rounded-pill px-3 fw-medium text-muted border-0"><i class="fa-solid fa-bell me-1"></i> Notifikasi</button>
        
        <!-- Active Chatbot Tab -->
        <button type="button" class="btn btn-primary btn-sm rounded-pill px-3.5 py-1.5 fw-semibold shadow-sm" style="background:#2563eb; border-color:#2563eb;">
            <i class="fa-solid fa-robot me-1.5"></i> Chatbot
        </button>
        
        <button type="button" class="btn btn-light btn-sm rounded-pill px-3 fw-medium text-muted border-0"><i class="fa-solid fa-shield-halved me-1"></i> Keamanan <i class="fa-solid fa-lock text-muted ms-1" style="font-size:0.7rem;"></i></button>
        <button type="button" class="btn btn-light btn-sm rounded-pill px-3 fw-medium text-muted border-0"><i class="fa-solid fa-wrench me-1"></i> Pemeliharaan <i class="fa-solid fa-lock text-muted ms-1" style="font-size:0.7rem;"></i></button>
        <button type="button" class="btn btn-light btn-sm rounded-pill px-3 fw-medium text-muted border-0"><i class="fa-solid fa-plug me-1"></i> Integrasi</button>
        <button type="button" class="btn btn-light btn-sm rounded-pill px-3 fw-medium text-muted border-0"><i class="fa-solid fa-circle-info me-1"></i> Info Sistem</button>
        <button type="button" class="btn btn-light btn-sm rounded-pill px-3 fw-medium text-muted border-0"><i class="fa-solid fa-clock-rotate-left me-1"></i> Riwayat</button>
    </div>

    <!-- 4. Card: Pengaturan Asisten AI Chatbot (SmartBot) -->
    <div class="card border-0 shadow-sm rounded-4 p-4 mb-4 bg-white">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-3 bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center p-3" style="width:48px;height:48px;font-size:1.4rem;">
                    <i class="fa-solid fa-robot"></i>
                </div>
                <div>
                    <h5 class="fw-bold text-dark mb-0.5">Pengaturan Asisten AI Chatbot (SmartBot) &bull; Pengaturan AI Assistant</h5>
                    <p class="text-muted small mb-0">Konfigurasi operasional, sapaan pembuka per role, batas frekuensi chat, dan basis pengetahuan.</p>
                </div>
            </div>
            <div>
                <a href="<?= $appUrl ?>/admin/whatsapp" class="btn btn-outline-secondary btn-sm rounded-pill px-3 fw-medium shadow-none">
                    <i class="fa-regular fa-file-lines me-1"></i> Buka Log Percakapan
                </a>
            </div>
        </div>
    </div>

    <!-- 5. Main Panel: AI Agent Utama (Primary Live Model) -->
    <div class="card border border-primary-subtle rounded-4 shadow-sm p-4 mb-4 bg-white" style="border-color: #bfdbfe !important;">
        
        <!-- Top Row Panel -->
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 pb-3 mb-3 border-bottom">
            <div class="d-flex flex-wrap align-items-center gap-2">
                <div class="d-flex align-items-center gap-2">
                    <i class="fa-solid fa-microchip text-primary fs-5"></i>
                    <span class="fw-bold text-dark fs-6">AI Agent Utama (Primary Live Model):</span>
                </div>
                <span class="badge bg-primary text-uppercase px-2.5 py-1.5 rounded-pill fw-semibold" style="letter-spacing:0.5px;">
                    <?= htmlspecialchars(strtoupper($provider . ' - ' . $model)) ?>
                </span>
                <?php if ($hasApiKey): ?>
                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-1.5 fw-semibold small">
                        <i class="fa-solid fa-circle me-1" style="font-size:0.5rem;vertical-align:middle;"></i> API Key Aktif (<?= $isStore ? 'Toko' : 'Database' ?>)
                    </span>
                <?php else: ?>
                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2.5 py-1.5 fw-semibold small">
                        <i class="fa-solid fa-circle-exclamation me-1"></i> API Key Belum Diatur
                    </span>
                <?php endif; ?>
            </div>
            
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <button type="button" class="btn btn-primary btn-sm rounded-pill px-3 fw-semibold shadow-none" id="btnOpenTambahModel" data-bs-toggle="modal" data-bs-target="#modalTambahModel">
                    <i class="fa-solid fa-circle-plus me-1"></i> Tambah Model AI
                </button>
                <button type="button" id="btnTestConnection" class="btn btn-outline-primary btn-sm rounded-pill px-3 fw-semibold shadow-none">
                    <i class="fa-solid fa-tower-broadcast me-1" id="testIcon"></i> <span id="testBtnText">Tes Koneksi AI Utama</span>
                </button>
                <span class="btn btn-light btn-sm rounded-pill px-3 fw-medium text-muted border">
                    <i class="fa-solid fa-gear me-1"></i> Konfigurasi Web Aktif
                </span>
            </div>
        </div>

        <!-- Alert Info Box -->
        <div class="alert alert-info border-0 rounded-3 py-2.5 px-3 d-flex align-items-center gap-2 mb-4" style="background:#e0f2fe; color:#0369a1;">
            <i class="fa-solid fa-circle-info fs-5 flex-shrink-0" style="color:#0284c7;"></i>
            <div class="small">
                <strong>Fleksibilitas Multi-Model:</strong> Anda dapat mengubah penyedia AI, mengganti API Key, serta menambahkan beberapa model cadangan (failover pool) langsung dari halaman ini tanpa perlu membuka file <code>.env</code> secara manual.
            </div>
        </div>

        <!-- Form 4 Columns (Horizontal Grid Layout Sesuai Referensi) -->
        <form id="aiConfigForm" action="<?= $isStore ? $appUrl . '/admin/store-ai-settings' : $appUrl . '/admin/ai-settings' ?>" method="POST">
            <input type="hidden" name="csrf_token" id="csrfToken" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

            <div class="row g-3 align-items-start mb-3">
                <!-- Col 1: Penyedia AI (Provider) -->
                <div class="col-12 col-md-3">
                    <label for="providerSelect" class="form-label fw-bold text-dark small mb-1.5">
                        Penyedia AI (Provider)
                    </label>
                    <select class="form-select border-secondary-subtle rounded-3 shadow-none" id="providerSelect" name="provider" required>
                        <option value="gemini" <?= $provider === 'gemini' ? 'selected' : '' ?>>Google Gemini</option>
                        <option value="groqcloud" <?= $provider === 'groqcloud' ? 'selected' : '' ?>>GroqCloud</option>
                        <option value="openai" <?= $provider === 'openai' ? 'selected' : '' ?>>OpenAI</option>
                        <option value="openai_compatible" <?= $provider === 'openai_compatible' ? 'selected' : '' ?>>OpenAI Compatible API</option>
                    </select>
                </div>

                <!-- Col 2: Nama Model Utama -->
                <div class="col-12 col-md-3">
                    <label for="modelInput" class="form-label fw-bold text-dark small mb-1.5">
                        Nama Model Utama
                    </label>
                    <input type="text" class="form-control border-secondary-subtle rounded-3 shadow-none" 
                           id="modelInput" name="model" value="<?= htmlspecialchars($model) ?>" 
                           placeholder="gemini-3.5-flash-lite" required>
                    <div class="mt-1 d-flex flex-wrap gap-1 align-items-center" id="quickModelSuggestions">
                        <small class="text-muted" style="font-size:0.7rem;">Saran:</small>
                    </div>
                </div>

                <!-- Col 3: API Key Utama -->
                <div class="col-12 col-md-3">
                    <div class="d-flex justify-content-between align-items-center mb-1.5">
                        <label for="apiKeyInput" class="form-label fw-bold text-dark small mb-0">
                            API Key Utama
                        </label>
                    </div>
                    <div class="input-group">
                        <input type="password" class="form-control border-secondary-subtle rounded-start-3 shadow-none" 
                               id="apiKeyInput" name="api_key" autocomplete="off"
                               placeholder="<?= $hasApiKey ? '•••••••••••••• (Tersimpan)' : 'Masukkan API Key Anda' ?>">
                        <button class="btn btn-outline-secondary rounded-end-3" type="button" id="btnToggleApiKey" 
                                title="<?= $hasApiKey ? ($canRevealKey ? 'Lihat API Key tersimpan' : 'Hanya menampilkan ****' . htmlspecialchars($keyLast4)) : 'Tampilkan/Sembunyikan' ?>">
                            <i class="fa-solid fa-eye" id="eyeIcon"></i>
                        </button>
                    </div>
                    <?php if ($hasApiKey): ?>
                        <div class="form-check form-check-inline mt-1">
                            <input class="form-check-input" type="checkbox" id="clearApiKeyCheck" name="clear_api_key" value="1">
                            <label class="form-check-label text-danger small fw-semibold" for="clearApiKeyCheck" style="font-size:0.75rem;">
                                Hapus Kunci
                            </label>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Col 4: Custom Base URL (Opsional) -->
                <div class="col-12 col-md-3">
                    <label for="baseUrlInput" class="form-label fw-bold text-dark small mb-1.5">
                        Custom Base URL (Opsional)
                    </label>
                    <input type="text" class="form-control border-secondary-subtle rounded-3 shadow-none" 
                           id="baseUrlInput" name="base_url" value="<?= htmlspecialchars($apiBaseUrl) ?>" 
                           placeholder="https://api.groq.com/openai/v1">
                    <small class="text-muted d-block mt-1" style="font-size:0.72rem;">
                        Kosongkan untuk Gemini bawaan.
                    </small>
                </div>
            </div>

            <!-- Status & Simpan Row -->
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 pt-3 mt-3 border-top">
                <div class="d-flex align-items-center gap-3">
                    <div class="form-check form-switch fs-5 mb-0">
                        <input class="form-check-input" type="checkbox" role="switch" 
                               id="aiEnabledSwitch" name="is_enabled" value="1" <?= $aiEnabled ? 'checked' : '' ?>>
                    </div>
                    <div>
                        <label class="form-check-label fw-bold text-dark small d-block mb-0" for="aiEnabledSwitch">
                            Status Chatbot Aktif
                        </label>
                        <small class="text-muted" style="font-size:0.75rem;">
                            Aktifkan asisten otomatis untuk melayani pelanggan toko.
                        </small>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-md rounded-pill px-4 fw-semibold shadow-sm">
                    <i class="fa-solid fa-floppy-disk me-1.5"></i> Simpan Konfigurasi
                </button>
            </div>
        </form>

        <!-- Container Hasil Tes Koneksi (AJAX) -->
        <div id="testResultBox" class="mt-3" style="display: none;"></div>

    </div>

    <!-- 6. Model AI Tersimpan & Cadangan (Failover Pool) -->
    <div class="card border border-light-subtle rounded-4 shadow-sm p-4 mb-4 bg-white">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 pb-3 mb-3 border-bottom">
            <div>
                <h5 class="fw-bold text-dark mb-1 d-flex align-items-center gap-2">
                    <i class="fa-solid fa-layer-group text-primary"></i> Model AI Tersimpan &amp; Cadangan (Failover Pool)
                </h5>
                <p class="text-muted small mb-0">
                    Koleksi model AI dari berbagai penyedia (GroqCloud, Google Gemini, OpenAI, dll) dengan API key masing-masing. Aktifkan model mana saja sebagai model utama dengan 1 klik.
                </p>
            </div>
            <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-3 py-1.5 fw-semibold shadow-none flex-shrink-0" data-bs-toggle="modal" data-bs-target="#modalTambahModel">
                <i class="fa-solid fa-circle-plus me-1"></i> Tambah Model Baru
            </button>
        </div>

        <?php if (empty($modelPool)): ?>
            <div class="text-center py-4 px-3 bg-light rounded-4 border border-dashed">
                <i class="fa-solid fa-server text-muted mb-2" style="font-size: 2.2rem;"></i>
                <h6 class="fw-bold text-dark mb-1">Belum Ada Model Cadangan di Pool</h6>
                <p class="text-muted small mb-3" style="max-width: 500px; margin: 0 auto;">
                    Tambahkan model cadangan dari penyedia lain (seperti GroqCloud atau OpenAI) agar chatbot Anda memiliki alternatif instan jika kuota model utama habis.
                </p>
                <button type="button" class="btn btn-primary btn-sm rounded-pill px-4 fw-semibold" data-bs-toggle="modal" data-bs-target="#modalTambahModel">
                    <i class="fa-solid fa-circle-plus me-1"></i> Tambah Model AI Sekarang
                </button>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small text-uppercase text-muted" style="font-size: 0.72rem; letter-spacing: 0.5px;">
                        <tr>
                            <th style="min-width: 180px;">Penyedia &amp; Model</th>
                            <th>Label / Keterangan</th>
                            <th>Kredensial API Key</th>
                            <th>Custom Base URL</th>
                            <th>Status</th>
                            <th class="text-end" style="min-width: 140px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        <?php foreach ($modelPool as $mp): ?>
                            <?php 
                                $pColor = match($mp['provider']) {
                                    'groqcloud' => 'warning',
                                    'openai'    => 'dark',
                                    'openai_compatible' => 'info',
                                    default     => 'primary',
                                };
                                $pIcon = match($mp['provider']) {
                                    'groqcloud' => 'fa-bolt',
                                    'openai'    => 'fa-brain',
                                    'openai_compatible' => 'fa-network-wired',
                                    default     => 'fa-wand-magic-sparkles',
                                };
                                $isCurPrimary = (bool)($mp['is_primary'] ?? false);
                            ?>
                            <tr class="<?= $isCurPrimary ? 'table-primary-subtle' : '' ?>">
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge bg-<?= $pColor ?>-subtle text-<?= $pColor ?> border border-<?= $pColor ?>-subtle rounded-pill px-2 py-1">
                                            <i class="fa-solid <?= $pIcon ?> me-1"></i> <?= htmlspecialchars(strtoupper($mp['provider'])) ?>
                                        </span>
                                        <span class="fw-bold text-dark font-monospace"><?= htmlspecialchars($mp['model_name']) ?></span>
                                    </div>
                                </td>
                                <td>
                                    <span class="text-dark fw-medium"><?= htmlspecialchars($mp['label'] ?: '-') ?></span>
                                </td>
                                <td>
                                    <?php if (!empty($mp['api_key_last4'])): ?>
                                        <span class="font-monospace text-muted">••••••••••••<?= htmlspecialchars($mp['api_key_last4']) ?></span>
                                    <?php elseif (!empty($mp['api_key_enc'])): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2">Tersimpan</span>
                                    <?php else: ?>
                                        <span class="text-muted fst-italic">Menggunakan Kunci Utama</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="font-monospace text-muted small"><?= htmlspecialchars($mp['base_url'] ?: '(Default endpoint)') ?></span>
                                </td>
                                <td>
                                    <?php if ($isCurPrimary): ?>
                                        <span class="badge bg-success text-white rounded-pill px-2.5 py-1 fw-semibold">
                                            <i class="fa-solid fa-circle-check me-1"></i> Model Utama Aktif
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary border rounded-pill px-2 py-1">
                                            Cadangan (Standby)
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-1.5 align-items-center">
                                        <?php if (!$isCurPrimary): ?>
                                            <form action="<?= $actionBaseUrl ?>/activate-model" method="POST" class="d-inline mb-0">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                <input type="hidden" name="model_id" value="<?= (int)$mp['id'] ?>">
                                                <button type="submit" class="btn btn-outline-primary btn-sm rounded-pill px-2.5 py-0.5 fw-medium" title="Jadikan AI Agent Utama">
                                                    <i class="fa-solid fa-play me-1"></i> Gunakan
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <form action="<?= $actionBaseUrl ?>/delete-model" method="POST" class="d-inline mb-0" onsubmit="return confirm('Hapus model \'<?= htmlspecialchars(addslashes($mp['model_name'])) ?>\' dari daftar cadangan?');">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                            <input type="hidden" name="model_id" value="<?= (int)$mp['id'] ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-sm rounded-pill px-2 py-0.5" title="Hapus dari daftar">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </form>
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

<!-- Modal Lihat API Key -->
<div class="modal fade" id="modalLihatApiKey" tabindex="-1" aria-labelledby="modalLihatApiKeyLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-bottom pb-3">
                <h5 class="modal-title fw-bold text-dark d-flex align-items-center" id="modalLihatApiKeyLabel">
                    <i class="fa-solid fa-key text-primary me-2"></i> Lihat API Key
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" id="btnCloseRevealModal"></button>
            </div>
            <div class="modal-body p-4">
                <!-- Bagian 1: Verifikasi Password Akun -->
                <div id="revealPasswordSection">
                    <p class="text-muted small mb-3">
                        Kunci disimpan terenkripsi (AES-256-GCM). Masukkan password akun login Anda untuk melihat kembali API key yang tersimpan langsung pada kolom input.
                    </p>
                    <div class="mb-3">
                        <label for="revealAccountPassword" class="form-label fw-semibold text-dark small mb-1">
                            Password Akun Login <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted border-secondary-subtle">
                                <i class="fa-solid fa-lock"></i>
                            </span>
                            <input type="password" class="form-control border-secondary-subtle" 
                                   id="revealAccountPassword" placeholder="Masukkan password akun Anda" autocomplete="current-password">
                        </div>
                    </div>
                    <div id="revealAlertBox" class="alert alert-danger d-none py-2 px-3 small rounded-3 mb-3"></div>
                    <div class="d-flex justify-content-end gap-2 pt-2 border-top">
                        <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal" id="btnCancelRevealModal">Batal</button>
                        <button type="button" id="btnSubmitRevealPassword" class="btn btn-primary rounded-pill px-4 fw-semibold">
                            <i class="fa-solid fa-unlock me-1" id="revealBtnIcon"></i> <span id="revealBtnText">Tampilkan</span>
                        </button>
                    </div>
                </div>

                <!-- Bagian 2: Tampilan Kunci Terbuka & Timer 30 Detik -->
                <div id="revealResultSection" class="d-none">
                    <div class="alert alert-warning border-0 rounded-3 py-2 px-3 small mb-3 d-flex align-items-center">
                        <i class="fa-solid fa-shield-halved text-warning me-2 fs-5"></i>
                        <div>
                            <strong>Peringatan Keamanan:</strong> Kunci ini bersifat rahasia. Kunci telah ditampilkan pada kolom API Key Utama dan otomatis dikosongkan setelah waktu habis.
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark small mb-1">API Key Tersimpan</label>
                        <div class="input-group">
                            <input type="text" class="form-control font-monospace border-secondary-subtle bg-light text-dark fw-bold" 
                                   id="revealedKeyInput" readonly spellcheck="false">
                            <button class="btn btn-outline-secondary px-3" type="button" id="btnCopyRevealedKey" title="Salin ke Clipboard">
                                <i class="fa-solid fa-copy me-1" id="copyIcon"></i> <span id="copyBtnText">Salin</span>
                            </button>
                        </div>
                    </div>
                    <div class="d-flex align-items-center justify-content-between p-2 bg-light rounded-3 border">
                        <div class="small text-danger fw-semibold">
                            <i class="fa-regular fa-clock me-1"></i> Disembunyikan dalam: <span id="revealTimerCount" class="badge bg-danger rounded-pill px-2">30</span> detik
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-dark rounded-pill px-3" id="btnHideKeyNow">
                            <i class="fa-solid fa-eye-slash me-1"></i> Tutup &amp; Bersihkan
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Tambah Model AI Baru -->
<div class="modal fade" id="modalTambahModel" tabindex="-1" aria-labelledby="modalTambahModelLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-bottom pb-3">
                <h5 class="modal-title fw-bold text-dark d-flex align-items-center" id="modalTambahModelLabel">
                    <i class="fa-solid fa-circle-plus text-primary me-2"></i> Tambah Model AI Baru (Multi-Provider)
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" id="btnCloseTambahModal"></button>
            </div>
            <form action="<?= $actionBaseUrl ?>/add-model" method="POST" id="formTambahModel">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

                <div class="modal-body p-4">
                    <div class="alert alert-info border-0 rounded-3 py-2 px-3 small mb-3 d-flex align-items-center gap-2" style="background:#e0f2fe; color:#0369a1;">
                        <i class="fa-solid fa-circle-info fs-5 flex-shrink-0" style="color:#0284c7;"></i>
                        <div>
                            Masukkan API key dan nama model dari penyedia AI pilihan Anda (misalnya <strong>GroqCloud, Google Gemini, OpenAI</strong>, atau endpoint kustom). Kunci akan disimpan terenkripsi (AES-256-GCM).
                        </div>
                    </div>

                    <div class="row g-3">
                        <!-- Penyedia AI -->
                        <div class="col-12 col-md-6">
                            <label for="newProviderSelect" class="form-label fw-semibold text-dark small mb-1">
                                Penyedia AI (Provider) <span class="text-danger">*</span>
                            </label>
                            <select class="form-select border-secondary-subtle rounded-3 shadow-none" id="newProviderSelect" name="provider" required>
                                <option value="groqcloud">GroqCloud (Ultra Cepat - Llama 3 / Mixtral)</option>
                                <option value="gemini">Google Gemini (Google AI Studio)</option>
                                <option value="openai">OpenAI (GPT-4o / GPT-4o-mini)</option>
                                <option value="openai_compatible">OpenAI Compatible (Ollama / LocalAI / DeepSeek)</option>
                            </select>
                        </div>

                        <!-- Label / Alias -->
                        <div class="col-12 col-md-6">
                            <label for="newLabelInput" class="form-label fw-semibold text-dark small mb-1">
                                Label / Alias Model <span class="text-muted fw-normal">(Opsional)</span>
                            </label>
                            <input type="text" class="form-control border-secondary-subtle rounded-3 shadow-none" 
                                   id="newLabelInput" name="label" placeholder="Contoh: Groq Llama 3 Cepat">
                        </div>

                        <!-- Nama Model Identifier -->
                        <div class="col-12 col-md-6">
                            <label for="newModelInput" class="form-label fw-semibold text-dark small mb-1">
                                Nama Model Identifier <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control border-secondary-subtle rounded-3 shadow-none" 
                                   id="newModelInput" name="model_name" value="llama-3.3-70b-versatile" placeholder="llama-3.3-70b-versatile" required>
                            <div class="mt-1.5 d-flex flex-wrap gap-1 align-items-center" id="newQuickSuggestions">
                                <small class="text-muted" style="font-size:0.7rem;">Saran:</small>
                            </div>
                        </div>

                        <!-- API Key -->
                        <div class="col-12 col-md-6">
                            <label for="newApiKeyInput" class="form-label fw-semibold text-dark small mb-1">
                                API Key Provider <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <input type="password" class="form-control border-secondary-subtle rounded-start-3 shadow-none" 
                                       id="newApiKeyInput" name="api_key" placeholder="gsk_... / AIzaSy... / sk-..." autocomplete="off" required>
                                <button class="btn btn-outline-secondary rounded-end-3" type="button" id="btnToggleNewApiKey" title="Tampilkan/Sembunyikan">
                                    <i class="fa-solid fa-eye" id="newEyeIcon"></i>
                                </button>
                            </div>
                            <small class="text-muted d-block mt-1" style="font-size:0.72rem;">
                                Disimpan terenkripsi dengan aman di database.
                            </small>
                        </div>

                        <!-- Custom Base URL -->
                        <div class="col-12">
                            <label for="newBaseUrlInput" class="form-label fw-semibold text-dark small mb-1">
                                Custom Base URL <span class="text-muted fw-normal">(Otomatis terisi sesuai penyedia)</span>
                            </label>
                            <input type="text" class="form-control border-secondary-subtle rounded-3 shadow-none" 
                                   id="newBaseUrlInput" name="base_url" value="https://api.groq.com/openai/v1" placeholder="https://api.groq.com/openai/v1">
                            <small class="text-muted d-block mt-1" style="font-size:0.72rem;">
                                Disesuaikan otomatis berdasarkan pilihan provider. Untuk Gemini bawaan, kolom ini otomatis dikosongkan.
                            </small>
                        </div>

                        <!-- Langsung Jadikan Model Utama -->
                        <div class="col-12 mt-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="newIsPrimary" name="is_primary" value="1" checked>
                                <label class="form-check-label fw-semibold text-dark small" for="newIsPrimary">
                                    Langsung aktifkan sebagai AI Agent Utama (Primary Live Model)
                                </label>
                            </div>
                            <small class="text-muted d-block ps-4 ms-2" style="font-size:0.72rem;">
                                Jika dicentang, model ini akan langsung dipakai oleh chatbot untuk melayani percakapan pelanggan.
                            </small>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-top px-4 py-3">
                    <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal" id="btnCancelTambahModal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold" id="btnSaveNewModel">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Simpan Model AI
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Interactive Client-Side JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const hasSavedApiKey = <?= $hasApiKey ? 'true' : 'false' ?>;
    const canRevealKey = <?= $canRevealKey ? 'true' : 'false' ?>;
    const keyLast4 = '<?= htmlspecialchars($keyLast4) ?>';
    const isStoreScope = <?= $isStore ? 'true' : 'false' ?>;

    const modalRevealElement = document.getElementById('modalLihatApiKey');
    let bsModalReveal = null;

    function getRevealModal() {
        if (!bsModalReveal && typeof window.bootstrap !== 'undefined' && window.bootstrap.Modal) {
            bsModalReveal = new window.bootstrap.Modal(modalRevealElement);
        }
        return bsModalReveal;
    }

    function openRevealModal() {
        purgeRevealedKey();
        const modal = getRevealModal();
        if (modal) {
            modal.show();
        } else if (modalRevealElement) {
            modalRevealElement.classList.add('show');
            modalRevealElement.style.display = 'block';
            modalRevealElement.removeAttribute('aria-hidden');
            modalRevealElement.setAttribute('aria-modal', 'true');
            let backdrop = document.getElementById('modalRevealBackdrop');
            if (!backdrop) {
                backdrop = document.createElement('div');
                backdrop.id = 'modalRevealBackdrop';
                backdrop.className = 'modal-backdrop fade show';
                document.body.appendChild(backdrop);
            }
            document.body.classList.add('modal-open');
        }
        setTimeout(function () {
            if (revealAccountPassword) revealAccountPassword.focus();
        }, 300);
    }

    function closeRevealModal() {
        const modal = getRevealModal();
        if (modal) {
            modal.hide();
        }
        if (modalRevealElement) {
            modalRevealElement.classList.remove('show');
            modalRevealElement.style.display = 'none';
            modalRevealElement.setAttribute('aria-hidden', 'true');
            modalRevealElement.removeAttribute('aria-modal');
        }
        const backdrop = document.getElementById('modalRevealBackdrop');
        if (backdrop) backdrop.remove();
        document.body.classList.remove('modal-open');
    }

    const btnCloseRevealModal = document.getElementById('btnCloseRevealModal');
    const btnCancelRevealModal = document.getElementById('btnCancelRevealModal');
    if (btnCloseRevealModal) btnCloseRevealModal.addEventListener('click', closeRevealModal);
    if (btnCancelRevealModal) btnCancelRevealModal.addEventListener('click', closeRevealModal);

    const revealPasswordSection = document.getElementById('revealPasswordSection');
    const revealResultSection = document.getElementById('revealResultSection');
    const revealAccountPassword = document.getElementById('revealAccountPassword');
    const revealAlertBox = document.getElementById('revealAlertBox');
    const btnSubmitRevealPassword = document.getElementById('btnSubmitRevealPassword');
    const revealBtnIcon = document.getElementById('revealBtnIcon');
    const revealBtnText = document.getElementById('revealBtnText');
    const revealedKeyInput = document.getElementById('revealedKeyInput');
    const btnCopyRevealedKey = document.getElementById('btnCopyRevealedKey');
    const copyIcon = document.getElementById('copyIcon');
    const copyBtnText = document.getElementById('copyBtnText');
    const revealTimerCount = document.getElementById('revealTimerCount');
    const btnHideKeyNow = document.getElementById('btnHideKeyNow');

    const providerSelect = document.getElementById('providerSelect');
    const modelInput = document.getElementById('modelInput');
    const baseUrlInput = document.getElementById('baseUrlInput');
    const apiKeyInput = document.getElementById('apiKeyInput');
    const btnToggleApiKey = document.getElementById('btnToggleApiKey');
    const eyeIcon = document.getElementById('eyeIcon');
    const quickModelSuggestions = document.getElementById('quickModelSuggestions');
    const btnTestConnection = document.getElementById('btnTestConnection');
    const testIcon = document.getElementById('testIcon');
    const testBtnText = document.getElementById('testBtnText');
    const testResultBox = document.getElementById('testResultBox');
    const csrfToken = document.getElementById('csrfToken').value;

    let revealTimerInterval = null;

    function purgeRevealedKey() {
        if (revealTimerInterval) {
            clearInterval(revealTimerInterval);
            revealTimerInterval = null;
        }
        if (revealedKeyInput) {
            revealedKeyInput.value = '';
        }
        if (revealAccountPassword) {
            revealAccountPassword.value = '';
        }
        if (revealAlertBox) {
            revealAlertBox.classList.add('d-none');
            revealAlertBox.textContent = '';
        }
        if (revealPasswordSection) {
            revealPasswordSection.classList.remove('d-none');
        }
        if (revealResultSection) {
            revealResultSection.classList.add('d-none');
        }
        if (btnSubmitRevealPassword) {
            btnSubmitRevealPassword.disabled = false;
        }
        if (revealBtnIcon) {
            revealBtnIcon.className = 'fa-solid fa-unlock me-1';
        }
        if (revealBtnText) {
            revealBtnText.textContent = 'Tampilkan';
        }
        // Dikosongkan kembali dari kolom input utama setelah masa aktif habis
        if (apiKeyInput && apiKeyInput.dataset.revealed === '1') {
            apiKeyInput.value = '';
            apiKeyInput.type = 'password';
            apiKeyInput.dataset.revealed = '0';
            if (eyeIcon) {
                eyeIcon.className = 'fa-solid fa-eye';
            }
        }
    }

    if (modalRevealElement) {
        modalRevealElement.addEventListener('hidden.bs.modal', function () {
            purgeRevealedKey();
        });
    }

    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            purgeRevealedKey();
            closeRevealModal();
        }
    });

    window.addEventListener('blur', function () {
        purgeRevealedKey();
        closeRevealModal();
    });

    if (btnHideKeyNow) {
        btnHideKeyNow.addEventListener('click', function () {
            purgeRevealedKey();
            closeRevealModal();
        });
    }

    // Model & Base URL Preset Mapping
    const presets = {
        gemini: {
            models: ['gemini-2.5-flash', 'gemini-3.5-flash-lite', 'gemini-2.5-pro'],
            defaultModel: 'gemini-2.5-flash',
            baseUrl: ''
        },
        groqcloud: {
            models: ['llama-3.3-70b-versatile', 'llama-3.1-8b-instant', 'mixtral-8x7b-32768'],
            defaultModel: 'llama-3.3-70b-versatile',
            baseUrl: 'https://api.groq.com/openai/v1'
        },
        openai: {
            models: ['gpt-4o-mini', 'gpt-4o', 'gpt-3.5-turbo'],
            defaultModel: 'gpt-4o-mini',
            baseUrl: 'https://api.openai.com/v1'
        },
        openai_compatible: {
            models: ['default', 'qwen-2.5-72b', 'deepseek-chat'],
            defaultModel: 'default',
            baseUrl: 'http://localhost:11434/v1'
        }
    };

    function updateSuggestions(provider) {
        const config = presets[provider] || presets.gemini;
        quickModelSuggestions.innerHTML = '<small class="text-muted" style="font-size:0.7rem;">Saran:</small>';
        
        config.models.forEach(function (m) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-sm btn-outline-secondary rounded-pill py-0 px-2' + (modelInput.value === m ? ' active' : '');
            btn.style.fontSize = '0.7rem';
            btn.textContent = m;
            btn.addEventListener('click', function () {
                modelInput.value = m;
                document.querySelectorAll('#quickModelSuggestions button').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
            });
            quickModelSuggestions.appendChild(btn);
        });
    }

    // Provider Change Handler
    providerSelect.addEventListener('change', function () {
        const selected = providerSelect.value;
        const config = presets[selected];
        if (config) {
            if (!baseUrlInput.value || Object.values(presets).some(p => p.baseUrl === baseUrlInput.value)) {
                baseUrlInput.value = config.baseUrl;
            }
            if (!modelInput.value || Object.values(presets).some(p => p.models.includes(modelInput.value))) {
                modelInput.value = config.defaultModel;
            }
            updateSuggestions(selected);
        }
    });

    // Inisialisasi saran awal
    updateSuggestions(providerSelect.value);

    // Toggle Eye Icon untuk API Key
    btnToggleApiKey.addEventListener('click', function () {
        // Jika kolom sedang terisi teks (misal sedang mengetik atau kunci sudah terbuka): toggle text/password
        if (apiKeyInput.value.trim() !== '') {
            if (apiKeyInput.type === 'password') {
                apiKeyInput.type = 'text';
                eyeIcon.classList.remove('fa-eye');
                eyeIcon.classList.add('fa-eye-slash');
            } else {
                apiKeyInput.type = 'password';
                eyeIcon.classList.remove('fa-eye-slash');
                eyeIcon.classList.add('fa-eye');
            }
            return;
        }

        // Jika kolom masih kosong dan ada kunci tersimpan:
        if (hasSavedApiKey) {
            if (!canRevealKey) {
                alert('Akses Terbatas: Role akun Anda tidak berhak melihat API key secara lengkap (hanya ****' + keyLast4 + ').');
                return;
            }

            openRevealModal();
            return;
        }

        apiKeyInput.focus();
    });

    // Enter key pada input password modal untuk submit
    if (revealAccountPassword) {
        revealAccountPassword.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                if (btnSubmitRevealPassword) btnSubmitRevealPassword.click();
            }
        });
    }

    // Submit password untuk lihat key (AJAX)
    if (btnSubmitRevealPassword) {
        btnSubmitRevealPassword.addEventListener('click', function () {
            const pwd = revealAccountPassword ? revealAccountPassword.value : '';
            if (!pwd) {
                if (revealAlertBox) {
                    revealAlertBox.textContent = 'Silakan masukkan password akun Anda terlebih dahulu.';
                    revealAlertBox.classList.remove('d-none');
                }
                if (revealAccountPassword) revealAccountPassword.focus();
                return;
            }

            if (revealAlertBox) revealAlertBox.classList.add('d-none');
            btnSubmitRevealPassword.disabled = true;
            if (revealBtnIcon) revealBtnIcon.className = 'fa-solid fa-spinner fa-spin me-1';
            if (revealBtnText) revealBtnText.textContent = 'Memverifikasi...';

            const payload = new URLSearchParams();
            payload.append('csrf_token', csrfToken);
            payload.append('_csrf_token', csrfToken);
            payload.append('password', pwd);
            payload.append('cakupan', isStoreScope ? 'toko' : 'platform');

            fetch('<?= $appUrl ?>/konfigurasi-ai/lihat-key', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: payload.toString()
            })
            .then(function (res) {
                return res.json();
            })
            .then(function (data) {
                btnSubmitRevealPassword.disabled = false;
                if (revealBtnIcon) revealBtnIcon.className = 'fa-solid fa-unlock me-1';
                if (revealBtnText) revealBtnText.textContent = 'Tampilkan';

                if (!data.ok) {
                    if (revealAlertBox) {
                        revealAlertBox.textContent = data.message || 'Verifikasi password gagal.';
                        revealAlertBox.classList.remove('d-none');
                    }
                    if (revealAccountPassword) {
                        revealAccountPassword.value = '';
                        revealAccountPassword.focus();
                    }
                    return;
                }

                // BERHASIL:
                // 1. Masukkan API key langsung ke kolom input utama "API Key Utama"!
                apiKeyInput.value = data.key;
                apiKeyInput.type = 'text';
                apiKeyInput.dataset.revealed = '1';
                if (eyeIcon) {
                    eyeIcon.className = 'fa-solid fa-eye-slash';
                }

                // 2. Tampilkan di modal
                if (revealPasswordSection) revealPasswordSection.classList.add('d-none');
                if (revealResultSection) revealResultSection.classList.remove('d-none');
                if (revealedKeyInput) revealedKeyInput.value = data.key;

                let remaining = data.detik || 30;
                if (revealTimerCount) revealTimerCount.textContent = remaining;

                if (revealTimerInterval) clearInterval(revealTimerInterval);
                revealTimerInterval = setInterval(function () {
                    remaining--;
                    if (revealTimerCount) revealTimerCount.textContent = remaining;
                    if (remaining <= 0) {
                        purgeRevealedKey();
                        closeRevealModal();
                    }
                }, 1000);
            })
            .catch(function () {
                btnSubmitRevealPassword.disabled = false;
                if (revealBtnIcon) revealBtnIcon.className = 'fa-solid fa-unlock me-1';
                if (revealBtnText) revealBtnText.textContent = 'Tampilkan';
                if (revealAlertBox) {
                    revealAlertBox.textContent = 'Gagal memproses permintaan ke server. Silakan coba lagi.';
                    revealAlertBox.classList.remove('d-none');
                }
            });
        });
    }

    if (btnCopyRevealedKey) {
        btnCopyRevealedKey.addEventListener('click', function () {
            if (!revealedKeyInput || !revealedKeyInput.value) return;
            navigator.clipboard.writeText(revealedKeyInput.value).then(function () {
                copyIcon.className = 'fa-solid fa-check text-success me-1';
                copyBtnText.textContent = 'Tersalin!';
                setTimeout(function () {
                    copyIcon.className = 'fa-solid fa-copy me-1';
                    copyBtnText.textContent = 'Salin';
                }, 2000);
            });
        });
    }

    // Tes Koneksi AI Handler (AJAX)
    btnTestConnection.addEventListener('click', function () {
        btnTestConnection.disabled = true;
        testIcon.className = 'fa-solid fa-spinner fa-spin me-1';
        testBtnText.textContent = 'Menguji...';
        testResultBox.style.display = 'none';

        const payload = new URLSearchParams();
        payload.append('csrf_token', csrfToken);
        payload.append('_csrf_token', csrfToken);
        payload.append('provider', providerSelect.value);
        payload.append('model', modelInput.value);
        payload.append('base_url', baseUrlInput.value);
        payload.append('api_key', apiKeyInput.value);

        const testUrl = isStoreScope 
            ? '<?= $appUrl ?>/admin/store-ai-settings/test-connection'
            : '<?= $appUrl ?>/admin/ai-settings/test-connection';

        fetch(testUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-TOKEN': csrfToken
            },
            body: payload.toString()
        })
        .then(function (res) {
            return res.json();
        })
        .then(function (data) {
            btnTestConnection.disabled = false;
            testIcon.className = 'fa-solid fa-tower-broadcast me-1';
            testBtnText.textContent = 'Tes Koneksi AI Utama';

            testResultBox.style.display = 'block';
            if (data.success) {
                testResultBox.innerHTML = `
                    <div class="alert alert-success border-0 rounded-3 shadow-sm py-2.5 px-3 mb-0 d-flex align-items-center gap-2">
                        <i class="fa-solid fa-circle-check fs-5 text-success"></i>
                        <div>
                            <strong>Tes Koneksi Berhasil!</strong>
                            <div class="small">${escapeHtml(data.message || 'Koneksi AI berjalan lancar.')} Latensi: <strong>${escapeHtml(data.latency || '0ms')}</strong></div>
                        </div>
                    </div>
                `;
            } else {
                testResultBox.innerHTML = `
                    <div class="alert alert-danger border-0 rounded-3 shadow-sm py-2.5 px-3 mb-0 d-flex align-items-center gap-2">
                        <i class="fa-solid fa-circle-xmark fs-5 text-danger"></i>
                        <div>
                            <strong>Tes Koneksi Gagal</strong>
                            <div class="small">${escapeHtml(data.message || 'Gagal tersambung ke provider.')}</div>
                        </div>
                    </div>
                `;
            }
        })
        .catch(function () {
            btnTestConnection.disabled = false;
            testIcon.className = 'fa-solid fa-tower-broadcast me-1';
            testBtnText.textContent = 'Tes Koneksi AI Utama';
            testResultBox.style.display = 'block';
            testResultBox.innerHTML = `
                <div class="alert alert-danger border-0 rounded-3 shadow-sm py-2.5 px-3 mb-0 d-flex align-items-center gap-2">
                    <i class="fa-solid fa-triangle-exclamation fs-5 text-danger"></i>
                    <div>
                        <strong>Terjadi Kesalahan Jaringan</strong>
                        <div class="small">Tidak dapat menghubungi server lokal untuk melakukan tes koneksi.</div>
                    </div>
                </div>
            `;
        });
    });

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // Modal Tambah Model Elements & Fallback
    const modalTambahElement = document.getElementById('modalTambahModel');
    let bsModalTambah = null;

    function getTambahModal() {
        if (!bsModalTambah && typeof window.bootstrap !== 'undefined' && window.bootstrap.Modal) {
            bsModalTambah = new window.bootstrap.Modal(modalTambahElement);
        }
        return bsModalTambah;
    }

    function openTambahModal() {
        const modal = getTambahModal();
        if (modal) {
            modal.show();
        } else if (modalTambahElement) {
            modalTambahElement.classList.add('show');
            modalTambahElement.style.display = 'block';
            modalTambahElement.removeAttribute('aria-hidden');
            modalTambahElement.setAttribute('aria-modal', 'true');
            let backdrop = document.getElementById('modalTambahBackdrop');
            if (!backdrop) {
                backdrop = document.createElement('div');
                backdrop.id = 'modalTambahBackdrop';
                backdrop.className = 'modal-backdrop fade show';
                document.body.appendChild(backdrop);
            }
            document.body.classList.add('modal-open');
        }
    }

    function closeTambahModal() {
        const modal = getTambahModal();
        if (modal) {
            modal.hide();
        }
        if (modalTambahElement) {
            modalTambahElement.classList.remove('show');
            modalTambahElement.style.display = 'none';
            modalTambahElement.setAttribute('aria-hidden', 'true');
            modalTambahElement.removeAttribute('aria-modal');
        }
        const backdrop = document.getElementById('modalTambahBackdrop');
        if (backdrop) backdrop.remove();
        document.body.classList.remove('modal-open');
    }

    const btnOpenTambahModel = document.getElementById('btnOpenTambahModel');
    if (btnOpenTambahModel) {
        btnOpenTambahModel.addEventListener('click', function (e) {
            e.preventDefault();
            openTambahModal();
        });
    }

    document.querySelectorAll('[data-bs-target="#modalTambahModel"]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            openTambahModal();
        });
    });

    const btnCloseTambahModal = document.getElementById('btnCloseTambahModal');
    const btnCancelTambahModal = document.getElementById('btnCancelTambahModal');
    if (btnCloseTambahModal) btnCloseTambahModal.addEventListener('click', closeTambahModal);
    if (btnCancelTambahModal) btnCancelTambahModal.addEventListener('click', closeTambahModal);

    // Form Tambah Model JS (Provider suggestions & Eye toggle)
    const newProviderSelect = document.getElementById('newProviderSelect');
    const newModelInput = document.getElementById('newModelInput');
    const newBaseUrlInput = document.getElementById('newBaseUrlInput');
    const newQuickSuggestions = document.getElementById('newQuickSuggestions');
    const newApiKeyInput = document.getElementById('newApiKeyInput');
    const btnToggleNewApiKey = document.getElementById('btnToggleNewApiKey');
    const newEyeIcon = document.getElementById('newEyeIcon');

    function updateNewSuggestions(provider) {
        const config = presets[provider] || presets.groqcloud;
        if (!newQuickSuggestions) return;
        newQuickSuggestions.innerHTML = '<small class="text-muted" style="font-size:0.7rem;">Saran:</small>';
        
        config.models.forEach(function (m) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-sm btn-outline-secondary rounded-pill py-0 px-2' + (newModelInput.value === m ? ' active' : '');
            btn.style.fontSize = '0.7rem';
            btn.textContent = m;
            btn.addEventListener('click', function () {
                newModelInput.value = m;
                document.querySelectorAll('#newQuickSuggestions button').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
            });
            newQuickSuggestions.appendChild(btn);
        });
    }

    if (newProviderSelect) {
        newProviderSelect.addEventListener('change', function () {
            const selected = newProviderSelect.value;
            const config = presets[selected];
            if (config) {
                newBaseUrlInput.value = config.baseUrl;
                newModelInput.value = config.defaultModel;
                updateNewSuggestions(selected);
            }
        });
        updateNewSuggestions(newProviderSelect.value);
    }

    if (btnToggleNewApiKey && newApiKeyInput && newEyeIcon) {
        btnToggleNewApiKey.addEventListener('click', function () {
            if (newApiKeyInput.type === 'password') {
                newApiKeyInput.type = 'text';
                newEyeIcon.classList.remove('fa-eye');
                newEyeIcon.classList.add('fa-eye-slash');
            } else {
                newApiKeyInput.type = 'password';
                newEyeIcon.classList.remove('fa-eye-slash');
                newEyeIcon.classList.add('fa-eye');
            }
        });
    }
});
</script>
