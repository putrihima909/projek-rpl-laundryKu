<?php
$showSidebar = true;
$baseUrl = config('app.url', 'http://localhost/rpl/public');
$filters = $filters ?? [];
$summary = $summary ?? [
    'total_omset' => 0,
    'total_transaksi' => 0,
    'rata_rata' => 0,
    'omset_hari_ini' => 0,
    'omset_minggu_ini' => 0,
    'omset_bulan_ini' => 0,
];
$transactions = $transactions ?? [];
$chartDaily = $chart_daily ?? ['labels' => [], 'data' => []];
$chartServices = $chart_services ?? ['labels' => [], 'data' => []];
$methodBreakdown = $method_breakdown ?? [];
?>

<!-- Load Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<div class="container-fluid pb-5">
    <!-- Header Page -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center pb-3 mb-4 border-bottom">
        <div>
            <div class="mb-1">
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1.5 rounded-pill fw-semibold">
                    <i class="fa-solid fa-chart-line me-1"></i> LAPORAN KEUANGAN
                </span>
                <?php if (!empty($store)): ?>
                    <span class="badge bg-light text-muted border px-2.5 py-1 rounded-pill small ms-1">
                        <i class="fa-solid fa-store text-info me-1"></i> Outlet: <?= htmlspecialchars($store['name']) ?>
                    </span>
                <?php else: ?>
                    <span class="badge bg-light text-muted border px-2.5 py-1 rounded-pill small ms-1">
                        <i class="fa-solid fa-globe text-primary me-1"></i> Cakupan Seluruh Toko (Platform)
                    </span>
                <?php endif; ?>
            </div>
            <h2 class="fw-bold text-dark mb-1">Detail Omset & Transaksi Keuangan</h2>
            <p class="text-muted mb-0">Rincian lengkap asal-usul transaksi kasir yang berstatus lunas / terbayar riil dari database</p>
        </div>
        <div class="mt-3 mt-md-0 d-flex gap-2">
            <?php
            $backUrl = $baseUrl . '/dashboard/admin-toko';
            if ($role === \App\Helpers\Auth::ROLE_OWNER) {
                $backUrl = $baseUrl . '/dashboard/owner';
            } elseif ($role === \App\Helpers\Auth::ROLE_ADMIN_OWNER) {
                $backUrl = $baseUrl . '/dashboard/admin-owner';
            } elseif ($role === \App\Helpers\Auth::ROLE_OWNER_TOKO) {
                $backUrl = $baseUrl . '/dashboard/owner-toko';
            }
            ?>
            <a href="<?= $backUrl ?>" class="btn btn-outline-secondary rounded-pill btn-sm fw-semibold shadow-sm">
                <i class="fa-solid fa-arrow-left me-1"></i> Kembali ke Dashboard
            </a>
            <button onclick="window.print()" class="btn btn-outline-primary rounded-pill btn-sm fw-semibold shadow-sm">
                <i class="fa-solid fa-print me-1"></i> Cetak Laporan
            </button>
            <?php if (in_array($role, [\App\Helpers\Auth::ROLE_OWNER, \App\Helpers\Auth::ROLE_ADMIN_OWNER], true)): ?>
                <a href="<?= $baseUrl ?>/admin/whatsapp#omzetReportSection" class="btn btn-success rounded-pill btn-sm fw-bold shadow-sm">
                    <i class="fa-brands fa-whatsapp me-1"></i> Kirim Laporan via WhatsApp
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- 1. SUMMARY STAT CARDS (6 KARTU LENGKAP) -->
    <div class="row g-3 mb-4">
        <!-- Total Omset -->
        <div class="col-sm-6 col-xl-4">
            <div class="card stat-card h-100 p-3.5 shadow-sm border-0 rounded-4 bg-primary text-white">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="small fw-semibold text-white-50 text-uppercase d-block mb-1">TOTAL OMSET (FILTER TERPILIH)</span>
                        <h3 class="fw-bold text-white mb-1">Rp <?= number_format($summary['total_omset'], 0, ',', '.') ?></h3>
                        <small class="text-white-50">
                            <i class="fa-solid fa-circle-check me-1"></i> Akumulasi Lunas Riil
                        </small>
                    </div>
                    <div class="icon-box bg-white bg-opacity-20 text-white rounded-circle p-3 fs-3">
                        <i class="fa-solid fa-vault"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Transaksi -->
        <div class="col-sm-6 col-xl-4">
            <div class="card stat-card h-100 p-3.5 shadow-sm border-0 rounded-4 bg-white border-start border-4 border-info">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase d-block mb-1">TOTAL TRANSAKSI</span>
                        <h3 class="fw-bold text-dark mb-1"><?= number_format($summary['total_transaksi'], 0, ',', '.') ?> <span class="fs-6 fw-normal text-muted">Order</span></h3>
                        <small class="text-info fw-semibold">
                            <i class="fa-solid fa-receipt me-1"></i> Transaksi Berhasil
                        </small>
                    </div>
                    <div class="icon-box bg-info bg-opacity-10 text-info rounded-circle p-3 fs-3">
                        <i class="fa-solid fa-receipt"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Rata-Rata Transaksi -->
        <div class="col-sm-6 col-xl-4">
            <div class="card stat-card h-100 p-3.5 shadow-sm border-0 rounded-4 bg-white border-start border-4 border-warning">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase d-block mb-1">RATA-RATA TRANSAKSI</span>
                        <h3 class="fw-bold text-dark mb-1">Rp <?= number_format($summary['rata_rata'], 0, ',', '.') ?></h3>
                        <small class="text-warning fw-semibold">
                            <i class="fa-solid fa-calculator me-1"></i> Nilai Keranjang Rata-rata
                        </small>
                    </div>
                    <div class="icon-box bg-warning bg-opacity-10 text-warning rounded-circle p-3 fs-3">
                        <i class="fa-solid fa-scale-balanced"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Omset Hari Ini -->
        <div class="col-sm-6 col-xl-4">
            <div class="card stat-card h-100 p-3 shadow-sm border-0 rounded-4 bg-white">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase d-block mb-1">OMSET HARI INI</span>
                        <h4 class="fw-bold text-success mb-1">Rp <?= number_format($summary['omset_hari_ini'], 0, ',', '.') ?></h4>
                        <small class="text-muted"><i class="fa-solid fa-calendar-day me-1"></i> <?= date('d M Y') ?></small>
                    </div>
                    <div class="icon-box bg-success bg-opacity-10 text-success rounded-circle p-2.5 fs-4">
                        <i class="fa-solid fa-calendar-check"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Omset Minggu Ini -->
        <div class="col-sm-6 col-xl-4">
            <div class="card stat-card h-100 p-3 shadow-sm border-0 rounded-4 bg-white">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase d-block mb-1">OMSET MINGGU INI</span>
                        <h4 class="fw-bold text-primary mb-1">Rp <?= number_format($summary['omset_minggu_ini'], 0, ',', '.') ?></h4>
                        <small class="text-muted"><i class="fa-solid fa-calendar-week me-1"></i> 7 Hari Berjalan</small>
                    </div>
                    <div class="icon-box bg-primary bg-opacity-10 text-primary rounded-circle p-2.5 fs-4">
                        <i class="fa-solid fa-calendar-week"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Omset Bulan Ini -->
        <div class="col-sm-6 col-xl-4">
            <div class="card stat-card h-100 p-3 shadow-sm border-0 rounded-4 bg-white">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase d-block mb-1">OMSET BULAN INI</span>
                        <h4 class="fw-bold text-dark mb-1">Rp <?= number_format($summary['omset_bulan_ini'], 0, ',', '.') ?></h4>
                        <small class="text-muted"><i class="fa-solid fa-calendar-days me-1"></i> Bulan <?= date('F Y') ?></small>
                    </div>
                    <div class="icon-box bg-secondary bg-opacity-10 text-secondary rounded-circle p-2.5 fs-4">
                        <i class="fa-solid fa-calendar-days"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. FILTER & SHORTCUT PERIODE CEPAT -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <h5 class="fw-bold mb-0 text-dark">
                    <i class="fa-solid fa-filter text-primary me-2"></i> Filter & Rentang Periode
                </h5>
                <!-- Shortcut Tombol Periode Cepat -->
                <div class="btn-group btn-group-sm flex-wrap" role="group">
                    <a href="?period=today&payment_status=<?= urlencode($filters['payment_status'] ?? 'paid') ?>" 
                       class="btn <?= ($filters['period'] ?? '') === 'today' ? 'btn-primary' : 'btn-outline-primary' ?>">Hari Ini</a>
                    <a href="?period=yesterday&payment_status=<?= urlencode($filters['payment_status'] ?? 'paid') ?>" 
                       class="btn <?= ($filters['period'] ?? '') === 'yesterday' ? 'btn-primary' : 'btn-outline-primary' ?>">Kemarin</a>
                    <a href="?period=7days&payment_status=<?= urlencode($filters['payment_status'] ?? 'paid') ?>" 
                       class="btn <?= ($filters['period'] ?? '') === '7days' ? 'btn-primary' : 'btn-outline-primary' ?>">7 Hari</a>
                    <a href="?period=this_month&payment_status=<?= urlencode($filters['payment_status'] ?? 'paid') ?>" 
                       class="btn <?= ($filters['period'] ?? '') === 'this_month' ? 'btn-primary' : 'btn-outline-primary' ?>">Bulan Ini</a>
                    <a href="?period=last_month&payment_status=<?= urlencode($filters['payment_status'] ?? 'paid') ?>" 
                       class="btn <?= ($filters['period'] ?? '') === 'last_month' ? 'btn-primary' : 'btn-outline-primary' ?>">Bulan Lalu</a>
                    <a href="?period=this_year&payment_status=<?= urlencode($filters['payment_status'] ?? 'paid') ?>" 
                       class="btn <?= ($filters['period'] ?? '') === 'this_year' ? 'btn-primary' : 'btn-outline-primary' ?>">Tahun Ini</a>
                    <a href="<?= $baseUrl ?>/dashboard/omzet-detail" 
                       class="btn <?= empty($filters['period']) && empty($filters['start_date']) ? 'btn-dark' : 'btn-outline-secondary' ?>">Semua</a>
                </div>
            </div>

            <!-- Form Filter Manual -->
            <form action="<?= $baseUrl ?>/dashboard/omzet-detail" method="GET" class="row g-3 align-items-end">
                <div class="col-md-3 col-sm-6">
                    <label for="start_date" class="form-label small fw-semibold text-muted">Tanggal Mulai</label>
                    <input type="date" class="form-control form-control-sm rounded-3" id="start_date" name="start_date" 
                           value="<?= htmlspecialchars($filters['start_date'] ?? '') ?>">
                </div>
                <div class="col-md-3 col-sm-6">
                    <label for="end_date" class="form-label small fw-semibold text-muted">Tanggal Akhir</label>
                    <input type="date" class="form-control form-control-sm rounded-3" id="end_date" name="end_date" 
                           value="<?= htmlspecialchars($filters['end_date'] ?? '') ?>">
                </div>
                <div class="col-md-2 col-sm-6">
                    <label for="payment_status" class="form-label small fw-semibold text-muted">Status Pembayaran</label>
                    <select class="form-select form-select-sm rounded-3" id="payment_status" name="payment_status">
                        <option value="paid" <?= ($filters['payment_status'] ?? 'paid') === 'paid' ? 'selected' : '' ?>>LUNAS (Paid)</option>
                        <option value="unpaid" <?= ($filters['payment_status'] ?? '') === 'unpaid' ? 'selected' : '' ?>>BELUM LUNAS (Unpaid)</option>
                        <option value="refunded" <?= ($filters['payment_status'] ?? '') === 'refunded' ? 'selected' : '' ?>>DIREFUND (Refunded)</option>
                        <option value="" <?= ($filters['payment_status'] ?? 'paid') === '' ? 'selected' : '' ?>>-- Semua Status --</option>
                    </select>
                </div>
                <div class="col-md-2 col-sm-6">
                    <label for="payment_method" class="form-label small fw-semibold text-muted">Metode Pembayaran</label>
                    <select class="form-select form-select-sm rounded-3" id="payment_method" name="payment_method">
                        <option value="">-- Semua Metode --</option>
                        <option value="online" <?= ($filters['payment_method'] ?? '') === 'online' ? 'selected' : '' ?>>QRIS / Online</option>
                        <option value="cash" <?= ($filters['payment_method'] ?? '') === 'cash' ? 'selected' : '' ?>>Tunai / Cash</option>
                    </select>
                </div>
                <div class="col-md-2 col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm rounded-pill fw-semibold flex-grow-1">
                        <i class="fa-solid fa-magnifying-glass me-1"></i> Filter
                    </button>
                    <a href="<?= $baseUrl ?>/dashboard/omzet-detail" class="btn btn-outline-secondary btn-sm rounded-pill fw-semibold" title="Reset Filter">
                        <i class="fa-solid fa-rotate-left"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- 3. DUA GRAFIK OMSET INTERAKTIF (Chart.js) -->
    <div class="row g-4 mb-4">
        <!-- Grafik 1: Omset Berdasarkan Tanggal -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-chart-column text-primary me-2"></i> Grafik Omset Harian</h5>
                        <small class="text-muted">Tren pendapatan terbayar harian sesuai periode filter</small>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div style="height: 280px; position: relative;">
                        <canvas id="chartDailyCanvas"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Grafik 2: Omset Berdasarkan Layanan -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-chart-pie text-info me-2"></i> Omset Berdasarkan Layanan</h5>
                        <small class="text-muted">Proporsi kontribusi pendapatan per jenis paket laundry</small>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div style="height: 280px; position: relative;">
                        <canvas id="chartServicesCanvas"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 4. TABEL RINCIAN TRANSAKSI PEMBENTUK OMSET -->
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-header bg-white border-0 pt-4 px-4 pb-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
            <div>
                <h5 class="fw-bold mb-0 text-dark">
                    <i class="fa-solid fa-table-list text-success me-2"></i> Rincian Seluruh Transaksi Pembentuk Omset
                </h5>
                <small class="text-muted">Klik pada baris transaksi untuk melihat detail kalkulasi lengkap</small>
            </div>
            <div>
                <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1.5 rounded-pill fw-semibold">
                    Total: <?= count($transactions) ?> Transaksi
                </span>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="omzetTable">
                    <thead class="table-light">
                        <tr class="small text-uppercase text-muted">
                            <th class="ps-4">Tanggal</th>
                            <th>No. Pesanan</th>
                            <th>Pelanggan</th>
                            <th>Layanan</th>
                            <th>Berat/Qty</th>
                            <th>Harga</th>
                            <th>Biaya Tambahan</th>
                            <th>Diskon</th>
                            <th>Total Bayar</th>
                            <th>Metode</th>
                            <th>Status</th>
                            <th class="pe-4 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($transactions)): ?>
                            <tr>
                                <td colspan="12" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-folder-open fs-1 text-muted d-block mb-3"></i>
                                    <h6 class="fw-bold">Tidak ada transaksi ditemukan</h6>
                                    <small>Silakan ubah tanggal atau status pembayaran pada filter di atas.</small>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($transactions as $tx): 
                                $isPaid = $tx['payment_status'] === 'paid';
                                $statusBadgeClass = match($tx['payment_status']) {
                                    'paid' => 'success',
                                    'unpaid' => 'warning',
                                    'refunded' => 'danger',
                                    default => 'secondary'
                                };
                                $statusLabel = match($tx['payment_status']) {
                                    'paid' => 'LUNAS',
                                    'unpaid' => 'BELUM LUNAS',
                                    'refunded' => 'REFUND',
                                    default => strtoupper($tx['payment_status'])
                                };
                            ?>
                                <tr role="button" onclick="showTransactionDetail(<?= htmlspecialchars(json_encode($tx), ENT_QUOTES, 'UTF-8') ?>)">
                                    <td class="ps-4 small fw-semibold text-muted">
                                        <?= htmlspecialchars($tx['tanggal_formatted']) ?>
                                    </td>
                                    <td>
                                        <span class="fw-bold text-primary">#<?= htmlspecialchars($tx['order_code']) ?></span>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-dark"><?= htmlspecialchars($tx['customer_name']) ?></div>
                                        <small class="text-muted"><i class="fa-solid fa-phone small me-1"></i><?= htmlspecialchars($tx['customer_phone']) ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border"><?= htmlspecialchars($tx['service_name']) ?></span>
                                    </td>
                                    <td class="fw-semibold">
                                        <?= (float)$tx['weight'] ?> <?= htmlspecialchars($tx['service_unit']) ?>
                                    </td>
                                    <td class="text-muted small">
                                        Rp <?= number_format($tx['unit_price'], 0, ',', '.') ?>
                                    </td>
                                    <td class="text-muted small">
                                        Rp <?= number_format($tx['additional_fee'], 0, ',', '.') ?>
                                    </td>
                                    <td class="text-danger small">
                                        <?= $tx['discount'] > 0 ? '-Rp ' . number_format($tx['discount'], 0, ',', '.') : 'Rp 0' ?>
                                    </td>
                                    <td class="fw-bold text-dark">
                                        Rp <?= number_format($tx['total_amount'], 0, ',', '.') ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill">
                                            <?= strtoupper($tx['payment_method']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $statusBadgeClass ?> rounded-pill px-2.5 py-1">
                                            <i class="fa-solid <?= $isPaid ? 'fa-circle-check' : 'fa-clock' ?> me-1"></i> <?= $statusLabel ?>
                                        </span>
                                    </td>
                                    <td class="pe-4 text-center">
                                        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-2.5 py-1" 
                                                onclick="event.stopPropagation(); showTransactionDetail(<?= htmlspecialchars(json_encode($tx), ENT_QUOTES, 'UTF-8') ?>)">
                                            <i class="fa-solid fa-eye me-1"></i> Detail
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <?php if (!empty($transactions)): ?>
                        <tfoot class="table-light border-top border-2">
                            <tr class="fw-bold">
                                <td colspan="8" class="text-end ps-4 py-3">TOTAL KESELURUHAN DARI TABEL INI:</td>
                                <td class="text-success fs-5 py-3">
                                    Rp <?= number_format($summary['total_omset'], 0, ',', '.') ?>
                                </td>
                                <td colspan="3" class="text-muted small py-3">
                                    <?= count($transactions) ?> Transaksi Terhitung Sempurna
                                </td>
                            </tr>
                        </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- 5. MODAL POPUP DETAIL TRANSAKSI LENGKAP -->
<div class="modal fade" id="transactionDetailModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header bg-dark text-white rounded-top-4">
                <h5 class="modal-title fw-bold" id="txModalTitle">
                    <i class="fa-solid fa-receipt text-info me-2"></i> Rincian Pesanan
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="list-group list-group-flush mb-3">
                    <div class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Nomor Pesanan</span>
                        <strong id="txModalOrderCode" class="text-primary">-</strong>
                    </div>
                    <div class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Tanggal Transaksi</span>
                        <span id="txModalTanggal" class="fw-semibold">-</span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Nama Pelanggan</span>
                        <strong id="txModalCustomer">-</strong>
                    </div>
                    <div class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Nomor WhatsApp</span>
                        <span id="txModalPhone" class="text-muted">-</span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Cabang Toko</span>
                        <span id="txModalStore" class="fw-semibold">-</span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Layanan Laundry</span>
                        <span id="txModalService" class="badge bg-light text-dark border">-</span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Berat / Qty</span>
                        <strong id="txModalWeight">-</strong>
                    </div>
                    <div class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Harga Satuan</span>
                        <span id="txModalUnitPrice">Rp 0</span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Subtotal Dasar</span>
                        <span id="txModalSubtotal">Rp 0</span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Biaya Tambahan</span>
                        <span id="txModalAddFee" class="text-muted">Rp 0</span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Diskon Promo</span>
                        <span id="txModalDiscount" class="text-danger">Rp 0</span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between px-0 bg-light rounded-3 p-2.5 mt-2">
                        <span class="fw-bold fs-6">TOTAL AKHIR BAYAR</span>
                        <strong id="txModalTotal" class="text-success fs-5">Rp 0</strong>
                    </div>
                    <div class="list-group-item d-flex justify-content-between px-0 pt-3">
                        <span class="text-muted">Metode Pembayaran</span>
                        <span id="txModalMethod" class="badge bg-info-subtle text-info border">-</span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Status Pembayaran</span>
                        <span id="txModalPaymentStatus" class="badge bg-success rounded-pill px-3">-</span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Diproses Oleh</span>
                        <span id="txModalProcessedBy" class="text-muted small">-</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light rounded-bottom-4">
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script>
// Fungsi Menampilkan Modal Detail Transaksi
function showTransactionDetail(tx) {
    document.getElementById('txModalOrderCode').innerText = '#' + tx.order_code;
    document.getElementById('txModalTanggal').innerText = tx.tanggal_formatted;
    document.getElementById('txModalCustomer').innerText = tx.customer_name;
    document.getElementById('txModalPhone').innerText = tx.customer_phone || '-';
    document.getElementById('txModalStore').innerText = tx.store_name || 'LaundryKu Outlet';
    document.getElementById('txModalService').innerText = tx.service_name;
    document.getElementById('txModalWeight').innerText = parseFloat(tx.weight) + ' ' + (tx.service_unit || 'Kg');
    document.getElementById('txModalUnitPrice').innerText = 'Rp ' + new Intl.NumberFormat('id-ID').format(tx.unit_price);
    document.getElementById('txModalSubtotal').innerText = 'Rp ' + new Intl.NumberFormat('id-ID').format(tx.subtotal);
    document.getElementById('txModalAddFee').innerText = 'Rp ' + new Intl.NumberFormat('id-ID').format(tx.additional_fee);
    document.getElementById('txModalDiscount').innerText = (parseFloat(tx.discount) > 0 ? '-Rp ' : 'Rp ') + new Intl.NumberFormat('id-ID').format(tx.discount);
    document.getElementById('txModalTotal').innerText = 'Rp ' + new Intl.NumberFormat('id-ID').format(tx.total_amount);
    document.getElementById('txModalMethod').innerText = (tx.payment_method || 'CASH').toUpperCase();
    
    var payStatus = (tx.payment_status || 'paid').toUpperCase();
    var payBadge = document.getElementById('txModalPaymentStatus');
    payBadge.innerText = payStatus === 'PAID' ? 'LUNAS' : payStatus;
    payBadge.className = 'badge rounded-pill px-3 ' + (payStatus === 'PAID' ? 'bg-success' : 'bg-warning');
    
    document.getElementById('txModalProcessedBy').innerText = tx.processed_by || 'Kasir Toko';

    var modal = new bootstrap.Modal(document.getElementById('transactionDetailModal'));
    modal.show();
}

// Inisialisasi Grafik Chart.js
document.addEventListener('DOMContentLoaded', function() {
    var dailyLabels = <?= json_encode($chartDaily['labels']) ?>;
    var dailyData = <?= json_encode($chartDaily['data']) ?>;

    var serviceLabels = <?= json_encode($chartServices['labels']) ?>;
    var serviceData = <?= json_encode($chartServices['data']) ?>;

    // 1. Line Chart: Omset Harian
    var ctxDaily = document.getElementById('chartDailyCanvas');
    if (ctxDaily) {
        new Chart(ctxDaily, {
            type: 'line',
            data: {
                labels: dailyLabels.length ? dailyLabels : ['Tidak Ada Data'],
                datasets: [{
                    label: 'Omset Terbayar (Rp)',
                    data: dailyData.length ? dailyData : [0],
                    borderColor: '#0d6efd',
                    backgroundColor: 'rgba(13, 110, 253, 0.1)',
                    fill: true,
                    tension: 0.3,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    borderWidth: 2.5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Omset: Rp ' + new Intl.NumberFormat('id-ID').format(context.raw);
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'Rp ' + (value >= 1000 ? (value / 1000) + 'k' : value);
                            }
                        }
                    }
                }
            }
        });
    }

    // 2. Doughnut Chart: Omset Per Layanan
    var ctxService = document.getElementById('chartServicesCanvas');
    if (ctxService) {
        new Chart(ctxService, {
            type: 'doughnut',
            data: {
                labels: serviceLabels.length ? serviceLabels : ['Tidak Ada Layanan'],
                datasets: [{
                    data: serviceData.length ? serviceData : [1],
                    backgroundColor: [
                        '#0d6efd',
                        '#0dcaf0',
                        '#198754',
                        '#ffc107',
                        '#6f42c1',
                        '#fd7e14',
                        '#20c997'
                    ],
                    borderWidth: 2,
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { boxWidth: 12, font: { size: 11 } }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                var val = context.raw || 0;
                                return ' ' + context.label + ': Rp ' + new Intl.NumberFormat('id-ID').format(val);
                            }
                        }
                    }
                }
            }
        });
    }
});
</script>

