<?php

/**
 * LaundryKu - Front Controller
 * Modules: Auth, RBAC, Profile, Address Management, Google OAuth, Order Laundry & Modul Kurir Pickup
 */

// 1. Register PSR-4 Autoloader for App namespace
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/../app/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// 2. Load Environment Variables (.env)
\App\Helpers\Env::load(__DIR__ . '/../.env');

// 3. Helper Config Function
if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed {
        return \App\Helpers\Config::get($key, $default);
    }
}

// 4. Global Error & Exception Handlers with Logging
use App\Helpers\Logger;

if (config('app.debug', false)) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}

set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    Logger::error("PHP Warning/Notice: {$message}", [
        'file' => $file,
        'line' => $line,
        'severity' => $severity
    ]);
    return false;
});

set_exception_handler(function (\Throwable $e) {
    Logger::error("Uncaught Exception: " . $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
        'uri' => $_SERVER['REQUEST_URI'] ?? '',
        'method' => $_SERVER['REQUEST_METHOD'] ?? ''
    ]);

    $isDebug = (bool) \config('app.debug', false);

    if (php_sapi_name() === 'cli') {
        echo "Fatal Error: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
        exit(1);
    }

    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
              || str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/')
              || str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/webhook/');

    http_response_code(500);

    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status' => 'error',
            'success' => false,
            'message' => $isDebug ? $e->getMessage() : 'Terjadi kesalahan internal pada server.',
            'error' => $isDebug ? $e->getMessage() : 'Internal Server Error'
        ]);
        exit;
    }

    if ($isDebug) {
        echo "<h1>Internal Server Error (Debug Mode)</h1>";
        echo "<p><strong>Message:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<p><strong>File:</strong> " . htmlspecialchars($e->getFile()) . ":" . $e->getLine() . "</p>";
        echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    } else {
        $view500 = __DIR__ . '/../app/Views/errors/500.php';
        if (file_exists($view500)) {
            require $view500;
        } else {
            echo "<h1>500 Internal Server Error</h1><p>Terjadi kesalahan sistem. Silakan coba beberapa saat lagi.</p>";
        }
    }
    exit;
});

// 5. Set Consistent Application Timezone
date_default_timezone_set(config('app.timezone', 'Asia/Jakarta'));

// 6. Initialize Router & Define Routes
use App\Core\Router;
use App\Core\AuthMiddleware;
use App\Helpers\Auth;
use App\Controllers\HomeController;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\UserController;
use App\Controllers\ProfileController;
use App\Controllers\AddressController;
use App\Controllers\OrderController;
use App\Controllers\CourierController;
use App\Controllers\PaymentController;
use App\Controllers\StoreController;
use App\Controllers\ServiceController;
use App\Controllers\RefundController;
use App\Controllers\NotificationController;
use App\Controllers\AiChatController;
use App\Controllers\WhatsAppController;

$router = new Router();


// Public Routes
$router->get('/', [HomeController::class, 'index']);
$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->get('/logout', [AuthController::class, 'logout']);

// Store Management Routes (Owner & Admin Owner — semua toko)
$router->get('/stores/manage', [StoreController::class, 'adminIndex'], [new AuthMiddleware([Auth::ROLE_OWNER, Auth::ROLE_ADMIN_OWNER])]);
$router->get('/stores/create', [StoreController::class, 'create'], [new AuthMiddleware([Auth::ROLE_OWNER, Auth::ROLE_ADMIN_OWNER])]);
$router->post('/stores/store', [StoreController::class, 'store'], [new AuthMiddleware([Auth::ROLE_OWNER, Auth::ROLE_ADMIN_OWNER])]);
$router->get('/stores/edit', [StoreController::class, 'edit'], [new AuthMiddleware([Auth::ROLE_OWNER, Auth::ROLE_ADMIN_OWNER])]);
$router->post('/stores/update', [StoreController::class, 'update'], [new AuthMiddleware([Auth::ROLE_OWNER, Auth::ROLE_ADMIN_OWNER])]);
$router->post('/stores/toggle-status', [StoreController::class, 'toggleStatus'], [new AuthMiddleware([Auth::ROLE_OWNER, Auth::ROLE_ADMIN_OWNER])]);

