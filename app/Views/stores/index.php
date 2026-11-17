<?php
$showSidebar = false;
$baseUrl = config('app.url', 'http://localhost/rpl/public');
?>

<div class="container py-5">
    <!-- Page Header -->
    <div class="row mb-4 align-items-center">
        <div class="col-lg-7">
            <span class="badge bg-primary px-3 py-2 mb-2 fw-semibold"><i class="fa-solid fa-store me-1"></i> MARKETPLACE LAUNDRY</span>
            <h1 class="fw-bold text-dark mb-1" style="font-size:2rem;">Temukan Toko Laundry Terbaik</h1>
            <p class="text-muted mb-0">Pilih dari <?= count($stores) ?> outlet laundry terpercaya dengan harga transparan dan layanan antar jemput.</p>
        </div>
        <div class="col-lg-5 mt-3 mt-lg-0 text-lg-end">
            <?php if ($isLoggedIn && isset($user['role_name']) && $user['role_name'] === 'customer'): ?>
                <a href="<?= $baseUrl ?>/orders/create" class="btn btn-success rounded-pill px-4 shadow-sm fw-bold">
                    <i class="fa-solid fa-cart-plus me-2"></i> Pesan Laundry Sekarang
                </a>
            <?php elseif (!$isLoggedIn): ?>
                <a href="<?= $baseUrl ?>/login" class="btn btn-primary rounded-pill px-4 shadow-sm fw-bold">
                    <i class="fa-solid fa-right-to-bracket me-2"></i> Login untuk Memesan
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filter & Search Bar -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-3">
            <form method="GET" action="<?= $baseUrl ?>/stores" class="row g-2 align-items-end" id="filter-form">
                <!-- Search Name -->
                <div class="col-12 col-md-4">
                    <label class="form-label small fw-semibold text-muted mb-1"><i class="fa-solid fa-magnifying-glass me-1"></i>Cari Nama / Lokasi Toko</label>
                    <input type="text" name="search" id="search-input"
                           class="form-control rounded-3 border-light bg-light"
                           placeholder="Contoh: CleanMaster, Jakarta Selatan..."
                           value="<?= htmlspecialchars($filters['search'] ?? '') ?>">
                </div>

                <!-- Filter Min Rating -->
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-semibold text-muted mb-1"><i class="fa-solid fa-star text-warning me-1"></i>Min. Rating</label>
                    <select name="min_rating" class="form-select rounded-3 border-light bg-light">
                        <option value="">Semua Rating</option>
                        <option value="4.0" <?= ($filters['min_rating'] ?? '') === '4.0' ? 'selected' : '' ?>>⭐ 4.0 ke atas</option>
                        <option value="4.5" <?= ($filters['min_rating'] ?? '') === '4.5' ? 'selected' : '' ?>>⭐ 4.5 ke atas</option>
                        <option value="4.8" <?= ($filters['min_rating'] ?? '') === '4.8' ? 'selected' : '' ?>>⭐ 4.8 ke atas</option>
                    </select>
                </div>

                <!-- Filter Service Type -->
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-semibold text-muted mb-1"><i class="fa-solid fa-tags me-1"></i>Jenis Layanan</label>
                    <select name="service_type" class="form-select rounded-3 border-light bg-light">
                        <option value="">Semua Layanan</option>
                        <option value="kg"          <?= ($filters['service_type'] ?? '') === 'kg'          ? 'selected' : '' ?>>Kiloan (kg)</option>
                        <option value="pcs"         <?= ($filters['service_type'] ?? '') === 'pcs'         ? 'selected' : '' ?>>Satuan (pcs)</option>
                        <option value="Express"     <?= ($filters['service_type'] ?? '') === 'Express'     ? 'selected' : '' ?>>Express / Kilat</option>
                        <option value="Dry"         <?= ($filters['service_type'] ?? '') === 'Dry'         ? 'selected' : '' ?>>Dry Cleaning</option>
                    </select>
                </div>

                <!-- Sort By -->
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-semibold text-muted mb-1"><i class="fa-solid fa-arrow-up-wide-short me-1"></i>Urutkan</label>
                    <select name="sort" class="form-select rounded-3 border-light bg-light">
                        <option value="name"      <?= ($filters['sort'] ?? '') === 'name'      ? 'selected' : '' ?>>Nama A–Z</option>
                        <option value="rating"    <?= ($filters['sort'] ?? '') === 'rating'    ? 'selected' : '' ?>>Rating Tertinggi</option>
                        <option value="price_asc" <?= ($filters['sort'] ?? '') === 'price_asc' ? 'selected' : '' ?>>Harga Termurah</option>
                        <option value="distance"  <?= ($filters['sort'] ?? '') === 'distance'  ? 'selected' : '' ?> <?= !$hasCoords ? 'disabled' : '' ?>>
                            Jarak Terdekat <?= !$hasCoords ? '(Perlu Alamat)' : '' ?>
                        </option>
                    </select>
                </div>

                <!-- Submit & Reset Button -->
                <div class="col-6 col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1 rounded-3 fw-semibold">
                        <i class="fa-solid fa-filter me-1"></i> Filter
                    </button>
                    <?php if (!empty($filters['search']) || !empty($filters['min_rating']) || !empty($filters['service_type']) || ($filters['sort'] ?? 'name') !== 'name'): ?>
                        <a href="<?= $baseUrl ?>/stores" class="btn btn-outline-secondary rounded-3" title="Reset Filter">
                            <i class="fa-solid fa-xmark"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Stores Grid -->
    <?php if (empty($stores)): ?>
        <div class="card border-0 shadow-sm rounded-4 text-center py-5">
            <div class="card-body">
                <div class="mb-3" style="font-size:3.5rem;">🔍</div>
                <h4 class="fw-bold text-dark mb-1">Tidak ada toko yang cocok dengan filter</h4>
                <p class="text-muted mb-3">Coba ubah kata kunci pencarian, turunkan rating minimum, atau pilih jenis layanan lain.</p>
                <a href="<?= $baseUrl ?>/stores" class="btn btn-primary rounded-pill px-4 fw-semibold">
                    <i class="fa-solid fa-rotate-left me-2"></i> Reset Filter
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-4" id="stores-grid">
            <?php foreach ($stores as $store): ?>
            <?php
                $distKm       = $store['distance_km'] ?? null;
                $distLabel    = ($distKm !== null) ? number_format((float)$distKm, 1) . ' km' : null;
                $minPrice     = $store['min_price']  ?? null;
                $svcCount     = (int)($store['service_count'] ?? 0);
                $rating       = (float)($store['rating'] ?? 4.8);
                $workingHours = $store['working_hours'] ?? '08:00 - 21:00';
            ?>
            <div class="col-12 col-sm-6 col-lg-4">
                <div class="card border-0 shadow-sm rounded-4 h-100 store-card" style="transition: transform .18s, box-shadow .18s; cursor:pointer;"
                     onclick="window.location='<?= $baseUrl ?>/stores/<?= $store['id'] ?>'">
                    <!-- Card Top Banner -->
                    <div class="rounded-top-4 d-flex align-items-center justify-content-between p-3"
                         style="background: linear-gradient(135deg, #0d6efd18 0%, #0dcaf018 100%);">
                        <div class="rounded-circle bg-white shadow-sm d-flex align-items-center justify-content-center"
                             style="width:52px;height:52px;">
                            <i class="fa-solid fa-store text-primary" style="font-size:1.4rem;"></i>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-warning text-dark fw-bold px-2 py-1 shadow-sm">
                                <i class="fa-solid fa-star me-1"></i><?= number_format($rating, 1) ?>
                            </span>
                            <div class="small text-muted mt-1" style="font-size:.72rem;">
                                <i class="fa-regular fa-clock me-1"></i><?= htmlspecialchars($workingHours) ?>
                            </div>
                        </div>
                    </div>

                    <div class="card-body d-flex flex-column p-3">
                        <!-- Status & Distance Badges -->
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge bg-success-subtle text-success border border-success-subtle">
                                <i class="fa-solid fa-circle-check me-1"></i> Outlet Terverifikasi
                            </span>
                            <?php if ($distLabel): ?>
                            <span class="badge bg-info-subtle text-info border border-info-subtle">
                                <i class="fa-solid fa-location-dot me-1"></i> <?= $distLabel ?>
                            </span>
                            <?php endif; ?>
                        </div>

                        <!-- Store Name & Address -->
                        <h5 class="fw-bold mb-1 text-dark" style="font-size:1.05rem; line-height:1.3;">
                            <?= htmlspecialchars($store['name']) ?>
                        </h5>
                        <p class="text-muted small mb-2" style="font-size:.8rem; line-height:1.4;">
                            <i class="fa-solid fa-location-dot text-danger me-1"></i>
                            <?= htmlspecialchars(mb_strimwidth($store['address'] ?? '', 0, 65, '...')) ?>
                        </p>
                        <?php if (!empty($store['phone'])): ?>
                        <p class="text-muted small mb-3" style="font-size:.78rem;">
                            <i class="fa-solid fa-phone me-1 text-success"></i>
                            <?= htmlspecialchars($store['phone']) ?>
                        </p>
                        <?php endif; ?>

                        <!-- Stats Row -->
                        <div class="d-flex justify-content-between align-items-center mb-3 mt-auto pt-2 border-top">
                            <div>
                                <div class="text-muted" style="font-size:.7rem;">Jumlah Layanan</div>
                                <div class="fw-bold text-primary" style="font-size:.9rem;"><?= $svcCount ?> Layanan</div>
                            </div>
                            <?php if ($minPrice): ?>
                            <div class="text-end">
                                <div class="text-muted" style="font-size:.7rem;">Harga Mulai</div>
                                <div class="fw-bold text-success" style="font-size:.95rem;">Rp <?= number_format((float)$minPrice, 0, ',', '.') ?></div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- CTA -->
                        <a href="<?= $baseUrl ?>/stores/<?= $store['id'] ?>"
                           class="btn btn-outline-primary rounded-pill btn-sm fw-semibold w-100" onclick="event.stopPropagation()">
                            <i class="fa-solid fa-arrow-right me-1"></i> Lihat Detail & Layanan
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Result count info -->
        <div class="text-center mt-4 text-muted small">
            Menampilkan <strong><?= count($stores) ?></strong> toko aktif
            <?= !empty($filters['search']) ? 'untuk pencarian "<strong>' . htmlspecialchars($filters['search']) . '</strong>"' : '' ?>
        </div>
    <?php endif; ?>
</div>

<style>
.store-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 32px rgba(13,110,253,.13) !important;
}
</style>
