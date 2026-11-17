<?php
/**
 * Partial View: Modal Transparansi Asal-Usul & Perhitungan Omzet
 * Variabel yang dibutuhkan: $transparency (array dari OmzetTransparencyService)
 */
if (empty($transparency)) {
    return;
}

$omzetAktif = $transparency['omzet_terbayar_aktif'] ?? [];
$gateway = $transparency['pembayaran_gateway'] ?? [];
$refundSelesai = $transparency['refund_selesai'] ?? [];
$refundBelum = $transparency['refund_belum_selesai'] ?? [];
$dikecualikan = $transparency['transaksi_dikecualikan'] ?? [];
$transaksiPaid = $transparency['transaksi_pembentuk_omzet'] ?? [];
$transaksiRefunded = $transparency['transaksi_refunded'] ?? [];
$isPlatform = $transparency['is_platform'] ?? false;
?>

<!-- Modal Transparansi Omzet -->
<div class="modal fade" id="omzetDetailModal" tabindex="-1" aria-labelledby="omzetDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header bg-light border-bottom px-4 py-3">
                <div class="d-flex align-items-center gap-2">
                    <div class="icon-box bg-success bg-opacity-10 text-success p-2 rounded-circle">
                        <i class="fa-solid fa-calculator fs-5"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold text-dark mb-0" id="omzetDetailModalLabel">Transparansi Asal-Usul & Perhitungan Omzet</h5>
                        <small class="text-muted">
                            Cakupan: 
                            <span class="badge <?= $isPlatform ? 'bg-primary' : 'bg-success' ?> rounded-pill">
                                <?= $isPlatform ? 'Seluruh Platform' : 'Toko Saya' ?>
                            </span>
                        </small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-4">
                <!-- 1. Empat Pilar Keuangan -->
                <div class="row g-3 mb-4">
                    <!-- Omzet Terbayar Aktif -->
                    <div class="col-md-6 col-lg-3">
                        <div class="card h-100 border-0 bg-success bg-opacity-10 rounded-4 p-3 border-start border-4 border-success">
                            <span class="text-muted small fw-semibold text-uppercase d-block mb-1">1. Omzet Terbayar Aktif</span>
                            <h4 class="fw-bold text-success mb-1">Rp <?= number_format($omzetAktif['nominal'] ?? 0, 0, ',', '.') ?></h4>
                            <small class="text-dark fw-medium d-block mb-2"><?= $omzetAktif['count'] ?? 0 ?> Pesanan Lunas</small>
                            <span class="badge bg-success bg-opacity-25 text-success small">Angka Utama Dashboard</span>
                        </div>
                    </div>

                    <!-- Pembayaran Gateway Aktual -->
                    <div class="col-md-6 col-lg-3">
                        <div class="card h-100 border-0 bg-primary bg-opacity-10 rounded-4 p-3 border-start border-4 border-primary">
                            <span class="text-muted small fw-semibold text-uppercase d-block mb-1">2. Kas Masuk Gateway</span>
                            <h4 class="fw-bold text-primary mb-1">Rp <?= number_format($gateway['nominal'] ?? 0, 0, ',', '.') ?></h4>
                            <small class="text-dark fw-medium d-block mb-2"><?= $gateway['count'] ?? 0 ?> Transaksi (QRIS)</small>
                            <span class="badge bg-primary bg-opacity-25 text-primary small">Tercatat di Gateway</span>
                        </div>
                    </div>

                    <!-- Refund Selesai -->
                    <div class="col-md-6 col-lg-3">
                        <div class="card h-100 border-0 bg-danger bg-opacity-10 rounded-4 p-3 border-start border-4 border-danger">
                            <span class="text-muted small fw-semibold text-uppercase d-block mb-1">3. Refund Selesai</span>
                            <h4 class="fw-bold text-danger mb-1">Rp <?= number_format($refundSelesai['nominal'] ?? 0, 0, ',', '.') ?></h4>
                            <small class="text-dark fw-medium d-block mb-2"><?= $refundSelesai['count'] ?? 0 ?> Pengembalian</small>
                            <span class="badge bg-danger bg-opacity-25 text-danger small">Dana Tuntas Keluar</span>
                        </div>
                    </div>

                    <!-- Refund Belum Selesai -->
                    <div class="col-md-6 col-lg-3">
                        <div class="card h-100 border-0 bg-warning bg-opacity-10 rounded-4 p-3 border-start border-4 border-warning">
                            <span class="text-muted small fw-semibold text-uppercase d-block mb-1">4. Refund Belum Selesai</span>
                            <h4 class="fw-bold text-warning text-dark mb-1">Rp <?= number_format($refundBelum['total_nominal'] ?? 0, 0, ',', '.') ?></h4>
                            <small class="text-dark fw-medium d-block mb-2"><?= $refundBelum['total_count'] ?? 0 ?> Pending/Proses/Gagal</small>
                            <span class="badge bg-warning bg-opacity-25 text-dark small">Dana Masih di Sistem</span>
                        </div>
                    </div>
                </div>

                <!-- 2. Penjelasan Rumus Sederhana -->
                <div class="alert alert-info border-0 rounded-4 p-3 mb-4">
                    <h6 class="fw-bold text-dark mb-1"><i class="fa-solid fa-circle-question me-1 text-primary"></i> Penjelasan Rumus Angka Omzet</h6>
                    <p class="small text-muted mb-2">
                        Angka utama pada kartu dashboard menggunakan definisi <strong>Omzet Terbayar Aktif</strong>:
                    </p>
                    <div class="bg-white p-2 rounded-3 border text-dark font-monospace small mb-2">
                        Omzet Terbayar Aktif = SUM(total_amount) WHERE payment_status = 'paid'
                    </div>
                    <ul class="small text-muted mb-0 ps-3">
                        <li><strong>Bebas Pengurangan Ganda (Double-Deduction):</strong> Angka ini <em>tidak dikurangi refund lagi</em> karena pesanan yang diretur statusnya telah secara otomatis dialihkan ke status <code>refunded</code> sehingga sudah keluar dari perhitungan omzet aktif.</li>
                        <li><strong>Bukan Saldo Gateway:</strong> Pesanan berstatus <code>paid</code> mencerminkan seluruh pesanan yang tervalidasi lunas di sistem, sedangkan Kas Masuk Gateway mencatat khusus pembayaran yang diselesaikan via QRIS.</li>
                    </ul>
                </div>

                <!-- 3. Rincian Pengecualian Transaksi -->
                <div class="card border rounded-4 p-3 mb-4">
                    <h6 class="fw-bold text-dark mb-3"><i class="fa-solid fa-filter me-1 text-secondary"></i> Transaksi yang Dikecualikan dari Omzet Terbayar</h6>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="border rounded-3 p-3 bg-light">
                                <span class="badge bg-secondary mb-1">payment_status = 'unpaid'</span>
                                <div class="fw-bold fs-5 text-dark">Rp <?= number_format($dikecualikan['unpaid']['nominal'] ?? 0, 0, ',', '.') ?></div>
                                <small class="text-muted d-block"><?= $dikecualikan['unpaid']['count'] ?? 0 ?> Pesanan</small>
                                <small class="text-muted mt-1 d-block"><strong>Alasan:</strong> <?= htmlspecialchars($dikecualikan['unpaid']['alasan'] ?? '') ?></small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded-3 p-3 bg-light">
                                <span class="badge bg-danger mb-1">payment_status = 'refunded'</span>
                                <div class="fw-bold fs-5 text-dark">Rp <?= number_format($dikecualikan['refunded']['nominal'] ?? 0, 0, ',', '.') ?></div>
                                <small class="text-muted d-block"><?= $dikecualikan['refunded']['count'] ?? 0 ?> Pesanan</small>
                                <small class="text-muted mt-1 d-block"><strong>Alasan:</strong> <?= htmlspecialchars($dikecualikan['refunded']['alasan'] ?? '') ?></small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded-3 p-3 bg-light">
                                <span class="badge bg-dark mb-1">status = 'cancelled'</span>
                                <div class="fw-bold fs-5 text-dark">Rp <?= number_format($dikecualikan['cancelled']['nominal'] ?? 0, 0, ',', '.') ?></div>
                                <small class="text-muted d-block"><?= $dikecualikan['cancelled']['count'] ?? 0 ?> Pesanan</small>
                                <small class="text-muted mt-1 d-block"><strong>Alasan:</strong> <?= htmlspecialchars($dikecualikan['cancelled']['alasan'] ?? '') ?></small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 4. Tab Navigasi Daftar Transaksi Sumber -->
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                    <div class="card-header bg-white border-bottom p-3">
                        <ul class="nav nav-pills card-header-pills" id="omzetTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active fw-semibold small rounded-pill px-3" id="paid-tab" data-bs-toggle="pill" data-bs-target="#paid-list" type="button" role="tab" aria-controls="paid-list" aria-selected="true">
                                    <i class="fa-solid fa-list-check me-1"></i> Pesanan Pembentuk Omzet (<?= count($transaksiPaid) ?>)
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link fw-semibold small rounded-pill px-3" id="refund-tab" data-bs-toggle="pill" data-bs-target="#refund-list" type="button" role="tab" aria-controls="refund-list" aria-selected="false">
                                    <i class="fa-solid fa-arrow-rotate-left me-1"></i> Pesanan Refunded (<?= count($transaksiRefunded) ?>)
                                </button>
                            </li>
                        </ul>
                    </div>

                    <div class="card-body p-0">
                        <div class="tab-content" id="omzetTabsContent">
                            <!-- Tab 1: Pesanan Pembentuk Omzet -->
                            <div class="tab-pane fade show active p-3" id="paid-list" role="tabpanel" aria-labelledby="paid-tab">
                                <div class="table-responsive" style="max-height: 380px;">
                                    <table class="table table-hover align-middle small mb-0">
                                        <thead class="table-light sticky-top">
                                            <tr>
                                                <th>#</th>
                                                <th>Order ID</th>
                                                <th>Kode Pesanan</th>
                                                <?php if ($isPlatform): ?>
                                                    <th>Outlet Toko</th>
                                                <?php endif; ?>
                                                <th>Tanggal</th>
                                                <th>Metode</th>
                                                <th>Status</th>
                                                <th class="text-end">Nominal (Rp)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($transaksiPaid)): ?>
                                                <tr><td colspan="<?= $isPlatform ? 8 : 7 ?>" class="text-center py-4 text-muted">Belum ada pesanan terbayar.</td></tr>
                                            <?php else: ?>
                                                <?php $no = 1; foreach ($transaksiPaid as $tx): ?>
                                                    <tr>
                                                        <td><?= $no++ ?></td>
                                                        <td><code>#<?= $tx['id'] ?></code></td>
                                                        <td class="fw-semibold"><?= htmlspecialchars($tx['order_code']) ?></td>
                                                        <?php if ($isPlatform): ?>
                                                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($tx['store_name'] ?? 'Toko') ?></span></td>
                                                        <?php endif; ?>
                                                        <td class="text-muted"><?= htmlspecialchars($tx['created_at']) ?></td>
                                                        <td><span class="badge bg-info-subtle text-info text-uppercase"><?= htmlspecialchars($tx['payment_method']) ?></span></td>
                                                        <td><span class="badge bg-success">paid</span></td>
                                                        <td class="text-end fw-bold text-success">Rp <?= number_format((float)$tx['total_amount'], 0, ',', '.') ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                        <tfoot class="table-light sticky-bottom fw-bold">
                                            <tr>
                                                <td colspan="<?= $isPlatform ? 7 : 6 ?>" class="text-end">Total Omzet Terbayar:</td>
                                                <td class="text-end text-success">Rp <?= number_format($omzetAktif['nominal'] ?? 0, 0, ',', '.') ?></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>

                            <!-- Tab 2: Pesanan Refunded -->
                            <div class="tab-pane fade p-3" id="refund-list" role="tabpanel" aria-labelledby="refund-tab">
                                <div class="table-responsive" style="max-height: 380px;">
                                    <table class="table table-hover align-middle small mb-0">
                                        <thead class="table-light sticky-top">
                                            <tr>
                                                <th>#</th>
                                                <th>Order ID</th>
                                                <th>Kode Pesanan</th>
                                                <?php if ($isPlatform): ?>
                                                    <th>Outlet Toko</th>
                                                <?php endif; ?>
                                                <th>Nominal Order</th>
                                                <th>Status Refund</th>
                                                <th>Nominal Refund</th>
                                                <th>Alasan</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($transaksiRefunded)): ?>
                                                <tr><td colspan="<?= $isPlatform ? 8 : 7 ?>" class="text-center py-4 text-muted">Tidak ada transaksi yang direfund.</td></tr>
                                            <?php else: ?>
                                                <?php $no = 1; foreach ($transaksiRefunded as $rf): ?>
                                                    <tr>
                                                        <td><?= $no++ ?></td>
                                                        <td><code>#<?= $rf['id'] ?></code></td>
                                                        <td class="fw-semibold"><?= htmlspecialchars($rf['order_code']) ?></td>
                                                        <?php if ($isPlatform): ?>
                                                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($rf['store_name'] ?? 'Toko') ?></span></td>
                                                        <?php endif; ?>
                                                        <td class="text-muted">Rp <?= number_format((float)$rf['total_amount'], 0, ',', '.') ?></td>
                                                        <td>
                                                            <?php
                                                            $st = $rf['refund_status'] ?? 'unknown';
                                                            $badgeClass = match($st) {
                                                                'completed' => 'bg-success',
                                                                'processing' => 'bg-primary',
                                                                'pending' => 'bg-warning text-dark',
                                                                'failed' => 'bg-danger',
                                                                default => 'bg-secondary'
                                                            };
                                                            ?>
                                                            <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($st) ?></span>
                                                        </td>
                                                        <td class="fw-bold <?= $st === 'completed' ? 'text-danger' : 'text-muted' ?>">
                                                            Rp <?= number_format((float)($rf['refund_amount'] ?? 0), 0, ',', '.') ?>
                                                        </td>
                                                        <td class="text-muted small"><?= htmlspecialchars($rf['reason'] ?? '-') ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
            <div class="modal-footer bg-light px-4 py-2 border-top">
                <span class="small text-muted me-auto"><i class="fa-solid fa-shield-check me-1 text-success"></i> Angka divalidasi langsung secara real-time dari database produksi.</span>
                <button type="button" class="btn btn-secondary btn-sm rounded-pill px-4" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

