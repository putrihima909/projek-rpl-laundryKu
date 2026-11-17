<?php
$baseUrl = config('app.url', 'http://localhost/rpl/public');
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-5 col-md-7">
            <div class="card glass-card border-0 shadow-lg">
                <div class="card-body p-4 p-md-5">
                    <div class="text-center mb-4">
                        <div class="bg-primary text-white d-inline-flex align-items-center justify-content-center rounded-circle mb-3" style="width: 60px; height: 60px;">
                            <i class="fa-solid fa-soap fs-2"></i>
                        </div>
                        <h3 class="fw-bold">Masuk ke Sistem</h3>
                        <p class="text-muted small">LaundryKu</p>
                    </div>

                    <!-- Google OAuth 2.0 Login Button -->
                    <div class="mb-4 text-center">
                        <a href="<?= $baseUrl ?>/auth/google" class="btn btn-outline-danger btn-lg w-100 rounded-pill shadow-sm fw-bold d-flex align-items-center justify-content-center gap-2">
                            <i class="fa-brands fa-google text-danger fs-5"></i>
                            <span>Masuk dengan Google</span>
                        </a>
                        <small class="text-muted d-block mt-2" style="font-size: 0.75rem;">Otomatis mendaftar sebagai Customer jika belum memiliki akun</small>
                    </div>

                    <div class="d-flex align-items-center my-3">
                        <hr class="flex-grow-1">
                        <span class="px-3 text-muted small">ATAU LOGIN MANUAL</span>
                        <hr class="flex-grow-1">
                    </div>

                    <form action="<?= $baseUrl ?>/login" method="POST">
                        <?= \App\Helpers\Csrf::field() ?>

                        <div class="mb-3">
                            <label for="email" class="form-label fw-semibold text-secondary">Alamat Email</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="fa-solid fa-envelope text-muted"></i></span>
                                <input type="email" class="form-control" id="email" name="email" placeholder="nama@domain.com" required value="customer@laundry.test">
                            </div>
                        </div>

                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label for="password" class="form-label fw-semibold text-secondary mb-0">Password</label>
                                <a href="#" class="text-decoration-none small text-primary">Lupa password?</a>
                            </div>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="fa-solid fa-lock text-muted"></i></span>
                                <input type="password" class="form-control" id="password" name="password" placeholder="••••••••" required value="password123">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg w-100 rounded-pill shadow-sm fw-bold">
                            <i class="fa-solid fa-right-to-bracket me-2"></i> Masuk Sekarang
                        </button>
                    </form>

                    <div class="mt-4 pt-3 border-top text-center">
                        <p class="text-muted small mb-0">Belum memiliki akun? <a href="<?= $baseUrl ?>/auth/google" class="fw-semibold text-primary text-decoration-none">Daftar Akun Baru via Google</a></p>
                    </div>
                </div>
            </div>

            <!-- Demo Account Cards -->
            <div class="card mt-4 border-0 shadow-sm rounded-4">
                <div class="card-header bg-dark text-white py-3 rounded-top-4">
                    <h6 class="fw-bold mb-0 text-center"><i class="fa-solid fa-users-gear text-info me-2"></i> Kredensial Uji Coba Demo</h6>
                </div>
                <div class="card-body p-3">
                    <div class="row g-2">
                        <?php foreach ($demoAccounts as $acc): ?>
                            <div class="col-12">
                                <a href="#"
                                   onclick="fillLoginForm(<?= htmlspecialchars(json_encode($acc['email']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($acc['password']), ENT_QUOTES) ?>); return false;"
                                   class="card p-2 text-decoration-none text-dark bg-light hover-shadow border border-secondary border-opacity-10 rounded-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <span class="badge bg-primary me-2"><?= htmlspecialchars($acc['role']) ?></span>
                                            <small class="fw-semibold text-dark"><?= htmlspecialchars($acc['email']) ?></small>
                                        </div>
                                        <small class="text-primary fw-bold">Isi Form <i class="fa-solid fa-arrow-right"></i></small>
                                    </div>
                                    <small class="text-muted ms-1" style="font-size: 0.75rem;"><?= htmlspecialchars($acc['desc']) ?> (Password: <code>password123</code>)</small>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="alert alert-warning border-0 rounded-3 small mt-3 mb-0 d-flex align-items-start gap-2">
                        <i class="fa-solid fa-shield-halved text-warning fs-5 mt-1 flex-shrink-0"></i>
                        <div>
                            <strong>Login Admin (Admin Owner & Admin Toko):</strong>
                            <div>Sesuai kebijakan keamanan, akses role admin tidak tersedia di menu ganti role cepat. Untuk masuk sebagai admin, silakan gunakan form login biasa di atas dengan kredensial:</div>
                            <ul class="mb-0 ps-3 mt-1 text-muted">
                                <li>Admin Owner: <code>admin.owner@laundry.test</code> / <code>password123</code></li>
                                <li>Admin Toko: <code>admin.toko@laundry.test</code> / <code>password123</code></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function fillLoginForm(email, password) {
    var emailField = document.getElementById('email');
    var passwordField = document.getElementById('password');
    if (emailField) { emailField.value = email; }
    if (passwordField) { passwordField.value = password; }
    if (emailField) { emailField.focus(); }
}
</script>
