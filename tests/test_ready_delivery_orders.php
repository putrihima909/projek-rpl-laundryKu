<?php

/**
 * Automated Test Suite: Fitur Operasional Siap Diambil / Diantar (Admin Toko)
 *
 * Verifikasi:
 * 1. Skema & Default delivery_type ('pickup', 'delivery').
 * 2. Model Order: getReadyCountsByStore & getReadyOrdersByStore.
 * 3. Tab Siap Diambil: memuat order status=ready AND delivery_type=pickup.
 * 4. Tab Siap Diantar: memuat order status=ready AND delivery_type=delivery, serta out_for_delivery.
 * 5. State Machine: ready -> delivered untuk pengambilan langsung di toko.
 * 6. Penugasan Kurir untuk Pengantaran: assignDeliveryCourier menyimpan courier_id & mencatat riwayat.
 * 7. Alur Mulai Pengantaran: startDelivery mengubah ready -> out_for_delivery.
 * 8. Penyelesaian Pengantaran: out_for_delivery -> delivered.
 * 9. Multi-Store Isolation: Toko 1 tidak dapat melihat atau mengubah pesanan Toko 2.
 * 10. Keutuhan data customer & nomor WhatsApp: nomor WA tidak diubah atau dipalsukan.
 * 11. Dashboard Admin Toko: kartu menghitung realtime Siap Diambil & Siap Diantar.
 */

require_once __DIR__ . '/../vendor/autoload.php';
\App\Helpers\Env::load(__DIR__ . '/../.env');
require_once __DIR__ . '/../app/Helpers/Config.php';

use App\Core\Database;
use App\Models\Order;
use App\Helpers\Auth;

$pdo = Database::getInstance();
$passCount = 0;
$failCount = 0;

function assertTest(string $desc, bool $condition, string $detail = ''): void {
    global $passCount, $failCount;
    if ($condition) {
        echo "  [PASS] {$desc}\n";
        $passCount++;
    } else {
        echo "  [FAIL] {$desc}" . ($detail ? " ({$detail})" : "") . "\n";
        $failCount++;
    }
}

echo "==============================================================================\n";
echo "   TEST SUITE: OPERASIONAL SIAP DIAMBIL & SIAP DIANTAR (ADMIN TOKO)           \n";
echo "==============================================================================\n\n";

$createdOrderIds = [];