// Marketplace — Public (no auth required)
$router->get('/stores', [StoreController::class, 'index']);
$router->get('/stores/detail', [StoreController::class, 'show']);
$router->get('/stores/{id}', [StoreController::class, 'show']);

// Google OAuth 2.0 Routes
$router->get('/auth/google', [AuthController::class, 'redirectToGoogle']);
$router->get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback']);

// Phone Verification (OTP WhatsApp) Routes
$router->get('/auth/verify', [AuthController::class, 'showVerifyPhone'], [new AuthMiddleware()]);
$router->post('/auth/send-otp', [AuthController::class, 'sendOtp'], [new AuthMiddleware()]);
$router->post('/auth/verify-otp', [AuthController::class, 'verifyOtp'], [new AuthMiddleware()]);

// Protected General Dashboard Router
$router->get('/dashboard', [DashboardController::class, 'index'], [new AuthMiddleware()]);

// Protected Role-Specific Dashboards (Server-Side RBAC Enforcement)
$router->get('/dashboard/owner', [DashboardController::class, 'owner'], [new AuthMiddleware([Auth::ROLE_OWNER])]);
$router->get('/dashboard/admin-owner', [DashboardController::class, 'adminOwner'], [new AuthMiddleware([Auth::ROLE_ADMIN_OWNER])]);
$router->get('/dashboard/owner-toko', [DashboardController::class, 'ownerToko'], [new AuthMiddleware([Auth::ROLE_OWNER_TOKO])]);
$router->get('/dashboard/admin-toko', [DashboardController::class, 'adminToko'], [new AuthMiddleware([Auth::ROLE_ADMIN_TOKO])]);
$router->get('/dashboard/kurir', [CourierController::class, 'dashboard'], [new AuthMiddleware([Auth::ROLE_KURIR])]);
$router->get('/dashboard/customer', [DashboardController::class, 'customer'], [new AuthMiddleware([Auth::ROLE_CUSTOMER])]);
$router->get('/dashboard/omzet-detail', [DashboardController::class, 'omzetDetail'], [new AuthMiddleware([Auth::ROLE_OWNER, Auth::ROLE_ADMIN_OWNER, Auth::ROLE_OWNER_TOKO, Auth::ROLE_ADMIN_TOKO])]);

// User Management Routes (Restricted to Owner & Admin Owner)
$router->get('/users', [UserController::class, 'index'], [new AuthMiddleware([Auth::ROLE_OWNER, Auth::ROLE_ADMIN_OWNER])]);
$router->get('/users/create', [UserController::class, 'create'], [new AuthMiddleware([Auth::ROLE_OWNER, Auth::ROLE_ADMIN_OWNER])]);
$router->post('/users/store', [UserController::class, 'store'], [new AuthMiddleware([Auth::ROLE_OWNER, Auth::ROLE_ADMIN_OWNER])]);
$router->get('/users/edit', [UserController::class, 'edit'], [new AuthMiddleware([Auth::ROLE_OWNER, Auth::ROLE_ADMIN_OWNER])]);
$router->post('/users/update', [UserController::class, 'update'], [new AuthMiddleware([Auth::ROLE_OWNER, Auth::ROLE_ADMIN_OWNER])]);
$router->post('/users/toggle-status', [UserController::class, 'toggleStatus'], [new AuthMiddleware([Auth::ROLE_OWNER, Auth::ROLE_ADMIN_OWNER])]);

// Service Management Routes (Owner Toko & Admin Toko — hanya toko milik sendiri)
$router->get('/services', [ServiceController::class, 'index'], [new AuthMiddleware([Auth::ROLE_OWNER_TOKO, Auth::ROLE_ADMIN_TOKO])]);
$router->get('/services/create', [ServiceController::class, 'create'], [new AuthMiddleware([Auth::ROLE_OWNER_TOKO, Auth::ROLE_ADMIN_TOKO])]);
$router->post('/services/store', [ServiceController::class, 'store'], [new AuthMiddleware([Auth::ROLE_OWNER_TOKO, Auth::ROLE_ADMIN_TOKO])]);
$router->get('/services/edit', [ServiceController::class, 'edit'], [new AuthMiddleware([Auth::ROLE_OWNER_TOKO, Auth::ROLE_ADMIN_TOKO])]);
$router->post('/services/update', [ServiceController::class, 'update'], [new AuthMiddleware([Auth::ROLE_OWNER_TOKO, Auth::ROLE_ADMIN_TOKO])]);
$router->post('/services/destroy', [ServiceController::class, 'destroy'], [new AuthMiddleware([Auth::ROLE_OWNER_TOKO, Auth::ROLE_ADMIN_TOKO])]);

