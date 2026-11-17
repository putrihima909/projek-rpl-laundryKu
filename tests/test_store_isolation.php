<?php

declare(strict_types=1);

/**
 * AUTOMATED TEST SUITE: MARKETPLACE & STORE/SERVICE ISOLATION (TAHAP 3)
 * Tests:
 * 1. Public Marketplace Filtering & Haversine Distance
 * 2. Store Details & Featured Stores (from Real DB)
 * 3. Multi-Store Isolation (Store A vs Store B)
 * 4. Tampering & Ownership Enforcement Gate
 * 5. Input Validation for Stores & Services
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

// Config helper
if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed {
        return \App\Helpers\Config::get($key, $default);
    }
}

use App\Models\Store;
use App\Models\Service;
use App\Helpers\Validator;
use App\Helpers\Auth;
use App\Helpers\Session;

class StoreIsolationTestSuite
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
        echo "   TEST SUITE: TAHAP 3 MARKETPLACE & STORE ISOLATION   \n";
        echo "=======================================================\n\n";

        $this->testMarketplaceFilteringAndHaversine();
        $this->testStoreDetailAndFeatured();
        $this->testStoreResolutionByOwnerAndAdmin();
        $this->testServiceOwnershipIsolationGate();
        $this->testTamperingAndSecurityGuards();
        $this->testInputValidation();

        echo "\n-------------------------------------------------------\n";
        echo "TOTAL RESULTS: {$this->passed} PASSED, {$this->failed} FAILED.\n";
        echo "=======================================================\n\n";

        if ($this->failed > 0) {
            exit(1);
        }
    }

    private function testMarketplaceFilteringAndHaversine(): void
    {
        echo "[TEST 1] Testing Public Marketplace Filtering & Haversine Distance...\n";

        $storeModel = new Store();

        // 1. All active stores
        $allStores = $storeModel->getActiveStores();
        $this->assert(count($allStores) >= 2, "Active stores query must return at least 2 stores from DB");

        // 2. Search filter (LIKE query)
        $searchResult = $storeModel->getActiveStoresFiltered(['search' => 'CleanMaster']);
        $this->assert(count($searchResult) >= 1, "Filter by search 'CleanMaster' must return CleanMaster store");
        $this->assert(str_contains($searchResult[0]['name'], 'CleanMaster'), "First result must contain CleanMaster");

        // 3. Min rating filter
        $ratingResult = $storeModel->getActiveStoresFiltered(['min_rating' => '4.8']);
        $allAbove48 = true;
        foreach ($ratingResult as $s) {
            if ((float)($s['rating'] ?? 0) < 4.8) {
                $allAbove48 = false;
            }
        }
        $this->assert($allAbove48 && count($ratingResult) >= 1, "Filter by min_rating 4.8 must only return stores with rating >= 4.8");

        // 4. Service type filter
        $kiloanResult = $storeModel->getActiveStoresFiltered(['service_type' => 'kg']);
        $this->assert(count($kiloanResult) >= 1, "Filter by service_type 'kg' must return stores providing kiloan service");

        // 5. Haversine distance sort (Customer coords: -6.2443, 106.7930)
        $coordsResult = $storeModel->getActiveStoresFiltered(['sort' => 'distance'], -6.24430000, 106.79300000);
        $this->assert(count($coordsResult) >= 2, "Haversine distance sorting must return stores");
        $this->assert(isset($coordsResult[0]['distance_km']) && $coordsResult[0]['distance_km'] !== null, "distance_km must be computed via Haversine formula");
        $this->assert($coordsResult[0]['distance_km'] <= $coordsResult[1]['distance_km'], "Stores must be ordered in ascending order of distance");
    }

    private function testStoreDetailAndFeatured(): void
    {
        echo "[TEST 2] Testing Store Detail & Real DB Featured Stores...\n";

        $storeModel = new Store();

        // Find store 1 with services
        $store = $storeModel->findWithServices(1);
        $this->assert($store !== null, "findWithServices(1) must return Store 1");
        $this->assert(isset($store['services']) && is_array($store['services']), "Store 1 must contain services array");
        $this->assert(count($store['services']) >= 3, "Store 1 must have at least 3 services");

        // Featured stores from real DB
        $featured = $storeModel->getFeaturedStores(4);
        $this->assert(!empty($featured), "getFeaturedStores() must return featured stores from real DB");
        $this->assert(isset($featured[0]['service_count']), "Featured store must include service_count aggregate");
    }

    private function testStoreResolutionByOwnerAndAdmin(): void
    {
        echo "[TEST 3] Testing Dynamic Store Resolution (Owner Toko & Admin Toko)...\n";

        $storeModel = new Store();

        // User 3 is Owner Toko 1
        $store1Owner = $storeModel->getStoreByOwner(3);
        $this->assert($store1Owner !== null && (int)$store1Owner['id'] === 1, "User ID 3 (owner_toko) must resolve to Store 1");

        // User 4 is Admin Toko 1 (linked via users.store_id = 1)
        $store1Admin = $storeModel->getStoreByOwner(4);
        $this->assert($store1Admin !== null && (int)$store1Admin['id'] === 1, "User ID 4 (admin_toko) must resolve to Store 1");

        // User 7 is Owner Toko 2
        $store2Owner = $storeModel->getStoreByOwner(7);
        $this->assert($store2Owner !== null && (int)$store2Owner['id'] === 2, "User ID 7 (owner_toko) must resolve to Store 2");

        // User 8 is Admin Toko 2 (linked via users.store_id = 2)
        $store2Admin = $storeModel->getStoreByOwner(8);
        $this->assert($store2Admin !== null && (int)$store2Admin['id'] === 2, "User ID 8 (admin_toko) must resolve to Store 2");
    }

    private function testServiceOwnershipIsolationGate(): void
    {
        echo "[TEST 4] Testing Service Ownership Isolation Gate (Multi-Tenancy Guard)...\n";

        $serviceModel = new Service();

        // Store 1 services
        $s1List = $serviceModel->getByStore(1);
        $this->assert(count($s1List) >= 3, "getByStore(1) must return Store 1 services");
        foreach ($s1List as $s) {
            $this->assert((int)$s['store_id'] === 1, "Every service in getByStore(1) must have store_id = 1");
        }

        // Store 2 services
        $s2List = $serviceModel->getByStore(2);
        $this->assert(count($s2List) >= 2, "getByStore(2) must return Store 2 services");
        foreach ($s2List as $s) {
            $this->assert((int)$s['store_id'] === 2, "Every service in getByStore(2) must have store_id = 2");
        }

        // Cross-store isolation checks
        // 1. Store 1 accessing Service 1 (belongs to Store 1) -> Allowed
        $ownService = $serviceModel->findWithOwnerCheck(1, 1);
        $this->assert($ownService !== null && (int)$ownService['id'] === 1, "Store 1 accessing its own Service 1 must succeed");

        // 2. Store 1 accessing Service 4 (belongs to Store 2) -> BLOCKED
        $crossAccess1 = $serviceModel->findWithOwnerCheck(4, 1);
        $this->assert($crossAccess1 === null, "Store 1 attempting to access Service 4 (Store 2) must return NULL (Blocked)");

        // 3. Store 2 accessing Service 1 (belongs to Store 1) -> BLOCKED
        $crossAccess2 = $serviceModel->findWithOwnerCheck(1, 2);
        $this->assert($crossAccess2 === null, "Store 2 attempting to access Service 1 (Store 1) must return NULL (Blocked)");

        // 4. Store 2 accessing Service 4 (belongs to Store 2) -> Allowed
        $ownService2 = $serviceModel->findWithOwnerCheck(4, 2);
        $this->assert($ownService2 !== null && (int)$ownService2['id'] === 4, "Store 2 accessing its own Service 4 must succeed");
    }

    private function testTamperingAndSecurityGuards(): void
    {
        echo "[TEST 5] Testing Controller Tampering Simulation & Security Guards...\n";

        // Simulate User 4 (Admin Toko 1) attempting to operate on Store 2's service
        Session::set('user', [
            'id' => 4,
            'role_name' => 'admin_toko',
            'name' => 'Rina Melati'
        ]);

        $storeModel = new Store();
        $userStore = $storeModel->getStoreByOwner(Auth::id());
        $this->assert((int)$userStore['id'] === 1, "Logged-in user 4 resolves strictly to Store 1");

        // Attacker attempts to target Service 4 (owned by Store 2)
        $targetServiceId = 4;
        $serviceModel = new Service();
        $gateCheck = $serviceModel->findWithOwnerCheck($targetServiceId, (int)$userStore['id']);
        $this->assert($gateCheck === null, "findWithOwnerCheck must reject Service 4 for user belonging to Store 1");

        // Clean up session
        Session::remove('user');
        $this->assert(!Auth::check(), "Session successfully cleared");
    }

    private function testInputValidation(): void
    {
        echo "[TEST 6] Testing Input Validation with Validator.php...\n";

        $validator = new Validator();

        // 1. Valid service input
        $validService = [
            'name' => 'Cuci Bed Cover Jumbo',
            'price' => '35000',
            'unit' => 'pcs'
        ];
        $validCheck = $validator->validate($validService, [
            'name' => 'required|min:3|max:100',
            'price' => 'required|numeric_positive',
            'unit' => 'required'
        ]);
        $this->assert($validCheck === true, "Valid service payload must pass validation");

        // 2. Invalid service price (negative/non-numeric)
        $invalidService = [
            'name' => 'Cuci',
            'price' => '-5000',
            'unit' => 'kg'
        ];
        $invalidCheck = $validator->validate($invalidService, [
            'name' => 'required|min:3|max:100',
            'price' => 'required|numeric_positive',
            'unit' => 'required'
        ]);
        $this->assert($invalidCheck === false, "Negative price must fail validation");

        // 3. Valid store input
        $validStore = [
            'name' => 'Berkah Laundry',
            'address' => 'Jl. Pahlawan No. 10',
            'phone' => '081234567890'
        ];
        $validStoreCheck = $validator->validate($validStore, [
            'name' => 'required|min:3|max:100',
            'address' => 'required|min:5',
            'phone' => 'required|phone_id'
        ]);
        $this->assert($validStoreCheck === true, "Valid store payload must pass validation");

        // 4. Invalid store phone
        $invalidStore = [
            'name' => 'Be',
            'address' => 'Jl',
            'phone' => '123'
        ];
        $invalidStoreCheck = $validator->validate($invalidStore, [
            'name' => 'required|min:3|max:100',
            'address' => 'required|min:5',
            'phone' => 'required|phone_id'
        ]);
        $this->assert($invalidStoreCheck === false, "Short name and invalid phone must fail validation");
    }
}

$suite = new StoreIsolationTestSuite();
$suite->run();