try {
    // -------------------------------------------------------------------------
    // 1. Skema & Kolom delivery_type
    // -------------------------------------------------------------------------
    echo "--- 1. Skema Database: Kolom delivery_type ---\n";
    $colStmt = $pdo->query("SHOW COLUMNS FROM orders LIKE 'delivery_type'");
    $col = $colStmt->fetch(\PDO::FETCH_ASSOC);
    assertTest("Kolom `delivery_type` ada di tabel `orders`", !empty($col));
    assertTest("Tipe kolom adalah enum('pickup','delivery')", str_contains(strtolower($col['Type'] ?? ''), 'pickup'));
    assertTest("Nilai default adalah 'delivery'", ($col['Default'] ?? '') === 'delivery');

    // -------------------------------------------------------------------------
    // 2. State Machine: Transisi ready -> delivered diizinkan
    // -------------------------------------------------------------------------
    echo "\n--- 2. State Machine Order: Transisi Baru ---\n";
    assertTest("Transisi ready -> delivered (Ambil di Toko) diizinkan", Order::isValidTransition(Order::STATUS_READY, Order::STATUS_DELIVERED));
    assertTest("Transisi ready -> out_for_delivery (Diantar Kurir) diizinkan", Order::isValidTransition(Order::STATUS_READY, Order::STATUS_OUT_FOR_DELIVERY));
    assertTest("Transisi out_for_delivery -> delivered (Selesai Diantar) diizinkan", Order::isValidTransition(Order::STATUS_OUT_FOR_DELIVERY, Order::STATUS_DELIVERED));
    assertTest("Transisi pending -> delivered TIDAK diizinkan", !Order::isValidTransition(Order::STATUS_PENDING, Order::STATUS_DELIVERED));

    // -------------------------------------------------------------------------
    // 3. Setup Test Orders (Store 1 & Store 2)
    // -------------------------------------------------------------------------
    echo "\n--- 3. Pembuatan Data Uji Transaksi Sementara ---\n";
    $orderModel = new Order();

    // Order A: Siap Diambil di Toko (Store 1)
    $stmt = $pdo->prepare("INSERT INTO orders 
        (order_code, customer_id, store_id, service_id, address_id, courier_id, delivery_type, estimated_weight, estimated_amount, actual_weight, unit_price, total_amount, payment_method, payment_status, status, created_at, updated_at)
        VALUES 
        (:code, 6, 1, 1, 1, NULL, 'pickup', 3.0, 18000, 3.0, 6000, 18000, 'cash', 'paid', 'ready', NOW(), NOW())");
    $codeA = 'ORD-TEST-PICKUP-' . uniqid();
    $stmt->execute(['code' => $codeA]);
    $orderIdA = (int)$pdo->lastInsertId();
    $createdOrderIds[] = $orderIdA;
    assertTest("Order A (Siap Diambil, Toko 1) berhasil dibuat", $orderIdA > 0);

    // Order B: Siap Diantar Kurir, Belum Ditugaskan (Store 1)
    $stmt = $pdo->prepare("INSERT INTO orders 
        (order_code, customer_id, store_id, service_id, address_id, courier_id, delivery_type, estimated_weight, estimated_amount, actual_weight, unit_price, total_amount, payment_method, payment_status, status, created_at, updated_at)
        VALUES 
        (:code, 6, 1, 1, 1, NULL, 'delivery', 4.0, 24000, 4.0, 6000, 24000, 'online', 'paid', 'ready', NOW(), NOW())");
    $codeB = 'ORD-TEST-DELIV-' . uniqid();
    $stmt->execute(['code' => $codeB]);
    $orderIdB = (int)$pdo->lastInsertId();
    $createdOrderIds[] = $orderIdB;
    assertTest("Order B (Siap Diantar, Toko 1) berhasil dibuat", $orderIdB > 0);

    // Order C: Sedang Dalam Pengantaran (Store 1)
    $stmt = $pdo->prepare("INSERT INTO orders 
        (order_code, customer_id, store_id, service_id, address_id, courier_id, delivery_type, estimated_weight, estimated_amount, actual_weight, unit_price, total_amount, payment_method, payment_status, status, created_at, updated_at)
        VALUES 
        (:code, 6, 1, 1, 1, 5, 'delivery', 2.0, 12000, 2.0, 6000, 12000, 'cash', 'unpaid', 'out_for_delivery', NOW(), NOW())");
    $codeC = 'ORD-TEST-OUT-' . uniqid();
    $stmt->execute(['code' => $codeC]);
    $orderIdC = (int)$pdo->lastInsertId();
    $createdOrderIds[] = $orderIdC;
    assertTest("Order C (Sedang Diantar, Toko 1) berhasil dibuat", $orderIdC > 0);

    // Order D: Toko 2 (Fresh & Clean) - Untuk Uji Isolasi
    $stmt = $pdo->prepare("INSERT INTO orders 
        (order_code, customer_id, store_id, service_id, address_id, courier_id, delivery_type, estimated_weight, estimated_amount, actual_weight, unit_price, total_amount, payment_method, payment_status, status, created_at, updated_at)
        VALUES 
        (:code, 6, 2, 4, 1, NULL, 'pickup', 5.0, 25000, 5.0, 5000, 25000, 'cash', 'paid', 'ready', NOW(), NOW())");
    $codeD = 'ORD-TEST-STORE2-' . uniqid();
    $stmt->execute(['code' => $codeD]);
    $orderIdD = (int)$pdo->lastInsertId();
    $createdOrderIds[] = $orderIdD;
    assertTest("Order D (Toko 2) berhasil dibuat untuk uji isolasi", $orderIdD > 0);

    // -------------------------------------------------------------------------
    // 4. Pengujian Model: getReadyCountsByStore()
    // -------------------------------------------------------------------------
    echo "\n--- 4. Agregasi Dashboard: getReadyCountsByStore ---\n";
    $countsStore1 = $orderModel->getReadyCountsByStore(1);
    assertTest("Hitungan ready_pickup Toko 1 >= 1", $countsStore1['ready_pickup'] >= 1);
    assertTest("Hitungan ready_delivery Toko 1 >= 2 (1 ready + 1 out_for_delivery)", $countsStore1['ready_delivery'] >= 2);
    assertTest("Hitungan delivering Toko 1 >= 1", $countsStore1['delivering'] >= 1);
    assertTest("Total paket siap Toko 1 >= 3", $countsStore1['total'] >= 3);

    $countsStore2 = $orderModel->getReadyCountsByStore(2);
    assertTest("Hitungan ready_pickup Toko 2 >= 1", $countsStore2['ready_pickup'] >= 1);
    assertTest("Hitungan ready_delivery Toko 2 adalah 0 (terisolasi)", $countsStore2['ready_delivery'] === 0);

    // -------------------------------------------------------------------------
    // 5. Pengujian Daftar: Tab Siap Diambil vs Siap Diantar
    // -------------------------------------------------------------------------
    echo "\n--- 5. Pemisahan Tab Siap Diambil vs Siap Diantar ---\n";
    $pickupOrders = $orderModel->getReadyOrdersByStore(1, 'pickup');
    $pickupIds = array_column($pickupOrders, 'id');
    assertTest("Order A (pickup) masuk ke Tab Siap Diambil", in_array($orderIdA, $pickupIds, true));
    assertTest("Order B (delivery) TIDAK masuk ke Tab Siap Diambil", !in_array($orderIdB, $pickupIds, true));
    assertTest("Order C (out_for_delivery) TIDAK masuk ke Tab Siap Diambil", !in_array($orderIdC, $pickupIds, true));
    assertTest("Order D (Toko 2) TIDAK bocor ke Toko 1", !in_array($orderIdD, $pickupIds, true));

    $deliveryOrders = $orderModel->getReadyOrdersByStore(1, 'delivery');
    $deliveryIds = array_column($deliveryOrders, 'id');
    assertTest("Order B (delivery) masuk ke Tab Siap Diantar", in_array($orderIdB, $deliveryIds, true));
    assertTest("Order C (out_for_delivery) masuk ke Tab Siap Diantar", in_array($orderIdC, $deliveryIds, true));
    assertTest("Order A (pickup) TIDAK masuk ke Tab Siap Diantar", !in_array($orderIdA, $deliveryIds, true));

    // -------------------------------------------------------------------------
    // 6. Pengujian Aksi: "Tandai Sudah Diambil" (ready -> delivered)
    // -------------------------------------------------------------------------
    echo "\n--- 6. Aksi Operasional: Tandai Sudah Diambil (Ambil di Toko) ---\n";
    $pickupRes = $orderModel->markAsPickedUp($orderIdA, 1, 4); // User 4 = Admin Toko
    assertTest("Aksi markAsPickedUp berhasil", $pickupRes['success'] === true);

    $orderACheck = $orderModel->find($orderIdA);
    assertTest("Status Order A berubah menjadi 'delivered'", ($orderACheck['status'] ?? '') === Order::STATUS_DELIVERED);

    // Pastikan tercatat di order_status_histories
    $histStmt = $pdo->prepare("SELECT * FROM order_status_histories WHERE order_id = :oid AND status = 'delivered' ORDER BY id DESC LIMIT 1");
    $histStmt->execute(['oid' => $orderIdA]);
    $histA = $histStmt->fetch(\PDO::FETCH_ASSOC);
    assertTest("Riwayat status 'delivered' tercatat di order_status_histories", !empty($histA));
    assertTest("Catatan riwayat memuat penyerahan langsung ke customer", str_contains($histA['notes'] ?? '', 'customer'));

    // Pastikan Order A keluar dari tab Siap Diambil setelah diserahkan
    $pickupAfter = $orderModel->getReadyOrdersByStore(1, 'pickup');
    $pickupIdsAfter = array_column($pickupAfter, 'id');
    assertTest("Order A tidak lagi muncul di Tab Siap Diambil setelah selesai", !in_array($orderIdA, $pickupIdsAfter, true));

    // -------------------------------------------------------------------------
    // 7. Pengujian Penugasan Kurir untuk Pengantaran
    // -------------------------------------------------------------------------
    echo "\n--- 7. Aksi Operasional: Penugasan Kurir Pengantaran ---\n";
    $assignRes = $orderModel->assignDeliveryCourier($orderIdB, 1, 5, 4); // Kurir Agus (id=5)
    assertTest("Aksi assignDeliveryCourier berhasil", $assignRes['success'] === true);

    $orderBCheck = $orderModel->find($orderIdB);
    assertTest("courier_id pada Order B terisi 5", (int)($orderBCheck['courier_id'] ?? 0) === 5);
    assertTest("Status Order B tetap 'ready' (menunggu diberangkatkan)", ($orderBCheck['status'] ?? '') === Order::STATUS_READY);

    // -------------------------------------------------------------------------
    // 8. Pengujian Aksi: "Mulai Pengantaran" (ready -> out_for_delivery)
    // -------------------------------------------------------------------------
    echo "\n--- 8. Aksi Operasional: Mulai Pengantaran Kurir ---\n";
    $startRes = $orderModel->startDelivery($orderIdB, 1, 4);
    assertTest("Aksi startDelivery berhasil", $startRes['success'] === true);

    $orderBOut = $orderModel->find($orderIdB);
    assertTest("Status Order B berubah menjadi 'out_for_delivery'", ($orderBOut['status'] ?? '') === Order::STATUS_OUT_FOR_DELIVERY);

    // -------------------------------------------------------------------------
    // 9. Pengujian Keutuhan Nomor WhatsApp Customer
    // -------------------------------------------------------------------------
    echo "\n--- 9. Verifikasi Nomor WhatsApp Customer Tetap Asli ---\n";
    $custStmt = $pdo->prepare("SELECT u.phone FROM orders o JOIN users u ON o.customer_id = u.id WHERE o.id = :oid");
    $custStmt->execute(['oid' => $orderIdB]);
    $phoneActual = $custStmt->fetchColumn();

    $expectedPhoneStmt = $pdo->prepare("SELECT phone FROM users WHERE id = 6");
    $expectedPhoneStmt->execute();
    $expectedPhone = $expectedPhoneStmt->fetchColumn();

    assertTest("Nomor WhatsApp customer tidak kosong dan sesuai data users", !empty($phoneActual) && $phoneActual === $expectedPhone);

    // -------------------------------------------------------------------------
    // 10. Pengujian Multi-Store Security Guard
    // -------------------------------------------------------------------------
    echo "\n--- 10. Keamanan Multi-Toko: Proteksi Akses Toko Lain ---\n";
    // Admin Toko 1 mencoba memproses Order D milik Toko 2 -> harus ditolak!
    $illegalPickup = $orderModel->markAsPickedUp($orderIdD, 1, 4);
    assertTest("Admin Toko 1 DITOLAK memproses order Toko 2", $illegalPickup['success'] === false);

    $illegalAssign = $orderModel->assignDeliveryCourier($orderIdD, 1, 5, 4);
    assertTest("Admin Toko 1 DITOLAK menugaskan kurir untuk order Toko 2", $illegalAssign['success'] === false);

} catch (\Throwable $e) {
    echo "  [ERROR EXCEPTION] " . $e->getMessage() . "\n";
    $failCount++;
} finally {
    // -------------------------------------------------------------------------
    // CLEANUP: Hapus seluruh test data agar database tetap bersih
    // -------------------------------------------------------------------------
    if (!empty($createdOrderIds)) {
        $inList = implode(',', array_map('intval', $createdOrderIds));
        $pdo->exec("DELETE FROM order_status_histories WHERE order_id IN ({$inList})");
        $pdo->exec("DELETE FROM notifications WHERE message LIKE '%ORD-TEST-%'");
        $pdo->exec("DELETE FROM orders WHERE id IN ({$inList})");
    }
}

echo "\n==============================================================================\n";
echo "HASIL AKHIR TEST SIAP DIAMBIL / DIANTAR: {$passCount} PASS, {$failCount} FAIL\n";
echo "==============================================================================\n";

if ($failCount > 0) {
    exit(1);
}

