<?php
$baseUrl = config('app.url', 'http://localhost/rpl/public');
?>

<footer class="app-footer text-center text-md-start">
    <div class="container-fluid px-4">
        <div class="row align-items-center">
            <div class="col-md-6 mb-2 mb-md-0">
                <span class="fw-semibold text-dark">LaundryKu</span> &copy; <?= date('Y') ?>. Hak Cipta Dilindungi.
            </div>
            <div class="col-md-6 text-md-end">
                <span class="badge bg-light text-secondary border me-2">Fondasi Framework MVC PHP + PDO</span>
                <a href="<?= $baseUrl ?>/" class="text-decoration-none text-muted me-3">Beranda</a>
                <a href="<?= $baseUrl ?>/login" class="text-decoration-none text-muted">Login</a>
            </div>
        </div>
    </div>
</footer>
