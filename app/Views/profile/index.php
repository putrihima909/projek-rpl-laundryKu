<?php
$showSidebar = true;
$baseUrl = config('app.url', 'http://localhost/rpl/public');
?>

<div class="container-fluid">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center pb-3 mb-4 border-bottom">
        <div>
            <span class="badge bg-info text-dark px-3 py-1 mb-2 fw-bold"><i class="fa-solid fa-user me-1"></i> PROFIL PELANGGAN</span>
            <h2 class="fw-bold text-dark mb-1">Profil Saya</h2>
            <p class="text-muted mb-0">Informasi data diri dan alamat utama penjemputan laundry Anda.</p>
        </div>
        <div class="mt-3 mt-md-0 d-flex gap-2">
            <a href="<?= $baseUrl ?>/profile/edit" class="btn btn-primary rounded-pill px-4 shadow-sm fw-bold">
                <i class="fa-solid fa-user-pen me-2"></i> Edit Profil
            </a>
            <a href="<?= $baseUrl ?>/addresses" class="btn btn-outline-primary rounded-pill px-4 fw-semibold">
                <i class="fa-solid fa-location-dot me-2"></i> Kelola Alamat Saya
            </a>
        </div>
    </div>

    <div class="row g-4">
        <!-- User Bio Card -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 text-center p-4">
                <div class="mx-auto mb-3">
                    <?php if (!empty($user['avatar'])): ?>
                        <img src="<?= htmlspecialchars($user['avatar']) ?>" class="rounded-circle shadow-sm border border-3 border-primary" style="width: 100px; height: 100px; object-fit: cover;" alt="Avatar">
                    <?php else: ?>
                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center mx-auto shadow-sm" style="width: 100px; height: 100px; font-size: 2.5rem; font-weight: 700;">
                            <?= strtoupper(substr($user['name'] ?? 'C', 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <h4 class="fw-bold text-dark mb-1"><?= htmlspecialchars($user['name'] ?? 'Customer') ?></h4>
                <p class="text-muted mb-2"><?= htmlspecialchars($user['email'] ?? '') ?></p>
                <span class="badge bg-primary rounded-pill px-3 py-1.5 align-self-center mb-3">
                    <i class="fa-solid fa-shield me-1"></i> Role: <?= htmlspecialchars($user['display_name'] ?? 'Customer') ?>
                </span>

                <hr>

                <div class="text-start">
                    <div class="mb-3">
                        <small class="text-muted d-block fw-semibold text-uppercase">Nomor Telepon / WhatsApp</small>
                        <span class="fw-bold text-dark fs-6"><?= htmlspecialchars($user['phone'] ?: 'Belum diisi') ?></span>
                    </div>
                    <div class="mb-3">
                        <small class="text-muted d-block fw-semibold text-uppercase">Status Akun</small>
                        <span class="badge bg-success rounded-pill px-3 py-1"><i class="fa-solid fa-check-circle me-1"></i> Akun Aktif</span>
                    </div>
                    <div>
                        <small class="text-muted d-block fw-semibold text-uppercase">Metode Login</small>
                        <span class="fw-semibold text-dark"><i class="fa-solid fa-key text-info me-1"></i> Form / Email & Password</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Primary Address Summary Card -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4 p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-house-chimney text-primary me-2"></i> Alamat Penjemputan Utama</h5>
                    <a href="<?= $baseUrl ?>/addresses" class="btn btn-sm btn-outline-primary rounded-pill">Daftar Alamat (<?= $addressesCount ?>)</a>
                </div>

                <?php if ($primaryAddress): ?>
                    <div class="p-3 bg-light rounded-3 border border-primary border-opacity-25 mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="fw-bold text-primary mb-0"><i class="fa-solid fa-tag me-1"></i> <?= htmlspecialchars($primaryAddress['title']) ?></h6>
                            <span class="badge bg-primary rounded-pill px-3 py-1">Utama</span>
                        </div>
                        <p class="fw-bold mb-1 text-dark"><?= htmlspecialchars($primaryAddress['address_line']) ?></p>
                        <p class="text-muted small mb-1">
                            <?= htmlspecialchars($primaryAddress['village']) ?>, RT <?= htmlspecialchars($primaryAddress['rt']) ?> / RW <?= htmlspecialchars($primaryAddress['rw']) ?>, 
                            <?= htmlspecialchars($primaryAddress['district']) ?>, <?= htmlspecialchars($primaryAddress['city']) ?>, <?= htmlspecialchars($primaryAddress['province']) ?>
                        </p>
                        <?php if (!empty($primaryAddress['detail_address'])): ?>
                            <small class="text-secondary d-block"><i class="fa-solid fa-info-circle me-1"></i> Detail: <?= htmlspecialchars($primaryAddress['detail_address']) ?></small>
                        <?php endif; ?>
                        <?php if (!empty($primaryAddress['latitude']) && !empty($primaryAddress['longitude'])): ?>
                            <small class="text-muted d-block mt-1"><i class="fa-solid fa-location-crosshairs text-success me-1"></i> GPS: <?= htmlspecialchars($primaryAddress['latitude']) ?>, <?= htmlspecialchars($primaryAddress['longitude']) ?></small>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4 bg-light rounded-3 border">
                        <i class="fa-solid fa-location-dot display-4 text-muted mb-2"></i>
                        <p class="text-muted mb-3">Anda belum mendaftarkan alamat penjemputan utama.</p>
                        <a href="<?= $baseUrl ?>/addresses/create" class="btn btn-primary rounded-pill btn-sm fw-bold px-4">
                            <i class="fa-solid fa-plus me-1"></i> Tambah Alamat Sekarang
                        </a>
                    </div>
                <?php endif; ?>

                <div class="mt-auto pt-3 border-top">
                    <div class="d-flex gap-2">
                        <a href="<?= $baseUrl ?>/orders/create" class="btn btn-success btn-lg w-100 rounded-pill shadow-sm fw-bold">
                            <i class="fa-solid fa-basket-shopping me-2"></i> Pesan Laundry Baru
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