// Customer Profile Routes
$router->get('/profile', [ProfileController::class, 'showProfile'], [new AuthMiddleware()]);
$router->get('/profile/edit', [ProfileController::class, 'editProfile'], [new AuthMiddleware()]);
$router->post('/profile/update', [ProfileController::class, 'updateProfile'], [new AuthMiddleware()]);

// Address Management Routes
$router->get('/addresses', [AddressController::class, 'index'], [new AuthMiddleware()]);
$router->get('/addresses/create', [AddressController::class, 'create'], [new AuthMiddleware()]);
$router->post('/addresses/store', [AddressController::class, 'store'], [new AuthMiddleware()]);
$router->get('/addresses/edit', [AddressController::class, 'edit'], [new AuthMiddleware()]);
$router->post('/addresses/update', [AddressController::class, 'update'], [new AuthMiddleware()]);
$router->post('/addresses/delete', [AddressController::class, 'delete'], [new AuthMiddleware()]);
$router->post('/addresses/set-primary', [AddressController::class, 'setPrimary'], [new AuthMiddleware()]);

// Order Laundry Routes
$router->get('/orders/create', [OrderController::class, 'createForm'], [new AuthMiddleware()]);
$router->post('/orders/store', [OrderController::class, 'storeOrder'], [new AuthMiddleware()]);
$router->get('/orders', [OrderController::class, 'customerOrders'], [new AuthMiddleware()]);
$router->get('/store/orders', [OrderController::class, 'storeOrders'], [new AuthMiddleware([Auth::ROLE_OWNER_TOKO, Auth::ROLE_ADMIN_TOKO, Auth::ROLE_OWNER, Auth::ROLE_ADMIN_OWNER])]);
$router->get('/store/orders/ready', [OrderController::class, 'readyOrders'], [new AuthMiddleware([Auth::ROLE_OWNER_TOKO, Auth::ROLE_ADMIN_TOKO, Auth::ROLE_OWNER, Auth::ROLE_ADMIN_OWNER])]);
$router->post('/store/orders/mark-picked-up', [OrderController::class, 'markPickedUp'], [new AuthMiddleware([Auth::ROLE_OWNER_TOKO, Auth::ROLE_ADMIN_TOKO, Auth::ROLE_OWNER, Auth::ROLE_ADMIN_OWNER])]);
$router->post('/store/orders/assign-delivery-courier', [OrderController::class, 'assignDeliveryCourier'], [new AuthMiddleware([Auth::ROLE_OWNER_TOKO, Auth::ROLE_ADMIN_TOKO, Auth::ROLE_OWNER, Auth::ROLE_ADMIN_OWNER])]);
$router->post('/store/orders/start-delivery', [OrderController::class, 'startDelivery'], [new AuthMiddleware([Auth::ROLE_OWNER_TOKO, Auth::ROLE_ADMIN_TOKO, Auth::ROLE_OWNER, Auth::ROLE_ADMIN_OWNER])]);
$router->get('/courier/orders', [OrderController::class, 'courierOrders'], [new AuthMiddleware([Auth::ROLE_KURIR, Auth::ROLE_OWNER, Auth::ROLE_ADMIN_OWNER])]);
$router->get('/orders/detail', [OrderController::class, 'showDetail'], [new AuthMiddleware()]);
$router->get('/orders/{id}/courier-location', [OrderController::class, 'courierLocationJson'], [new AuthMiddleware()]);
$router->post('/orders/update-weighing', [OrderController::class, 'updateWeighing'], [new AuthMiddleware([Auth::ROLE_OWNER_TOKO, Auth::ROLE_ADMIN_TOKO, Auth::ROLE_OWNER, Auth::ROLE_ADMIN_OWNER])]);
// SECURITY: Customer TIDAK boleh mengakses endpoint update-status.
// Customer hanya boleh cancel order melalui flow khusus (jika diimplementasikan).
// Semua role yang berwenang mengubah status order: Owner, Admin, Kurir.
$router->post('/orders/update-status', [OrderController::class, 'updateStatus'], [new AuthMiddleware([Auth::ROLE_OWNER, Auth::ROLE_ADMIN_OWNER, Auth::ROLE_OWNER_TOKO, Auth::ROLE_ADMIN_TOKO, Auth::ROLE_KURIR])]);
$router->post('/orders/mock-payment', [OrderController::class, 'mockPayment'], [new AuthMiddleware()]);

