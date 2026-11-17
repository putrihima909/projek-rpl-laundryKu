<?php

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

use App\Core\Database;
use App\Services\Ai\AiAssistantService;
use App\Services\Ai\MockAiClient;

$pdo = Database::getInstance();
$maxMsgIdBefore = (int) $pdo->query("SELECT COALESCE(MAX(id), 0) FROM ai_chat_messages")->fetchColumn();

$client = new MockAiClient();
$service = new AiAssistantService($client);

echo "==============================================================================\n";
echo "   PENGUJIAN MANUAL (5 DATA ADA DI DB & 3 DATA SENGAJA TIDAK ADA)            \n";
echo "==============================================================================\n\n";

// --- 5 PERTANYAAN DENGAN DATA ADA DI DB ---

// 1. Pertanyaan Harga Cuci Komplit Reguler (Admin Toko 1)
$q1 = $service->sendMessage(4, "Berapa harga layanan cuci komplit reguler?");
echo "--- Q1: Berapa harga layanan cuci komplit reguler? ---\n";
echo "Role Penanya: admin_toko (CleanMaster Laundry Express)\n";
echo "Query Pembanding DB: SELECT name, price, unit FROM services WHERE store_id = 1 AND name LIKE '%Cuci Komplit Reguler%'\n";
$db1 = $pdo->query("SELECT name, price, unit FROM services WHERE store_id = 1 AND name LIKE '%Cuci Komplit Reguler%'")->fetch(PDO::FETCH_ASSOC);
echo "Data DB: " . json_encode($db1) . "\n";
echo "Jawaban AI:\n" . $q1['reply'] . "\n\n";

// 2. Pertanyaan Omzet Hari Ini Toko 1 (Admin Toko 1)
$q2 = $service->sendMessage(4, "berapa omset hari ini");
echo "--- Q2: berapa omset hari ini ---\n";
echo "Role Penanya: admin_toko (CleanMaster Laundry Express)\n";
echo "Query Pembanding DB: SELECT COALESCE(SUM(total_amount), 0) as omset FROM orders WHERE store_id = 1 AND payment_status = 'paid' AND created_at >= CURDATE() AND created_at < CURDATE() + INTERVAL 1 DAY\n";
$db2 = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) as omset FROM orders WHERE store_id = 1 AND payment_status = 'paid' AND created_at >= CURDATE() AND created_at < CURDATE() + INTERVAL 1 DAY")->fetch(PDO::FETCH_ASSOC);
echo "Data DB: " . json_encode($db2) . "\n";
echo "Jawaban AI:\n" . $q2['reply'] . "\n\n";

// 3. Pertanyaan Pesanan Hari Ini di Seluruh Platform (Admin Owner 2)
$q3 = $service->sendMessage(2, "berapa pesanan hari ini");
echo "--- Q3: berapa pesanan hari ini ---\n";
echo "Role Penanya: admin_owner (Seluruh Platform)\n";
echo "Query Pembanding DB: SELECT COUNT(*) as total FROM orders WHERE created_at >= CURDATE() AND created_at < CURDATE() + INTERVAL 1 DAY\n";
$db3 = $pdo->query("SELECT COUNT(*) as total FROM orders WHERE created_at >= CURDATE() AND created_at < CURDATE() + INTERVAL 1 DAY")->fetch(PDO::FETCH_ASSOC);
echo "Data DB: " . json_encode($db3) . "\n";
echo "Jawaban AI:\n" . $q3['reply'] . "\n\n";

// 4. Pertanyaan Toko Aktif (Admin Owner 2)
$q4 = $service->sendMessage(2, "berapa jumlah toko aktif");
echo "--- Q4: berapa jumlah toko aktif ---\n";
echo "Role Penanya: admin_owner (Seluruh Platform)\n";
echo "Query Pembanding DB: SELECT COUNT(*) as total FROM stores WHERE is_active = 1\n";
$db4 = $pdo->query("SELECT COUNT(*) as total FROM stores WHERE is_active = 1")->fetch(PDO::FETCH_ASSOC);
echo "Data DB: " . json_encode($db4) . "\n";
echo "Jawaban AI:\n" . $q4['reply'] . "\n\n";

