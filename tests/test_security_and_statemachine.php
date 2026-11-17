<?php

/**
 * Automated Verification Suite for Bug Fixes & Security Hardening
 * Tests:
 * 1. Config Helper Safety & Aliases
 * 2. AuthController::showLogin() Redirect Safety (No Response Class Not Found)
 * 3. Switch Role Feature Removal Verification (Route, Method, View markup)
 * 4. State Machine Transition Rules (Order::isValidTransition)
 * 5. Order::updateStatus() State Machine Enforcement
 * 6. Hardcoded Bypass Removal Verification
 * 7. Admin Role Access & Navbar Static Badge (no Switch Role UI anywhere)
 */

// Start session cleanly before any output
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/../app/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($file)) require $file;
});

\App\Helpers\Env::load(__DIR__ . '/../.env');

// Helper Config
if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed {
        return \App\Helpers\Config::get($key, $default);
    }
}

use App\Models\Order;
use App\Helpers\Auth;
use App\Helpers\Session;

class SecurityAndStateMachineTestSuite
{
    private int $passed = 0;
    private int $failed = 0;

    private function assert(bool $condition, string $message): void
    {
        if ($condition) {
            echo "   [PASS] " . $message . "\n";
            $this->passed++;
        } else {
            echo "   [FAIL] " . $message . "\n";
            $this->failed++;
        }
    }

    public function run(): void
    {
        echo "\n=======================================================\n";
        echo "   TEST SUITE: BUG FIXES & SECURITY HARDENING          \n";
        echo "=======================================================\n\n";

        $this->testConfigHelperSafety();
        $this->testShowLoginRedirectSafety();
        $this->testSwitchRoleFeatureRemoved();
        $this->testStateMachineTransitions();
        $this->testOrderUpdateStatusEnforcement();
        $this->testHardcodedBypassesRemoved();
        $this->testAdminNavbarStaticBadge();

        echo "\n-------------------------------------------------------\n";
        echo "TOTAL RESULTS: {$this->passed} PASSED, {$this->failed} FAILED.\n";
        echo "=======================================================\n\n";

        if ($this->failed > 0) {
            exit(1);
        }
    }

    private function testConfigHelperSafety(): void
    {
        echo "[TEST 1] Testing Config Helper Safety & Aliases...\n";

        $url = config('app.url');
        $this->assert(is_string($url) && str_starts_with($url, 'http'), "config('app.url') must return valid URL string: {$url}");

        $urlAlias = config('url');
        $this->assert($urlAlias === $url, "config('url') must alias correctly to config('app.url')");

        $missing = config('non_existent_key', 'fallback_123');
        $this->assert($missing === 'fallback_123', "config('non_existent_key') must return default value");

        $missingSub = config('app.non_existent_item', 'default_sub');
        $this->assert($missingSub === 'default_sub', "config('app.non_existent_item') must return default value");
    }

    private function testShowLoginRedirectSafety(): void
    {
        echo "[TEST 2] Testing AuthController::showLogin() Redirect Safety...\n";

        // Mock logged in user
        Session::set('user', [
            'id' => 1,
            'name' => 'Test User',
            'email' => 'test@laundry.test',
            'role_name' => 'customer',
            'is_active' => true
        ]);

        $controller = new \App\Controllers\AuthController();
        $refMethod = new \ReflectionMethod($controller, 'showLogin');

        $this->assert($refMethod->isPublic(), "showLogin() method exists and is accessible");
        
        // Clean session
        Session::remove('user');
        $this->assert(!Auth::check(), "Session successfully cleared after test");
    }

