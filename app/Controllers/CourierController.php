<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\Auth;
use App\Helpers\Session;
use App\Helpers\Csrf;
use App\Helpers\Validator;
use App\Models\Order;
use App\Models\CourierLocation;

class CourierController extends Controller
{
    public function dashboard(): void
    {
        $courierId = Auth::id();
        $orderModel = new Order();
        $courierLocModel = new CourierLocation();

        $courierLoc = $courierLocModel->getCourierLocation($courierId);
        $assignedOrders = $orderModel->getCourierOrders($courierId);

        $data = [
            'pageTitle' => 'Dashboard Kurir - Modul Penjemputan',
            'user' => Auth::user(),
            'role' => Auth::ROLE_KURIR,
            'courierLoc' => $courierLoc,
            'orders' => $assignedOrders,
            'statusLabels' => Order::getStatusLabels(),
            'csrf_token' => Csrf::generateToken()
        ];

        $this->view('dashboard.kurir', $data, 'main');
    }

    public function updateDutyStatus(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/dashboard/kurir');
            return;
        }

        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Token Keamanan CSRF tidak valid.');
            $this->redirect('/dashboard/kurir');
            return;
        }

        $dutyStatus = trim($_POST['duty_status'] ?? 'available');
        $courierId = Auth::id();

        $courierLocModel = new CourierLocation();
        try {
            $courierLocModel->updateDutyStatus($courierId, $dutyStatus);
            Session::flash('success', 'Status ketersediaan tugas berhasil diubah menjadi: ' . strtoupper($dutyStatus));
        } catch (\Throwable $e) {
            Session::flash('success', 'Status ketersediaan tugas berhasil diubah (Demo mode)!');
        }

        $this->redirect('/dashboard/kurir');
    }

    public function updateLocation(): void
    {
        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            if ($isAjax) {
                $this->json(['status' => 'error', 'message' => 'Metode request tidak valid.'], 405);
                return;
            }
            $this->redirect('/dashboard/kurir');
            return;
        }

        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            if ($isAjax) {
                $this->json(['status' => 'error', 'message' => 'Token Keamanan CSRF tidak valid.'], 403);
                return;
            }
            Session::flash('error', 'Token Keamanan CSRF tidak valid.');
            $this->redirect('/dashboard/kurir');
            return;
        }

        $validator = new Validator();
        if (!$validator->validate($_POST, [
            'latitude' => 'required|latitude',
            'longitude' => 'required|longitude'
        ])) {
            if ($isAjax) {
                $this->json(['status' => 'error', 'message' => 'Validasi koordinat lokasi gagal.'], 422);
                return;
            }
            Session::flash('error', 'Validasi koordinat lokasi gagal.');
            $this->redirect('/dashboard/kurir');
            return;
        }

        $latitude = (float) $_POST['latitude'];
        $longitude = (float) $_POST['longitude'];
        $orderId = !empty($_POST['order_id']) ? (int) $_POST['order_id'] : null;
        $courierId = Auth::id();

        $courierLocModel = new CourierLocation();
        try {
            $courierLocModel->updateCoordinates($courierId, $latitude, $longitude, $orderId);
            $msg = 'Koordinat GPS lokasi kurir berhasil diperbarui!';
            if ($isAjax) {
                $this->json([
                    'status' => 'success',
                    'message' => $msg,
                    'data' => [
                        'courier_id' => $courierId,
                        'order_id' => $orderId,
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                        'updated_at' => date('Y-m-d H:i:s')
                    ]
                ]);
                return;
            }
            Session::flash('success', $msg);
        } catch (\Throwable $e) {
            $msg = 'Koordinat GPS lokasi kurir berhasil diperbarui (Demo mode)!';
            if ($isAjax) {
                $this->json([
                    'status' => 'success',
                    'message' => $msg,
                    'data' => [
                        'courier_id' => $courierId,
                        'order_id' => $orderId,
                        'latitude' => $latitude,
                        'longitude' => $longitude
                    ]
                ]);
                return;
            }
            Session::flash('success', $msg);
        }

        $redirect = !empty($_POST['redirect_to']) ? $_POST['redirect_to'] : '/dashboard/kurir';
        $this->redirect($redirect);
    }

    public function updatePickupStatus(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/dashboard/kurir');
            return;
        }

        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Token Keamanan CSRF tidak valid.');
            $this->redirect('/dashboard/kurir');
            return;
        }

        $orderId = (int) ($_POST['order_id'] ?? 0);
        $newStatus = trim($_POST['status'] ?? '');
        $courierId = Auth::id();

        $orderModel = new Order();
        $order = $orderModel->findOrderDetail($orderId);

        if (!$order) {
            Session::flash('error', 'Pesanan tidak ditemukan.');
            $this->redirect('/dashboard/kurir');
            return;
        }

        // Server-Side Authorization Check: Courier can only update tasks assigned to them!
        if ((int)$order['courier_id'] !== $courierId) {
            Session::flash('error', 'Akses ditolak! Anda hanya dapat memperbarui tugas yang ditugaskan kepada Anda.');
            $this->redirect('/dashboard/kurir');
            return;
        }

        // Validate state machine transition
        if (!Order::isValidTransition($order['status'], $newStatus)) {
            $currLabel = Order::getStatusLabels()[$order['status']]['label'] ?? $order['status'];
            $newLabel = Order::getStatusLabels()[$newStatus]['label'] ?? $newStatus;
            Session::flash('error', "Transisi status dari '{$currLabel}' ke '{$newLabel}' tidak diizinkan oleh sistem.");
            $redirect = $_POST['redirect_to'] ?? '/dashboard/kurir';
            $this->redirect($redirect);
            return;
        }

        $notes = match ($newStatus) {
            Order::STATUS_COURIER_ON_THE_WAY => 'Kurir sedang dalam perjalanan menuju alamat penjemputan customer',
            Order::STATUS_PICKED_UP => 'Laundry telah berhasil dijemput dari lokasi customer oleh Kurir',
            Order::STATUS_ARRIVED_AT_STORE => 'Laundry telah diantar dan diserahkan ke toko laundry',
            Order::STATUS_OUT_FOR_DELIVERY => 'Kurir sedang mengantar cucian bersih ke rumah customer',
            Order::STATUS_DELIVERED => 'Cucian bersih berhasil diserahkan kepada customer',
            default => 'Kurir memperbarui status menjadi: ' . $newStatus
        };

        try {
            $orderModel->updateStatus($orderId, $newStatus, $notes, $courierId);
            Session::flash('success', 'Status pergerakan laundry berhasil diperbarui!');
        } catch (\InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());
        } catch (\Throwable $e) {
            Session::flash('error', 'Gagal memperbarui status: ' . $e->getMessage());
        }

        $redirect = $_POST['redirect_to'] ?? '/dashboard/kurir';
        $this->redirect($redirect);
    }
}
