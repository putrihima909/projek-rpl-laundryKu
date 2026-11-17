<?php
$showSidebar = true;
$baseUrl = config('app.url', 'http://localhost/rpl/public');
$dutyStatus = $courierLoc['duty_status'] ?? 'available';
?>

<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center pb-3 mb-4 border-bottom">
        <div>
            <span class="badge bg-secondary text-white px-3 py-1 mb-2 fw-bold"><i class="fa-solid fa-truck-fast me-1"></i> KURIR / JASA ANTAR</span>
            <h2 class="fw-bold text-dark mb-1">Dashboard Kurir - <?= htmlspecialchars($user['name'] ?? 'Agus Express') ?></h2>
            <p class="text-muted mb-0">Kelola status ketersediaan tugas, tugas penjemputan (pickup), dan pengantaran laundry.</p>
        </div>
        <div class="mt-3 mt-md-0 d-flex gap-2">
            <a href="<?= $baseUrl ?>/logout" class="btn btn-outline-danger rounded-pill btn-sm">
                <i class="fa-solid fa-right-from-bracket me-1"></i> Logout
            </a>
        </div>
    </div>

    <!-- Duty Status Switcher Bar -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white p-3">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="p-3 rounded-circle bg-<?= $dutyStatus === 'available' ? 'success' : ($dutyStatus === 'busy' ? 'warning' : 'danger') ?> bg-opacity-10 text-<?= $dutyStatus === 'available' ? 'success' : ($dutyStatus === 'busy' ? 'warning' : 'danger') ?> fs-3">
                    <i class="fa-solid fa-motorcycle"></i>
                </div>
                <div>
                    <small class="text-muted d-block fw-bold text-uppercase">STATUS KETERSEDIAAN TUGAS KURIR</small>
                    <h5 class="fw-bold text-dark mb-0">
                        Status Saat Ini: 
                        <span class="badge bg-<?= $dutyStatus === 'available' ? 'success' : ($dutyStatus === 'busy' ? 'warning' : 'danger') ?> rounded-pill px-3 py-1">
                            <?= strtoupper($dutyStatus) ?>
                        </span>
                    </h5>
                </div>
            </div>

            <!-- Duty Status Form Buttons -->
            <form action="<?= $baseUrl ?>/courier/duty-status" method="POST" class="d-flex gap-2">
                <?= \App\Helpers\Csrf::field() ?>
                <button type="submit" name="duty_status" value="available" class="btn btn-sm <?= $dutyStatus === 'available' ? 'btn-success' : 'btn-outline-success' ?> rounded-pill px-3 fw-bold">
                    <i class="fa-solid fa-circle-check me-1"></i> Available
                </button>
                <button type="submit" name="duty_status" value="busy" class="btn btn-sm <?= $dutyStatus === 'busy' ? 'btn-warning' : 'btn-outline-warning' ?> rounded-pill px-3 fw-bold">
                    <i class="fa-solid fa-clock me-1"></i> Busy
                </button>
                <button type="submit" name="duty_status" value="offline" class="btn btn-sm <?= $dutyStatus === 'offline' ? 'btn-danger' : 'btn-outline-danger' ?> rounded-pill px-3 fw-bold">
                    <i class="fa-solid fa-circle-xmark me-1"></i> Offline
                </button>
            </form>
        </div>
    </div>

    <!-- GPS Coordinates Tracker Card (HTML5 Geolocation + Manual Fallback) -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white overflow-hidden border-start border-4 border-success">
        <div class="card-header bg-white py-3 border-0 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h6 class="fw-bold mb-0 text-dark">
                    <i class="fa-solid fa-location-crosshairs text-success me-2"></i> Koordinat GPS Kurir Terkini
                </h6>
                <small class="text-muted">
                    Posisi tersimpan: <code>Lat: <?= htmlspecialchars($courierLoc['latitude']) ?>, Lng: <?= htmlspecialchars($courierLoc['longitude']) ?></code>
                    <span class="ms-1">(Diperbarui <?= date('H:i', strtotime($courierLoc['updated_at'] ?? 'now')) ?> WIB)</span>
                </small>
            </div>
            <div>
                <button type="button" id="btn-detect-gps" class="btn btn-sm btn-success rounded-pill px-3 fw-bold shadow-sm">
                    <i class="fa-solid fa-satellite-dish me-1"></i> Deteksi GPS Device (HTML5)
                </button>
            </div>
        </div>

        <div class="card-body p-3 bg-light border-top">
            <!-- Geolocation Status Alert Box -->
            <div id="geo-status-box" class="alert alert-info alert-dismissible fade show py-2 px-3 mb-3 small d-none" role="alert">
                <div class="d-flex align-items-center gap-2" id="geo-status-content">
                    <i class="fa-solid fa-circle-info"></i>
                    <span id="geo-status-text">Siap mendeteksi koordinat GPS device.</span>
                </div>
            </div>

            <!-- Location Update Form -->
            <form id="form-courier-location" action="<?= $baseUrl ?>/courier/location" method="POST">
                <?= \App\Helpers\Csrf::field() ?>
                <div class="row g-2 align-items-end">
                    <div class="col-12 col-md-3">
                        <label for="input-lat" class="form-label small fw-bold text-dark mb-1">
                            <i class="fa-solid fa-arrows-up-down text-secondary me-1"></i> Latitude
                        </label>
                        <input type="text" class="form-control form-control-sm rounded-pill font-monospace" 
                               id="input-lat" name="latitude" 
                               value="<?= htmlspecialchars($courierLoc['latitude']) ?>" 
                               placeholder="-6.20880000" required>
                    </div>
                    <div class="col-12 col-md-3">
                        <label for="input-lng" class="form-label small fw-bold text-dark mb-1">
                            <i class="fa-solid fa-arrows-left-right text-secondary me-1"></i> Longitude
                        </label>
                        <input type="text" class="form-control form-control-sm rounded-pill font-monospace" 
                               id="input-lng" name="longitude" 
                               value="<?= htmlspecialchars($courierLoc['longitude']) ?>" 
                               placeholder="106.84560000" required>
                    </div>
                    <div class="col-12 col-md-4">
                        <label for="select-order" class="form-label small fw-bold text-dark mb-1">
                            <i class="fa-solid fa-clipboard-list text-secondary me-1"></i> Terkait Tugas Order (Opsional)
                        </label>
                        <select name="order_id" id="select-order" class="form-select form-select-sm rounded-pill">
                            <option value="">-- Umum (Pembaruan Posisi Kurir) --</option>
                            <?php foreach ($orders as $o): ?>
                                <?php if (in_array($o['status'], [\App\Models\Order::STATUS_ASSIGNED_TO_COURIER, \App\Models\Order::STATUS_COURIER_ON_THE_WAY, \App\Models\Order::STATUS_PICKED_UP, \App\Models\Order::STATUS_OUT_FOR_DELIVERY])): ?>
                                    <option value="<?= $o['id'] ?>">
                                        #<?= htmlspecialchars($o['order_code']) ?> (<?= htmlspecialchars($o['customer_name']) ?> - <?= htmlspecialchars($statusLabels[$o['status']]['label'] ?? $o['status']) ?>)
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-2">
                        <button type="submit" id="btn-submit-location" class="btn btn-sm btn-primary w-100 rounded-pill fw-bold">
                            <i class="fa-solid fa-floppy-disk me-1"></i> Simpan Lokasi
                        </button>
                    </div>
                </div>
            </form>
            <div class="form-text small text-muted mt-2">
                <i class="fa-solid fa-circle-question me-1 text-primary"></i> 
                Klik <strong>Deteksi GPS Device</strong> untuk otomatis mengambil posisi dari sensor HP/browser, atau ketik koordinat manual jika izin akses lokasi ditolak.
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const btnDetect = document.getElementById('btn-detect-gps');
        const latInput = document.getElementById('input-lat');
        const lngInput = document.getElementById('input-lng');
        const statusBox = document.getElementById('geo-status-box');
        const statusText = document.getElementById('geo-status-text');

        function setStatus(type, message, iconClass) {
            statusBox.className = `alert alert-${type} alert-dismissible fade show py-2 px-3 mb-3 small`;
            statusBox.classList.remove('d-none');
            statusText.innerHTML = `<i class="${iconClass} me-1"></i> ${message}`;
        }

        if (btnDetect) {
            btnDetect.addEventListener('click', function() {
                if (!navigator.geolocation) {
                    setStatus('warning', 'Browser Anda tidak mendukung HTML5 Geolocation. Silakan masukkan koordinat secara manual pada kolom input.', 'fa-solid fa-triangle-exclamation');
                    return;
                }

                btnDetect.disabled = true;
                btnDetect.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Mengunci Sinyal GPS...';
                setStatus('info', 'Sedang menghubungi sensor GPS perangkat Anda. Pastikan izin lokasi (Location Permission) diizinkan pada browser...', 'fa-solid fa-spinner fa-spin');

                const options = {
                    enableHighAccuracy: true,
                    timeout: 12000,
                    maximumAge: 0
                };

                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        btnDetect.disabled = false;
                        btnDetect.innerHTML = '<i class="fa-solid fa-satellite-dish me-1"></i> Deteksi GPS Device (HTML5)';

                        const lat = position.coords.latitude.toFixed(8);
                        const lng = position.coords.longitude.toFixed(8);
                        const accuracy = Math.round(position.coords.accuracy);

                        latInput.value = lat;
                        lngInput.value = lng;

                        setStatus('success', `Lokasi GPS berhasil didapatkan! <strong>Lat: ${lat}, Lng: ${lng}</strong> (Akurasi: ±${accuracy} meter). Silakan klik "Simpan Lokasi" untuk memperbarui data ke sistem.`, 'fa-solid fa-circle-check');
                    },
                    function(error) {
                        btnDetect.disabled = false;
                        btnDetect.innerHTML = '<i class="fa-solid fa-satellite-dish me-1"></i> Deteksi GPS Device (HTML5)';

                        let errorMsg = 'Gagal mendeteksi lokasi otomatis. ';
                        switch(error.code) {
                            case error.PERMISSION_DENIED:
                                errorMsg += 'Izin akses lokasi ditolak oleh browser/pengguna. Silakan izinkan akses lokasi pada pengaturan browser atau isi koordinat secara manual.';
                                break;
                            case error.POSITION_UNAVAILABLE:
                                errorMsg += 'Informasi lokasi perangkat tidak tersedia atau sinyal GPS lemah. Silakan masukkan koordinat secara manual.';
                                break;
                            case error.TIMEOUT:
                                errorMsg += 'Permintaan akses lokasi melebihi batas waktu (timeout). Silakan coba lagi atau masukkan koordinat manual.';
                                break;
                            default:
                                errorMsg += 'Terjadi kendala sensor perangkat (' + error.message + '). Silakan gunakan input manual.';
                        }
                        setStatus('warning', errorMsg, 'fa-solid fa-triangle-exclamation');
                        latInput.focus();
                    },
                    options
                );
            });
        }
    });
    </script>


    <!-- Role Switcher Floating Bar -->
    <?php require __DIR__ . '/_role_bar.php'; ?>

    <!-- Assigned Tasks List -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-list-check text-primary me-2"></i> Daftar Tugas Penjemputan & Pengantaran (<?= count($orders) ?> Tugas)</h5>
            <span class="badge bg-light text-dark border">Dikawal Otorisasi Server-Side</span>
        </div>
        <div class="card-body p-4">
            <?php if (empty($orders)): ?>
                <div class="text-center py-4 text-muted">
                    <i class="fa-solid fa-box-open display-4 mb-2 text-secondary"></i>
                    <p class="mb-0">Tidak ada tugas penjemputan/pengantaran aktif saat ini.</p>
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($orders as $ord): ?>
                        <?php 
                            $stInfo = $statusLabels[$ord['status']] ?? ['label' => $ord['status'], 'badge' => 'secondary'];
                        ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card h-100 border-0 shadow-sm rounded-4 p-3 border-start border-4 border-primary">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="badge bg-light text-primary border fw-bold"><?= htmlspecialchars($ord['order_code']) ?></span>
                                    <span class="badge bg-<?= $stInfo['badge'] ?> rounded-pill px-3 py-1"><?= htmlspecialchars($stInfo['label']) ?></span>
                                </div>

                                <h5 class="fw-bold text-dark mb-1"><?= htmlspecialchars($ord['service_name'] ?? 'Cuci Laundry') ?></h5>

                                <!-- Customer Info -->
                                <div class="p-2.5 bg-light rounded-3 small mb-3">
                                    <div class="fw-bold text-dark mb-1"><i class="fa-solid fa-user text-primary me-1"></i> Customer: <?= htmlspecialchars($ord['customer_name'] ?? 'Dewi Lestari') ?></div>
                                    <div class="text-success fw-bold mb-1"><i class="fa-solid fa-phone me-1"></i> WA: <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $ord['customer_phone'] ?? '081234567895') ?>" target="_blank" class="text-success text-decoration-none"><?= htmlspecialchars($ord['customer_phone'] ?? '081234567895') ?></a></div>
                                    <div class="text-muted"><i class="fa-solid fa-location-dot text-danger me-1"></i> <strong>Pickup:</strong> <?= htmlspecialchars($ord['address_line'] ?? 'Jl. Mawar Indah No. 12') ?></div>
                                </div>

                                <!-- Action Buttons based on Pickup Flow Status -->
                                <div class="pt-2 border-top mt-auto d-grid gap-2">
                                    <?php if ($ord['status'] === \App\Models\Order::STATUS_ASSIGNED_TO_COURIER): ?>
                                        <form action="<?= $baseUrl ?>/courier/update-pickup-status" method="POST">
                                            <?= \App\Helpers\Csrf::field() ?>
                                            <input type="hidden" name="order_id" value="<?= $ord['id'] ?>">
                                            <input type="hidden" name="status" value="<?= \App\Models\Order::STATUS_COURIER_ON_THE_WAY ?>">
                                            <button type="submit" class="btn btn-info text-white btn-sm w-100 rounded-pill fw-bold">
                                                <i class="fa-solid fa-motorcycle me-1"></i> Terima Tugas & Menuju Customer
                                            </button>
                                        </form>
                                    <?php elseif ($ord['status'] === \App\Models\Order::STATUS_COURIER_ON_THE_WAY): ?>
                                        <form action="<?= $baseUrl ?>/courier/update-pickup-status" method="POST">
                                            <?= \App\Helpers\Csrf::field() ?>
                                            <input type="hidden" name="order_id" value="<?= $ord['id'] ?>">
                                            <input type="hidden" name="status" value="<?= \App\Models\Order::STATUS_PICKED_UP ?>">
                                            <button type="submit" class="btn btn-primary btn-sm w-100 rounded-pill fw-bold">
                                                <i class="fa-solid fa-box-archive me-1"></i> Laundry Dijemput dari Customer
                                            </button>
                                        </form>
                                    <?php elseif ($ord['status'] === \App\Models\Order::STATUS_PICKED_UP): ?>
                                        <form action="<?= $baseUrl ?>/courier/update-pickup-status" method="POST">
                                            <?= \App\Helpers\Csrf::field() ?>
                                            <input type="hidden" name="order_id" value="<?= $ord['id'] ?>">
                                            <input type="hidden" name="status" value="<?= \App\Models\Order::STATUS_ARRIVED_AT_STORE ?>">
                                            <button type="submit" class="btn btn-success btn-sm w-100 rounded-pill fw-bold">
                                                <i class="fa-solid fa-store me-1"></i> Sampai & Serahkan ke Toko
                                            </button>
                                        </form>
                                    <?php elseif ($ord['status'] === \App\Models\Order::STATUS_READY || $ord['status'] === \App\Models\Order::STATUS_OUT_FOR_DELIVERY): ?>
                                        <form action="<?= $baseUrl ?>/courier/update-pickup-status" method="POST">
                                            <?= \App\Helpers\Csrf::field() ?>
                                            <input type="hidden" name="order_id" value="<?= $ord['id'] ?>">
                                            <input type="hidden" name="status" value="<?= \App\Models\Order::STATUS_DELIVERED ?>">
                                            <button type="submit" class="btn btn-success btn-sm w-100 rounded-pill fw-bold">
                                                <i class="fa-solid fa-house-circle-check me-1"></i> Serahkan ke Customer (Selesai)
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <a href="<?= $baseUrl ?>/orders/detail?id=<?= $ord['id'] ?>" class="btn btn-sm btn-outline-secondary rounded-pill fw-semibold">
                                        Lihat Detail & Timeline <i class="fa-solid fa-arrow-right ms-1"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
