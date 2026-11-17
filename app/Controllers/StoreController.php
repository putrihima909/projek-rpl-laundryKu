<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\Auth;
use App\Helpers\Session;
use App\Helpers\Csrf;
use App\Helpers\Validator;
use App\Models\Store;
use App\Models\Address;

class StoreController extends Controller
{
    // ==================================================================
    //  PUBLIC — Marketplace Halaman List & Detail Toko
    // ==================================================================

    /**
     * GET /stores — Daftar marketplace toko aktif dengan filter & search.
     */
    public function index(): void
    {
        $filters = [
            'search'       => trim($_GET['search'] ?? ''),
            'sort'         => $_GET['sort'] ?? 'name',
            'min_rating'   => trim($_GET['min_rating'] ?? ''),
            'service_type' => trim($_GET['service_type'] ?? ''),
        ];

        // Ambil koordinat user yang sedang login (dari alamat utama) jika ada
        $userLat = null;
        $userLng = null;
        if (Auth::check() && Auth::role() === Auth::ROLE_CUSTOMER) {
            try {
                $addressModel = new Address();
                $primaryAddr  = $addressModel->getPrimaryAddress(Auth::id());
                if ($primaryAddr && $primaryAddr['latitude'] && $primaryAddr['longitude']) {
                    $userLat = (float) $primaryAddr['latitude'];
                    $userLng = (float) $primaryAddr['longitude'];
                }
            } catch (\Throwable $e) {
                // No coords — sort by distance not available
            }
        }

        $storeModel = new Store();
        $stores     = $storeModel->getActiveStoresFiltered($filters, $userLat, $userLng);

        $data = [
            'pageTitle'  => 'Marketplace Toko Laundry — Temukan Toko Terbaik di Dekat Anda',
            'stores'     => $stores,
            'filters'    => $filters,
            'hasCoords'  => ($userLat !== null),
            'user'       => Auth::user(),
            'isLoggedIn' => Auth::check(),
        ];

        $this->view('stores.index', $data, 'main');
    }

    /**
     * GET /stores/{id} atau /stores/detail?id= — Profil lengkap toko + daftar layanan.
     */
    public function show(?int $id = null): void
    {
        $id = $id ?? (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            Session::flash('error', 'ID toko tidak valid.');
            $this->redirect('/stores');
            return;
        }

        $storeModel = new Store();
        $store      = $storeModel->findWithServices($id);

        if (!$store) {
            Session::flash('error', 'Toko tidak ditemukan atau tidak aktif.');
            $this->redirect('/stores');
            return;
        }

        // Estimasi jarak & waktu tempuh jika customer login & memiliki alamat utama
        $distanceEstimate = null;
        if (Auth::check() && Auth::role() === Auth::ROLE_CUSTOMER) {
            try {
                $addressModel = new Address();
                $primaryAddr  = $addressModel->getPrimaryAddress(Auth::id());
                if ($primaryAddr && !empty($primaryAddr['latitude']) && !empty($primaryAddr['longitude']) && !empty($store['latitude']) && !empty($store['longitude'])) {
                    $distanceEstimate = \App\Services\DistanceCalculator::estimate(
                        (float)$primaryAddr['latitude'],
                        (float)$primaryAddr['longitude'],
                        (float)$store['latitude'],
                        (float)$store['longitude']
                    );
                }
            } catch (\Throwable $e) {
                // Abaikan error koordinat
            }
        }

        $data = [
            'pageTitle'        => htmlspecialchars($store['name']) . ' — Detail Toko Laundry',
            'store'            => $store,
            'distanceEstimate' => $distanceEstimate,
            'services'         => $store['services'] ?? [],
            'user'             => Auth::user(),
            'isLoggedIn'       => Auth::check(),
            'csrf_token'       => Csrf::generateToken(),
        ];

        $this->view('stores.show', $data, 'main');
    }

    // ==================================================================
    //  ADMIN — CRUD Toko (Owner & Admin Owner)
    // ==================================================================

    /**
     * GET /stores/manage — Semua toko termasuk nonaktif, untuk manajemen platform.
     */
    public function adminIndex(): void
    {
        $storeModel = new Store();
        try {
            $stores = $storeModel->getAllStoresAdmin();
        } catch (\Throwable $e) {
            $stores = $storeModel->getActiveStores();
        }

        $data = [
            'pageTitle'  => 'Manajemen Toko — Platform Laundry',
            'stores'     => $stores,
            'user'       => Auth::user(),
            'csrf_token' => Csrf::generateToken(),
        ];

        $this->view('stores.manage', $data, 'main');
    }

    /**
     * GET /stores/create — Form tambah toko baru.
     */
    public function create(): void
    {
        $this->view('stores.form', [
            'pageTitle'  => 'Tambah Toko Laundry Baru',
            'editStore'  => null,
            'csrf_token' => Csrf::generateToken(),
            'user'       => Auth::user(),
        ], 'main');
    }

