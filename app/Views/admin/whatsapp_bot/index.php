<?php
$showSidebar = true;
$baseUrl     = config('app.url', 'http://localhost/rpl/public');
$isBotActive = !empty($botSettings['enabled']);
$botBadge    = $isBotActive ? 'success' : 'secondary';
$botStatus   = $isBotActive ? 'Aktif' : 'Nonaktif';
?>

<div class="container-fluid">

    <!-- ══════════════════════════════════════════════════════════════════════
         HEADER
    ══════════════════════════════════════════════════════════════════════ -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-start pb-3 mb-4 border-bottom">
        <div>
            <div class="d-flex flex-wrap gap-2 mb-2">
                <span class="badge bg-primary px-3 py-1 fw-semibold rounded-pill">
                    <i class="fa-solid fa-robot me-1"></i> Modul Chat Bot Toko
                </span>
                <span class="badge bg-<?= $botBadge ?> px-3 py-1 fw-semibold rounded-pill">
                    <i class="fa-solid fa-circle-<?= $isBotActive ? 'check' : 'xmark' ?> me-1"></i> Bot <?= $botStatus ?>
                </span>
                <?php if (!empty($store['name'])): ?>
                    <span class="badge bg-info text-dark px-3 py-1 fw-semibold rounded-pill">
                        <i class="fa-solid fa-shop me-1"></i> Outlet: <?= htmlspecialchars($store['name']) ?>
                    </span>
                <?php endif; ?>
            </div>
            <h2 class="fw-bold text-dark mb-1">🤖 Manajemen Chat Bot WhatsApp</h2>
            <p class="text-muted mb-0">Atur pesan balasan otomatis berdasarkan kata kunci (keyword) untuk pelanggan toko Anda.</p>
        </div>
        <div class="mt-3 mt-md-0 d-flex flex-wrap gap-2">
            <a href="<?= $baseUrl ?>/admin/system-config" class="btn btn-sm btn-outline-secondary rounded-pill">
                <i class="fa-solid fa-arrow-left me-1"></i> Pengaturan Sistem &amp; Integrasi
            </a>
            <a href="<?= $baseUrl ?>/dashboard/admin-toko" class="btn btn-sm btn-outline-dark rounded-pill">
                <i class="fa-solid fa-house me-1"></i> Dashboard Admin Toko
            </a>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════════
         STATUS SUMMARY CARD & WEBHOOK INFO
    ══════════════════════════════════════════════════════════════════════ -->
    <div class="row g-3 mb-4">
        <!-- Status Bot -->
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 h-100">
                <div class="d-flex align-items-center gap-3">
                    <div class="icon-box bg-<?= $botBadge ?> bg-opacity-10 text-<?= $botBadge ?>">
                        <i class="fa-solid fa-robot"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="text-muted small fw-semibold text-uppercase mb-1">Status Chat Bot</div>
                        <div class="fw-bold fs-5 text-dark"><?= $botStatus ?></div>
                        <small class="text-<?= $botBadge ?> fw-semibold">
                            <?= $isBotActive ? 'Siap menjawab pesan' : 'Bot dimatikan sementara' ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Keyword Aktif -->
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 h-100">
                <div class="d-flex align-items-center gap-3">
                    <div class="icon-box bg-success bg-opacity-10 text-success">
                        <i class="fa-solid fa-key"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="text-muted small fw-semibold text-uppercase mb-1">Keyword Aktif</div>
                        <div class="fw-bold fs-5 text-success"><?= number_format($activeKeywords ?? 0) ?> Kata Kunci</div>
                        <small class="text-muted">Siap memicu respons otomatis</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Respons Tersimpan -->
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 h-100">
                <div class="d-flex align-items-center gap-3">
                    <div class="icon-box bg-primary bg-opacity-10 text-primary">
                        <i class="fa-solid fa-comments"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="text-muted small fw-semibold text-uppercase mb-1">Total Respons</div>
                        <div class="fw-bold fs-5 text-primary"><?= number_format($totalResponses ?? 0) ?> Respons</div>
                        <small class="text-muted">Database keyword toko ini</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Webhook Status & URL -->
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 h-100">
                <div class="d-flex align-items-center gap-3">
                    <div class="icon-box bg-info bg-opacity-10 text-info">
                        <i class="fa-solid fa-bolt"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="text-muted small fw-semibold text-uppercase mb-1">Webhook Fonnte</div>
                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill">Aktif (Endpoint Siap)</span>
                        <div class="mt-1">
                            <small class="text-muted d-block" style="font-size:0.7rem;">URL Webhook Fonnte:</small>
                            <code style="font-size:0.7rem;" class="user-select-all"><?= $baseUrl ?>/webhook/fonnte</code>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════════
         PANEL PENGATURAN BOT & PESAN FALLBACK
    ══════════════════════════════════════════════════════════════════════ -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-4">
            <h5 class="fw-bold mb-3"><i class="fa-solid fa-sliders text-primary me-2"></i>Pengaturan Layanan Bot</h5>
            <form action="<?= $baseUrl ?>/admin/whatsapp-bot/settings" method="POST">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">

                <div class="row g-3">
                    <div class="col-12 col-md-4">
                        <div class="form-check form-switch p-3 bg-light rounded-3 h-100">
                            <input class="form-check-input ms-0 me-3" type="checkbox" role="switch" id="botEnabledSwitch" name="enabled" value="1" <?= $isBotActive ? 'checked' : '' ?>>
                            <label class="form-check-label fw-semibold text-dark" for="botEnabledSwitch">
                                Aktifkan Chat Bot Toko
                            </label>
                            <small class="d-block text-muted mt-1" style="font-size:0.75rem;">
                                Jika aktif, bot akan membalas otomatis pesan yang cocok dengan keyword toko.
                            </small>
                        </div>
                    </div>

                    <div class="col-12 col-md-8">
                        <label class="form-label small fw-semibold text-muted text-uppercase">Pesan Fallback (Jika Keyword Tidak Ditemukan)</label>
                        <textarea class="form-control" name="fallback_message" rows="2" placeholder="Contoh: Halo! Terima kasih telah menghubungi kami. Pesan Anda akan segera dibalas staf kami."><?= htmlspecialchars($botSettings['fallback_message'] ?? '') ?></textarea>
                        <small class="text-muted" style="font-size:0.75rem;">Pesan ini dikirimkan jika pelanggan mengirim pesan di luar daftar keyword aktif.</small>
                    </div>
                </div>

                <div class="text-end mt-3">
                    <button type="submit" class="btn btn-sm btn-primary rounded-pill px-4">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Simpan Pengaturan Bot
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════════
         DAFTAR KEYWORD & RESPONS OTOMATIS
    ══════════════════════════════════════════════════════════════════════ -->
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-header bg-white border-0 p-4 pb-0 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h5 class="fw-bold mb-1"><i class="fa-solid fa-list-check text-success me-2"></i>Daftar Keyword &amp; Respons Otomatis</h5>
                <p class="text-muted small mb-0">Tambahkan kata kunci seperti 'harga', 'jam buka', 'alamat', dsb.</p>
            </div>
            <button type="button" class="btn btn-sm btn-success rounded-pill px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalAddKeyword">
                <i class="fa-solid fa-plus me-1"></i> Tambah Keyword Baru
            </button>
        </div>

        <div class="card-body p-4">
            <?php if (empty($responses)): ?>
                <div class="text-center py-5">
                    <div class="icon-box bg-light text-muted mx-auto mb-3" style="width:64px;height:64px;font-size:1.8rem;">
                        <i class="fa-solid fa-keyboard"></i>
                    </div>
                    <h6 class="fw-bold text-dark mb-1">Belum Ada Keyword Terdaftar</h6>
                    <p class="text-muted small mb-3">Mulai tambahkan kata kunci pemicu dan jawaban otomatis untuk pelanggan Anda.</p>
                    <button type="button" class="btn btn-sm btn-primary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#modalAddKeyword">
                        <i class="fa-solid fa-plus me-1"></i> Tambah Keyword Pertama
                    </button>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr class="small text-muted text-uppercase">
                                <th style="width: 50px;">No</th>
                                <th style="width: 180px;">Keyword</th>
                                <th>Respons Otomatis</th>
                                <th style="width: 110px;" class="text-center">Status</th>
                                <th style="width: 140px;" class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($responses as $idx => $r): ?>
                                <tr>
                                    <td class="text-muted small"><?= $idx + 1 ?></td>
                                    <td>
                                        <span class="badge bg-light text-dark border px-2.5 py-1.5 font-monospace fw-semibold" style="font-size:0.85rem;">
                                            <i class="fa-solid fa-hashtag text-primary me-1"></i><?= htmlspecialchars($r['keyword']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="text-dark small" style="max-height: 80px; overflow-y: auto; white-space: pre-wrap;"><?= htmlspecialchars($r['response']) ?></div>
                                    </td>
                                    <td class="text-center">
                                        <form action="<?= $baseUrl ?>/admin/whatsapp-bot/responses/toggle" method="POST" class="d-inline">
                                            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                                            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                            <button type="submit" class="btn btn-xs rounded-pill px-2.5 py-1 border-0 <?= !empty($r['is_active']) ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' ?>" title="Klik untuk ubah status">
                                                <i class="fa-solid fa-circle-<?= !empty($r['is_active']) ? 'check' : 'xmark' ?> me-1"></i>
                                                <?= !empty($r['is_active']) ? 'Aktif' : 'Nonaktif' ?>
                                            </button>
                                        </form>
                                    </td>
                                    <td class="text-end">
                                        <!-- Tombol Edit Modal -->
                                        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill btn-edit-kw" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#modalEditKeyword"
                                            data-id="<?= (int)$r['id'] ?>"
                                            data-keyword="<?= htmlspecialchars($r['keyword'], ENT_QUOTES) ?>"
                                            data-response="<?= htmlspecialchars($r['response'], ENT_QUOTES) ?>"
                                            data-active="<?= (int)$r['is_active'] ?>"
                                            title="Edit Keyword">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>

                                        <!-- Form Delete -->
                                        <form action="<?= $baseUrl ?>/admin/whatsapp-bot/responses/delete" method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus keyword ini?');">
                                            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                                            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill" title="Hapus Keyword">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div><!-- /container-fluid -->

<!-- ══════════════════════════════════════════════════════════════════════
     MODAL: TAMBAH KEYWORD
══════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalAddKeyword" tabindex="-1" aria-labelledby="modalAddKeywordLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <form action="<?= $baseUrl ?>/admin/whatsapp-bot/responses/store" method="POST">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" id="modalAddKeywordLabel">
                        <i class="fa-solid fa-plus-circle text-success me-2"></i>Tambah Keyword Baru
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted text-uppercase">Kata Kunci (Keyword)</label>
                        <input type="text" class="form-control rounded-3 font-monospace" name="keyword" placeholder="Contoh: harga / promo / alamat" required>
                        <small class="text-muted" style="font-size:0.75rem;">Kata kunci tidak membedakan huruf besar/kecil (case-insensitive).</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted text-uppercase">Respons Balasan Otomatis</label>
                        <textarea class="form-control rounded-3" name="response" rows="4" placeholder="Tuliskan jawaban yang akan dikirim bot kepada pelanggan..." required></textarea>
                    </div>
                    <div class="form-check form-switch mb-1">
                        <input class="form-check-input" type="checkbox" role="switch" id="addActiveSwitch" name="is_active" value="1" checked>
                        <label class="form-check-label small fw-semibold" for="addActiveSwitch">Langsung Aktifkan Keyword</label>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-sm btn-light rounded-pill px-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-sm btn-success rounded-pill px-4">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Simpan Keyword
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════
     MODAL: EDIT KEYWORD
══════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalEditKeyword" tabindex="-1" aria-labelledby="modalEditKeywordLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <form action="<?= $baseUrl ?>/admin/whatsapp-bot/responses/update" method="POST">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                <input type="hidden" name="id" id="editKwId" value="">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" id="modalEditKeywordLabel">
                        <i class="fa-solid fa-pen-to-square text-primary me-2"></i>Edit Keyword &amp; Respons
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted text-uppercase">Kata Kunci (Keyword)</label>
                        <input type="text" class="form-control rounded-3 font-monospace" name="keyword" id="editKwKeyword" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted text-uppercase">Respons Balasan Otomatis</label>
                        <textarea class="form-control rounded-3" name="response" id="editKwResponse" rows="4" required></textarea>
                    </div>
                    <div class="form-check form-switch mb-1">
                        <input class="form-check-input" type="checkbox" role="switch" id="editActiveSwitch" name="is_active" value="1">
                        <label class="form-check-label small fw-semibold" for="editActiveSwitch">Keyword Aktif</label>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-sm btn-light rounded-pill px-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-sm btn-primary rounded-pill px-4">
                        <i class="fa-solid fa-check me-1"></i> Perbarui Keyword
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const editBtns = document.querySelectorAll('.btn-edit-kw');
    editBtns.forEach(btn => {
        btn.addEventListener('click', function () {
            document.getElementById('editKwId').value = this.dataset.id;
            document.getElementById('editKwKeyword').value = this.dataset.keyword;
            document.getElementById('editKwResponse').value = this.dataset.response;
            document.getElementById('editActiveSwitch').checked = (this.dataset.active === '1');
        });
    });
});
</script>

