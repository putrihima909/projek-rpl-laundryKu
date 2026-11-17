<?php
$showSidebar = true;
$baseUrl = config('app.url', 'http://localhost/rpl/public');
$isEdit = !empty($editUser);
$actionUrl = $isEdit ? $baseUrl . '/users/update' : $baseUrl . '/users/store';
?>

<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center pb-3 mb-4 border-bottom">
        <div>
            <span class="badge bg-primary text-white px-3 py-1 mb-2 fw-bold"><i class="fa-solid fa-user-gear me-1"></i> FORM USER RBAC</span>
            <h2 class="fw-bold text-dark mb-1"><?= $isEdit ? 'Edit Data User' : 'Tambah User Baru' ?></h2>
            <p class="text-muted mb-0"><?= $isEdit ? 'Ubah informasi, role akses, atau password user.' : 'Daftarkan user baru dan pilih role aksesnya.' ?></p>
        </div>
        <div class="mt-3 mt-md-0">
            <a href="<?= $baseUrl ?>/users" class="btn btn-outline-secondary rounded-pill btn-sm">
                <i class="fa-solid fa-arrow-left me-1"></i> Kembali ke Daftar User
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
                            <input type="hidden" name="id" value="<?= $editUser['id'] ?>">
                        <?php endif; ?>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="name" class="form-label fw-bold text-secondary">Nama Lengkap <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" required placeholder="Contoh: Ahmad Subagja" value="<?= htmlspecialchars($editUser['name'] ?? '') ?>">
                            </div>

                            <div class="col-md-6">
                                <label for="email" class="form-label fw-bold text-secondary">Alamat Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" required placeholder="nama@domain.com" value="<?= htmlspecialchars($editUser['email'] ?? '') ?>">
                            </div>

                            <div class="col-md-6">
                                <label for="role_id" class="form-label fw-bold text-secondary">Role Akses Sistem <span class="text-danger">*</span></label>
                                <select class="form-select" id="role_id" name="role_id" required>
                                    <option value="">-- Pilih Role --</option>
                                    <?php foreach ($roles as $r): ?>
                                        <?php 
                                            $selected = ($isEdit && isset($editUser['role_id']) && (int)$editUser['role_id'] === (int)$r['id']) ? 'selected' : '';
                                        ?>
                                        <option value="<?= $r['id'] ?>" <?= $selected ?>>
                                            <?= htmlspecialchars($r['display_name'] ?? $r['name']) ?> (<code><?= $r['name'] ?></code>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="phone" class="form-label fw-bold text-secondary">Nomor Telepon / WhatsApp</label>
                                <input type="text" class="form-control" id="phone" name="phone" placeholder="0812xxxxxxxx" value="<?= htmlspecialchars($editUser['phone'] ?? '') ?>">
                            </div>

                            <div class="col-md-12">
                                <label for="password" class="form-label fw-bold text-secondary">
                                    Password <?= $isEdit ? '<small class="text-muted fw-normal">(Kosongkan jika tidak ingin mengubah password)</small>' : '<span class="text-danger">*</span>' ?>
                                </label>
                                <input type="password" class="form-control" id="password" name="password" <?= $isEdit ? '' : 'required' ?> placeholder="Minimal 6 karakter">
                            </div>

                            <div class="col-md-12 mt-4">
                                <div class="form-check form-switch bg-light p-3 rounded-3 border">
                                    <input class="form-check-input ms-0 me-2" type="checkbox" id="is_active" name="is_active" value="1" <?= (!isset($editUser) || !empty($editUser['is_active'])) ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-bold text-dark" for="is_active">
                                        Akun Aktif (Dapat Login ke Sistem)
                                    </label>
                                </div>
                            </div>

                            <div class="col-md-12 mt-4 text-end">
                                <a href="<?= $baseUrl ?>/users" class="btn btn-light rounded-pill px-4 me-2">Batal</a>
                                <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm">
                                    <i class="fa-solid fa-save me-2"></i> <?= $isEdit ? 'Simpan Perubahan' : 'Tambah User' ?>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