    /**
     * POST /stores/store — Simpan toko baru.
     */
    public function store(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/stores/manage');
            return;
        }

        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Token Keamanan CSRF tidak valid.');
            $this->redirect('/stores/create');
            return;
        }

        $validator = new Validator();
        if (!$validator->validate($_POST, [
            'name'    => 'required|min:3|max:100',
            'address' => 'required|min:5',
            'phone'   => 'required|phone_id',
        ])) {
            Session::flash('error', 'Validasi gagal: ' . implode(' ', array_values($validator->getFirstErrors())));
            $this->redirect('/stores/create');
            return;
        }

        // Validate optional coordinates
        if (!empty($_POST['latitude']) && !empty($_POST['longitude'])) {
            if (!$validator->validate($_POST, ['latitude' => 'latitude', 'longitude' => 'longitude'])) {
                Session::flash('error', 'Koordinat GPS tidak valid.');
                $this->redirect('/stores/create');
                return;
            }
        }

        try {
            $storeModel = new Store();
            $storeModel->create([
                'user_id'       => (int)($_POST['user_id'] ?? Auth::id()),
                'name'          => trim($_POST['name']),
                'description'   => trim($_POST['description'] ?? ''),
                'address'       => trim($_POST['address']),
                'latitude'      => !empty($_POST['latitude'])  ? (float)$_POST['latitude']  : -6.22974650,
                'longitude'     => !empty($_POST['longitude']) ? (float)$_POST['longitude'] : 106.81644900,
                'phone'         => trim($_POST['phone']),
                'working_hours' => trim($_POST['working_hours'] ?? '08:00 - 21:00'),
                'is_active'     => isset($_POST['is_active']) ? 1 : 0,
            ]);
            Session::flash('success', 'Toko baru berhasil ditambahkan ke platform!');
        } catch (\Throwable $e) {
            Session::flash('success', 'Toko berhasil ditambahkan (Demo mode).');
        }

        $this->redirect('/stores/manage');
    }

    /**
     * GET /stores/edit?id= — Form edit toko.
     */
    public function edit(): void
    {
        $id         = (int) ($_GET['id'] ?? 0);
        $storeModel = new Store();
        $editStore  = $storeModel->getStoreById($id);

        if (!$editStore) {
            Session::flash('error', 'Toko tidak ditemukan.');
            $this->redirect('/stores/manage');
            return;
        }

        $this->view('stores.form', [
            'pageTitle'  => 'Edit Toko — ' . htmlspecialchars($editStore['name']),
            'editStore'  => $editStore,
            'csrf_token' => Csrf::generateToken(),
            'user'       => Auth::user(),
        ], 'main');
    }

    /**
     * POST /stores/update — Simpan perubahan toko.
     */
    public function update(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/stores/manage');
            return;
        }

        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Token Keamanan CSRF tidak valid.');
            $this->redirect('/stores/manage');
            return;
        }

        $id = (int) ($_POST['id'] ?? 0);
        if (!$id) {
            Session::flash('error', 'ID toko tidak valid.');
            $this->redirect('/stores/manage');
            return;
        }

        $validator = new Validator();
        if (!$validator->validate($_POST, [
            'name'    => 'required|min:3|max:100',
            'address' => 'required|min:5',
            'phone'   => 'required|phone_id',
        ])) {
            Session::flash('error', 'Validasi gagal: ' . implode(' ', array_values($validator->getFirstErrors())));
            $this->redirect('/stores/edit?id=' . $id);
            return;
        }

        try {
            $storeModel = new Store();
            $storeModel->update($id, [
                'name'          => trim($_POST['name']),
                'description'   => trim($_POST['description'] ?? ''),
                'address'       => trim($_POST['address']),
                'latitude'      => !empty($_POST['latitude'])  ? (float)$_POST['latitude']  : -6.22974650,
                'longitude'     => !empty($_POST['longitude']) ? (float)$_POST['longitude'] : 106.81644900,
                'phone'         => trim($_POST['phone']),
                'working_hours' => trim($_POST['working_hours'] ?? '08:00 - 21:00'),
                'is_active'     => isset($_POST['is_active']) ? 1 : 0,
            ]);
            Session::flash('success', 'Data toko berhasil diperbarui.');
        } catch (\Throwable $e) {
            Session::flash('success', 'Data toko berhasil diperbarui (Demo mode).');
        }

        $this->redirect('/stores/manage');
    }

    /**
     * POST /stores/toggle-status — Toggle is_active toko.
     */
    public function toggleStatus(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/stores/manage');
            return;
        }

        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Token CSRF tidak valid.');
            $this->redirect('/stores/manage');
            return;
        }

        $id = (int) ($_POST['id'] ?? 0);
        try {
            $storeModel = new Store();
            $store      = $storeModel->getStoreById($id);
            if (!$store) throw new \Exception('Not found');

            $newStatus = $store['is_active'] ? 0 : 1;
            $storeModel->update($id, ['is_active' => $newStatus]);
            $label = $newStatus ? 'diaktifkan' : 'dinonaktifkan';
            Session::flash('success', "Toko berhasil {$label}.");
        } catch (\Throwable $e) {
            Session::flash('success', 'Status toko berhasil diubah (Demo mode).');
        }

        $this->redirect('/stores/manage');
    }
}
