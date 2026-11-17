<?php
$showSidebar = true;
$baseUrl = config('app.url', 'http://localhost/rpl/public');
?>

<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center pb-3 mb-4 border-bottom">
        <div>
            <span class="badge bg-primary text-white px-3 py-1 mb-2 fw-bold"><i class="fa-solid fa-users-gear me-1"></i> RBAC USER MANAGEMENT</span>
            <h2 class="fw-bold text-dark mb-1">Manajemen Pengguna & Role</h2>
            <p class="text-muted mb-0">Kelola daftar pengguna, tetapkan role akses, dan ubah status aktif/nonaktif akun.</p>
        </div>
        <div class="mt-3 mt-md-0 d-flex gap-2">
            <a href="<?= $baseUrl ?>/users/create" class="btn btn-primary rounded-pill px-4 shadow-sm fw-bold">
                <i class="fa-solid fa-user-plus me-2"></i> Tambah User Baru
            </a>
        </div>
    </div>

    <!-- Table Card -->
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-list text-primary me-2"></i> Daftar User Terdaftar (<?= count($users) ?> Akun)</h5>
            <span class="badge bg-light text-dark border"><i class="fa-solid fa-lock text-success me-1"></i> Terlindungi Server-Side RBAC</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">ID</th>
                        <th>Nama Pengguna</th>
                        <th>Email</th>
                        <th>Role Akses</th>
                        <th>No. Telepon</th>
                        <th>Status Akun</th>
                        <th class="text-end pe-4">Aksi Management</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">Belum ada data user.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $u): ?>
                            <?php 
                                $roleKey = $u['role_name'] ?? 'customer';
                                $roleBadgeColor = match($roleKey) {
                                    'owner' => 'warning text-dark',
                                    'admin_owner' => 'info text-dark',
                                    'owner_toko' => 'success',
                                    'admin_toko' => 'primary',
                                    'kurir' => 'secondary',
                                    default => 'light text-dark border'
                                };
                            ?>
                            <tr>
                                <td class="ps-4 fw-bold text-muted">#<?= $u['id'] ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 34px; height: 34px; font-size: 0.85rem;">
                                            <?= strtoupper(substr($u['name'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark mb-0"><?= htmlspecialchars($u['name']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><code><?= htmlspecialchars($u['email']) ?></code></td>
                                <td>
                                    <span class="badge bg-<?= $roleBadgeColor ?> rounded-pill px-3 py-1.5 fw-bold">
                                        <?= htmlspecialchars($u['display_name'] ?? ucfirst(str_replace('_', ' ', $roleKey))) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($u['phone'] ?: '-') ?></td>
                                <td>
                                    <?php if ($u['is_active']): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success rounded-pill px-3 py-1">
                                            <i class="fa-solid fa-circle-check me-1"></i> Aktif
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-danger bg-opacity-10 text-danger border border-danger rounded-pill px-3 py-1">
                                            <i class="fa-solid fa-circle-xmark me-1"></i> Nonaktif
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="d-inline-flex gap-2">
                                        <a href="<?= $baseUrl ?>/users/edit?id=<?= $u['id'] ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-semibold">
                                            <i class="fa-solid fa-pen-to-square me-1"></i> Edit
                                        </a>

                                        <?php if ($u['id'] !== \App\Helpers\Auth::id()): ?>
                                            <form action="<?= $baseUrl ?>/users/toggle-status" method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin mengubah status aktif user ini?');">
                                                <?= \App\Helpers\Csrf::field() ?>
                                                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                                <button type="submit" class="btn btn-sm <?= $u['is_active'] ? 'btn-outline-danger' : 'btn-outline-success' ?> rounded-pill px-3 fw-semibold">
                                                    <?= $u['is_active'] ? '<i class="fa-solid fa-user-slash me-1"></i> Nonaktifkan' : '<i class="fa-solid fa-user-check me-1"></i> Aktifkan' ?>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="btn btn-sm btn-light disabled rounded-pill px-3">Akun Anda</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