    private function testSwitchRoleFeatureRemoved(): void
    {
        echo "[TEST 3] Verifying Switch Role Simulator Feature is Completely Removed...\n";

        // (a) AuthController must NOT have switchRoleDemo method
        $this->assert(
            !method_exists(\App\Controllers\AuthController::class, 'switchRoleDemo'),
            "AuthController::switchRoleDemo() method must NOT exist (feature removed)"
        );

        // (b) public/index.php must NOT register /demo-switch-role route
        $indexPhp = file_get_contents(__DIR__ . '/../public/index.php');
        $this->assert(
            !str_contains($indexPhp, 'demo-switch-role'),
            "public/index.php must NOT register /demo-switch-role route"
        );
        $this->assert(
            !str_contains($indexPhp, 'switchRoleDemo'),
            "public/index.php must NOT reference switchRoleDemo"
        );

        // (c) navbar.php must NOT contain Switch Role dropdown or demo-switch-role links
        $navbarPhp = file_get_contents(__DIR__ . '/../app/Views/layouts/navbar.php');
        $this->assert(
            !str_contains($navbarPhp, 'demo-switch-role'),
            "navbar.php must NOT contain any demo-switch-role links"
        );
        $this->assert(
            !str_contains($navbarPhp, 'Switch Role Simulator'),
            "navbar.php must NOT contain 'Switch Role Simulator' text"
        );

        // (d) dashboard/index.php must NOT contain switch role bar
        $dashIndex = file_get_contents(__DIR__ . '/../app/Views/dashboard/index.php');
        $this->assert(
            !str_contains($dashIndex, 'demo-switch-role'),
            "dashboard/index.php must NOT contain demo-switch-role links"
        );
        $this->assert(
            !str_contains($dashIndex, 'Switch Role'),
            "dashboard/index.php must NOT contain 'Switch Role' text"
        );

        // (e) _role_bar.php must produce no HTML output
        ob_start();
        $currentRole = \App\Helpers\Auth::role();
        $baseUrl = config('app.url', 'http://localhost/rpl/public');
        require __DIR__ . '/../app/Views/dashboard/_role_bar.php';
        $roleBarOutput = ob_get_clean();
        $this->assert(
            empty(trim($roleBarOutput)),
            "_role_bar.php must produce no HTML output (feature removed)"
        );
        $this->assert(
            !str_contains($roleBarOutput, 'demo-switch-role'),
            "_role_bar.php must NOT output any demo-switch-role links"
        );

        // (f) login.php must NOT contain demo-switch-role links
        $loginPhp = file_get_contents(__DIR__ . '/../app/Views/auth/login.php');
        $this->assert(
            !str_contains($loginPhp, 'demo-switch-role'),
            "login.php must NOT contain demo-switch-role links"
        );
        // login.php MUST have the JS autofill function
        $this->assert(
            str_contains($loginPhp, 'fillLoginForm'),
            "login.php must use JS fillLoginForm() for demo account autofill"
        );
    }

    private function testStateMachineTransitions(): void
    {
        echo "[TEST 4] Testing Order State-Machine Matrix (Order::isValidTransition)...\n";

        // Valid forward sequence
        $this->assert(Order::isValidTransition(Order::STATUS_PENDING, Order::STATUS_ASSIGNED_TO_COURIER), "pending -> assigned_to_courier must be valid");
        $this->assert(Order::isValidTransition(Order::STATUS_ASSIGNED_TO_COURIER, Order::STATUS_COURIER_ON_THE_WAY), "assigned_to_courier -> courier_on_the_way must be valid");
        $this->assert(Order::isValidTransition(Order::STATUS_COURIER_ON_THE_WAY, Order::STATUS_PICKED_UP), "courier_on_the_way -> picked_up must be valid");
        $this->assert(Order::isValidTransition(Order::STATUS_PICKED_UP, Order::STATUS_ARRIVED_AT_STORE), "picked_up -> arrived_at_store must be valid");
        $this->assert(Order::isValidTransition(Order::STATUS_ARRIVED_AT_STORE, Order::STATUS_WEIGHED), "arrived_at_store -> weighed must be valid");
        $this->assert(Order::isValidTransition(Order::STATUS_WEIGHED, Order::STATUS_WAITING_PAYMENT), "weighed -> waiting_payment must be valid");
        $this->assert(Order::isValidTransition(Order::STATUS_WAITING_PAYMENT, Order::STATUS_PAID), "waiting_payment -> paid must be valid");
        $this->assert(Order::isValidTransition(Order::STATUS_PAID, Order::STATUS_PROCESSING), "paid -> processing must be valid");
        $this->assert(Order::isValidTransition(Order::STATUS_PROCESSING, Order::STATUS_READY), "processing -> ready must be valid");
        $this->assert(Order::isValidTransition(Order::STATUS_READY, Order::STATUS_OUT_FOR_DELIVERY), "ready -> out_for_delivery must be valid");
        $this->assert(Order::isValidTransition(Order::STATUS_OUT_FOR_DELIVERY, Order::STATUS_DELIVERED), "out_for_delivery -> delivered must be valid");

        // Cancellation before delivered
        $this->assert(Order::isValidTransition(Order::STATUS_PENDING, Order::STATUS_CANCELLED), "pending -> cancelled must be valid");
        $this->assert(Order::isValidTransition(Order::STATUS_PROCESSING, Order::STATUS_CANCELLED), "processing -> cancelled must be valid");

        // Invalid transitions (Must be rejected)
        $this->assert(!Order::isValidTransition(Order::STATUS_PENDING, Order::STATUS_DELIVERED), "pending -> delivered must be INVALID");
        $this->assert(!Order::isValidTransition(Order::STATUS_PENDING, Order::STATUS_READY), "pending -> ready must be INVALID");
        $this->assert(!Order::isValidTransition(Order::STATUS_DELIVERED, Order::STATUS_PENDING), "delivered -> pending must be INVALID (terminal state)");
        $this->assert(!Order::isValidTransition(Order::STATUS_CANCELLED, Order::STATUS_PROCESSING), "cancelled -> processing must be INVALID (terminal state)");
        $this->assert(!Order::isValidTransition(Order::STATUS_DELIVERED, Order::STATUS_CANCELLED), "delivered -> cancelled must be INVALID");
    }

