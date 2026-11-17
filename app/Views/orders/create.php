<?php
$showSidebar = true;
$baseUrl = config('app.url', 'http://localhost/rpl/public');
?>

<div class="container-fluid">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center pb-3 mb-4 border-bottom">
        <div>
            <span class="badge bg-success text-white px-3 py-1 mb-2 fw-bold"><i class="fa-solid fa-basket-shopping me-1"></i> ORDER LAUNDRY</span>
            <h2 class="fw-bold text-dark mb-1">Pesan Layanan Laundry Baru</h2>
            <p class="text-muted mb-0">Pilih toko, jenis layanan, alamat penjemputan, dan estimasi berat pakaian Anda.</p>
        </div>
        <div class="mt-3 mt-md-0">
            <a href="<?= $baseUrl ?>/orders" class="btn btn-outline-secondary rounded-pill btn-sm">
                <i class="fa-solid fa-clock-rotate-left me-1"></i> Riwayat Pesanan
            </a>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-9">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4 p-md-5">
                    <form action="<?= $baseUrl ?>/orders/store" method="POST" id="orderForm">
                        <?= \App\Helpers\Csrf::field() ?>

                        <div class="row g-4">
                            <!-- 1. Pilih Toko Laundry -->
                            <div class="col-md-6">
                                <label for="store_id" class="form-label fw-bold text-dark"><i class="fa-solid fa-store text-primary me-1"></i> 1. Pilih Toko Laundry <span class="text-danger">*</span></label>
                                <select class="form-select form-select-lg" id="store_id" name="store_id" required onchange="window.location.href='<?= $baseUrl ?>/orders/create?store_id=' + this.value">
                                    <?php foreach ($stores as $st): ?>
                                        <option value="<?= $st['id'] ?>" 
                                                data-lat="<?= htmlspecialchars($st['latitude'] ?? '-6.22974650') ?>" 
                                                data-lng="<?= htmlspecialchars($st['longitude'] ?? '106.81644900') ?>" 
                                                <?= $st['id'] === $selectedStoreId ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($st['name']) ?> (<?= htmlspecialchars($st['address']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- 2. Pilih Jenis Layanan -->
                            <div class="col-md-6">
                                <label for="service_id" class="form-label fw-bold text-dark"><i class="fa-solid fa-tags text-primary me-1"></i> 2. Pilih Jenis Layanan <span class="text-danger">*</span></label>
                                <select class="form-select form-select-lg" id="service_id" name="service_id" required onchange="calculateEstimate()">
                                    <?php foreach ($services as $sv): ?>
                                        <option value="<?= $sv['id'] ?>" data-price="<?= $sv['price'] ?>" data-unit="<?= $sv['unit'] ?>" <?= ((int)$sv['id'] === ($selectedServiceId ?? 0)) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($sv['name']) ?> - Rp <?= number_format($sv['price'], 0, ',', '.') ?> / <?= $sv['unit'] ?> (<?= $sv['duration_hours'] ?> jam)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- 3. Estimasi Berat/Jumlah -->
                            <div class="col-md-6">
                                <label for="estimated_weight" class="form-label fw-bold text-dark"><i class="fa-solid fa-weight-scale text-primary me-1"></i> 3. Estimasi Berat / Jumlah <span class="text-danger">*</span></label>
                                <div class="input-group input-group-lg">
                                    <input type="number" step="0.5" min="0.5" class="form-control" id="estimated_weight" name="estimated_weight" required value="5.0" oninput="calculateEstimate()">
                                    <span class="input-group-text bg-light fw-bold" id="unitBadge">kg</span>
                                </div>
                                <small class="text-muted"><i class="fa-solid fa-info-circle me-1"></i> Harga pasti akan dihitung oleh Toko setelah cucian ditimbang.</small>
                            </div>

                            <!-- 4. Alamat Penjemputan -->
                            <div class="col-md-6">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label for="address_id" class="form-label fw-bold text-dark mb-0"><i class="fa-solid fa-location-dot text-primary me-1"></i> 4. Alamat Pickup <span class="text-danger">*</span></label>
                                    <a href="<?= $baseUrl ?>/addresses/create" class="small text-primary text-decoration-none">+ Tambah Alamat</a>
                                </div>
                                <select class="form-select form-select-lg" id="address_id" name="address_id" required onchange="calculateDistance()">
                                    <?php if (empty($userAddresses)): ?>
                                        <option value="">-- Belum ada alamat, silakan tambah alamat dahulu --</option>
                                    <?php else: ?>
                                        <?php foreach ($userAddresses as $addr): ?>
                                            <option value="<?= $addr['id'] ?>" 
                                                    data-lat="<?= htmlspecialchars($addr['latitude'] ?? '-6.24430000') ?>" 
                                                    data-lng="<?= htmlspecialchars($addr['longitude'] ?? '106.79300000') ?>" 
                                                    <?= ($selectedAddress && (int)$addr['id'] === (int)$selectedAddress['id']) ? 'selected' : '' ?>>
                                                [<?= htmlspecialchars($addr['title']) ?>] <?= htmlspecialchars($addr['address_line']) ?>, <?= htmlspecialchars($addr['city']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>

                            <!-- Distance Matrix Estimation Preview Banner -->
                            <div class="col-md-12">
                                <div class="card border-0 bg-primary bg-opacity-10 rounded-4 p-3 border-start border-4 border-primary shadow-sm" id="distancePreviewCard">
                                    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="bg-primary text-white p-2.5 rounded-circle fs-5 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                                                <i class="fa-solid fa-route"></i>
                                            </div>
                                            <div>
                                                <div class="small fw-bold text-uppercase text-primary">Estimasi Jarak & Waktu Tempuh Kurir</div>
                                                <div class="text-dark fw-bold">
                                                    Jarak Antar: <span id="distValText" class="text-primary fs-6"><?= htmlspecialchars($distanceEstimate['formatted_distance'] ?? '2.4 km') ?></span>
                                                    <span class="text-muted mx-1">•</span>
                                                    Estimasi Kurir Tiba: <span id="durValText" class="text-success fs-6"><?= htmlspecialchars($distanceEstimate['formatted_duration'] ?? '6 menit') ?></span>
                                                </div>
                                                <small class="text-muted">Dihitung otomatis dari koordinat GPS alamat penjemputan ke toko laundry (asumsi kurir motor 30 km/jam).</small>
                                            </div>
                                        </div>
                                        <span class="badge bg-white text-primary border rounded-pill px-3 py-1.5 align-self-start align-self-md-center">
                                            <i class="fa-solid fa-calculator me-1"></i> Haversine Matrix
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- 5. Metode Penerimaan Cucian Bersih -->
                            <div class="col-md-12">
                                <label class="form-label fw-bold text-dark d-block"><i class="fa-solid fa-box-open text-primary me-1"></i> 5. Metode Penerimaan Cucian Setelah Selesai <span class="text-danger">*</span></label>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="card p-3 border rounded-4 cursor-pointer hover-shadow h-100">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="delivery_type" id="dt_pickup" value="pickup">
                                                <label class="form-check-label fw-bold ms-2 text-dark" for="dt_pickup">
                                                    <i class="fa-solid fa-store text-warning me-2 fs-5"></i> Ambil Sendiri di Toko
                                                </label>
                                                <small class="text-muted d-block ms-4 mt-1">Anda mengambil cucian bersih langsung di outlet toko setelah selesai dicuci.</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card p-3 border rounded-4 cursor-pointer hover-shadow h-100">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="delivery_type" id="dt_delivery" value="delivery" checked>
                                                <label class="form-check-label fw-bold ms-2 text-dark" for="dt_delivery">
                                                    <i class="fa-solid fa-truck-fast text-primary me-2 fs-5"></i> Diantar Kurir ke Rumah
                                                </label>
                                                <small class="text-muted d-block ms-4 mt-1">Kurir toko akan mengantarkan cucian bersih kembali ke alamat Anda.</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- 6. Catatan Khusus -->
                            <div class="col-md-12">
                                <label for="notes" class="form-label fw-bold text-dark"><i class="fa-solid fa-note-sticky text-primary me-1"></i> 6. Catatan Khusus untuk Toko / Kurir</label>
                                <textarea class="form-control" id="notes" name="notes" rows="2" placeholder="Contoh: Pisahkan pakaian putih, lipat rapi, beri pewangi lavender"></textarea>
                            </div>

                            <!-- 7. Metode Pembayaran -->
                            <div class="col-md-12">
                                <label class="form-label fw-bold text-dark d-block"><i class="fa-solid fa-wallet text-primary me-1"></i> 7. Pilih Metode Pembayaran <span class="text-danger">*</span></label>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="card p-3 border rounded-4 cursor-pointer hover-shadow">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="payment_method" id="pay_cash" value="cash" checked>
                                                <label class="form-check-label fw-bold ms-2" for="pay_cash">
                                                    <i class="fa-solid fa-money-bill-wave text-success me-2 fs-5"></i> Cash On Delivery (Tunai saat diambil/dikirim)
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card p-3 border rounded-4 cursor-pointer hover-shadow">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="payment_method" id="pay_online" value="online">
                                                <label class="form-check-label fw-bold ms-2" for="pay_online">
                                                    <i class="fa-solid fa-credit-card text-primary me-2 fs-5"></i> Pembayaran Online (E-Wallet / Transfer Bank)
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Price Summary Preview Box -->
                        <div class="p-4 bg-light rounded-4 border border-primary border-opacity-25 mt-4">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <small class="text-muted fw-bold text-uppercase d-block mb-1">RINGKASAN ESTIMASI HARGA</small>
                                    <h3 class="fw-extrabold text-primary mb-0" id="estimatedPricePreview">Rp 30.000</h3>
                                    <small class="text-secondary"><i class="fa-solid fa-info-circle me-1"></i> Harga estimasi dapat menyesuaikan setelah penimbangan aktual toko.</small>
                                </div>
                                <div>
                                    <button type="submit" class="btn btn-primary btn-lg rounded-pill px-5 shadow-sm fw-bold">
                                        <i class="fa-solid fa-paper-plane me-2"></i> Buat Pesanan Sekarang
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function calculateEstimate() {
    const selectService = document.getElementById('service_id');
    if (!selectService || selectService.selectedIndex < 0) return;
    const selectedOption = selectService.options[selectService.selectedIndex];
    const price = parseFloat(selectedOption.getAttribute('data-price') || 0);
    const unit = selectedOption.getAttribute('data-unit') || 'kg';
    const weight = parseFloat(document.getElementById('estimated_weight').value || 1);

    document.getElementById('unitBadge').innerText = unit;
    const total = price * weight;
    document.getElementById('estimatedPricePreview').innerText = 'Rp ' + new Intl.NumberFormat('id-ID').format(total);
}

function calculateDistance() {
    const storeSelect = document.getElementById('store_id');
    const addrSelect = document.getElementById('address_id');
    if (!storeSelect || !addrSelect || storeSelect.selectedIndex < 0 || addrSelect.selectedIndex < 0) return;

    const storeOpt = storeSelect.options[storeSelect.selectedIndex];
    const addrOpt = addrSelect.options[addrSelect.selectedIndex];

    const sLat = parseFloat(storeOpt.getAttribute('data-lat'));
    const sLng = parseFloat(storeOpt.getAttribute('data-lng'));
    const aLat = parseFloat(addrOpt.getAttribute('data-lat'));
    const aLng = parseFloat(addrOpt.getAttribute('data-lng'));

    if (isNaN(sLat) || isNaN(sLng) || isNaN(aLat) || isNaN(aLng)) return;

    // Rumus Haversine Client-Side
    const R = 6371; // km
    const dLat = (aLat - sLat) * Math.PI / 180;
    const dLng = (aLng - sLng) * Math.PI / 180;
    const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
              Math.cos(sLat * Math.PI / 180) * Math.cos(aLat * Math.PI / 180) *
              Math.sin(dLng/2) * Math.sin(dLng/2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    const distKm = R * c;

    // Kecepatan kurir motor dalam kota ~ 30 km/jam, detour 1.25x
    const effectiveKm = distKm * 1.25;
    const durationMins = Math.max(1, Math.round((effectiveKm / 30) * 60));

    const distFormatted = distKm < 1 ? Math.round(distKm * 1000) + ' m' : distKm.toFixed(1) + ' km';
    const durFormatted = durationMins < 60 ? durationMins + ' menit' : Math.floor(durationMins/60) + ' jam ' + (durationMins%60) + ' menit';

    const distText = document.getElementById('distValText');
    const durText = document.getElementById('durValText');
    if (distText) distText.innerText = distFormatted;
    if (durText) durText.innerText = durFormatted;
}

document.addEventListener('DOMContentLoaded', function() {
    calculateEstimate();
    calculateDistance();
});
</script>