// Modul Kurir / Pickup Flow Routes
$router->post('/courier/duty-status', [CourierController::class, 'updateDutyStatus'], [new AuthMiddleware([Auth::ROLE_KURIR, Auth::ROLE_OWNER])]);
$router->post('/courier/location', [CourierController::class, 'updateLocation'], [new AuthMiddleware([Auth::ROLE_KURIR, Auth::ROLE_OWNER])]);
$router->post('/courier/assign-order', [OrderController::class, 'assignCourier'], [new AuthMiddleware([Auth::ROLE_OWNER_TOKO, Auth::ROLE_ADMIN_TOKO, Auth::ROLE_OWNER, Auth::ROLE_ADMIN_OWNER])]);
$router->post('/courier/update-pickup-status', [CourierController::class, 'updatePickupStatus'], [new AuthMiddleware([Auth::ROLE_KURIR, Auth::ROLE_OWNER])]);

// Modul Online Payment (QRIS & Webhook)
$router->get('/orders/payment', [PaymentController::class, 'showQris'], [new AuthMiddleware()]);
$router->get('/api/payment/status', [PaymentController::class, 'checkStatusAjax'], [new AuthMiddleware()]);
$router->post('/webhook/payment', [PaymentController::class, 'handleWebhook']);
$router->post('/payment/mock-gateway/simulate', [PaymentController::class, 'simulateWebhook']);

// Modul Refund
$router->get('/refunds', [RefundController::class, 'index'], [new AuthMiddleware([Auth::ROLE_OWNER, Auth::ROLE_ADMIN_OWNER, Auth::ROLE_OWNER_TOKO, Auth::ROLE_ADMIN_TOKO])]);
$router->post('/refunds/initiate', [RefundController::class, 'initiate'], [new AuthMiddleware([Auth::ROLE_OWNER, Auth::ROLE_ADMIN_OWNER, Auth::ROLE_OWNER_TOKO, Auth::ROLE_ADMIN_TOKO])]);
$router->post('/refunds/confirm', [RefundController::class, 'confirm'], [new AuthMiddleware([Auth::ROLE_OWNER, Auth::ROLE_ADMIN_OWNER, Auth::ROLE_OWNER_TOKO, Auth::ROLE_ADMIN_TOKO])]);
$router->get('/api/refunds', [RefundController::class, 'listForOrder'], [new AuthMiddleware()]);

// Modul Notifikasi
$router->get('/notifications', [NotificationController::class, 'index'], [new AuthMiddleware()]);
$router->get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
$router->post('/notifications/read', [NotificationController::class, 'markRead'], [new AuthMiddleware()]);
$router->post('/notifications/mark-all-read', [NotificationController::class, 'markAllRead'], [new AuthMiddleware()]);
$router->post('/notifications/whatsapp-preference', [NotificationController::class, 'updateWhatsAppPreference'], [new AuthMiddleware()]);
$router->get('/notifications/open', [NotificationController::class, 'openAndRedirect'], [new AuthMiddleware()]);

// Modul AI Chat Assistant
$router->post('/api/ai/chat', [AiChatController::class, 'chat'], [new AuthMiddleware()]);
$router->get('/api/ai/history', [AiChatController::class, 'history'], [new AuthMiddleware()]);
$router->post('/api/ai/clear', [AiChatController::class, 'clear'], [new AuthMiddleware()]);

