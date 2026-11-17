<?php
$showSidebar = true;
$baseUrl = config('app.url', 'http://localhost/rpl/public');
?>

<div class="container-fluid">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center pb-3 mb-4 border-bottom">
        <div>
            <span class="badge bg-primary text-white px-3 py-1 mb-2 fw-bold"><i class="fa-solid fa-location-dot me-1"></i> ADDRESS MANAGEMENT</span>
            <h2 class="fw-bold text-dark mb-1">Manajemen Alamat Saya</h2>
            <p class="text-muted mb-0">Kelola alamat penjemputan & pengantaran paket laundry Anda.</p>
        </div>
        <div class="mt-3 mt-md-0 d-flex gap-2">
            <a href="<?= $baseUrl ?>/addresses/create" class="btn btn-primary rounded-pill px-4 shadow-sm fw-bold">
                <i class="fa-solid fa-plus me-2"></i> Tambah Alamat Baru
            </a>
            <a href="<?= $baseUrl ?>/profile" class="btn btn-outline-secondary rounded-pill btn-sm">
                <i class="fa-solid fa-user me-1"></i> Profil
            </a>
        </div>
    </div>

    <div class="row g-4">
        <?php if (empty($addresses)): ?>
            <div class="col-12 text-center py-5 bg-white rounded-4 shadow-sm">
                <i class="fa-solid fa-map-location-dot display-1 text-muted mb-3"></i>
                <h4 class="fw-bold">Belum Ada Alamat Tersimpan</h4>
                <p class="text-muted">Tambahkan alamat penjemputan pertama Anda untuk mulai memesan laundry.</p>
                <a href="<?= $baseUrl ?>/addresses/create" class="btn btn-primary rounded-pill px-4 fw-bold">
                    <i class="fa-solid fa-plus me-2"></i> Tambah Alamat Sekarang
                </a>
            </div>
        <?php else: ?>
            <?php foreach ($addresses as $addr): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 border-0 shadow-sm rounded-4 p-3 <?= $addr['is_primary'] ? 'border border-2 border-primary' : '' ?>">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h5 class="fw-bold text-dark mb-0"><i class="fa-solid fa-tag text-primary me-2"></i> <?= htmlspecialchars($addr['title']) ?></h5>
                            <?php if ($addr['is_primary']): ?>
                                <span class="badge bg-primary rounded-pill px-3 py-1 fw-bold">Utama</span>
                            <?php endif; ?>
                        </div>

                        <p class="fw-semibold text-secondary mb-2"><?= htmlspecialchars($addr['address_line']) ?></p>
                        
                        <div class="bg-light p-2.5 rounded-3 small mb-3">
                            <div class="text-muted mb-1">
                                <strong>Provinsi:</strong> <?= htmlspecialchars($addr['province']) ?> | <strong>Kota/Kab:</strong> <?= htmlspecialchars($addr['city']) ?>
                            </div>
                            <div class="text-muted mb-1">
                                <strong>Kecamatan:</strong> <?= htmlspecialchars($addr['district']) ?> | <strong>Kelurahan:</strong> <?= htmlspecialchars($addr['village']) ?>
                            </div>
                            <div class="text-muted">
                                <strong>RT:</strong> <?= htmlspecialchars($addr['rt']) ?> / <strong>RW:</strong> <?= htmlspecialchars($addr['rw']) ?>
                            </div>
                        </div>

                        <?php if (!empty($addr['detail_address'])): ?>
                            <small class="text-secondary d-block mb-2"><i class="fa-solid fa-circle-info me-1"></i> <strong>Detail:</strong> <?= htmlspecialchars($addr['detail_address']) ?></small>
                        <?php endif; ?>

                        <?php if (!empty($addr['latitude']) && !empty($addr['longitude'])): ?>
                            <small class="text-success d-block mb-3"><i class="fa-solid fa-location-crosshairs me-1"></i> GPS: <?= htmlspecialchars($addr['latitude']) ?>, <?= htmlspecialchars($addr['longitude']) ?></small>
                        <?php endif; ?>

                        <div class="d-flex gap-2 pt-3 border-top mt-auto">
                            <a href="<?= $baseUrl ?>/addresses/edit?id=<?= $addr['id'] ?>" class="btn btn-sm btn-outline-primary rounded-pill flex-grow-1">
                                <i class="fa-solid fa-pen-to-square me-1"></i> Edit
                            </a>

                            <?php if (!$addr['is_primary']): ?>
                                <form action="<?= $baseUrl ?>/addresses/set-primary" method="POST" class="d-inline">
                                    <?= \App\Helpers\Csrf::field() ?>
                                    <input type="hidden" name="id" value="<?= $addr['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-success rounded-pill px-3">
                                        Set Utama
                                    </button>
                                </form>
                                <form action="<?= $baseUrl ?>/addresses/delete" method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus alamat ini?');">
                                    <?= \App\Helpers\Csrf::field() ?>
                                    <input type="hidden" name="id" value="<?= $addr['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger rounded-circle">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
