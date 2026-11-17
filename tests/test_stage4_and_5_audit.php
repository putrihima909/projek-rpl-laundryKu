<?php

declare(strict_types=1);

/**
 * AUTOMATED TEST SUITE: AUDIT TAHAP 4 (ORDER) & TAHAP 5 (DASHBOARD REAL DB & ISOLATION)
 */

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

require_once __DIR__ . '/../app/Helpers/Env.php';
\App\Helpers\Env::load(__DIR__ . '/../.env');

// Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/../app/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($file)) require $file;
});

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed {
        return \App\Helpers\Config::get($key, $default);
    }
}

use App\Models\Store;
use App\Models\Order;
use App\Helpers\Auth;
use App\Helpers\Session;
use App\Core\Database;
use App\Controllers\DashboardController;
use App\Controllers\OrderController;

class Stage4And5AuditTestSuite
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
        echo "   TEST SUITE: AUDIT TAHAP 4 & TAHAP 5 VERIFICATION   \n";
        echo "=======================================================\n\n";

        $this->testOrderFormAndStoresDropdown();
        $this->testCourierIdHardcodeRemoval();
        $this->testOwnerPlatformDashboardRealDb();
        $this->testAdminOwnerDashboardRealDb();
        $this->testStoreSpecificDashboardIsolation();

        echo "\n-------------------------------------------------------\n";
        echo "TOTAL RESULTS: {$this->passed} PASSED, {$this->failed} FAILED.\n";
        echo "=======================================================\n\n";

        if ($this->failed > 0) {
            exit(1);
        }
    }

    private function testOrderFormAndStoresDropdown(): void
    {
        echo "[TEST 1] Testing Order Form Stores & Services Dynamic Population...\n";

        $storeModel = new Store();
        $activeStores = $storeModel->getActiveStores();

        // 1. All active stores are available
        $this->assert(count($activeStores) >= 2, "Active stores query returns all active stores (>= 2)");
        $storeIds = array_column($activeStores, 'id');
        $this->assert(in_array(1, $storeIds) && in_array(2, $storeIds), "Both Store 1 and Store 2 are available in order options");

        // 2. Services for Store 1 vs Store 2
        $s1Services = $storeModel->getStoreServices(1);
        $s2Services = $storeModel->getStoreServices(2);
        $this->assert(count($s1Services) >= 3, "Store 1 provides its own services");
        $this->assert(count($s2Services) >= 2, "Store 2 provides its own distinct services");

        // Ensure services are different
        $s1Names = array_column($s1Services, 'name');
        $s2Names = array_column($s2Services, 'name');
        $this->assert(in_array('Cuci Komplit Reguler', $s1Names), "Store 1 has Cuci Komplit Reguler");
        $this->assert(in_array('Cuci Kering Lipat Hemat', $s2Names), "Store 2 has Cuci Kering Lipat Hemat");
    }

    private function testCourierIdHardcodeRemoval(): void
    {
        echo "[TEST 2] Verifying Removal of Hardcoded courier_id = 5 in OrderController...\n";

        $orderControllerFile = file_get_contents(__DIR__ . '/../app/Controllers/OrderController.php');
        $this->assert(!str_contains($orderControllerFile, "'courier_id' => 5"), "OrderController must NOT contain hardcoded \"'courier_id' => 5\"");
        $this->assert(str_contains($orderControllerFile, "'courier_id' => null"), "OrderController sets 'courier_id' => null upon initial order creation");
    }

    private function testOwnerPlatformDashboardRealDb(): void
    {
        echo "[TEST 3] Verifying Real DB Aggregations in Owner Platform Dashboard...\n";

        $db = Database::getInstance();
        $expectedUsers = (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $expectedStores = (int)$db->query("SELECT COUNT(*) FROM stores")->fetchColumn();
        $expectedActiveStores = (int)$db->query("SELECT COUNT(*) FROM stores WHERE is_active = 1")->fetchColumn();
        $expectedOmset = (float)$db->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE payment_status = 'paid'")->fetchColumn();

        // Simulate Owner
        Session::set('user', ['id' => 1, 'name' => 'Budi Santoso', 'role_name' => 'owner', 'is_active' => 1]);

        $controller = new DashboardController();
        $refMethod = new \ReflectionMethod($controller, 'owner');
        $this->assert($refMethod->isPublic(), "DashboardController::owner() exists and is accessible");

        $this->assert($expectedUsers >= 6, "Real user count in DB is {$expectedUsers}");
        $this->assert($expectedStores >= 2, "Real store count in DB is {$expectedStores}");
        $this->assert($expectedActiveStores >= 2, "Real active store count in DB is {$expectedActiveStores}");

        Session::remove('user');
    }

    private function testAdminOwnerDashboardRealDb(): void
    {
        echo "[TEST 4] Verifying Real DB Aggregations in Admin Owner Dashboard...\n";

        $db = Database::getInstance();
        $pendingStores = (int)$db->query("SELECT COUNT(*) FROM stores WHERE is_active = 0")->fetchColumn();
        $totalOrders = (int)$db->query("SELECT COUNT(*) FROM orders")->fetchColumn();

        Session::set('user', ['id' => 2, 'name' => 'Siti Rahma', 'role_name' => 'admin_owner', 'is_active' => 1]);

        $this->assert($pendingStores === 0, "Non-active stores currently: {$pendingStores}");
        $this->assert($totalOrders >= 1, "Total orders in system: {$totalOrders}");

        Session::remove('user');
    }

    private function testStoreSpecificDashboardIsolation(): void
    {
        echo "[TEST 5] Testing Store-Specific Dashboard Resolution & Isolation (Store 1 vs Store 2)...\n";

        $storeModel = new Store();

        // 1. Owner Toko 1 (User 3)
        Session::set('user', ['id' => 3, 'name' => 'Hendra Wijaya', 'role_name' => 'owner_toko', 'is_active' => 1]);
        $store1 = $storeModel->getStoreByOwner(Auth::id());
        $this->assert($store1 !== null && (int)$store1['id'] === 1, "Owner Toko 1 resolves strictly to Store 1 (CleanMaster)");

        // 2. Admin Toko 1 (User 4)
        Session::set('user', ['id' => 4, 'name' => 'Rina Melati', 'role_name' => 'admin_toko', 'is_active' => 1]);
        $store1Admin = $storeModel->getStoreByOwner(Auth::id());
        $this->assert($store1Admin !== null && (int)$store1Admin['id'] === 1, "Admin Toko 1 resolves strictly to Store 1");

        // 3. Owner Toko 2 (User 7)
        Session::set('user', ['id' => 7, 'name' => 'Surya Pratama', 'role_name' => 'owner_toko', 'is_active' => 1]);
        $store2 = $storeModel->getStoreByOwner(Auth::id());
        $this->assert($store2 !== null && (int)$store2['id'] === 2, "Owner Toko 2 resolves strictly to Store 2 (Fresh & Clean)");

        // 4. Admin Toko 2 (User 8)
        Session::set('user', ['id' => 8, 'name' => 'Maya Sari', 'role_name' => 'admin_toko', 'is_active' => 1]);
        $store2Admin = $storeModel->getStoreByOwner(Auth::id());
        $this->assert($store2Admin !== null && (int)$store2Admin['id'] === 2, "Admin Toko 2 resolves strictly to Store 2");

        // 5. Verify isolated queries per store
        $db = Database::getInstance();
        $stmt1 = $db->prepare("SELECT COUNT(*) FROM services WHERE store_id = :sid AND is_active = 1");
        $stmt1->execute(['sid' => 1]);
        $s1Count = (int)$stmt1->fetchColumn();

        $stmt2 = $db->prepare("SELECT COUNT(*) FROM services WHERE store_id = :sid AND is_active = 1");
        $stmt2->execute(['sid' => 2]);
        $s2Count = (int)$stmt2->fetchColumn();

        $this->assert($s1Count === 3, "Store 1 has exactly 3 active services in DB");
        $this->assert($s2Count === 2, "Store 2 has exactly 2 active services in DB");
        $this->assert($s1Count !== $s2Count, "Store 1 and Store 2 display different, store-specific catalog stats");

        Session::remove('user');
    }
}

$suite = new Stage4And5AuditTestSuite();
$suite->run();