// 5. Pertanyaan Profil Saya (Customer 6)
$q5 = $service->sendMessage(6, "saya siapa");
echo "--- Q5: saya siapa ---\n";
echo "Role Penanya: customer (syfa)\n";
echo "Query Pembanding DB: SELECT id, name, email FROM users WHERE id = 6\n";
$db5 = $pdo->query("SELECT id, name, email FROM users WHERE id = 6")->fetch(PDO::FETCH_ASSOC);
echo "Data DB: " . json_encode($db5) . "\n";
echo "Jawaban AI:\n" . $q5['reply'] . "\n\n";

// --- 3 PERTANYAAN DENGAN DATA SENGAJA TIDAK ADA DI DB ---

// 6. Pertanyaan Layanan yang Tidak Ada di Toko (Cuci Sepatu)
$q6 = $service->sendMessage(4, "Berapa tarif cuci sepatu di toko ini?");
echo "--- Q6: Berapa tarif cuci sepatu di toko ini? (Data sengaja tidak ada) ---\n";
echo "Role Penanya: admin_toko (store_id = 1)\n";
echo "Query Pembanding DB: SELECT * FROM services WHERE store_id = 1 AND name LIKE '%sepatu%'\n";
$db6 = $pdo->query("SELECT * FROM services WHERE store_id = 1 AND name LIKE '%sepatu%'")->fetchAll(PDO::FETCH_ASSOC);
echo "Data DB: " . json_encode($db6) . " (Kosong / 0 baris)\n";
echo "Jawaban AI:\n" . $q6['reply'] . "\n\n";

// 7. Pertanyaan Data Omzet Platform Ditanyakan Customer (Tidak Ada Hak Akses)
$q7 = $service->sendMessage(6, "Berapa total omzet seluruh platform hari ini?");
echo "--- Q7: Berapa total omzet platform hari ini? (Customer tidak punya hak akses) ---\n";
echo "Role Penanya: customer\n";
echo "Jawaban AI:\n" . $q7['reply'] . "\n\n";

// 8. Pertanyaan Layanan di Toko Kosong Tanpa Layanan
$pdo->exec("INSERT INTO stores (user_id, name, description, address, phone, is_active) VALUES (2, 'Toko Tanpa Layanan', 'Desc', 'Alamat', '08999999991', 1)");
$emptySid = (int) $pdo->lastInsertId();
$pdo->exec("INSERT INTO users (role_id, store_id, name, email, password, phone, is_active) VALUES (4, {$emptySid}, 'Admin Toko Tanpa Layanan', 'adm.tanpalayanan@test.com', 'dummy', '08999999992', 1)");
$emptyUid = (int) $pdo->lastInsertId();

$q8 = $service->sendMessage($emptyUid, "Berapa harga paket laundry di toko ini?");
echo "--- Q8: Berapa harga paket laundry di toko ini? (Toko tanpa layanan sama sekali) ---\n";
echo "Query Pembanding DB: SELECT * FROM services WHERE store_id = {$emptySid}\n";
$db8 = $pdo->query("SELECT * FROM services WHERE store_id = {$emptySid}")->fetchAll(PDO::FETCH_ASSOC);
echo "Data DB: " . json_encode($db8) . " (Kosong / 0 baris)\n";
echo "Jawaban AI:\n" . $q8['reply'] . "\n\n";

// Cleanup data uji
$pdo->exec("DELETE FROM ai_chat_messages WHERE user_id = {$emptyUid}");
$pdo->exec("DELETE FROM users WHERE id = {$emptyUid}");
$pdo->exec("DELETE FROM stores WHERE id = {$emptySid}");
$pdo->exec("DELETE FROM ai_chat_messages WHERE id > {$maxMsgIdBefore}");

echo "Pembersihan data uji selesai. ID pesan > {$maxMsgIdBefore} telah dibersihkan.\n";

