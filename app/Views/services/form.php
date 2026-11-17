<?php
$baseUrl = config('app.url', 'http://localhost/rpl/public');
$isEdit = !empty($editService);
$actionUrl = $isEdit ? $baseUrl . '/services/update' : $baseUrl . '/services/store';
?>

<div class="container-fluid py-4" style="max-width: 760px;">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= $baseUrl ?>/services" class="text-decoration-none">Kelola Layanan</a></li>
            <li class="breadcrumb-item active"><?= $isEdit ? 'Edit Layanan' : 'Tambah Layanan Baru' ?></li>
        </ol>
    </nav>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-header bg-transparent border-0 p-4 pb-0">
            <div class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1.5 rounded-pill mb-2">
                <i class="fa-solid fa-shop me-1"></i> Outlet: <?= htmlspecialchars($store['name']) ?>
            </div>
            <h4 class="fw-bold text-dark mb-1"><?= $isEdit ? 'Edit Layanan Laundry' : 'Tambah Layanan Laundry Baru' ?></h4>
            <p class="text-muted small mb-0">Tentukan nama, tarif, satuan unit, dan durasi pengerjaan cucian.</p>
        </div>

        <div class="card-body p-4">
            <form method="POST" action="<?= $actionUrl ?>">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                <?php if ($isEdit): ?>
                    <input type="hidden" name="id" value="<?= $editService['id'] ?>">
                <?php endif; ?>

                <div class="row g-3">
                    <!-- Nama Layanan -->
                    <div class="col-12">
                        <label class="form-label fw-semibold text-dark">Nama Layanan <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control rounded-3" required minlength="3" maxlength="100"
                               placeholder="Contoh: Cuci Komplit Reguler, Dry Cleaning Jas..."
                               value="<?= htmlspecialchars($editService['name'] ?? '') ?>">
                    </div>

                    <!-- Harga / Tarif -->
                    <div class="col-12 col-md-6">
                        <label class="form-label fw-semibold text-dark">Harga / Tarif (Rp) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">Rp</span>
                            <input type="number" name="price" step="500" min="500" class="form-control rounded-end-3" required
                                   placeholder="Contoh: 6000"
                                   value="<?= htmlspecialchars($editService['price'] ?? '') ?>">
                        </div>
                    </div>

                    <!-- Satuan / Unit -->
                    <div class="col-12 col-md-6">
                        <label class="form-label fw-semibold text-dark">Satuan Unit <span class="text-danger">*</span></label>
                        <select name="unit" class="form-select rounded-3" required>
                            <?php foreach ($units as $u): ?>
                                <option value="<?= htmlspecialchars($u) ?>"
                                    <?= ($editService['unit'] ?? 'kg') === $u ? 'selected' : '' ?>>
                                    per <?= htmlspecialchars($u) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Durasi Pengerjaan -->
                    <div class="col-12 col-md-6">
                        <label class="form-label fw-semibold text-dark">Estimasi Durasi (Jam) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" name="duration_hours" min="1" max="168" class="form-control" required
                                   placeholder="24"
                                   value="<?= htmlspecialchars($editService['duration_hours'] ?? '24') ?>">
                            <span class="input-group-text bg-light">Jam</span>
                        </div>
                        <div class="form-text small">Contoh: 24 (1 hari), 3 (express 3 jam), 48 (2 hari).</div>
                    </div>

                    <!-- Status Aktif -->
                    <div class="col-12 col-md-6 d-flex align-items-center">
                        <div class="form-check form-switch mt-3">
                            <input class="form-check-input" type="checkbox" name="is_active" id="svcIsActive"
                                   value="1" <?= (!isset($editService) || !empty($editService['is_active'])) ? 'checked' : '' ?>>
                            <label class="form-check-label fw-semibold" for="svcIsActive">
                                Status Layanan Aktif
                            </label>
                            <div class="form-text small">Layanan aktif dapat dipilih customer saat memesan.</div>
                        </div>
                    </div>

                    <!-- Deskripsi Layanan -->
                    <div class="col-12">
                        <label class="form-label fw-semibold text-dark">Deskripsi Layanan (Opsional)</label>
                        <textarea name="description" class="form-control rounded-3" rows="3"
                                  placeholder="Rincian proses pencucian, jenis deterjen/pewangi, atau instruksi pakaian..."><?= htmlspecialchars($editService['description'] ?? '') ?></textarea>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                    <a href="<?= $baseUrl ?>/services" class="btn btn-outline-secondary rounded-pill px-4">
                        Batal
                    </a>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 shadow-sm fw-semibold">
                        <i class="fa-solid fa-floppy-disk me-1"></i> <?= $isEdit ? 'Simpan Perubahan' : 'Tambahkan Layanan' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
