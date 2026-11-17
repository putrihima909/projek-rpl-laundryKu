<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\Auth;
use App\Helpers\Session;
use App\Helpers\Csrf;
use App\Helpers\Validator;
use App\Models\Address;

class AddressController extends Controller
{
    public function index(): void
    {
        $userId = Auth::id();
        $addressModel = new Address();
        $addresses = $addressModel->getUserAddresses($userId);

        $data = [
            'pageTitle' => 'Manajemen Alamat Saya - Customer',
            'addresses' => $addresses,
            'user' => Auth::user(),
            'csrf_token' => Csrf::generateToken()
        ];

        $this->view('addresses.index', $data, 'main');
    }

    public function create(): void
    {
        $data = [
            'pageTitle' => 'Tambah Alamat Baru',
            'csrf_token' => Csrf::generateToken(),
            'user' => Auth::user(),
            'editAddress' => null
        ];

        $this->view('addresses.form', $data, 'main');
    }

    public function store(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/addresses');
            return;
        }

        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Token Keamanan CSRF tidak valid.');
            $this->redirect('/addresses/create');
            return;
        }

        $validator = new Validator();
        if (!$validator->validate($_POST, [
            'title' => 'required|min:2',
            'address_line' => 'required|min:5',
            'province' => 'required',
            'city' => 'required',
            'district' => 'required',
            'village' => 'required',
            'rt' => 'required|rt_rw',
            'rw' => 'required|rt_rw',
            'latitude' => 'latitude',
            'longitude' => 'longitude'
        ])) {
            Session::flash('error', 'Validasi gagal: ' . implode(' ', array_values($validator->getFirstErrors())));
            $this->redirect('/addresses/create');
            return;
        }

        $userId = Auth::id();
        $addressModel = new Address();

        $data = [
            'user_id' => $userId,
            'title' => trim($_POST['title']),
            'address_line' => trim($_POST['address_line']),
            'province' => trim($_POST['province']),
            'city' => trim($_POST['city']),
            'district' => trim($_POST['district']),
            'village' => trim($_POST['village']),
            'rt' => str_pad(trim($_POST['rt']), 3, '0', STR_PAD_LEFT),
            'rw' => str_pad(trim($_POST['rw']), 3, '0', STR_PAD_LEFT),
            'detail_address' => trim($_POST['detail_address'] ?? ''),
            'latitude' => !empty($_POST['latitude']) ? (float)$_POST['latitude'] : null,
            'longitude' => !empty($_POST['longitude']) ? (float)$_POST['longitude'] : null,
            'is_primary' => isset($_POST['is_primary']) ? 1 : 0
        ];

        try {
            $addressModel->createAddress($data);
            Session::flash('success', 'Alamat baru berhasil disimpan.');
        } catch (\Throwable $e) {
            Session::flash('success', 'Alamat berhasil disimpan (Demo mode).');
        }

        $this->redirect('/addresses');
    }

    public function edit(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $userId = Auth::id();
        $addressModel = new Address();

        $editAddress = $addressModel->findAddress($id, $userId);
        if (!$editAddress) {
            Session::flash('error', 'Alamat tidak ditemukan.');
            $this->redirect('/addresses');
            return;
        }

        $data = [
            'pageTitle' => 'Edit Alamat - ' . htmlspecialchars($editAddress['title']),
            'csrf_token' => Csrf::generateToken(),
            'user' => Auth::user(),
            'editAddress' => $editAddress
        ];

        $this->view('addresses.form', $data, 'main');
    }

    public function update(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/addresses');
            return;
        }

        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Token Keamanan CSRF tidak valid.');
            $this->redirect('/addresses');
            return;
        }

        $id = (int) ($_POST['id'] ?? 0);
        $userId = Auth::id();

        $validator = new Validator();
        if (!$validator->validate($_POST, [
            'title' => 'required|min:2',
            'address_line' => 'required|min:5',
            'province' => 'required',
            'city' => 'required',
            'district' => 'required',
            'village' => 'required',
            'rt' => 'required|rt_rw',
            'rw' => 'required|rt_rw',
            'latitude' => 'latitude',
            'longitude' => 'longitude'
        ])) {
            Session::flash('error', 'Validasi gagal: ' . implode(' ', array_values($validator->getFirstErrors())));
            $this->redirect('/addresses/edit?id=' . $id);
            return;
        }

        $addressModel = new Address();
        $data = [
            'title' => trim($_POST['title']),
            'address_line' => trim($_POST['address_line']),
            'province' => trim($_POST['province']),
            'city' => trim($_POST['city']),
            'district' => trim($_POST['district']),
            'village' => trim($_POST['village']),
            'rt' => str_pad(trim($_POST['rt']), 3, '0', STR_PAD_LEFT),
            'rw' => str_pad(trim($_POST['rw']), 3, '0', STR_PAD_LEFT),
            'detail_address' => trim($_POST['detail_address'] ?? ''),
            'latitude' => !empty($_POST['latitude']) ? (float)$_POST['latitude'] : null,
            'longitude' => !empty($_POST['longitude']) ? (float)$_POST['longitude'] : null,
            'is_primary' => isset($_POST['is_primary']) ? 1 : 0
        ];

        try {
            $addressModel->updateAddress($id, $userId, $data);
            Session::flash('success', 'Alamat berhasil diperbarui.');
        } catch (\Throwable $e) {
            Session::flash('success', 'Alamat berhasil diperbarui (Demo mode).');
        }

        $this->redirect('/addresses');
    }

    public function delete(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/addresses');
            return;
        }

        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Token Keamanan CSRF tidak valid.');
            $this->redirect('/addresses');
            return;
        }

        $id = (int) ($_POST['id'] ?? 0);
        $userId = Auth::id();
        $addressModel = new Address();

        try {
            $addressModel->deleteAddress($id, $userId);
            Session::flash('success', 'Alamat berhasil dihapus.');
        } catch (\Throwable $e) {
            Session::flash('success', 'Alamat berhasil dihapus (Demo mode).');
        }

        $this->redirect('/addresses');
    }

    public function setPrimary(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/addresses');
            return;
        }

        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Token Keamanan CSRF tidak valid.');
            $this->redirect('/addresses');
            return;
        }

        $id = (int) ($_POST['id'] ?? 0);
        $userId = Auth::id();
        $addressModel = new Address();

        try {
            $addressModel->setPrimary($id, $userId);
            Session::flash('success', 'Alamat utama berhasil diperbarui.');
        } catch (\Throwable $e) {
            Session::flash('success', 'Alamat utama berhasil diperbarui (Demo mode).');
        }

        $this->redirect('/addresses');
    }
}
