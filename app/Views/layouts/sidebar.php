<?php
use App\Helpers\Auth;
$baseUrl = config('app.url', 'http://localhost/rpl/public');
$role = Auth::role();
$roleDisplay = Auth::roleDisplay();
?>

<aside class="sidebar shadow-sm" id="appSidebar">
    <div class="d-flex flex-column h-100 py-3">
        <div class="px-3 mb-3">
            <div class="p-2.5 rounded-3 bg-light border text-center">
                <small class="text-muted d-block" style="font-size: 0.7rem;">ROLE ANDA SAAT INI</small>
                <span class="badge bg-primary text-wrap mt-1" style="font-size: 0.8rem;"><?= htmlspecialchars($roleDisplay) ?></span>
            </div>
        </div>

        <nav class="nav flex-column mb-auto">
            <div class="sidebar-heading">Menu Utama</div>
            
            <?php if ($role === Auth::ROLE_OWNER): ?>
                <a class="nav-link" href="<?= $baseUrl ?>/dashboard/owner"><i class="fa-solid fa-crown text-warning"></i> <span>Dashboard Owner</span></a>
                <div class="sidebar-heading">Keuangan & Platform</div>
                <a class="nav-link" href="<?= $baseUrl ?>/dashboard/omzet-detail"><i class="fa-solid fa-chart-line text-success"></i> <span>Detail Omset</span></a>
                <a class="nav-link" href="<?= $baseUrl ?>/stores/manage"><i class="fa-solid fa-store text-info"></i> <span>Manajemen Toko</span></a>
                <a class="nav-link" href="<?= $baseUrl ?>/users"><i class="fa-solid fa-users-gear text-primary"></i> <span>User Management</span></a>
                <a class="nav-link" href="<?= $baseUrl ?>/orders"><i class="fa-solid fa-basket-shopping text-warning"></i> <span>Seluruh Pesanan</span></a>
                <a class="nav-link" href="<?= $baseUrl ?>/admin/ai-settings"><i class="fa-solid fa-robot text-primary"></i> <span>🤖 Pengaturan AI</span></a>
                <a class="nav-link" href="<?= $baseUrl ?>/admin/whatsapp"><i class="fa-brands fa-whatsapp text-success"></i> <span>📱 WhatsApp</span></a>

            <?php elseif ($role === Auth::ROLE_ADMIN_OWNER): ?>
                <a class="nav-link" href="<?= $baseUrl ?>/dashboard/admin-owner"><i class="fa-solid fa-user-shield text-info"></i> <span>Dashboard Admin Owner</span></a>
                <div class="sidebar-heading">Operasional & Keuangan</div>
                <a class="nav-link" href="<?= $baseUrl ?>/dashboard/omzet-detail"><i class="fa-solid fa-chart-line text-success"></i> <span>Detail Omset</span></a>
                <a class="nav-link" href="<?= $baseUrl ?>/stores/manage"><i class="fa-solid fa-store text-info"></i> <span>Manajemen Toko</span></a>
                <a class="nav-link" href="<?= $baseUrl ?>/users"><i class="fa-solid fa-users text-primary"></i> <span>User Management</span></a>
                <a class="nav-link" href="<?= $baseUrl ?>/orders"><i class="fa-solid fa-basket-shopping text-warning"></i> <span>Monitoring Pesanan</span></a>
                <a class="nav-link" href="<?= $baseUrl ?>/admin/ai-settings"><i class="fa-solid fa-robot text-primary"></i> <span>🤖 Pengaturan AI</span></a>
                <a class="nav-link" href="<?= $baseUrl ?>/admin/whatsapp"><i class="fa-brands fa-whatsapp text-success"></i> <span>📱 WhatsApp</span></a>

            <?php elseif ($role === Auth::ROLE_OWNER_TOKO): ?>
                <a class="nav-link" href="<?= $baseUrl ?>/dashboard/owner-toko"><i class="fa-solid fa-shop text-success"></i> <span>Dashboard Owner Toko</span></a>
                <div class="sidebar-heading">Manajemen & Keuangan Toko</div>
                <a class="nav-link" href="<?= $baseUrl ?>/dashboard/omzet-detail"><i class="fa-solid fa-chart-line text-success"></i> <span>Detail Omset Toko</span></a>
                <a class="nav-link" href="<?= $baseUrl ?>/services"><i class="fa-solid fa-tags text-warning"></i> <span>Kelola Layanan</span></a>
                <a class="nav-link" href="<?= $baseUrl ?>/store/orders"><i class="fa-solid fa-basket-shopping text-primary"></i> <span>Pesanan Masuk</span></a>
                <a class="nav-link" href="<?= $baseUrl ?>/admin/store-ai-settings"><i class="fa-solid fa-robot text-primary"></i> <span>Konfigurasi API Chatbot</span></a>

            <?php elseif ($role === Auth::ROLE_ADMIN_TOKO): ?>
                <a class="nav-link" href="<?= $baseUrl ?>/dashboard/admin-toko"><i class="fa-solid fa-cash-register text-primary"></i> <span>Dashboard Admin Toko</span></a>
                <div class="sidebar-heading">Kasir Laundry</div>
                <a class="nav-link" href="<?= $baseUrl ?>/dashboard/omzet-detail"><i class="fa-solid fa-chart-line text-success"></i> <span>Detail Omset Kasir</span></a>
                <a class="nav-link" href="<?= $baseUrl ?>/services"><i class="fa-solid fa-tags text-warning"></i> <span>Kelola Layanan</span></a>
                <a class="nav-link" href="<?= $baseUrl ?>/orders/create"><i class="fa-solid fa-cart-plus text-success"></i> <span>Input Pesanan Baru</span></a>
                <a class="nav-link" href="<?= $baseUrl ?>/store/orders"><i class="fa-solid fa-basket-shopping text-primary"></i> <span>Daftar Pesanan Toko</span></a>
                <a class="nav-link" href="<?= $baseUrl ?>/admin/whatsapp"><i class="fa-brands fa-whatsapp text-success"></i> <span>📱 WhatsApp Toko</span></a>
                <a class="nav-link" href="<?= $baseUrl ?>/admin/store-ai-settings"><i class="fa-solid fa-robot text-primary"></i> <span>Konfigurasi API Chatbot</span></a>

            <?php elseif ($role === Auth::ROLE_KURIR): ?>
                <a class="nav-link" href="<?= $baseUrl ?>/dashboard/kurir"><i class="fa-solid fa-truck-fast text-secondary"></i> <span>Dashboard Kurir</span></a>
                <div class="sidebar-heading">Tugas Antar Jemput</div>
                <a class="nav-link" href="<?= $baseUrl ?>/courier/orders"><i class="fa-solid fa-truck-arrow-right text-info"></i> <span>Tugas Penjemputan/Pengantaran</span></a>

            <?php else: // Customer ?>
                <a class="nav-link" href="<?= $baseUrl ?>/dashboard/customer"><i class="fa-solid fa-house text-info"></i> <span>Dashboard Customer</span></a>
                <div class="sidebar-heading">Layanan Pelanggan</div>
                <a class="nav-link" href="<?= $baseUrl ?>/stores"><i class="fa-solid fa-store text-success"></i> <span>Marketplace Toko</span></a>
                <a class="nav-link" href="<?= $baseUrl ?>/orders/create"><i class="fa-solid fa-cart-plus text-success"></i> <span>Pesan Laundry Baru</span></a>
                <a class="nav-link" href="<?= $baseUrl ?>/orders"><i class="fa-solid fa-basket-shopping text-primary"></i> <span>Status Pesanan Saya</span></a>
                <a class="nav-link" href="<?= $baseUrl ?>/profile"><i class="fa-solid fa-user-gear text-secondary"></i> <span>Profil Saya</span></a>
                <a class="nav-link" href="<?= $baseUrl ?>/addresses"><i class="fa-solid fa-location-dot text-danger"></i> <span>Alamat Saya</span></a>
            <?php endif; ?>
        </nav>

        <div class="px-3 pt-3 border-top mt-auto">
            <a href="<?= $baseUrl ?>/logout" class="btn btn-outline-danger btn-sm w-100 rounded-pill">
                <i class="fa-solid fa-right-from-bracket me-1"></i> Logout
            </a>
        </div>
    </div>
</aside>
