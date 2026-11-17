<?php

/**
 * Regression Test Suite: Store Routing & RBAC Isolation
 *
 * Tests:
 * 1. GET /stores/manage does NOT throw TypeError (not hijacked by /stores/{id})
 * 2. GET /stores/{id} with a numeric ID still works
 * 3. Non-numeric segments like /stores/manage return 404 from dynamic route (not injected as string ID)
 * 4. Unauthorized roles are rejected by RBAC middleware
 * 5. /stores/detail?id=N query-param path still works
 */

// PSR-4 Autoloader
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
require_once __DIR__ . '/../app/Helpers/Config.php';
date_default_timezone_set(\config('app.timezone', 'Asia/Jakarta'));

use App\Core\Router;
use App\Core\AuthMiddleware;
use App\Helpers\Auth;
use App\Helpers\Session;
use App\Controllers\StoreController;

Session::start();

$passCount = 0;
$failCount = 0;

function assertTest(bool $condition, string $description, ?string $detail = null): void {
    global $passCount, $failCount;
    if ($condition) {
        echo "[PASS] " . $description . "\n";
        $passCount++;
    } else {
        echo "[FAIL] " . $description . ($detail ? " -> " . $detail : "") . "\n";
        $failCount++;
    }
}

echo "\n==============================================================================\n";
echo "   STORE ROUTING & RBAC REGRESSION TEST SUITE                               \n";
echo "==============================================================================\n\n";

// Helper: build a Router identical to public/index.php's store routes
function buildStoreRouter(): Router {
    $router = new Router();

    // Static store management routes must come BEFORE dynamic /stores/{id}
    $router->get('/stores/manage', [StoreController::class, 'adminIndex'], [new AuthMiddleware([Auth::ROLE_OWNER, Auth::ROLE_ADMIN_OWNER])]);
    $router->get('/stores/create', [StoreController::class, 'create'], [new AuthMiddleware([Auth::ROLE_OWNER, Auth::ROLE_ADMIN_OWNER])]);
    $router->post('/stores/store', [StoreController::class, 'store'], [new AuthMiddleware([Auth::ROLE_OWNER, Auth::ROLE_ADMIN_OWNER])]);
    $router->get('/stores/edit', [StoreController::class, 'edit'], [new AuthMiddleware([Auth::ROLE_OWNER, Auth::ROLE_ADMIN_OWNER])]);
    $router->post('/stores/update', [StoreController::class, 'update'], [new AuthMiddleware([Auth::ROLE_OWNER, Auth::ROLE_ADMIN_OWNER])]);
    $router->post('/stores/toggle-status', [StoreController::class, 'toggleStatus'], [new AuthMiddleware([Auth::ROLE_OWNER, Auth::ROLE_ADMIN_OWNER])]);

    // Public marketplace routes — dynamic route AFTER all static /stores/* routes
    $router->get('/stores', [StoreController::class, 'index']);
    $router->get('/stores/detail', [StoreController::class, 'show']);
    $router->get('/stores/{id}', [StoreController::class, 'show']);

    return $router;
}

// Simulate $_SERVER['SCRIPT_NAME'] so Router::dispatch() strips the right prefix
$_SERVER['SCRIPT_NAME'] = '/rpl/public/index.php';