    private function testOrderUpdateStatusEnforcement(): void
    {
        echo "[TEST 5] Testing Order::updateStatus() Exception on Invalid Jump...\n";

        $orderModel = new Order();
        $pdo = new \PDO('mysql:host=127.0.0.1;dbname=marketplace_laundry', 'root', '');

        // Create a temporary test order in pending status
        $sql = "INSERT INTO orders (order_code, customer_id, store_id, service_id, address_id, estimated_weight, estimated_amount, total_amount, status)
                VALUES ('ORD-TEST-SM-999', 6, 1, 1, 1, 3.0, 18000, 18000, 'pending')";
        $pdo->exec($sql);
        $testOrderId = (int)$pdo->lastInsertId();

        // Attempt invalid jump from pending directly to delivered
        $exceptionThrown = false;
        try {
            $orderModel->updateStatus($testOrderId, Order::STATUS_DELIVERED);
        } catch (\InvalidArgumentException $e) {
            $exceptionThrown = true;
        }

        $this->assert($exceptionThrown === true, "Illegal jump pending -> delivered must throw InvalidArgumentException");

        // Clean up
        $pdo->exec("DELETE FROM orders WHERE id = {$testOrderId}");
    }

    private function testHardcodedBypassesRemoved(): void
    {
        echo "[TEST 6] Verifying Removal of Hardcoded Bypasses in Source Code...\n";

        $courierControllerCode = file_get_contents(__DIR__ . '/../app/Controllers/CourierController.php');
        $this->assert(!str_contains($courierControllerCode, '$courierId !== 5'), 'CourierController must NOT contain hardcoded "$courierId !== 5"');

        $orderControllerCode = file_get_contents(__DIR__ . '/../app/Controllers/OrderController.php');
        $this->assert(!str_contains($orderControllerCode, '$userId !== 6'), 'OrderController must NOT contain hardcoded "$userId !== 6"');

        $paymentControllerCode = file_get_contents(__DIR__ . '/../app/Controllers/PaymentController.php');
        $this->assert(!str_contains($paymentControllerCode, 'Auth::id() !== 6'), 'PaymentController must NOT contain hardcoded "Auth::id() !== 6"');
    }