// Modul Pengaturan AI / Konfigurasi API Chatbot (Owner, Admin Owner, Admin Toko, Owner Toko)
$aiRoles = [Auth::ROLE_OWNER, Auth::ROLE_ADMIN_OWNER, Auth::ROLE_ADMIN_TOKO, Auth::ROLE_OWNER_TOKO];
$router->get('/admin/ai-settings', [\App\Controllers\AiSettingsController::class, 'index'], [new AuthMiddleware($aiRoles)]);
$router->post('/admin/ai-settings', [\App\Controllers\AiSettingsController::class, 'save'], [new AuthMiddleware($aiRoles)]);
$router->post('/admin/ai-settings/test-connection', [\App\Controllers\AiSettingsController::class, 'testConnection'], [new AuthMiddleware($aiRoles)]);
$router->get('/admin/store-ai-settings', [\App\Controllers\AiSettingsController::class, 'index'], [new AuthMiddleware($aiRoles)]);
$router->post('/admin/store-ai-settings', [\App\Controllers\AiSettingsController::class, 'save'], [new AuthMiddleware($aiRoles)]);
$router->post('/admin/store-ai-settings/test-connection', [\App\Controllers\AiSettingsController::class, 'testConnection'], [new AuthMiddleware($aiRoles)]);
$router->get('/platform/settings', [\App\Controllers\AiSettingsController::class, 'index'], [new AuthMiddleware($aiRoles)]);
$router->post('/platform/settings', [\App\Controllers\AiSettingsController::class, 'save'], [new AuthMiddleware($aiRoles)]);
$router->post('/platform/settings/test-connection', [\App\Controllers\AiSettingsController::class, 'testConnection'], [new AuthMiddleware($aiRoles)]);
$router->post('/admin/ai-settings/add-model', [\App\Controllers\AiSettingsController::class, 'addModel'], [new AuthMiddleware($aiRoles)]);
$router->post('/admin/ai-settings/activate-model', [\App\Controllers\AiSettingsController::class, 'activateModel'], [new AuthMiddleware($aiRoles)]);
$router->post('/admin/ai-settings/delete-model', [\App\Controllers\AiSettingsController::class, 'deleteModel'], [new AuthMiddleware($aiRoles)]);
$router->post('/admin/store-ai-settings/add-model', [\App\Controllers\AiSettingsController::class, 'addModel'], [new AuthMiddleware($aiRoles)]);
$router->post('/admin/store-ai-settings/activate-model', [\App\Controllers\AiSettingsController::class, 'activateModel'], [new AuthMiddleware($aiRoles)]);
$router->post('/admin/store-ai-settings/delete-model', [\App\Controllers\AiSettingsController::class, 'deleteModel'], [new AuthMiddleware($aiRoles)]);
$router->post('/platform/settings/add-model', [\App\Controllers\AiSettingsController::class, 'addModel'], [new AuthMiddleware($aiRoles)]);
$router->post('/platform/settings/activate-model', [\App\Controllers\AiSettingsController::class, 'activateModel'], [new AuthMiddleware($aiRoles)]);
$router->post('/platform/settings/delete-model', [\App\Controllers\AiSettingsController::class, 'deleteModel'], [new AuthMiddleware($aiRoles)]);

// Endpoint Lihat API Key Terproteksi Password (POST only)
$router->post('/konfigurasi-ai/lihat-key', [\App\Controllers\AiSettingsController::class, 'revealKey'], [new AuthMiddleware()]);
$router->post('/admin/ai-settings/reveal-key', [\App\Controllers\AiSettingsController::class, 'revealKey'], [new AuthMiddleware()]);
$router->get('/konfigurasi-ai/lihat-key', function () {
    header('HTTP/1.1 405 Method Not Allowed');
    header('Allow: POST');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'message' => 'Metode GET tidak diizinkan. Endpoint hanya melayani request POST.']);
    exit;
});

// Modul WhatsApp Gateway Fonnte & Cloud API
$router->get('/admin/whatsapp', [WhatsAppController::class, 'index'], [new AuthMiddleware([Auth::ROLE_OWNER, Auth::ROLE_ADMIN_OWNER, Auth::ROLE_ADMIN_TOKO])]);
$router->post('/admin/whatsapp/settings', [WhatsAppController::class, 'saveSettings'], [new AuthMiddleware([Auth::ROLE_OWNER, Auth::ROLE_ADMIN_OWNER])]);
$router->post('/admin/whatsapp/send-omzet-report', [WhatsAppController::class, 'sendOmzetReport'], [new AuthMiddleware([Auth::ROLE_OWNER, Auth::ROLE_ADMIN_OWNER, Auth::ROLE_ADMIN_TOKO])]);
$router->post('/admin/whatsapp/test-send', [WhatsAppController::class, 'testSend'], [new AuthMiddleware([Auth::ROLE_OWNER, Auth::ROLE_ADMIN_OWNER, Auth::ROLE_ADMIN_TOKO])]);
$router->post('/admin/whatsapp/check-device', [WhatsAppController::class, 'checkDevice'], [new AuthMiddleware([Auth::ROLE_OWNER, Auth::ROLE_ADMIN_OWNER, Auth::ROLE_ADMIN_TOKO])]);
$router->post('/admin/whatsapp/retry', [WhatsAppController::class, 'retry'], [new AuthMiddleware([Auth::ROLE_OWNER, Auth::ROLE_ADMIN_OWNER, Auth::ROLE_ADMIN_TOKO])]);

