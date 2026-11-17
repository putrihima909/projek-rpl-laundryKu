<?php
$baseUrl = config('app.url', 'http://localhost/rpl/public');
?>

<div class="container-fluid py-4">
    <!-- Header with Store Info -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1.5 rounded-pill fw-semibold">
                    <i class="fa-solid fa-shop me-1"></i> Outlet: <?= htmlspecialchars($store['name']) ?>
                </span>
            </div>
            <h2 class="fw-bold text-dark mb-0">Kelola Layanan & Tarif Laundry</h2>
            <p class="text-muted small mb-0">Atur paket laundry, harga per kilogram/satuan, dan estimasi waktu selesai untuk toko Anda.</p>
        </div>
        <div>
            <a href="<?= $baseUrl ?>/services/create" class="btn btn-primary rounded-pill px-4 shadow-sm fw-semibold">
                <i class="fa-solid fa-plus me-1"></i> Tambah Layanan Baru
            </a>
        </div>
    </div>

    <!-- Services Table Card -->
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4 py-3 text-muted small fw-semibold">NAMA LAYANAN</th>
                            <th class="py-3 text-muted small fw-semibold">TARIF / HARGA</th>
                            <th class="py-3 text-muted small fw-semibold">SATUAN</th>
                            <th class="py-3 text-muted small fw-semibold">ESTIMASI SELESAI</th>
                            <th class="py-3 text-muted small fw-semibold text-center">STATUS</th>
                            <th class="pe-4 py-3 text-muted small fw-semibold text-end">AKSI</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($services)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-tags fs-1 d-block mb-2 text-secondary"></i>
                                    Belum ada layanan yang dibuat untuk toko ini.<br>
                                    <a href="<?= $baseUrl ?>/services/create" class="btn btn-sm btn-primary rounded-pill mt-2">
                                        <i class="fa-solid fa-plus me-1"></i> Buat Layanan Pertama
                                    </a>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($services as $svc): ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($svc['name']) ?></div>
                                        <?php if (!empty($svc['description'])): ?>
                                            <small class="text-muted d-block" style="max-width:320px;">
                                                <?= htmlspecialchars($svc['description']) ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="fw-bold text-success fs-6">
                                            Rp <?= number_format((float)$svc['price'], 0, ',', '.') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2.5 py-1 text-uppercase">
                                            / <?= htmlspecialchars($svc['unit']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="text-dark small">
                                            <i class="fa-regular fa-clock me-1 text-primary"></i><?= (int)$svc['duration_hours'] ?> Jam
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <?php if (!empty($svc['is_active'])): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1.5 rounded-pill">
                                                <i class="fa-solid fa-circle-check me-1"></i> Aktif
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-1.5 rounded-pill">
                                                <i class="fa-solid fa-circle-xmark me-1"></i> Non-Aktif
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="pe-4 text-end">
                                        <div class="d-inline-flex gap-1">
                                            <!-- Edit -->
                                            <a href="<?= $baseUrl ?>/services/edit?id=<?= $svc['id'] ?>"
                                               class="btn btn-sm btn-outline-primary rounded-pill px-2.5" title="Edit Layanan">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </a>

                                            <!-- Delete -->
                                            <form method="POST" action="<?= $baseUrl ?>/services/destroy" class="d-inline"
                                                  onsubmit="return confirm('Apakah Anda yakin ingin menghapus layanan \'<?= htmlspecialchars(addslashes($svc['name'])) ?>\'?');">
                                                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                                                <input type="hidden" name="id" value="<?= $svc['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-2.5" title="Hapus Layanan">
                                                    <i class="fa-solid fa-trash"></i>
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
