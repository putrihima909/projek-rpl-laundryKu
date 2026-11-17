<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\Auth;
use App\Helpers\Session;
use App\Helpers\Csrf;
use App\Helpers\Validator;
use App\Models\User;
use App\Models\Address;

class ProfileController extends Controller
{
    public function showProfile(): void
    {
        $userId = Auth::id();
        $userModel = new User();
        $addressModel = new Address();

        $userData = $userModel->findWithRole($userId) ?? Auth::user();
        $primaryAddress = $addressModel->getPrimaryAddress($userId);
        $allAddresses = $addressModel->getUserAddresses($userId);

        $data = [
            'pageTitle' => 'Profil Saya - Customer',
            'user' => $userData,
            'primaryAddress' => $primaryAddress,
            'addressesCount' => count($allAddresses)
        ];

        $this->view('profile.index', $data, 'main');
    }

    public function editProfile(): void
    {
        $userId = Auth::id();
        $userModel = new User();
        $userData = $userModel->findWithRole($userId) ?? Auth::user();

        $data = [
            'pageTitle' => 'Edit Profil Saya',
            'user' => $userData,
            'csrf_token' => Csrf::generateToken()
        ];

        $this->view('profile.form', $data, 'main');
    }

    public function updateProfile(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/profile');
            return;
        }

        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Token Keamanan CSRF tidak valid.');
            $this->redirect('/profile/edit');
            return;
        }

        $validator = new Validator();
        if (!$validator->validate($_POST, [
            'name' => 'required|min:3',
            'phone' => 'phone_id'
        ])) {
            Session::flash('error', 'Validasi gagal: ' . implode(' ', array_values($validator->getFirstErrors())));
            $this->redirect('/profile/edit');
            return;
        }

        $userId = Auth::id();
        $userModel = new User();

        $updateData = [
            'name' => trim($_POST['name']),
            'phone' => trim($_POST['phone'] ?? '')
        ];

        if (!empty($_POST['password'])) {
            if (strlen($_POST['password']) < 6) {
                Session::flash('error', 'Password minimal 6 karakter.');
                $this->redirect('/profile/edit');
                return;
            }
            $updateData['password'] = $_POST['password'];
        }

        try {
            $userModel->updateUser($userId, $updateData);
        } catch (\Throwable $e) {
            // Proceed demo update
        }

        // Refresh session
        $updatedUser = $userModel->findWithRole($userId) ?? Auth::user();
        $updatedUser['name'] = $updateData['name'];
        $updatedUser['phone'] = $updateData['phone'];
        Auth::login($updatedUser);

        Session::flash('success', 'Profil Anda berhasil diperbarui.');
        $this->redirect('/profile');
    }
}