// Modul Pusat Konfigurasi Sistem & Integrasi (Owner, Admin Owner, Admin Toko)
$router->get('/admin/system-config', [\App\Controllers\SystemConfigController::class, 'index'], [new AuthMiddleware([Auth::ROLE_OWNER, Auth::ROLE_ADMIN_OWNER, Auth::ROLE_ADMIN_TOKO])]);
$router->post('/admin/system-config/whatsapp', [\App\Controllers\SystemConfigController::class, 'saveWhatsApp'], [new AuthMiddleware([Auth::ROLE_OWNER, Auth::ROLE_ADMIN_OWNER, Auth::ROLE_ADMIN_TOKO])]);
$router->post('/admin/system-config/whatsapp/test-send', [\App\Controllers\SystemConfigController::class, 'testSendWhatsApp'], [new AuthMiddleware([Auth::ROLE_OWNER, Auth::ROLE_ADMIN_OWNER, Auth::ROLE_ADMIN_TOKO])]);
$router->post('/admin/system-config/whatsapp/check-device', [\App\Controllers\SystemConfigController::class, 'checkDeviceWhatsApp'], [new AuthMiddleware([Auth::ROLE_OWNER, Auth::ROLE_ADMIN_OWNER, Auth::ROLE_ADMIN_TOKO])]);

// Modul Chat Bot WhatsApp Toko (Admin Toko, Owner, Admin Owner)
$router->get('/admin/whatsapp-bot', [\App\Controllers\WhatsAppBotController::class, 'index'], [new AuthMiddleware([Auth::ROLE_ADMIN_TOKO, Auth::ROLE_OWNER, Auth::ROLE_ADMIN_OWNER])]);
$router->post('/admin/whatsapp-bot/settings', [\App\Controllers\WhatsAppBotController::class, 'saveSettings'], [new AuthMiddleware([Auth::ROLE_ADMIN_TOKO, Auth::ROLE_OWNER, Auth::ROLE_ADMIN_OWNER])]);
$router->post('/admin/whatsapp-bot/responses/store', [\App\Controllers\WhatsAppBotController::class, 'store'], [new AuthMiddleware([Auth::ROLE_ADMIN_TOKO, Auth::ROLE_OWNER, Auth::ROLE_ADMIN_OWNER])]);
$router->post('/admin/whatsapp-bot/responses/update', [\App\Controllers\WhatsAppBotController::class, 'update'], [new AuthMiddleware([Auth::ROLE_ADMIN_TOKO, Auth::ROLE_OWNER, Auth::ROLE_ADMIN_OWNER])]);
$router->post('/admin/whatsapp-bot/responses/delete', [\App\Controllers\WhatsAppBotController::class, 'delete'], [new AuthMiddleware([Auth::ROLE_ADMIN_TOKO, Auth::ROLE_OWNER, Auth::ROLE_ADMIN_OWNER])]);
$router->post('/admin/whatsapp-bot/responses/toggle', [\App\Controllers\WhatsAppBotController::class, 'toggle'], [new AuthMiddleware([Auth::ROLE_ADMIN_TOKO, Auth::ROLE_OWNER, Auth::ROLE_ADMIN_OWNER])]);

// Webhook Fonnte (Incoming Message, Publik tanpa AuthMiddleware)
$router->post('/webhook/fonnte', [\App\Controllers\WhatsAppWebhookController::class, 'handle']);

// 6. Dispatch Request
$router->dispatch($_SERVER['REQUEST_URI'] ?? '/', $_SERVER['REQUEST_METHOD'] ?? 'GET');
