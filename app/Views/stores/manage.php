<?php
$baseUrl = config('app.url', 'http://localhost/rpl/public');
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-dark mb-1">Manajemen Toko Laundry</h2>
            <p class="text-muted mb-0">Kelola seluruh outlet mitra laundry yang terdaftar di platform.</p>
        </div>
        <div>
            <a href="<?= $baseUrl ?>/stores/create" class="btn btn-primary rounded-pill px-4 shadow-sm fw-semibold">
                <i class="fa-solid fa-plus me-1"></i> Tambah Toko Baru
            </a>
        </div>
    </div>

    <!-- Stores Table Card -->
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4 py-3 text-muted small fw-semibold">TOKO</th>
                            <th class="py-3 text-muted small fw-semibold">OWNER</th>
                            <th class="py-3 text-muted small fw-semibold">KONTAK & JAM KERJA</th>
                            <th class="py-3 text-muted small fw-semibold text-center">RATING & LAYANAN</th>
                            <th class="py-3 text-muted small fw-semibold text-center">STATUS</th>
                            <th class="pe-4 py-3 text-muted small fw-semibold text-end">AKSI</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($stores)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-store-slash fs-1 d-block mb-2 text-secondary"></i>
                                    Belum ada toko yang terdaftar.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($stores as $s): ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center"
                                                 style="width:44px;height:44px;flex-shrink:0;">
                                                <i class="fa-solid fa-store text-primary"></i>
                                            </div>
                                            <div>
                                                <div class="fw-bold text-dark"><?= htmlspecialchars($s['name']) ?></div>
                                                <small class="text-muted text-truncate d-block" style="max-width:260px;">
                                                    <i class="fa-solid fa-location-dot text-danger me-1"></i><?= htmlspecialchars($s['address']) ?>
                                                </small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-dark"><?= htmlspecialchars($s['owner_name'] ?? 'User ID: ' . $s['user_id']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($s['owner_email'] ?? '-') ?></small>
                                    </td>
                                    <td>
                                        <div class="text-dark small"><i class="fa-solid fa-phone text-success me-1"></i><?= htmlspecialchars($s['phone']) ?></div>
                                        <small class="text-muted"><i class="fa-regular fa-clock text-info me-1"></i><?= htmlspecialchars($s['working_hours'] ?? '08:00 - 21:00') ?></small>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-warning text-dark fw-bold mb-1">
                                            <i class="fa-solid fa-star me-1"></i><?= number_format((float)($s['rating'] ?? 4.8), 1) ?>
                                        </span>
                                        <div class="small text-muted"><?= (int)($s['service_count'] ?? 0) ?> Layanan</div>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($s['is_active']): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 rounded-pill">
                                                <i class="fa-solid fa-circle-check me-1"></i> Aktif
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-2 rounded-pill">
                                                <i class="fa-solid fa-circle-xmark me-1"></i> Non-Aktif
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="pe-4 text-end">
                                        <div class="d-inline-flex gap-1">
                                            <!-- View Public -->
                                            <a href="<?= $baseUrl ?>/stores/<?= $s['id'] ?>" target="_blank"
                                               class="btn btn-sm btn-outline-info rounded-pill px-2.5" title="Lihat di Marketplace">
                                                <i class="fa-solid fa-arrow-up-right-from-square"></i>
                                            </a>

                                            <!-- Edit -->
                                            <a href="<?= $baseUrl ?>/stores/edit?id=<?= $s['id'] ?>"
                                               class="btn btn-sm btn-outline-primary rounded-pill px-2.5" title="Edit Data Toko">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </a>

                                            <!-- Toggle Status -->
                                            <form method="POST" action="<?= $baseUrl ?>/stores/toggle-status" class="d-inline"
                                                  onsubmit="return confirm('Apakah Anda yakin ingin mengubah status aktif toko ini?');">
                                                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                                                <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                                <button type="submit" class="btn btn-sm <?= $s['is_active'] ? 'btn-outline-warning' : 'btn-outline-success' ?> rounded-pill px-2.5"
                                                        title="<?= $s['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>">
                                                    <i class="fa-solid <?= $s['is_active'] ? 'fa-toggle-on' : 'fa-toggle-off' ?>"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