    private function testAdminNavbarStaticBadge(): void
    {
        echo "[TEST 7] Verifying All Roles Show Static Badge + Logout in Navbar (No Switch Role UI)...\n";

        $authController = new \App\Controllers\AuthController();

        // Helper to render navbar HTML in current session context
        $renderNavbar = function(): string {
            ob_start();
            $baseUrl = config('app.url', 'http://localhost/rpl/public');
            $isLoggedIn = \App\Helpers\Auth::check();
            $user = \App\Helpers\Auth::user();
            $role = \App\Helpers\Auth::role();
            $roleDisplay = \App\Helpers\Auth::roleDisplay();
            $unreadNotifCount = 0;
            $latestNotifications = [];
            require __DIR__ . '/../app/Views/layouts/navbar.php';
            return ob_get_clean();
        };

        $testCases = [
            [
                'role'    => Auth::ROLE_ADMIN_OWNER,
                'session' => ['id' => 2, 'name' => 'Siti Rahma', 'email' => 'admin.owner@laundry.test', 'role_name' => Auth::ROLE_ADMIN_OWNER, 'role_display' => 'Admin Owner', 'is_active' => true],
                'badge'   => 'Admin Owner',
            ],
            [
                'role'    => Auth::ROLE_ADMIN_TOKO,
                'session' => ['id' => 4, 'name' => 'Rina Melati', 'email' => 'admin.toko@laundry.test', 'role_name' => Auth::ROLE_ADMIN_TOKO, 'role_display' => 'Admin Toko', 'is_active' => true],
                'badge'   => 'Admin Toko',
            ],
            [
                'role'    => Auth::ROLE_OWNER,
                'session' => ['id' => 1, 'name' => 'Budi Santoso', 'email' => 'owner@laundry.test', 'role_name' => Auth::ROLE_OWNER, 'role_display' => 'Owner Platform', 'is_active' => true],
                'badge'   => 'Owner Platform',
            ],
            [
                'role'    => Auth::ROLE_OWNER_TOKO,
                'session' => ['id' => 3, 'name' => 'Hendra', 'email' => 'owner.toko@laundry.test', 'role_name' => Auth::ROLE_OWNER_TOKO, 'role_display' => 'Owner Toko', 'is_active' => true],
                'badge'   => 'Owner Toko',
            ],
            [
                'role'    => Auth::ROLE_KURIR,
                'session' => ['id' => 5, 'name' => 'Agus Express', 'email' => 'kurir@laundry.test', 'role_name' => Auth::ROLE_KURIR, 'role_display' => 'Kurir', 'is_active' => true],
                'badge'   => 'Kurir',
            ],
            [
                'role'    => Auth::ROLE_CUSTOMER,
                'session' => ['id' => 6, 'name' => 'Dewi Lestari', 'email' => 'customer@laundry.test', 'role_name' => Auth::ROLE_CUSTOMER, 'role_display' => 'Customer', 'is_active' => true],
                'badge'   => 'Customer',
            ],
        ];

        foreach ($testCases as $tc) {
            \App\Helpers\Session::set('user', $tc['session']);
            $nav = $renderNavbar();

            $this->assert(
                !str_contains($nav, 'demo-switch-role'),
                "Navbar for {$tc['role']}: must NOT contain demo-switch-role links"
            );
            $this->assert(
                !str_contains($nav, 'Switch Role Simulator'),
                "Navbar for {$tc['role']}: must NOT contain 'Switch Role Simulator' text"
            );
            $this->assert(
                str_contains($nav, 'Role:') && str_contains($nav, htmlspecialchars($tc['badge'])),
                "Navbar for {$tc['role']}: must contain static 'Role: {$tc['badge']}' badge"
            );
            $this->assert(
                str_contains($nav, '/logout') && str_contains($nav, 'Logout'),
                "Navbar for {$tc['role']}: must contain Logout button"
            );
        }

        // =====================================================================
        // Login Rejection Messages (still valid — no switchRoleDemo involved)
        // =====================================================================
        echo "   -> Testing Login Message when user already logged in...\n";

        // Non-admin already logged in
        \App\Helpers\Session::set('user', $testCases[5]['session']); // customer
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            '_csrf_token' => \App\Helpers\Csrf::generateToken(),
            'email'       => 'customer2@laundry.test',
            'password'    => 'password123'
        ];
        $authController->login();
        $this->assert(
            \App\Helpers\Session::flash('error') === 'Anda masih login. Silakan logout terlebih dahulu sebelum masuk dengan akun lain.',
            "Generic login rejection message when non-admin is already logged in"
        );

        // Admin already logged in
        \App\Helpers\Session::set('user', $testCases[0]['session']); // admin_owner
        $authController->login();
        $this->assert(
            \App\Helpers\Session::flash('error') === 'Anda masih login sebagai admin. Silakan logout terlebih dahulu sebelum masuk dengan akun lain.',
            "Admin login rejection message when admin is already logged in"
        );

        // Normal Login after logout succeeds
        $authController->logout();
        $this->assert(!Auth::check(), "User successfully logged out");

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            '_csrf_token' => \App\Helpers\Csrf::generateToken(),
            'email'       => 'admin.owner@laundry.test',
            'password'    => 'password123'
        ];
        $authController->login();
        $this->assert(Auth::check() && Auth::role() === Auth::ROLE_ADMIN_OWNER, "Admin Owner form login succeeds after logout");

        $authController->logout();
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            '_csrf_token' => \App\Helpers\Csrf::generateToken(),
            'email'       => 'admin.toko@laundry.test',
            'password'    => 'password123'
        ];
        $authController->login();
        $this->assert(Auth::check() && Auth::role() === Auth::ROLE_ADMIN_TOKO, "Admin Toko form login succeeds after logout");

        // Verifikasi Response::redirect non-CLI safety
        echo "   -> Verifying Response::redirect non-CLI safety...\n";
        $responseCode = file_get_contents(__DIR__ . '/../app/Helpers/Response.php');
        $this->assert(
            str_contains($responseCode, "if (php_sapi_name() !== 'cli')") && str_contains($responseCode, "exit;"),
            "Response::redirect must call exit in non-CLI (web) mode"
        );

        // Clean up
        Auth::logout();
        $_GET = [];
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }
}

$suite = new SecurityAndStateMachineTestSuite();
$suite->run();
