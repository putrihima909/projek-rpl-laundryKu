<?php
use App\Helpers\Auth;
use App\Models\Notification;

$baseUrl = config('app.url', 'http://localhost/rpl/public');
?>

<div class="container py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= $baseUrl ?>/"><i class="fa-solid fa-house me-1"></i> Beranda</a></li>
            <li class="breadcrumb-item active" aria-current="page">Notifikasi</li>
        </ol>
    </nav>

    <!-- Header & Action Bar -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 pb-3 border-bottom">
        <div>
            <h2 class="fw-bold mb-1 d-flex align-items-center gap-2">
                <i class="fa-solid fa-bell text-warning"></i>
                <span>Notifikasi Saya</span>
                <?php if ($unreadCount > 0): ?>
                    <span class="badge bg-danger rounded-pill fs-6"><?= $unreadCount ?> Belum Dibaca</span>
                <?php endif; ?>
            </h2>
            <p class="text-muted mb-0">Semua riwayat pembaruan pesanan, penugasan kurir, dan konfirmasi transaksi.</p>
        </div>

        <div class="d-flex align-items-center gap-2">
            <?php if ($unreadCount > 0): ?>
                <form action="<?= $baseUrl ?>/notifications/mark-all-read" method="POST" class="d-inline">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                    <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/notifications') ?>">
                    <button type="submit" class="btn btn-outline-primary btn-sm rounded-pill px-3 fw-semibold">
                        <i class="fa-solid fa-check-double me-1"></i> Tandai Semua Dibaca
                    </button>
                </form>
            <?php endif; ?>
            <a href="<?= Auth::getRedirectUrl() ?>" class="btn btn-light btn-sm border rounded-pill px-3">
                <i class="fa-solid fa-arrow-left me-1"></i> Kembali ke Dashboard
            </a>
        </div>
    </div>

    <!-- WhatsApp Preference Opt-in/Opt-out Card -->
    <?php
    $userWaEnabled = (new \App\Services\WhatsApp\WhatsAppNotificationService())->isUserOptedIn((int)Auth::id());
    ?>
    <div class="row mb-4">
        <div class="col-lg-10 col-xl-9 mx-auto">
            <div class="card border-0 shadow-sm rounded-4 bg-light">
                <div class="card-body p-3 d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle p-2.5 bg-success text-white">
                            <i class="fa-brands fa-whatsapp fa-xl"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-0 text-dark">Notifikasi Otomatis WhatsApp</h6>
                            <small class="text-muted">Kirimkan rincian pesanan, bukti pembayaran, dan status jemput/antar ke nomor WhatsApp Anda.</small>
                        </div>
                    </div>
                    <div>
                        <form action="<?= $baseUrl ?>/notifications/whatsapp-preference" method="POST" class="m-0">
                            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                            <input type="hidden" name="enabled" value="<?= $userWaEnabled ? '0' : '1' ?>">
                            <button type="submit" class="btn btn-sm <?= $userWaEnabled ? 'btn-success' : 'btn-outline-secondary' ?> rounded-pill px-3 fw-semibold">
                                <i class="fa-solid <?= $userWaEnabled ? 'fa-toggle-on' : 'fa-toggle-off' ?> me-1"></i>
                                <?= $userWaEnabled ? 'Aktif (Menerima WA)' : 'Nonaktif (Opt-out)' ?>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Notification List -->
    <div class="row">
        <div class="col-lg-10 col-xl-9 mx-auto">
            <?php if (empty($notifications)): ?>
                <div class="card border-0 shadow-sm rounded-4 text-center py-5">
                    <div class="card-body py-5">
                        <div class="mb-3">
                            <span class="d-inline-flex align-items-center justify-content-center bg-light rounded-circle text-muted" style="width: 80px; height: 80px;">
                                <i class="fa-regular fa-bell-slash fs-1 text-secondary"></i>
                            </span>
                        </div>
                        <h4 class="fw-bold text-dark">Belum Ada Notifikasi</h4>
                        <p class="text-muted max-w-md mx-auto mb-4" style="max-width: 450px;">
                            Saat ada aktivitas pesanan laundry, penugasan kurir, penimbangan, atau pembayaran, notifikasi akan muncul di sini.
                        </p>
                        <a href="<?= $baseUrl ?>/orders/create" class="btn btn-primary rounded-pill px-4">
                            <i class="fa-solid fa-cart-plus me-1"></i> Buat Pesanan Baru
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="d-flex flex-column gap-3">
                    <?php foreach ($notifications as $notif): ?>
                        <?php
                        $isUnread = !(bool)($notif['is_read'] ?? 0);
                        $type = $notif['type'] ?? 'info';
                        
                        $typeConfig = match ($type) {
                            'success' => ['icon' => 'fa-circle-check', 'bg' => 'bg-success-subtle', 'text' => 'text-success', 'border' => 'border-success'],
                            'warning' => ['icon' => 'fa-circle-exclamation', 'bg' => 'bg-warning-subtle', 'text' => 'text-warning', 'border' => 'border-warning'],
                            'danger'  => ['icon' => 'fa-circle-xmark', 'bg' => 'bg-danger-subtle', 'text' => 'text-danger', 'border' => 'border-danger'],
                            'primary' => ['icon' => 'fa-truck-fast', 'bg' => 'bg-primary-subtle', 'text' => 'text-primary', 'border' => 'border-primary'],
                            default   => ['icon' => 'fa-circle-info', 'bg' => 'bg-info-subtle', 'text' => 'text-info', 'border' => 'border-info'],
                        };
                        ?>
                        <div class="card border-0 shadow-sm rounded-3 <?= $isUnread ? 'border-start border-4 border-primary bg-light' : 'bg-white' ?> transition-all">
                            <div class="card-body p-3 p-md-4">
                                <div class="d-flex align-items-start gap-3">
                                    <!-- Type Icon -->
                                    <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 <?= $typeConfig['bg'] ?> <?= $typeConfig['text'] ?>" style="width: 44px; height: 44px;">
                                        <i class="fa-solid <?= $typeConfig['icon'] ?> fs-5"></i>
                                    </div>

                                    <!-- Content -->
                                    <div class="flex-grow-1 min-w-0">
                                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-1">
                                            <h6 class="fw-bold text-dark mb-0 text-truncate">
                                                <?= htmlspecialchars($notif['title']) ?>
                                                <?php if ($isUnread): ?>
                                                    <span class="badge bg-danger rounded-pill ms-2" style="font-size: 0.65rem;">BARU</span>
                                                <?php endif; ?>
                                            </h6>
                                            <small class="text-muted" style="font-size: 0.8rem;">
                                                <i class="fa-regular fa-clock me-1"></i><?= Notification::timeAgo($notif['created_at']) ?>
                                            </small>
                                        </div>

                                        <p class="text-secondary mb-3" style="font-size: 0.92rem; line-height: 1.5;">
                                            <?= nl2br(htmlspecialchars($notif['message'])) ?>
                                        </p>

                                        <!-- Action buttons -->
                                        <div class="d-flex align-items-center gap-2">
                                            <a href="<?= $baseUrl ?>/notifications/open?id=<?= (int)$notif['id'] ?>" class="btn btn-sm btn-light border rounded-pill px-3">
                                                <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Buka Detail
                                            </a>

                                            <?php if ($isUnread): ?>
                                                <form action="<?= $baseUrl ?>/notifications/read" method="POST" class="d-inline">
                                                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                                                    <input type="hidden" name="id" value="<?= (int)$notif['id'] ?>">
                                                    <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/notifications') ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                                                        <i class="fa-solid fa-check me-1"></i> Tandai Dibaca
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <nav class="mt-4" aria-label="Navigasi Halaman Notifikasi">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link rounded-pill me-1" href="<?= $baseUrl ?>/notifications?page=<?= $currentPage - 1 ?>">
                                    <i class="fa-solid fa-chevron-left me-1"></i> Sebelumnya
                                </a>
                            </li>

                            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                <li class="page-item <?= $p === $currentPage ? 'active' : '' ?>">
                                    <a class="page-link rounded-circle mx-1 text-center" style="width: 38px; height: 38px; line-height: 24px;" href="<?= $baseUrl ?>/notifications?page=<?= $p ?>">
                                        <?= $p ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                                <a class="page-link rounded-pill ms-1" href="<?= $baseUrl ?>/notifications?page=<?= $currentPage + 1 ?>">
                                    Berikutnya <i class="fa-solid fa-chevron-right ms-1"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
