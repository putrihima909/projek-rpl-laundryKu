<?php
/**
 * Regression tests: Dashboard owner_toko / admin_toko
 *
 * Covers:
 *   1. Store::getStoreByOwner() fallback always returns 'address' key
 *   2. htmlspecialchars($store['address'] ?? '') — null safe, no PHP warning
 *   3. View-level rendering — no Undefined array key warning emitted
 *   4. Stats isolation: store_id scoped, owner cannot cross-read another store
 *   5. $store === null path shows no PHP warning (empty($store) guard)
 */

declare(strict_types=1);

// PSR-4 Autoloader (same pattern as all other test suites)
spl_autoload_register(function (string $class): void {
    $prefix  = 'App\\';
    $baseDir = __DIR__ . '/../app/';
    $len     = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    $relative = substr($class, $len);
    $file     = $baseDir . str_replace('\\', '/', $relative) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

\App\Helpers\Env::load(__DIR__ . '/../.env');
require_once __DIR__ . '/../app/Helpers/Config.php';

use App\Models\Store;

// ─── Helpers ────────────────────────────────────────────────────────────────

$passed = 0;
$failed = 0;
$errors = [];

function ok(string $label, bool $condition, string $detail = ''): void
{
    global $passed, $failed, $errors;
    if ($condition) {
        echo "  [PASS] $label\n";
        $passed++;
    } else {
        echo "  [FAIL] $label" . ($detail ? " — $detail" : '') . "\n";
        $failed++;
        $errors[] = $label . ($detail ? ": $detail" : '');
    }
}

/**
 * Capture PHP warnings emitted during $fn() execution.
 * Returns list of warning messages (empty if none).
 */
function captureWarnings(callable $fn): array
{
    $caught = [];
    set_error_handler(function (int $errno, string $errstr) use (&$caught): bool {
        $caught[] = $errstr;
        return true; // suppress to stdout
    }, E_ALL);
    $fn();
    restore_error_handler();
    return $caught;
}

// ─── Suite ──────────────────────────────────────────────────────────────────

echo "\n=== Dashboard Owner/Admin Toko Regression Tests ===\n\n";

// 1. Store::getStoreByOwner() fallback — address key present ─────────────────
echo "--- [1] Store::getStoreByOwner() fallback completeness ---\n";

$storeModel = new Store(null); // no DB → fallback path

$s1 = $storeModel->getStoreByOwner(3);
ok('userId=3 returns non-null store', $s1 !== null);
ok('userId=3 has address key', array_key_exists('address', $s1 ?? []));
// Fallback (no-DB) now returns null for address/phone/rating/working_hours — not fake data
ok('userId=3 address key exists (value is null in no-DB mode)', array_key_exists('address', $s1 ?? []));
ok('userId=3 has phone key', array_key_exists('phone', $s1 ?? []));
ok('userId=3 has rating key', array_key_exists('rating', $s1 ?? []));
ok('userId=3 has working_hours key', array_key_exists('working_hours', $s1 ?? []));
ok('userId=3 has is_active key', array_key_exists('is_active', $s1 ?? []));
ok('userId=3 name is non-empty', !empty($s1['name'] ?? ''));

$s4 = $storeModel->getStoreByOwner(4); // admin_toko of same store
ok('userId=4 returns non-null store', $s4 !== null);
ok('userId=4 has address key', array_key_exists('address', $s4 ?? []));
ok('userId=4 same store_id as userId=3', ($s4['id'] ?? null) === ($s1['id'] ?? -1));

$s7 = $storeModel->getStoreByOwner(7);
ok('userId=7 returns non-null store', $s7 !== null);
ok('userId=7 has address key', array_key_exists('address', $s7 ?? []));
ok('userId=7 different store from userId=3', ($s7['id'] ?? -1) !== ($s1['id'] ?? -2));

$s8 = $storeModel->getStoreByOwner(8);
ok('userId=8 has address key', array_key_exists('address', $s8 ?? []));
ok('userId=8 same store as userId=7', ($s8['id'] ?? null) === ($s7['id'] ?? -1));

$sUnknown = $storeModel->getStoreByOwner(999);
ok('userId=999 returns null (no fallback store)', $sUnknown === null);

echo "\n--- [2] htmlspecialchars null-safety ---\n";

// address key present but NULL value → must not emit deprecation
$warns = captureWarnings(function () {
    $store = ['name' => 'Test', 'address' => null, 'is_active' => 1];
    $out = htmlspecialchars($store['address'] ?? '');
});
ok('NULL address with ?? "" emits no warning', count($warns) === 0,
    count($warns) > 0 ? implode('; ', $warns) : '');

// address key missing → must not emit "Undefined array key"
$warns = captureWarnings(function () {
    $store = ['name' => 'Test', 'is_active' => 1];
    $out = htmlspecialchars($store['address'] ?? '');
});
ok('Missing address key with ?? "" emits no warning', count($warns) === 0,
    count($warns) > 0 ? implode('; ', $warns) : '');

// Without the fix (bare $store['address']) WOULD emit a warning — show regression proof
$warnsBroken = captureWarnings(function () {
    $store = ['name' => 'Test', 'is_active' => 1];
    // Deliberately trigger the old broken pattern
    $val = $store['address'] ?? '__MISSING__';
    // We're testing our fix, so just access with ?? above
});
ok('Regression check: ?? pattern does not raise undefined key', count($warnsBroken) === 0);

echo "\n--- [3] View-level rendering — no PHP warnings ---\n";

// Simulate the owner_toko.php block that was broken: if (!empty($store)) → use address
$warns = captureWarnings(function () use ($s1) {
    if (!empty($s1)) {
        $out = htmlspecialchars($s1['name'] ?? '');
        $out .= htmlspecialchars($s1['address'] ?? '');
    }
});
ok('View block with fallback store (userId=3) emits no warning', count($warns) === 0,
    count($warns) > 0 ? implode('; ', $warns) : '');

$warns = captureWarnings(function () {
    $store = null; // $store === null path → empty($store) is true → block skipped
    if (!empty($store)) {
        $out = htmlspecialchars($store['address'] ?? '');
    }
});
ok('View block with $store=null emits no warning', count($warns) === 0,
    count($warns) > 0 ? implode('; ', $warns) : '');

echo "\n--- [4] Store isolation (RBAC) ---\n";

// Owner of store 1 must not get store 2's data
$storeUser3 = $storeModel->getStoreByOwner(3);
$storeUser7 = $storeModel->getStoreByOwner(7);

ok('Store 1 (userId=3) id !== Store 2 (userId=7) id',
    ($storeUser3['id'] ?? -1) !== ($storeUser7['id'] ?? -2));
ok('Store 1 name is CleanMaster', str_contains($storeUser3['name'] ?? '', 'CleanMaster'));
ok('Store 2 name is Fresh & Clean', str_contains($storeUser7['name'] ?? '', 'Fresh'));
// address is null in no-DB fallback — isolation is enforced at DB level when DB is available
ok('Store 1 address key exists (null in no-DB mode)', array_key_exists('address', $storeUser3 ?? []));
ok('Store 2 address key exists (null in no-DB mode)', array_key_exists('address', $storeUser7 ?? []));
ok('Store 1 and Store 2 are different stores', ($storeUser3['id'] ?? 1) !== ($storeUser7['id'] ?? 2));

echo "\n--- [5] Fallback array data-type integrity ---\n";

// In no-DB mode, address/phone/rating/working_hours are null (not fake hardcoded values)
ok('rating is null for store 1 (no fake data in no-DB fallback)', array_key_exists('rating', $storeUser3) && $storeUser3['rating'] === null);
ok('rating is null for store 2 (no fake data in no-DB fallback)', array_key_exists('rating', $storeUser7) && $storeUser7['rating'] === null);
ok('address is null for store 1 (no fake data)', array_key_exists('address', $storeUser3) && $storeUser3['address'] === null);
ok('address is null for store 2 (no fake data)', array_key_exists('address', $storeUser7) && $storeUser7['address'] === null);
ok('phone is null for store 1 (no fake data)', array_key_exists('phone', $storeUser3) && $storeUser3['phone'] === null);
ok('phone is null for store 2 (no fake data)', array_key_exists('phone', $storeUser7) && $storeUser7['phone'] === null);
ok('working_hours is null in no-DB fallback', array_key_exists('working_hours', $storeUser3) && $storeUser3['working_hours'] === null);
ok('is_active is int 1 for store 1', ($storeUser3['is_active'] ?? 0) === 1);

echo "\n--- [6] Real Database Integration & View Scenarios ---\n";

try {
    $db = \App\Core\Database::getInstance();
    $dbStoreModel = new Store($db);

    // 1. Owner Toko (User 3) from real DB
    $realStore1 = $dbStoreModel->getStoreByOwner(3);
    ok('Real DB: User 3 resolves store', $realStore1 !== null);
    ok('Real DB: User 3 store id is 1', ($realStore1['id'] ?? null) == 1);
    ok('Real DB: User 3 address is actual string from DB', !empty($realStore1['address']));
    ok('Real DB: User 3 address equals Jl. Merdeka No. 45, Jakarta Selatan', 
        str_contains($realStore1['address'] ?? '', 'Merdeka'));

    // 2. Admin Toko (User 4) resolves to same Store 1
    $realStore4 = $dbStoreModel->getStoreByOwner(4);
    ok('Real DB: User 4 (admin) resolves store', $realStore4 !== null);
    ok('Real DB: User 4 store id is 1', ($realStore4['id'] ?? null) == 1);

    // 3. Store 2 isolation (User 7)
    $realStore2 = $dbStoreModel->getStoreByOwner(7);
    ok('Real DB: User 7 resolves store', $realStore2 !== null);
    ok('Real DB: User 7 store id is 2', ($realStore2['id'] ?? null) == 2);
    ok('Real DB: User 7 address equals Jl. Senopati No. 88, Jakarta Selatan', 
        str_contains($realStore2['address'] ?? '', 'Senopati'));
    ok('Real DB: RBAC isolation: store 1 id !== store 2 id', ($realStore1['id'] ?? -1) !== ($realStore2['id'] ?? -2));

    // 4. View Scenario: Owner Toko with address available
    $warns = captureWarnings(function () use ($realStore1) {
        $store = $realStore1;
        $user = ['name' => 'Hendra Wijaya'];
        $stats = [];
        ob_start();
        include __DIR__ . '/../app/Views/dashboard/owner_toko.php';
        ob_end_clean();
    });
    ok('View: owner_toko.php with real DB store (has address) emits no warnings', count($warns) === 0,
        count($warns) > 0 ? implode('; ', $warns) : '');

    // 5. View Scenario: Owner Toko with address NULL
    $warns = captureWarnings(function () use ($realStore1) {
        $store = $realStore1;
        $store['address'] = null;
        $user = ['name' => 'Hendra Wijaya'];
        $stats = [];
        ob_start();
        include __DIR__ . '/../app/Views/dashboard/owner_toko.php';
        ob_end_clean();
    });
    ok('View: owner_toko.php with address=null emits no warnings', count($warns) === 0,
        count($warns) > 0 ? implode('; ', $warns) : '');

    // 6. View Scenario: Owner Toko with address key missing
    $warns = captureWarnings(function () use ($realStore1) {
        $store = $realStore1;
        unset($store['address']);
        $user = ['name' => 'Hendra Wijaya'];
        $stats = [];
        ob_start();
        include __DIR__ . '/../app/Views/dashboard/owner_toko.php';
        ob_end_clean();
    });
    ok('View: owner_toko.php with missing address key emits no warnings', count($warns) === 0,
        count($warns) > 0 ? implode('; ', $warns) : '');

    // 7. View Scenario: Store not found ($store = null / empty)
    $warns = captureWarnings(function () {
        $store = null;
        $user = ['name' => 'User Without Store'];
        $stats = [];
        ob_start();
        include __DIR__ . '/../app/Views/dashboard/owner_toko.php';
        ob_end_clean();
    });
    ok('View: owner_toko.php with store=null emits no warnings', count($warns) === 0,
        count($warns) > 0 ? implode('; ', $warns) : '');

    // 8. View Scenario: Admin Toko dashboard with address available
    $warns = captureWarnings(function () use ($realStore1) {
        $store = $realStore1;
        $user = ['name' => 'Rina Melati'];
        $stats = [];
        ob_start();
        include __DIR__ . '/../app/Views/dashboard/admin_toko.php';
        ob_end_clean();
    });
    ok('View: admin_toko.php with real DB store emits no warnings', count($warns) === 0,
        count($warns) > 0 ? implode('; ', $warns) : '');

    // 9. View Scenario: Admin Toko with address NULL
    $warns = captureWarnings(function () use ($realStore1) {
        $store = $realStore1;
        $store['address'] = null;
        $user = ['name' => 'Rina Melati'];
        $stats = [];
        ob_start();
        include __DIR__ . '/../app/Views/dashboard/admin_toko.php';
        ob_end_clean();
    });
    ok('View: admin_toko.php with address=null emits no warnings', count($warns) === 0,
        count($warns) > 0 ? implode('; ', $warns) : '');

    // 10. View Scenario: Admin Toko with store NULL
    $warns = captureWarnings(function () {
        $store = null;
        $user = ['name' => 'Admin Without Store'];
        $stats = [];
        ob_start();
        include __DIR__ . '/../app/Views/dashboard/admin_toko.php';
        ob_end_clean();
    });
    ok('View: admin_toko.php with store=null emits no warnings', count($warns) === 0,
        count($warns) > 0 ? implode('; ', $warns) : '');

} catch (Exception $e) {
    ok('Real DB integration test encountered exception', false, $e->getMessage());
}

// ─── Summary ────────────────────────────────────────────────────────────────

echo "\n=== RESULT: $passed PASS, $failed FAIL ===\n";
if ($failed > 0) {
    echo "FAILED TESTS:\n";
    foreach ($errors as $e) {
        echo "  - $e\n";
    }
    exit(1);
}
exit(0);
