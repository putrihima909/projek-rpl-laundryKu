<?php
$baseUrl = config('app.url', 'http://localhost/rpl/public');
$isEdit = !empty($editStore);
$actionUrl = $isEdit ? $baseUrl . '/stores/update' : $baseUrl . '/stores/store';
?>

<div class="container-fluid py-4" style="max-width: 860px;">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= $baseUrl ?>/stores/manage" class="text-decoration-none">Manajemen Toko</a></li>
            <li class="breadcrumb-item active"><?= $isEdit ? 'Edit Toko' : 'Tambah Toko Baru' ?></li>
        </ol>
    </nav>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-header bg-transparent border-0 p-4 pb-0">
            <h4 class="fw-bold text-dark mb-1"><?= $isEdit ? 'Edit Data Toko Laundry' : 'Tambah Toko Laundry Baru' ?></h4>
            <p class="text-muted small mb-0">Isi formulir di bawah ini dengan lengkap untuk mendaftarkan outlet ke platform.</p>
        </div>

        <div class="card-body p-4">
            <form method="POST" action="<?= $actionUrl ?>">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                <?php if ($isEdit): ?>
                    <input type="hidden" name="id" value="<?= $editStore['id'] ?>">
                <?php endif; ?>

                <div class="row g-3">
                    <!-- Nama Toko -->
                    <div class="col-12 col-md-8">
                        <label class="form-label fw-semibold text-dark">Nama Toko / Outlet <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control rounded-3" required minlength="3" maxlength="100"
                               placeholder="Contoh: CleanMaster Laundry Express"
                               value="<?= htmlspecialchars($editStore['name'] ?? '') ?>">
                    </div>

                    <!-- Telepon -->
                    <div class="col-12 col-md-4">
                        <label class="form-label fw-semibold text-dark">Nomor Telepon / WhatsApp <span class="text-danger">*</span></label>
                        <input type="text" name="phone" class="form-control rounded-3" required
                               placeholder="081234567890"
                               value="<?= htmlspecialchars($editStore['phone'] ?? '') ?>">
                    </div>

                    <!-- User ID / Owner ID -->
                    <?php if (!$isEdit): ?>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold text-dark">ID Owner Akun (User ID)</label>
                            <input type="number" name="user_id" class="form-control rounded-3"
                                   placeholder="Kosongkan jika akun owner saat ini"
                                   value="<?= htmlspecialchars($_POST['user_id'] ?? '') ?>">
                            <div class="form-text small">User ID pemilik toko (role owner_toko).</div>
                        </div>
                    <?php endif; ?>

                    <!-- Jam Operasional -->
                    <div class="col-12 <?= !$isEdit ? 'col-md-6' : 'col-md-12' ?>">
                        <label class="form-label fw-semibold text-dark">Jam Operasional</label>
                        <input type="text" name="working_hours" class="form-control rounded-3"
                               placeholder="Contoh: 07:00 - 21:00"
                               value="<?= htmlspecialchars($editStore['working_hours'] ?? '08:00 - 21:00') ?>">
                    </div>

                    <!-- Deskripsi Toko -->
                    <div class="col-12">
                        <label class="form-label fw-semibold text-dark">Deskripsi Toko</label>
                        <textarea name="description" class="form-control rounded-3" rows="3"
                                  placeholder="Jelaskan keunggulan toko, layanan spesialis, atau garansi wangi..."><?= htmlspecialchars($editStore['description'] ?? '') ?></textarea>
                    </div>

                    <!-- Alamat Lengkap -->
                    <div class="col-12">
                        <label class="form-label fw-semibold text-dark">Alamat Lengkap <span class="text-danger">*</span></label>
                        <textarea name="address" class="form-control rounded-3" rows="2" required minlength="5"
                                  placeholder="Jl. Merdeka No. 45, RT 02 / RW 05, Kelurahan, Kecamatan, Kota..."><?= htmlspecialchars($editStore['address'] ?? '') ?></textarea>
                    </div>

                    <!-- Koordinat GPS -->
                    <div class="col-12 col-md-6">
                        <label class="form-label fw-semibold text-dark">Latitude (GPS)</label>
                        <input type="text" name="latitude" class="form-control rounded-3"
                               placeholder="-6.22974650"
                               value="<?= htmlspecialchars($editStore['latitude'] ?? '-6.22974650') ?>">
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label fw-semibold text-dark">Longitude (GPS)</label>
                        <input type="text" name="longitude" class="form-control rounded-3"
                               placeholder="106.81644900"
                               value="<?= htmlspecialchars($editStore['longitude'] ?? '106.81644900') ?>">
                    </div>

                    <!-- Status Aktif -->
                    <div class="col-12">
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" name="is_active" id="isActiveSwitch"
                                   value="1" <?= (!isset($editStore) || !empty($editStore['is_active'])) ? 'checked' : '' ?>>
                            <label class="form-check-label fw-semibold" for="isActiveSwitch">
                                Aktifkan Toko di Marketplace
                            </label>
                            <div class="form-text small">Toko aktif dapat dilihat dan dipesan oleh pelanggan.</div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                    <a href="<?= $baseUrl ?>/stores/manage" class="btn btn-outline-secondary rounded-pill px-4">
                        Batal
                    </a>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 shadow-sm fw-semibold">
                        <i class="fa-solid fa-floppy-disk me-1"></i> <?= $isEdit ? 'Simpan Perubahan' : 'Daftarkan Toko' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