try {
    // =========================================================================
    // TEST 1: GET /stores/manage as OWNER — must NOT throw TypeError
    // =========================================================================
    echo "--- 1. GET /stores/manage as OWNER (no TypeError expected) ---\n";
    Auth::login([
        'id'        => 1,
        'name'      => 'Owner Platform Test',
        'email'     => 'owner@laundry.test',
        'role_name' => Auth::ROLE_OWNER,
        'is_active' => true,
    ]);

    ob_start();
    $typeErrorThrown = false;
    try {
        buildStoreRouter()->dispatch('/rpl/public/stores/manage', 'GET');
    } catch (\TypeError $e) {
        $typeErrorThrown = true;
    }
    $outputOwner = ob_get_clean();

    assertTest(
        !$typeErrorThrown,
        "GET /stores/manage tidak melempar TypeError saat router memproses permintaan"
    );
    assertTest(
        str_contains($outputOwner, 'Manajemen Toko') || str_contains($outputOwner, 'Tambah Toko Baru'),
        "GET /stores/manage untuk role OWNER merender view manajemen toko"
    );

    // =========================================================================
    // TEST 2: GET /stores/manage as ADMIN_OWNER
    // =========================================================================
    echo "\n--- 2. GET /stores/manage as ADMIN_OWNER ---\n";
    Auth::login([
        'id'        => 2,
        'name'      => 'Admin Owner Test',
        'email'     => 'adminowner@laundry.test',
        'role_name' => Auth::ROLE_ADMIN_OWNER,
        'is_active' => true,
    ]);

    ob_start();
    buildStoreRouter()->dispatch('/rpl/public/stores/manage', 'GET');
    $outputAdminOwner = ob_get_clean();

    assertTest(
        str_contains($outputAdminOwner, 'Manajemen Toko'),
        "GET /stores/manage untuk role ADMIN_OWNER merender view manajemen toko"
    );

    // =========================================================================
    // TEST 3: GET /stores/{id} — numeric ID must still be dispatched correctly
    // =========================================================================
    echo "\n--- 3. GET /stores/1 (numeric ID route still works) ---\n";
    Auth::logout(); // Public route

    ob_start();
    $numericTypeError = false;
    try {
        buildStoreRouter()->dispatch('/rpl/public/stores/1', 'GET');
    } catch (\TypeError $e) {
        $numericTypeError = true;
    }
    $outputStore1 = ob_get_clean();

    assertTest(
        !$numericTypeError,
        "GET /stores/1 tidak melempar TypeError"
    );
    assertTest(
        str_contains($outputStore1, 'Detail Toko') || str_contains($outputStore1, 'CleanMaster') || str_contains($outputStore1, 'Laundry'),
        "GET /stores/1 merender profil toko (atau redirect ke /stores jika ID tidak ada di DB)"
    );

    // =========================================================================
    // TEST 4: Non-numeric segment /stores/manage must NOT be matched by /stores/{id}
    //         The Router's id constraint (\d+) should prevent this; result is 404
    // =========================================================================
    echo "\n--- 4. Non-numeric segment /stores/invalid-slug → 404, NOT TypeError ---\n";
    ob_start();
    $slugTypeError = false;
    try {
        buildStoreRouter()->dispatch('/rpl/public/stores/invalid-slug', 'GET');
    } catch (\TypeError $e) {
        $slugTypeError = true;
    }
    $outputSlug = ob_get_clean();
    $http404 = http_response_code();

    assertTest(
        !$slugTypeError,
        "Segmen non-numerik '/stores/invalid-slug' tidak melempar TypeError"
    );
    assertTest(
        $http404 === 404,
        "Segmen non-numerik '/stores/invalid-slug' menghasilkan HTTP 404 Not Found (http_response_code: {$http404})"
    );

    // Reset http_response_code after 404 test
    http_response_code(200);

    // =========================================================================
    // TEST 5: RBAC — Customer is rejected from /stores/manage
    // =========================================================================
    echo "\n--- 5. RBAC: Customer tidak dapat akses /stores/manage ---\n";
    Auth::login([
        'id'        => 10,
        'name'      => 'Customer Test',
        'email'     => 'customer@laundry.test',
        'role_name' => Auth::ROLE_CUSTOMER,
        'is_active' => true,
    ]);

    ob_start();
    buildStoreRouter()->dispatch('/rpl/public/stores/manage', 'GET');
    $outputCustomer = ob_get_clean();
    $flashCustomer = Session::flash('error');

    assertTest(
        str_contains($flashCustomer ?? '', 'Akses ditolak') || !str_contains($outputCustomer, 'Manajemen Toko'),
        "Role Customer ditolak mengakses /stores/manage (RBAC middleware)"
    );

    // =========================================================================
    // TEST 6: RBAC — Kurir is rejected from /stores/manage
    // =========================================================================
    echo "\n--- 6. RBAC: Kurir tidak dapat akses /stores/manage ---\n";
    Auth::login([
        'id'        => 20,
        'name'      => 'Kurir Test',
        'email'     => 'kurir@laundry.test',
        'role_name' => Auth::ROLE_KURIR,
        'is_active' => true,
    ]);

    ob_start();
    buildStoreRouter()->dispatch('/rpl/public/stores/manage', 'GET');
    $outputKurir = ob_get_clean();
    $flashKurir = Session::flash('error');

    assertTest(
        str_contains($flashKurir ?? '', 'Akses ditolak') || !str_contains($outputKurir, 'Manajemen Toko'),
        "Role Kurir ditolak mengakses /stores/manage (RBAC middleware)"
    );

    // =========================================================================
    // TEST 7: RBAC — Owner Toko is rejected from /stores/manage (platform-wide page)
    // =========================================================================
    echo "\n--- 7. RBAC: Owner Toko tidak dapat akses /stores/manage platform-wide ---\n";
    Auth::login([
        'id'        => 30,
        'name'      => 'Owner Toko Test',
        'email'     => 'ownertoko@laundry.test',
        'role_name' => Auth::ROLE_OWNER_TOKO,
        'is_active' => true,
    ]);

    ob_start();
    buildStoreRouter()->dispatch('/rpl/public/stores/manage', 'GET');
    $outputOwnerToko = ob_get_clean();
    $flashOwnerToko = Session::flash('error');

    assertTest(
        str_contains($flashOwnerToko ?? '', 'Akses ditolak') || !str_contains($outputOwnerToko, 'Manajemen Toko'),
        "Role Owner Toko ditolak mengakses /stores/manage platform oleh RBAC middleware"
    );

    // =========================================================================
    // TEST 8: RBAC — Guest (unauthenticated) is rejected
    // =========================================================================
    echo "\n--- 8. RBAC: Guest tidak dapat akses /stores/manage ---\n";
    Auth::logout();

    ob_start();
    buildStoreRouter()->dispatch('/rpl/public/stores/manage', 'GET');
    $outputGuest = ob_get_clean();
    $flashGuest = Session::flash('error');

    assertTest(
        str_contains($flashGuest ?? '', 'Silakan login') || !str_contains($outputGuest, 'Manajemen Toko'),
        "Guest tidak login diarahkan ke halaman login (AuthMiddleware)"
    );

    // =========================================================================
    // TEST 9: /stores/detail?id=N still works via query param
    // =========================================================================
    echo "\n--- 9. GET /stores/detail?id=1 via query parameter ---\n";
    Auth::logout();
    $_GET['id'] = '1';

    ob_start();
    buildStoreRouter()->dispatch('/rpl/public/stores/detail', 'GET');
    $outputDetail = ob_get_clean();
    unset($_GET['id']);

    assertTest(
        str_contains($outputDetail, 'Detail Toko') || str_contains($outputDetail, 'CleanMaster') || str_contains($outputDetail, 'Laundry') || str_contains($outputDetail, 'stores'),
        "Route /stores/detail?id=1 tetap berfungsi (query param path)"
    );

    // =========================================================================
    // TEST 10: Router correctly casts numeric string '5' to int for show(int $id)
    // =========================================================================
    echo "\n--- 10. Router meneruskan ID numerik sebagai integer ke StoreController::show() ---\n";
    ob_start();
    $castTypeError = false;
    try {
        buildStoreRouter()->dispatch('/rpl/public/stores/5', 'GET');
    } catch (\TypeError $e) {
        $castTypeError = true;
    }
    ob_get_clean();

    assertTest(
        !$castTypeError,
        "GET /stores/5 — Router melakukan cast string '5' ke int, tidak ada TypeError dari show(int \$id)"
    );

} finally {
    Auth::logout();
    unset($_GET['id']);
}

echo "\n==============================================================================\n";
echo "STORE ROUTING REGRESSION RESULTS: {$passCount} PASSED, {$failCount} FAILED\n";
echo "==============================================================================\n\n";

exit($failCount > 0 ? 1 : 0);
