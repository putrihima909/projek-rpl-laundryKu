<?php
use App\Helpers\Auth;
use App\Helpers\Csrf;
use App\Models\Notification;

$baseUrl = config('app.url', 'http://localhost/rpl/public');
$isLoggedIn = Auth::check();
$user = Auth::user();
$role = Auth::role();
$roleDisplay = Auth::roleDisplay();

$unreadNotifCount = 0;
$latestNotifications = [];
if ($isLoggedIn && !empty($user['id'])) {
    $unreadNotifCount = Notification::countUnread((int)$user['id']);
    $latestNotifications = Notification::findByUser((int)$user['id'], 10);
}
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top shadow-sm">
    <div class="container-fluid px-3 px-lg-4">
        <button class="btn btn-link text-white d-lg-none me-2 p-0 border-0" type="button" id="sidebarToggle">
            <i class="fa-solid fa-bars fs-4"></i>
        </button>

        <a class="navbar-brand d-flex align-items-center gap-2" href="<?= $baseUrl ?>/">
            <i class="fa-solid fa-soap text-info"></i>
            <span>LaundryKu</span>
            <span class="brand-badge ms-1">Marketplace</span>
        </a>

        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarMain">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-3">
                <li class="nav-item">
                    <a class="nav-link" href="<?= $baseUrl ?>/"><i class="fa-solid fa-house me-1"></i> Beranda</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= $baseUrl ?>/#services"><i class="fa-solid fa-list-check me-1"></i> Layanan</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= $baseUrl ?>/stores"><i class="fa-solid fa-store me-1"></i> Cari Toko Laundry</a>
                </li>
                <?php if ($isLoggedIn && $role === Auth::ROLE_CUSTOMER): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= $baseUrl ?>/orders"><i class="fa-solid fa-basket-shopping text-info me-1"></i> Status Pesanan Saya</a>
                    </li>
                <?php endif; ?>
            </ul>

            <div class="d-flex align-items-center gap-3">
                <?php if ($isLoggedIn): ?>
                    <?php if (in_array($role, [Auth::ROLE_ADMIN_OWNER, Auth::ROLE_ADMIN_TOKO], true)): ?>
                        <!-- Admin Active Session Indicator -->
                        <div class="d-none d-md-flex align-items-center gap-2">
                            <span class="badge bg-secondary bg-opacity-75 text-white border border-secondary rounded-pill px-3 py-2 d-inline-flex align-items-center" style="font-size: 0.85rem;">
                                <i class="fa-solid fa-shield-halved text-info me-1.5"></i> Role: <strong class="ms-1"><?= htmlspecialchars($roleDisplay) ?></strong>
                            </span>
                            <a href="<?= $baseUrl ?>/logout" class="btn btn-sm btn-outline-danger rounded-pill px-3">
                                <i class="fa-solid fa-right-from-bracket me-1"></i> Logout
                            </a>
                        </div>
                    <?php else: ?>
                        <!-- Static Role Badge (Non-Admin) -->
                        <div class="d-none d-md-flex align-items-center gap-2">
                            <span class="badge bg-secondary bg-opacity-75 text-white border border-secondary rounded-pill px-3 py-2 d-inline-flex align-items-center" style="font-size: 0.85rem;">
                                <i class="fa-solid fa-user-circle text-info me-1.5"></i> Role: <strong class="ms-1"><?= htmlspecialchars($roleDisplay) ?></strong>
                            </span>
                            <a href="<?= $baseUrl ?>/logout" class="btn btn-sm btn-outline-danger rounded-pill px-3">
                                <i class="fa-solid fa-right-from-bracket me-1"></i> Logout
                            </a>
                        </div>
                    <?php endif; ?>

                    <!-- Notification Bell Dropdown -->
                    <div class="dropdown">
                        <button class="btn btn-link text-white position-relative p-2 border-0 text-decoration-none" type="button" data-bs-toggle="dropdown" aria-expanded="false" id="notifDropdownBtn" title="Notifikasi">
                            <i class="fa-solid fa-bell fs-5"></i>
                            <span id="navbarNotifBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger <?= $unreadNotifCount > 0 ? '' : 'd-none' ?>" style="font-size: 0.65rem;">
                                <?= $unreadNotifCount > 99 ? '99+' : $unreadNotifCount ?>
                            </span>
                        </button>

                        <div class="dropdown-menu dropdown-menu-end shadow-lg border-0 rounded-3 p-0 notif-dropdown-menu" style="width: 360px; max-width: 90vw; z-index: 1050;">
                            <!-- Header -->
                            <div class="d-flex align-items-center justify-content-between p-3 border-bottom bg-light rounded-top-3">
                                <div class="fw-bold text-dark d-flex align-items-center gap-1.5">
                                    <i class="fa-solid fa-bell text-warning me-1"></i> Notifikasi
                                    <span id="notifDropdownCount" class="badge bg-primary rounded-pill ms-1" style="font-size: 0.7rem;"><?= $unreadNotifCount ?> baru</span>
                                </div>
                                <?php if ($unreadNotifCount > 0): ?>
                                    <form action="<?= $baseUrl ?>/notifications/mark-all-read" method="POST" class="m-0">
                                        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(Csrf::generateToken()) ?>">
                                        <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/') ?>">
                                        <button type="submit" class="btn btn-link text-primary p-0 border-0 text-decoration-none" style="font-size: 0.75rem; font-weight: 600;">
                                            Tandai semua dibaca
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>

                            <!-- List -->
                            <div class="notif-list-container overflow-y-auto" style="max-height: 380px;">
                                <?php if (empty($latestNotifications)): ?>
                                    <div class="text-center py-4 px-3 text-muted">
                                        <i class="fa-regular fa-bell-slash fs-3 text-secondary mb-2 d-block"></i>
                                        <div style="font-size: 0.85rem;">Belum ada notifikasi baru</div>
                                    </div>
                                <?php else: ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($latestNotifications as $notif): ?>
                                            <?php
                                            $isUnread = !(bool)($notif['is_read'] ?? 0);
                                            $type = $notif['type'] ?? 'info';
                                            $badgeColor = match ($type) {
                                                'success' => 'text-success bg-success-subtle',
                                                'warning' => 'text-warning bg-warning-subtle',
                                                'danger'  => 'text-danger bg-danger-subtle',
                                                'primary' => 'text-primary bg-primary-subtle',
                                                default   => 'text-info bg-info-subtle',
                                            };
                                            $icon = match ($type) {
                                                'success' => 'fa-circle-check',
                                                'warning' => 'fa-triangle-exclamation',
                                                'danger'  => 'fa-circle-xmark',
                                                'primary' => 'fa-truck-fast',
                                                default   => 'fa-circle-info',
                                            };
                                            ?>
                                            <a href="<?= $baseUrl ?>/notifications/open?id=<?= (int)$notif['id'] ?>" class="list-group-item list-group-item-action p-3 border-bottom <?= $isUnread ? 'bg-light fw-semibold' : '' ?>" style="text-decoration: none;">
                                                <div class="d-flex align-items-start gap-2.5">
                                                    <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 <?= $badgeColor ?>" style="width: 32px; height: 32px; font-size: 0.85rem;">
                                                        <i class="fa-solid <?= $icon ?>"></i>
                                                    </div>
                                                    <div class="flex-grow-1 min-w-0">
                                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                                            <span class="text-dark small text-truncate pe-1 <?= $isUnread ? 'fw-bold' : '' ?>" style="font-size: 0.82rem;">
                                                                <?= htmlspecialchars($notif['title']) ?>
                                                            </span>
                                                            <small class="text-muted flex-shrink-0" style="font-size: 0.7rem;">
                                                                <?= Notification::timeAgo($notif['created_at']) ?>
                                                            </small>
                                                        </div>
                                                        <p class="text-secondary small mb-0 text-truncate" style="font-size: 0.78rem; max-height: 2.4em; line-height: 1.25;">
                                                            <?= htmlspecialchars($notif['message']) ?>
                                                        </p>
                                                    </div>
                                                    <?php if ($isUnread): ?>
                                                        <span class="bg-primary rounded-circle flex-shrink-0 mt-1" style="width: 8px; height: 8px;"></span>
                                                    <?php endif; ?>
                                                </div>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Footer -->
                            <div class="p-2 text-center border-top bg-light rounded-bottom-3">
                                <a href="<?= $baseUrl ?>/notifications" class="text-primary text-decoration-none small fw-semibold">
                                    Lihat Semua Notifikasi <i class="fa-solid fa-arrow-right ms-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- User Profile Dropdown -->
                    <div class="dropdown">
                        <a href="#" class="d-flex align-items-center text-white text-decoration-none dropdown-toggle" data-bs-toggle="dropdown">
                            <?php if (!empty($user['avatar'])): ?>
                                <img src="<?= htmlspecialchars($user['avatar']) ?>" class="rounded-circle me-2 border border-2 border-info" style="width: 36px; height: 36px; object-fit: cover;">
                            <?php else: ?>
                                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 36px; height: 36px; font-weight: 600;">
                                    <?= strtoupper(substr($user['name'] ?? 'U', 0, 1)) ?>
                                </div>
                            <?php endif; ?>
                            <div class="d-none d-sm-block text-start me-1">
                                <div class="fw-semibold lh-1" style="font-size: 0.9rem;"><?= htmlspecialchars($user['name'] ?? 'User') ?></div>
                                <small class="text-info" style="font-size: 0.75rem;"><?= htmlspecialchars($roleDisplay) ?></small>
                            </div>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow">
                            <li><a class="dropdown-item" href="<?= Auth::getRedirectUrl() ?>"><i class="fa-solid fa-gauge me-2"></i> Dashboard</a></li>
                            <li><a class="dropdown-item" href="<?= $baseUrl ?>/notifications"><i class="fa-solid fa-bell me-2"></i> Notifikasi</a></li>
                            <li><a class="dropdown-item" href="<?= $baseUrl ?>/profile"><i class="fa-solid fa-user-gear me-2"></i> Profil Saya</a></li>
                            <li><a class="dropdown-item" href="<?= $baseUrl ?>/addresses"><i class="fa-solid fa-location-dot me-2"></i> Alamat Saya</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="<?= $baseUrl ?>/logout"><i class="fa-solid fa-right-from-bracket me-2"></i> Logout</a></li>
                        </ul>
                    </div>
                <?php else: ?>
                    <a href="<?= $baseUrl ?>/login" class="btn btn-outline-light rounded-pill px-4 btn-sm fw-semibold">
                        <i class="fa-solid fa-right-to-bracket me-1"></i> Masuk
                    </a>
                    <a href="<?= $baseUrl ?>/auth/google" class="btn btn-info text-white rounded-pill px-4 btn-sm fw-semibold">
                        <i class="fa-brands fa-google me-1"></i> Google Login
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<?php if ($isLoggedIn): ?>
<script>
// Polling Notifikasi Otomatis (Setiap 30 Detik)
(function () {
    const pollInterval = 30000; // 30 seconds
    const unreadUrl = '<?= $baseUrl ?>/notifications/unread-count';
    const badgeEl = document.getElementById('navbarNotifBadge');
    const countEl = document.getElementById('notifDropdownCount');

    function updateBadge(count) {
        if (!badgeEl) return;
        if (count > 0) {
            badgeEl.textContent = count > 99 ? '99+' : count;
            badgeEl.classList.remove('d-none');
            if (countEl) countEl.textContent = count + ' baru';
        } else {
            badgeEl.textContent = '0';
            badgeEl.classList.add('d-none');
            if (countEl) countEl.textContent = '0 baru';
        }
    }

    function checkUnread() {
        fetch(unreadUrl, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(res => {
            if (res.ok) return res.json();
            throw new Error('Network error');
        })
        .then(data => {
            if (data && data.status === 'success' && typeof data.unread_count !== 'undefined') {
                updateBadge(data.unread_count);
            }
        })
        .catch(err => {
            // Silently handle transient network polling failures
        });
    }

    setInterval(checkUnread, pollInterval);
})();
</script>
<?php endif; ?>
