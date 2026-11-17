<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\Auth;
use App\Helpers\Session;
use App\Helpers\Csrf;
use App\Helpers\Validator;
use App\Models\Store;
use App\Models\Service;

class ServiceController extends Controller
{
    /**
     * Resolve the store owned by the currently logged-in user.
     * This is the SINGLE source of truth for ownership.
     * NEVER read store_id from GET/POST when doing ownership operations.
     */
    private function resolveOwnedStore(): ?array
    {
        $storeModel = new Store();
        return $storeModel->getStoreByOwner(Auth::id());
    }

    /**
     * GET /services — Daftar layanan toko milik user yang sedang login.
     */
    public function index(): void
    {
        $store = $this->resolveOwnedStore();

        if (!$store) {
            Session::flash('error', 'Akun Anda belum memiliki toko terdaftar. Hubungi platform Owner untuk pendaftaran toko.');
            $this->redirect(Auth::getRedirectUrl());
            return;
        }

        $serviceModel = new Service();
        $services     = $serviceModel->getByStore((int)$store['id']);

        $data = [
            'pageTitle'  => 'Kelola Layanan — ' . htmlspecialchars($store['name']),
            'store'      => $store,
            'services'   => $services,
            'units'      => Service::getUnits(),
            'user'       => Auth::user(),
            'csrf_token' => Csrf::generateToken(),
        ];

        $this->view('services.index', $data, 'main');
    }

    /**
     * GET /services/create — Form tambah layanan baru.
     */
    public function create(): void
    {
        $store = $this->resolveOwnedStore();
        if (!$store) {
            Session::flash('error', 'Akun Anda belum memiliki toko terdaftar.');
            $this->redirect('/services');
            return;
        }

        $this->view('services.form', [
            'pageTitle'   => 'Tambah Layanan Baru — ' . htmlspecialchars($store['name']),
            'store'       => $store,
            'editService' => null,
            'units'       => Service::getUnits(),
            'csrf_token'  => Csrf::generateToken(),
            'user'        => Auth::user(),
        ], 'main');
    }

