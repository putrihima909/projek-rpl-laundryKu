<?php
$showSidebar = true;
$baseUrl = config('app.url', 'http://localhost/rpl/public');
$isEdit = !empty($editAddress);
$actionUrl = $isEdit ? $baseUrl . '/addresses/update' : $baseUrl . '/addresses/store';
?>

<div class="container-fluid">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center pb-3 mb-4 border-bottom">
        <div>
            <span class="badge bg-primary text-white px-3 py-1 mb-2 fw-bold"><i class="fa-solid fa-location-dot me-1"></i> FORM ALAMAT</span>
            <h2 class="fw-bold text-dark mb-1"><?= $isEdit ? 'Edit Alamat' : 'Tambah Alamat Baru' ?></h2>
            <p class="text-muted mb-0"><?= $isEdit ? 'Ubah rincian informasi lokasi penjemputan Anda.' : 'Lengkapi data alamat untuk penjemputan & pengantaran laundry.' ?></p>
        </div>
        <div class="mt-3 mt-md-0">
            <a href="<?= $baseUrl ?>/addresses" class="btn btn-outline-secondary rounded-pill btn-sm">
                <i class="fa-solid fa-arrow-left me-1"></i> Kembali ke Daftar Alamat
            </a>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4 p-md-5">
                    <form action="<?= $actionUrl ?>" method="POST">
                        <?= \App\Helpers\Csrf::field() ?>

                        <?php if ($isEdit): ?>
                            <input type="hidden" name="id" value="<?= $editAddress['id'] ?>">
                        <?php endif; ?>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="title" class="form-label fw-bold text-secondary">Judul / Label Alamat <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="title" name="title" required placeholder="Contoh: Rumah Utama, Kantor, Kost" value="<?= htmlspecialchars($editAddress['title'] ?? 'Rumah') ?>">
                            </div>

                            <div class="col-md-6">
                                <label for="address_line" class="form-label fw-bold text-secondary">Jalan / No. Rumah <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="address_line" name="address_line" required placeholder="Contoh: Jl. Mawar Indah No. 12" value="<?= htmlspecialchars($editAddress['address_line'] ?? '') ?>">
                            </div>

                            <div class="col-md-6">
                                <label for="province" class="form-label fw-bold text-secondary">Provinsi <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="province" name="province" required placeholder="Contoh: DKI Jakarta" value="<?= htmlspecialchars($editAddress['province'] ?? 'DKI Jakarta') ?>">
                            </div>

                            <div class="col-md-6">
                                <label for="city" class="form-label fw-bold text-secondary">Kota / Kabupaten <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="city" name="city" required placeholder="Contoh: Jakarta Selatan" value="<?= htmlspecialchars($editAddress['city'] ?? 'Jakarta Selatan') ?>">
                            </div>

                            <div class="col-md-6">
                                <label for="district" class="form-label fw-bold text-secondary">Kecamatan <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="district" name="district" required placeholder="Contoh: Kebayoran Baru" value="<?= htmlspecialchars($editAddress['district'] ?? '') ?>">
                            </div>

                            <div class="col-md-6">
                                <label for="village" class="form-label fw-bold text-secondary">Kelurahan <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="village" name="village" required placeholder="Contoh: Gandaria Utara" value="<?= htmlspecialchars($editAddress['village'] ?? '') ?>">
                            </div>

                            <div class="col-md-3">
                                <label for="rt" class="form-label fw-bold text-secondary">RT <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="rt" name="rt" required placeholder="003" value="<?= htmlspecialchars($editAddress['rt'] ?? '001') ?>">
                            </div>

                            <div class="col-md-3">
                                <label for="rw" class="form-label fw-bold text-secondary">RW <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="rw" name="rw" required placeholder="005" value="<?= htmlspecialchars($editAddress['rw'] ?? '001') ?>">
                            </div>

                            <div class="col-md-6">
                                <label for="detail_address" class="form-label fw-bold text-secondary">Detail Alamat / Patokan</label>
                                <input type="text" class="form-control" id="detail_address" name="detail_address" placeholder="Contoh: Pagar hitam depan lapangan" value="<?= htmlspecialchars($editAddress['detail_address'] ?? '') ?>">
                            </div>

                            <div class="col-md-6">
                                <label for="latitude" class="form-label fw-bold text-secondary">Latitude <small class="text-muted">(Opsional GPS)</small></label>
                                <input type="text" class="form-control" id="latitude" name="latitude" placeholder="-6.24430000" value="<?= htmlspecialchars($editAddress['latitude'] ?? '') ?>">
                            </div>

                            <div class="col-md-6">
                                <label for="longitude" class="form-label fw-bold text-secondary">Longitude <small class="text-muted">(Opsional GPS)</small></label>
                                <input type="text" class="form-control" id="longitude" name="longitude" placeholder="106.79300000" value="<?= htmlspecialchars($editAddress['longitude'] ?? '') ?>">
                            </div>

                            <div class="col-md-12 mt-3">
                                <div class="form-check form-switch bg-light p-3 rounded-3 border">
                                    <input class="form-check-input ms-0 me-2" type="checkbox" id="is_primary" name="is_primary" value="1" <?= (!empty($editAddress['is_primary'])) ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-bold text-dark" for="is_primary">
                                        Jadikan Alamat Penjemputan Utama
                                    </label>
                                </div>
                            </div>

                            <div class="col-md-12 mt-4 text-end">
                                <a href="<?= $baseUrl ?>/addresses" class="btn btn-light rounded-pill px-4 me-2">Batal</a>
                                <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm">
                                    <i class="fa-solid fa-save me-2"></i> <?= $isEdit ? 'Simpan Perubahan' : 'Tambah Alamat' ?>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
