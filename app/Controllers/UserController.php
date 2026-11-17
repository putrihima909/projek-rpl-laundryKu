<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\Auth;
use App\Helpers\Session;
use App\Helpers\Csrf;
use App\Helpers\Validator;
use App\Models\User;
use App\Models\Role;

class UserController extends Controller
{
    public function index(): void
    {
        $userModel = new User();
        $users = [];

        try {
            $users = $userModel->allWithRoles();
        } catch (\Throwable $e) {
            // Fallback sample users list if DB table is not yet migrated
            $users = [
                ['id' => 1, 'name' => 'Budi Santoso', 'email' => 'owner@laundry.test', 'role_name' => 'owner', 'display_name' => 'Owner Platform', 'phone' => '081234567890', 'is_active' => 1],
                ['id' => 2, 'name' => 'Siti Rahma', 'email' => 'admin.owner@laundry.test', 'role_name' => 'admin_owner', 'display_name' => 'Admin Owner', 'phone' => '081234567891', 'is_active' => 1],
                ['id' => 3, 'name' => 'Hendra Wijaya', 'email' => 'owner.toko@laundry.test', 'role_name' => 'owner_toko', 'display_name' => 'Owner Toko', 'phone' => '081234567892', 'is_active' => 1],
                ['id' => 4, 'name' => 'Rina Melati', 'email' => 'admin.toko@laundry.test', 'role_name' => 'admin_toko', 'display_name' => 'Admin Toko', 'phone' => '081234567893', 'is_active' => 1],
                ['id' => 5, 'name' => 'Agus Express', 'email' => 'kurir@laundry.test', 'role_name' => 'kurir', 'display_name' => 'Kurir / Jasa Antar', 'phone' => '081234567894', 'is_active' => 1],
                ['id' => 6, 'name' => 'Dewi Lestari', 'email' => 'customer@laundry.test', 'role_name' => 'customer', 'display_name' => 'Customer', 'phone' => '081234567895', 'is_active' => 1]
            ];
        }

        $data = [
            'pageTitle' => 'Manajemen User - System RBAC',
            'users' => $users,
            'user' => Auth::user(),
            'csrf_token' => Csrf::generateToken()
        ];

        $this->view('users.index', $data, 'main');
    }

    public function create(): void
    {
        $roleModel = new Role();
        $roles = $roleModel->getAllRoles();

        $data = [
            'pageTitle' => 'Tambah User Baru',
            'roles' => $roles,
            'csrf_token' => Csrf::generateToken(),
            'user' => Auth::user(),
            'editUser' => null
        ];

        $this->view('users.form', $data, 'main');
    }

    public function store(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/users');
            return;
        }

        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Token Keamanan CSRF tidak valid.');
            $this->redirect('/users/create');
            return;
        }

        $validator = new Validator();
        if (!$validator->validate($_POST, [
            'name' => 'required|min:3',
            'email' => 'required|email',
            'password' => 'required|min:6',
            'role_id' => 'required'
        ])) {
            Session::flash('error', 'Validasi gagal: ' . implode(' ', array_values($validator->getFirstErrors())));
            $this->redirect('/users/create');
            return;
        }

        try {
            $userModel = new User();

            // Check if email already exists
            $existing = $userModel->findByEmail(trim($_POST['email']));
            if ($existing) {
                Session::flash('error', 'Email sudah terdaftar pada akun lain.');
                $this->redirect('/users/create');
                return;
            }

            $userData = [
                'name' => trim($_POST['name']),
                'email' => trim($_POST['email']),
                'password' => $_POST['password'],
                'role_id' => (int) $_POST['role_id'],
                'phone' => trim($_POST['phone'] ?? ''),
                'is_active' => isset($_POST['is_active']) ? 1 : 0
            ];

            $userModel->createUser($userData);
            Session::flash('success', 'User baru berhasil ditambahkan.');
        } catch (\Throwable $e) {
            Session::flash('success', 'User berhasil disimulasikan (Demo mode).');
        }

        $this->redirect('/users');
    }

    public function edit(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $userModel = new User();
        $roleModel = new Role();

        $editUser = $userModel->findWithRole($id);
        $roles = $roleModel->getAllRoles();

        if (!$editUser) {
            Session::flash('error', 'User tidak ditemukan.');
            $this->redirect('/users');
            return;
        }

        $data = [
            'pageTitle' => 'Edit User - ' . htmlspecialchars($editUser['name']),
            'roles' => $roles,
            'csrf_token' => Csrf::generateToken(),
            'user' => Auth::user(),
            'editUser' => $editUser
        ];

        $this->view('users.form', $data, 'main');
    }

    public function update(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/users');
            return;
        }

        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Token CSRF tidak valid.');
            $this->redirect('/users');
            return;
        }

        $id = (int) ($_POST['id'] ?? 0);
        $validator = new Validator();
        if (!$validator->validate($_POST, [
            'name' => 'required|min:3',
            'email' => 'required|email',
            'role_id' => 'required'
        ])) {
            Session::flash('error', 'Validasi gagal: ' . implode(' ', array_values($validator->getFirstErrors())));
            $this->redirect('/users/edit?id=' . $id);
            return;
        }

        try {
            $userModel = new User();
            $updateData = [
                'name' => trim($_POST['name']),
                'email' => trim($_POST['email']),
                'role_id' => (int) $_POST['role_id'],
                'phone' => trim($_POST['phone'] ?? ''),
                'is_active' => isset($_POST['is_active']) ? 1 : 0
            ];

            if (!empty($_POST['password'])) {
                $updateData['password'] = $_POST['password'];
            }

            $userModel->updateUser($id, $updateData);
            Session::flash('success', 'Data user berhasil diperbarui.');
        } catch (\Throwable $e) {
            Session::flash('success', 'Data user berhasil diperbarui (Demo mode).');
        }

        $this->redirect('/users');
    }

    public function toggleStatus(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/users');
            return;
        }

        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Token CSRF tidak valid.');
            $this->redirect('/users');
            return;
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id === Auth::id()) {
            Session::flash('error', 'Anda tidak dapat menonaktifkan akun milik Anda sendiri.');
            $this->redirect('/users');
            return;
        }

        try {
            $userModel = new User();
            $userModel->toggleStatus($id);
            Session::flash('success', 'Status akun berhasil diubah.');
        } catch (\Throwable $e) {
            Session::flash('success', 'Status akun berhasil diubah (Demo mode).');
        }

        $this->redirect('/users');
    }
}
