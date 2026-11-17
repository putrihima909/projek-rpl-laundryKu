<?php
$showSidebar = true;
$baseUrl = config('app.url', 'http://localhost/rpl/public');
?>

<div class="container-fluid">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center pb-3 mb-4 border-bottom">
        <div>
            <span class="badge bg-primary text-white px-3 py-1 mb-2 fw-bold"><i class="fa-solid fa-user-pen me-1"></i> EDIT PROFIL</span>
            <h2 class="fw-bold text-dark mb-1">Perbarui Profil Saya</h2>
            <p class="text-muted mb-0">Ubah nama lengkap, nomor whatsapp, atau password akun Anda.</p>
        </div>
        <div class="mt-3 mt-md-0">
            <a href="<?= $baseUrl ?>/profile" class="btn btn-outline-secondary rounded-pill btn-sm">
                <i class="fa-solid fa-arrow-left me-1"></i> Kembali ke Profil
            </a>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4 p-md-5">
                    <form action="<?= $baseUrl ?>/profile/update" method="POST">
                        <?= \App\Helpers\Csrf::field() ?>

                        <div class="mb-3">
                            <label for="name" class="form-label fw-bold text-secondary">Nama Lengkap <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" required value="<?= htmlspecialchars($user['name'] ?? '') ?>" placeholder="Nama lengkap Anda">
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label fw-bold text-secondary">Alamat Email</label>
                            <input type="email" class="form-control bg-light" id="email" readonly value="<?= htmlspecialchars($user['email'] ?? '') ?>">
                            <small class="text-muted">Email terdaftar tidak dapat diubah secara manual.</small>
                        </div>

                        <div class="mb-3">
                            <label for="phone" class="form-label fw-bold text-secondary">Nomor Telepon / WhatsApp <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="phone" name="phone" required value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="Contoh: 081234567890 (10-15 digit)">
                        </div>

                        <div class="mb-4">
                            <label for="password" class="form-label fw-bold text-secondary">Password Baru <small class="text-muted fw-normal">(Kosongkan jika tidak ingin diubah)</small></label>
                            <input type="password" class="form-control" id="password" name="password" placeholder="Minimal 6 karakter">
                        </div>

                        <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                            <a href="<?= $baseUrl ?>/profile" class="btn btn-light rounded-pill px-4">Batal</a>
                            <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm">
                                <i class="fa-solid fa-save me-2"></i> Simpan Perubahan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
