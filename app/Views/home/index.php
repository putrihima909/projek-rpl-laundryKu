<?php
$baseUrl = config('app.url', 'http://localhost/rpl/public');
?>

<div class="container py-5">
    <!-- Hero Banner -->
    <div class="hero-banner text-center text-lg-start">
        <div class="row align-items-center">
            <div class="col-lg-7 mb-4 mb-lg-0">
                <span class="badge bg-info text-dark rounded-pill px-3 py-2 fw-semibold mb-3">
                    <i class="fa-solid fa-sparkles me-1"></i> Platform Marketplace Laundry Masa Depan
                </span>
                <h1 class="display-4 fw-extrabold mb-3">LaundryKu</h1>
                <p class="lead mb-4 opacity-90">
                    Solusi praktis mencuci pakaian. Temukan toko laundry terbaik di sekitar Anda, pesan layanan cuci komplit, dan nikmati kemudahan penjemputan oleh kurir secara real-time.
                </p>
                <div class="d-flex flex-wrap justify-content-center justify-content-lg-start gap-3">
                    <a href="<?= $baseUrl ?>/login" class="btn btn-info text-white btn-lg rounded-pill px-4 shadow fw-semibold">
                        <i class="fa-solid fa-basket-shopping me-2"></i> Pesan Laundry Sekarang
                    </a>
                    <a href="<?= $baseUrl ?>/login" class="btn btn-outline-light btn-lg rounded-pill px-4 fw-semibold">
                        <i class="fa-solid fa-store me-2"></i> Daftarkan Toko Laundry
                    </a>
                </div>
            </div>
            <div class="col-lg-5 text-center">
                <div class="p-4 rounded-4 bg-white text-dark shadow-lg">
                    <div class="d-flex align-items-center gap-3 mb-3 text-start">
                        <div class="bg-primary text-white p-3 rounded-circle">
                            <i class="fa-solid fa-soap fs-3"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-0">Platform Laundry Terpadu</h5>
                            <small class="text-muted">6 Role Akses Berbeda</small>
                        </div>
                    </div>
                    <hr>
                    <div class="row g-2 text-start small">
                        <div class="col-6"><i class="fa-solid fa-circle-check text-success me-1"></i> Owner Platform</div>
                        <div class="col-6"><i class="fa-solid fa-circle-check text-success me-1"></i> Admin Owner</div>
                        <div class="col-6"><i class="fa-solid fa-circle-check text-success me-1"></i> Owner Toko</div>
                        <div class="col-6"><i class="fa-solid fa-circle-check text-success me-1"></i> Admin Toko</div>
                        <div class="col-6"><i class="fa-solid fa-circle-check text-success me-1"></i> Kurir / Jasa Antar</div>
                        <div class="col-6"><i class="fa-solid fa-circle-check text-success me-1"></i> Customer</div>
                    </div>
                    <div class="mt-3 p-2 bg-light rounded-3 text-muted text-center" style="font-size: 0.8rem;">
                        <i class="fa-solid fa-shield-halved text-info me-1"></i> Didukung PDO MySQL & PHP MVC Clean Code
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Featured Services Section -->
    <div id="services" class="my-5 pt-3">
        <div class="text-center mb-5">
            <span class="text-primary fw-bold text-uppercase tracking-wider">Pilihan Layanan</span>
            <h2 class="fw-bold display-6">Layanan Laundry Populer</h2>
            <p class="text-muted">Pilih berbagai macam perawatan pakaian sesuai kebutuhan Anda</p>
        </div>

        <div class="row g-4">
            <?php foreach ($featuredServices as $service): ?>
                <div class="col-md-6 col-lg-3">
                    <div class="card h-100 stat-card p-4">
                        <div class="icon-box bg-light text-primary mb-3">
                            <i class="fa-solid <?= $service['icon'] ?>"></i>
                        </div>
                        <h5 class="fw-bold mb-2"><?= htmlspecialchars($service['name']) ?></h5>
                        <p class="text-muted small flex-grow-1"><?= htmlspecialchars($service['desc']) ?></p>
                        <div class="d-flex justify-content-between align-items-center pt-3 border-top mt-2">
                            <span class="fw-bold text-primary"><?= htmlspecialchars($service['price']) ?></span>
                            <a href="<?= $baseUrl ?>/login" class="btn btn-sm btn-outline-primary rounded-pill">Pesan</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Features Showcase -->
    <div id="stores" class="my-5 py-4 bg-white rounded-4 p-4 p-md-5 border shadow-sm">
        <div class="row align-items-center">
            <div class="col-lg-6 mb-4 mb-lg-0">
                <span class="badge bg-primary text-white rounded-pill px-3 py-2 mb-2">Mengapa Memilih Kami</span>
                <h2 class="fw-bold mb-4">Pengalaman Mencuci Pakaian Lebih Cerdas & Transparan</h2>
                <div class="d-flex align-items-start gap-3 mb-3">
                    <div class="bg-light text-primary p-2.5 rounded-3 fs-4">
                        <i class="fa-solid fa-map-location-dot"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-1">Multi-Store & Pencarian Lokasi</h6>
                        <p class="text-muted small">Pilih toko laundry terdekat dengan rating dan harga terbaik sesuai keinginan Anda.</p>
                    </div>
                </div>
                <div class="d-flex align-items-start gap-3 mb-3">
                    <div class="bg-light text-primary p-2.5 rounded-3 fs-4">
                        <i class="fa-solid fa-truck-fast"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-1">Kurir Pickup & Delivery</h6>
                        <p class="text-muted small">Tidak perlu keluar rumah. Kurir siap mengambil dan mengantarkan cucian rapi Anda.</p>
                    </div>
                </div>
                <div class="d-flex align-items-start gap-3">
                    <div class="bg-light text-primary p-2.5 rounded-3 fs-4">
                        <i class="fa-solid fa-bell"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-1">Notifikasi Status Real-Time</h6>
                        <p class="text-muted small">Dapatkan pembaruan status laundry mulai dari penjemputan, pencucian, hingga siap dikirim.</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="p-4 bg-light rounded-4 border border-2 border-primary border-opacity-25 text-center">
                    <i class="fa-solid fa-rocket text-primary display-3 mb-3"></i>
                    <h4 class="fw-bold">Fondasi Sistem Siap Dikembangkan</h4>
                    <p class="text-muted">
                        Arsitektur kode dirancang modular menggunakan pola MVC PHP & PDO MySQL untuk memudahkan pengembangan modul AI, Payment Gateway, dan Geolocation selanjutnya.
                    </p>
                    <a href="<?= $baseUrl ?>/login" class="btn btn-primary rounded-pill px-4 fw-semibold mt-2">
                        Masuk ke Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