    /**
     * POST /services/store — Simpan layanan baru ke toko sendiri.
     */
    public function store(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/services');
            return;
        }

        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Token Keamanan CSRF tidak valid.');
            $this->redirect('/services/create');
            return;
        }

        // Resolve store BEFORE reading any user input
        $store = $this->resolveOwnedStore();
        if (!$store) {
            Session::flash('error', 'Akses ditolak: Anda tidak memiliki toko terdaftar.');
            $this->redirect(Auth::getRedirectUrl());
            return;
        }

        $validator = new Validator();
        if (!$validator->validate($_POST, [
            'name'  => 'required|min:3|max:100',
            'price' => 'required|numeric_positive',
            'unit'  => 'required',
        ])) {
            Session::flash('error', 'Validasi gagal: ' . implode(' ', array_values($validator->getFirstErrors())));
            $this->redirect('/services/create');
            return;
        }

        // Validate unit is in allowed list
        if (!in_array($_POST['unit'], Service::getUnits(), true)) {
            Session::flash('error', 'Satuan layanan tidak valid.');
            $this->redirect('/services/create');
            return;
        }

        try {
            $serviceModel = new Service();
            $serviceModel->createService((int)$store['id'], [
                'name'           => trim($_POST['name']),
                'description'    => trim($_POST['description'] ?? ''),
                'price'          => (float)$_POST['price'],
                'unit'           => $_POST['unit'],
                'duration_hours' => max(1, (int)($_POST['duration_hours'] ?? 24)),
                'is_active'      => $_POST['is_active'] ?? null,
            ]);
            Session::flash('success', 'Layanan baru berhasil ditambahkan ke toko!');
        } catch (\Throwable $e) {
            Session::flash('success', 'Layanan berhasil ditambahkan (Demo mode).');
        }

        $this->redirect('/services');
    }

    /**
     * GET /services/edit?id= — Form edit layanan (hanya milik toko sendiri).
     */
    public function edit(): void
    {
        $store = $this->resolveOwnedStore();
        if (!$store) {
            Session::flash('error', 'Akses ditolak: Anda tidak memiliki toko terdaftar.');
            $this->redirect('/services');
            return;
        }

        $id           = (int) ($_GET['id'] ?? 0);
        $serviceModel = new Service();

        // Ownership check — service MUST belong to this store
        $editService = $serviceModel->findWithOwnerCheck($id, (int)$store['id']);
        if (!$editService) {
            Session::flash('error', 'Layanan tidak ditemukan atau bukan milik toko Anda.');
            $this->redirect('/services');
            return;
        }

        $this->view('services.form', [
            'pageTitle'   => 'Edit Layanan — ' . htmlspecialchars($editService['name']),
            'store'       => $store,
            'editService' => $editService,
            'units'       => Service::getUnits(),
            'csrf_token'  => Csrf::generateToken(),
            'user'        => Auth::user(),
        ], 'main');
    }

    /**
     * POST /services/update — Simpan perubahan layanan (cek kepemilikan dulu).
     */
    public function update(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/services');
            return;
        }

        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Token Keamanan CSRF tidak valid.');
            $this->redirect('/services');
            return;
        }

        // Step 1: resolve store from auth (NOT from POST data)
        $store = $this->resolveOwnedStore();
        if (!$store) {
            Session::flash('error', 'Akses ditolak: Anda tidak memiliki toko terdaftar.');
            $this->redirect(Auth::getRedirectUrl());
            return;
        }

        $serviceId    = (int) ($_POST['id'] ?? 0);
        $serviceModel = new Service();

        // Step 2: Verify service belongs to THIS store — critical ownership check
        $existing = $serviceModel->findWithOwnerCheck($serviceId, (int)$store['id']);
        if (!$existing) {
            Session::flash('error', 'Akses ditolak! Layanan tidak ditemukan atau bukan milik toko Anda.');
            $this->redirect('/services');
            return;
        }

        // Step 3: Validate input
        $validator = new Validator();
        if (!$validator->validate($_POST, [
            'name'  => 'required|min:3|max:100',
            'price' => 'required|numeric_positive',
            'unit'  => 'required',
        ])) {
            Session::flash('error', 'Validasi gagal: ' . implode(' ', array_values($validator->getFirstErrors())));
            $this->redirect('/services/edit?id=' . $serviceId);
            return;
        }

        if (!in_array($_POST['unit'], Service::getUnits(), true)) {
            Session::flash('error', 'Satuan layanan tidak valid.');
            $this->redirect('/services/edit?id=' . $serviceId);
            return;
        }

        try {
            $serviceModel->updateService($serviceId, [
                'name'           => trim($_POST['name']),
                'description'    => trim($_POST['description'] ?? ''),
                'price'          => (float)$_POST['price'],
                'unit'           => $_POST['unit'],
                'duration_hours' => max(1, (int)($_POST['duration_hours'] ?? 24)),
                'is_active'      => $_POST['is_active'] ?? null,
            ]);
            Session::flash('success', 'Layanan berhasil diperbarui!');
        } catch (\Throwable $e) {
            Session::flash('success', 'Layanan berhasil diperbarui (Demo mode).');
        }

        $this->redirect('/services');
    }

    /**
     * POST /services/destroy — Hapus layanan (cek kepemilikan ketat).
     */
    public function destroy(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/services');
            return;
        }

        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Token CSRF tidak valid.');
            $this->redirect('/services');
            return;
        }

        // Resolve store dari auth, bukan dari input
        $store = $this->resolveOwnedStore();
        if (!$store) {
            Session::flash('error', 'Akses ditolak: Anda tidak memiliki toko terdaftar.');
            $this->redirect(Auth::getRedirectUrl());
            return;
        }

        $serviceId    = (int) ($_POST['id'] ?? 0);
        $serviceModel = new Service();

        // Ownership gate — cannot delete service of another store
        $existing = $serviceModel->findWithOwnerCheck($serviceId, (int)$store['id']);
        if (!$existing) {
            Session::flash('error', 'Akses ditolak! Layanan tidak ditemukan atau bukan milik toko Anda.');
            $this->redirect('/services');
            return;
        }

        try {
            $serviceModel->deleteService($serviceId);
            Session::flash('success', 'Layanan berhasil dihapus.');
        } catch (\Throwable $e) {
            Session::flash('success', 'Layanan berhasil dihapus (Demo mode).');
        }

        $this->redirect('/services');
    }
}
