<?php
$baseUrl = config('app.url', 'http://localhost/rpl/public');
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-6 col-md-8">
            <div class="card glass-card border-0 shadow-lg rounded-4">
                <div class="card-body p-4 p-md-5">
                    <div class="text-center mb-4">
                        <div class="bg-success text-white d-inline-flex align-items-center justify-content-center rounded-circle mb-3" style="width: 70px; height: 70px;">
                            <i class="fa-brands fa-whatsapp fs-1"></i>
                        </div>
                        <h3 class="fw-bold">Verifikasi Nomor WhatsApp</h3>
                        <p class="text-muted small">Untuk keamanan akun dan menerima update cucian, mohon verifikasi nomor WhatsApp Anda.</p>
                    </div>

                    <div class="alert alert-info border-0 rounded-3 d-flex align-items-center gap-2 mb-4">
                        <i class="fa-solid fa-circle-info fs-5 text-info"></i>
                        <div>
                            Nomor WhatsApp terdaftar: <strong><?= htmlspecialchars($user['phone'] ?? '-') ?></strong>
                        </div>
                    </div>

                    <!-- Form Minta / Kirim Ulang OTP -->
                    <div class="mb-4 text-center">
                        <form action="<?= $baseUrl ?>/auth/send-otp" method="POST">
                            <?= \App\Helpers\Csrf::field() ?>
                            <button type="submit" class="btn btn-outline-success rounded-pill px-4 fw-bold">
                                <i class="fa-solid fa-paper-plane me-2"></i> Kirim Kode OTP via WhatsApp
                            </button>
                        </form>
                        <small class="text-muted d-block mt-2">Kode OTP terdiri dari 6 digit dan berlaku selama 10 menit.</small>
                    </div>

                    <div class="d-flex align-items-center my-4">
                        <hr class="flex-grow-1">
                        <span class="px-3 text-muted small fw-bold">MASUKKAN KODE OTP</span>
                        <hr class="flex-grow-1">
                    </div>

                    <!-- Form Input OTP -->
                    <form action="<?= $baseUrl ?>/auth/verify-otp" method="POST">
                        <?= \App\Helpers\Csrf::field() ?>

                        <div class="mb-4">
                            <label for="otp" class="form-label fw-bold text-center w-100">6 Digit Kode Verifikasi</label>
                            <input type="text" 
                                   class="form-control form-control-lg text-center fw-bold fs-3 tracking-widest rounded-3" 
                                   id="otp" 
                                   name="otp" 
                                   placeholder="123456" 
                                   maxlength="6" 
                                   pattern="\d{6}" 
                                   required 
                                   autocomplete="one-time-code"
                                   autofocus>
                            <small class="text-muted text-center d-block mt-2">Pastikan nomor WhatsApp Anda aktif dan dapat menerima pesan.</small>
                        </div>

                        <button type="submit" class="btn btn-success btn-lg w-100 rounded-pill shadow fw-bold">
                            <i class="fa-solid fa-circle-check me-2"></i> Verifikasi Sekarang
                        </button>
                    </form>

                    <div class="mt-4 pt-3 border-top text-center">
                        <a href="<?= $baseUrl ?>/dashboard/customer" class="text-decoration-none small text-muted">
                            <i class="fa-solid fa-arrow-left me-1"></i> Kembali ke Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

