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
$storeId = 1;

// Method 1: Existing inline queries in DashboardController
// Admin Toko
$stmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE store_id = :sid AND status IN ('pending', 'assigned_to_courier', 'courier_on_the_way', 'picked_up', 'arrived_at_store', 'weighed', 'waiting_payment', 'paid', 'processing')");
$stmt->execute(['sid' => $storeId]);
$oldNeedProcess = (int) $stmt->fetchColumn();

$orderModel = new \App\Models\Order();
$oldReadyCounts = $orderModel->getReadyCountsByStore($storeId);

$stmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE store_id = :sid AND status = 'delivered'");
$stmt->execute(['sid' => $storeId]);
$oldDelivered = (int) $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COALESCE(SUM(total_amount), 0), COUNT(id) FROM orders WHERE store_id = :sid AND payment_status = 'paid'");
$stmt->execute(['sid' => $storeId]);
$oldOmsetRow = $stmt->fetch(PDO::FETCH_NUM);
$oldOmset = (float) $oldOmsetRow[0];
$oldOmsetTx = (int) $oldOmsetRow[1];

echo "ADMIN TOKO (OLD INLINE):\n";
echo "Perlu Diproses: $oldNeedProcess\n";
echo "Siap Diambil/Diantar: {$oldReadyCounts['total']} (Pickup: {$oldReadyCounts['ready_pickup']}, Delivery: {$oldReadyCounts['ready_delivery']})\n";
echo "Selesai: $oldDelivered\n";
echo "Omset: $oldOmset ($oldOmsetTx tx)\n";

