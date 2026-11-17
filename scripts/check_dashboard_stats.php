<?php
require_once __DIR__ . '/../app/Core/Database.php';
require_once __DIR__ . '/../app/Helpers/Env.php';
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

$db = \App\Core\Database::getInstance();

echo "=== CHECK ACTUAL DASHBOARD STATS PER ROLE ===\n\n";

// 1. Owner Toko & Admin Toko for Store 1 (CleanMaster)
$store = $db->query("SELECT * FROM stores WHERE name LIKE '%CleanMaster%' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$sid = (int) $store['id'];
echo "Store: {$store['name']} (ID: {$sid})\n";

// adminToko stats
$stmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE store_id = :sid AND status IN ('pending', 'assigned_to_courier', 'courier_on_the_way', 'picked_up', 'arrived_at_store', 'weighed', 'waiting_payment', 'paid', 'processing')");
$stmt->execute(['sid' => $sid]);
$needProcess = (int) $stmt->fetchColumn();

$orderModel = new \App\Models\Order();
$readyCounts = $orderModel->getReadyCountsByStore($sid);

$stmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE store_id = :sid AND status = 'delivered'");
$stmt->execute(['sid' => $sid]);
$deliveredCount = (int) $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COALESCE(SUM(total_amount), 0), COUNT(id) FROM orders WHERE store_id = :sid AND payment_status = 'paid'");
$stmt->execute(['sid' => $sid]);
$omsetRow = $stmt->fetch(PDO::FETCH_NUM);
$omsetToko = (float) ($omsetRow[0] ?? 0.0);
$omsetTx = (int) ($omsetRow[1] ?? 0);

echo "--- Admin Toko Cards ---\n";
echo "1. Pesanan Perlu Diproses: {$needProcess}\n";
echo "2. Siap Diambil / Diantar: {$readyCounts['total']} (Pickup: {$readyCounts['ready_pickup']}, Delivery: {$readyCounts['ready_delivery']})\n";
echo "3. Selesai Terkirim: {$deliveredCount}\n";
echo "4. OMSET KASIR TERBAYAR: Rp " . number_format($omsetToko, 0, ',', '.') . " ({$omsetTx} transaksi)\n";

// ownerToko stats
$stmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE store_id = :sid");
$stmt->execute(['sid' => $sid]);
$totalOrderStore = (int) $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM services WHERE store_id = :sid AND is_active = 1");
$stmt->execute(['sid' => $sid]);
$serviceCount = (int) $stmt->fetchColumn();

echo "\n--- Owner Toko Cards ---\n";
echo "1. Omset Toko (Terbayar): Rp " . number_format($omsetToko, 0, ',', '.') . " ({$omsetTx} transaksi)\n";
echo "2. Total Pesanan Toko: {$totalOrderStore}\n";
echo "3. Layanan Aktif: {$serviceCount}\n";
echo "4. Rating Pelanggan Toko: {$store['rating']}\n";

// Platform stats (owner & admin_owner)
$userCount = (int) $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalStores = (int) $db->query("SELECT COUNT(*) FROM stores")->fetchColumn();
$activeStores = (int) $db->query("SELECT COUNT(*) FROM stores WHERE is_active = 1")->fetchColumn();
$pendingStores = (int) $db->query("SELECT COUNT(*) FROM stores WHERE is_active = 0")->fetchColumn();
$omsetRowP = $db->query("SELECT COALESCE(SUM(total_amount), 0), COUNT(id) FROM orders WHERE payment_status = 'paid'")->fetch(PDO::FETCH_NUM);
$totalOmsetP = (float) ($omsetRowP[0] ?? 0.0);
$omsetTxP = (int) ($omsetRowP[1] ?? 0);
$totalOrdersP = (int) $db->query("SELECT COUNT(*) FROM orders")->fetchColumn();

echo "\n--- Owner Platform Cards ---\n";
echo "1. Total Pengguna: {$userCount}\n";
echo "2. Toko Terdaftar: {$totalStores} ({$activeStores} Aktif)\n";
echo "3. Total Omset Terbayar: Rp " . number_format($totalOmsetP, 0, ',', '.') . " ({$omsetTxP} transaksi)\n";
echo "4. Keamanan RBAC: Server-Side Active\n";

echo "\n--- Admin Owner Cards ---\n";
echo "1. Toko Non-Aktif: {$pendingStores}\n";
echo "2. Total Omset Platform: Rp " . number_format($totalOmsetP, 0, ',', '.') . " ({$omsetTxP} transaksi)\n";
echo "3. User Terdaftar: {$userCount}\n";
echo "4. Monitoring Pesanan: {$totalOrdersP}\n";

