<?php
/**
 * View: Daftar Permintaan Refund
 * Route: GET /refunds
 * Roles: owner, admin_owner → semua toko | owner_toko, admin_toko → toko sendiri
 */
$baseUrl = config('app.url', 'http://localhost/rpl/public');

$statusBadges = [
    'pending'    => ['bg-warning text-dark',   'Menunggu'],
    'processing' => ['bg-info text-white',     'Diproses'],
    'completed'  => ['bg-success text-white',  'Selesai'],
    'failed'     => ['bg-danger text-white',   'Gagal'],
];
?>

<div class="container-fluid px-4 py-4">

    <!-- Page Header -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 fw-bold mb-0 text-dark">
                <i class="fa-solid fa-rotate-left text-danger me-2"></i>
                Daftar Permintaan Refund
            </h1>
            <p class="text-muted small mb-0 mt-1">
                Kelola pengembalian dana untuk transaksi yang sudah dibayar.
            </p>
        </div>
        <a href="<?= $baseUrl ?>/store/orders" class="btn btn-outline-secondary btn-sm rounded-pill">
            <i class="fa-solid fa-arrow-left me-1"></i> Kembali ke Pesanan
        </a>
    </div>

    <!-- Stats Cards -->
    <?php
    $total      = count($refunds);
    $pending    = count(array_filter($refunds, fn($r) => $r['status'] === 'pending'));
    $processing = count(array_filter($refunds, fn($r) => $r['status'] === 'processing'));
    $completed  = count(array_filter($refunds, fn($r) => $r['status'] === 'completed'));
    $failed     = count(array_filter($refunds, fn($r) => $r['status'] === 'failed'));
    ?>
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-body text-center py-3">
                    <div class="fs-2 fw-bold text-primary"><?= $total ?></div>
                    <div class="small text-muted">Total Refund</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-body text-center py-3">
                    <div class="fs-2 fw-bold text-warning"><?= $pending ?></div>
                    <div class="small text-muted">Menunggu</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-body text-center py-3">
                    <div class="fs-2 fw-bold text-success"><?= $completed ?></div>
                    <div class="small text-muted">Selesai</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-body text-center py-3">
                    <div class="fs-2 fw-bold text-danger"><?= $failed ?></div>
                    <div class="small text-muted">Gagal</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white border-bottom py-3 px-4">
            <h6 class="mb-0 fw-semibold">
                <i class="fa-solid fa-list me-2 text-muted"></i>
                Riwayat Permintaan Refund
            </h6>
        </div>
        <div class="card-body p-0">
            <?php if (empty($refunds)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="fa-solid fa-rotate-left fa-3x mb-3 opacity-25"></i>
                    <p class="mb-0">Belum ada permintaan refund.</p>
                </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="refund-table">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">#</th>
                            <th>Kode Pesanan</th>
                            <?php if (in_array($role, ['owner', 'admin_owner'])): ?>
                            <th>Toko</th>
                            <?php endif; ?>
                            <th>Customer</th>
                            <th class="text-end">Jumlah Refund</th>
                            <th>Alasan</th>
                            <th class="text-center">Status</th>
                            <th>Dibuat Oleh</th>
                            <th>Tgl Dibuat</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($refunds as $refund):
                        [$badgeClass, $badgeLabel] = $statusBadges[$refund['status']] ?? ['bg-secondary', $refund['status']];
                        $canConfirm = in_array($refund['status'], ['pending', 'processing']);
                    ?>
                        <tr>
                            <td class="ps-4 text-muted small"><?= (int)$refund['id'] ?></td>
                            <td>
                                <a href="<?= $baseUrl ?>/orders/detail?id=<?= (int)$refund['order_id'] ?>"
                                   class="fw-semibold text-primary text-decoration-none">
                                    <?= htmlspecialchars($refund['order_code'] ?? 'ORD-'.$refund['order_id']) ?>
                                </a>
                            </td>
                            <?php if (in_array($role, ['owner', 'admin_owner'])): ?>
                            <td class="small"><?= htmlspecialchars($refund['store_name'] ?? '-') ?></td>
                            <?php endif; ?>
                            <td class="small"><?= htmlspecialchars($refund['customer_name'] ?? '-') ?></td>
                            <td class="text-end fw-semibold text-success">
                                Rp <?= number_format((float)$refund['amount'], 0, ',', '.') ?>
                            </td>
                            <td class="small" style="max-width:200px;">
                                <span title="<?= htmlspecialchars($refund['reason']) ?>">
                                    <?= htmlspecialchars(mb_strimwidth($refund['reason'], 0, 60, '...')) ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <span class="badge <?= $badgeClass ?> rounded-pill px-3 py-1">
                                    <?= $badgeLabel ?>
                                </span>
                            </td>
                            <td class="small text-muted"><?= htmlspecialchars($refund['created_by_name'] ?? 'Sistem') ?></td>
                            <td class="small text-muted">
                                <?= $refund['created_at'] ? date('d/m/Y H:i', strtotime($refund['created_at'])) : '-' ?>
                            </td>
                            <td class="text-center">
                                <?php if ($canConfirm): ?>
                                <div class="d-flex gap-1 justify-content-center">
                                    <button class="btn btn-success btn-sm rounded-pill px-3"
                                            onclick="confirmRefund(<?= (int)$refund['id'] ?>, 'complete')"
                                            title="Konfirmasi Refund Selesai">
                                        <i class="fa-solid fa-check"></i>
                                    </button>
                                    <button class="btn btn-danger btn-sm rounded-pill px-3"
                                            onclick="confirmRefund(<?= (int)$refund['id'] ?>, 'fail')"
                                            title="Tolak / Gagalkan Refund">
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>
                                </div>
                                <?php elseif ($refund['status'] === 'completed'): ?>
                                <span class="text-success small">
                                    <i class="fa-solid fa-circle-check"></i> Selesai
                                </span>
                                <?php else: ?>
                                <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Hidden CSRF & Confirm form -->
<form id="refund-confirm-form" method="POST" action="<?= $baseUrl ?>/refunds/confirm" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
    <input type="hidden" name="refund_id" id="fc-refund-id" value="">
    <input type="hidden" name="action"    id="fc-action"    value="">
</form>

<script>
function confirmRefund(refundId, action) {
    const labelMap = { complete: 'SELESAI (dana dikembalikan)', fail: 'GAGAL (tolak refund)' };
    if (!confirm('Tandai refund #' + refundId + ' sebagai ' + (labelMap[action] || action) + '?\n\nTindakan ini tidak dapat dibatalkan.')) return;

    document.getElementById('fc-refund-id').value = refundId;
    document.getElementById('fc-action').value    = action;

    // Submit via fetch to get JSON response
    const form = document.getElementById('refund-confirm-form');
    const fd   = new FormData(form);
    fetch(form.action, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            } else {
                alert('Gagal: ' + data.message);
            }
        })
        .catch(() => { form.submit(); });
}
</script>
