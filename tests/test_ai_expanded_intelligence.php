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
use App\Models\User;
use App\Models\Store;
use App\Services\Ai\AiAssistantService;
use App\Services\Ai\AiDataProvider;
use App\Services\Ai\MockAiClient;

echo "\n==============================================================================\n";
echo "   TEST SUITE: AI EXPANDED DATABASE INTELLIGENCE, SCOPING & SECURITY         \n";
echo "==============================================================================\n\n";

$pdo = Database::getInstance();

$passed = 0;
$failed = 0;

function assertTest(bool $condition, string $description, ?string $detail = null): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "[PASS] {$description}\n";
    } else {
        $failed++;
        echo "[FAIL] {$description}\n";
        if ($detail !== null) {
            echo "       Detail: {$detail}\n";
        }
    }
}

// Catat ID pesan uji yang ditambahkan
$testInsertedMessageIds = [];

// Buat wrapper / rekam ID sebelum dan sesudah uji
$maxIdBefore = (int) $pdo->query("SELECT COALESCE(MAX(id), 0) FROM ai_chat_messages")->fetchColumn();
$tables = ['orders', 'users', 'stores', 'services', 'refunds', 'payments', 'whatsapp_logs', 'notifications', 'ai_chat_messages'];
$initialCounts = [];
foreach ($tables as $t) {
    $initialCounts[$t] = (int) $pdo->query("SELECT COUNT(*) FROM {$t}")->fetchColumn();
}

$mockClient = new MockAiClient();
$dataProvider = new AiDataProvider($pdo);
$service = new AiAssistantService($mockClient, null, $dataProvider);

// Cari user untuk setiap role
$adminOwnerUser = $pdo->query("SELECT u.id, u.name, r.name as role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE r.name = 'admin_owner' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$adminTokoUser = $pdo->query("SELECT u.id, u.name, u.store_id, r.name as role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE r.name = 'admin_toko' AND u.store_id = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$customerUser = $pdo->query("SELECT u.id, u.name, r.name as role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE r.name = 'customer' LIMIT 1")->fetch(PDO::FETCH_ASSOC);

echo "--- 1. Uji (a): Pertanyaan Harga Layanan Dijawab Sesuai Tabel Services ---\n";
// Prompt untuk admin_toko toko 1 (CleanMaster) harus memuat layanan toko 1 saja
$promptAdminToko = $service->buildSystemPrompt((int)$adminTokoUser['id']);
assertTest(str_contains($promptAdminToko, "Cuci Komplit Reguler") && str_contains($promptAdminToko, "6.000"), "System prompt admin toko 1 memuat Cuci Komplit Reguler Rp 6.000");
assertTest(str_contains($promptAdminToko, "Dry Cleaning Jas / Tuxedo") && str_contains($promptAdminToko, "35.000"), "System prompt admin toko 1 memuat Dry Cleaning Jas Rp 35.000");
assertTest(!str_contains($promptAdminToko, "Cuci Kering Lipat Hemat"), "System prompt admin toko 1 TIDAK memuat layanan toko 2 (Cuci Kering Lipat Hemat)");

// Respon pertanyaan harga layanan
$resPrice = $service->sendMessage((int)$adminTokoUser['id'], "Berapa harga layanan cuci komplit reguler?");
assertTest($resPrice['success'] === true, "Query harga layanan dieksekusi sukses");
assertTest(str_contains($resPrice['reply'], "6.000") || str_contains($resPrice['reply'], "Cuci Komplit Reguler"), "Jawaban AI memuat harga 6.000 sesuai tabel services", $resPrice['reply']);

echo "\n--- 2. Uji (b): Toko Tanpa Layanan Dijawab 'Tidak Ditemukan' Bukan Daftar Umum ---\n";
// Buat toko dummy sementara tanpa layanan
$pdo->exec("INSERT INTO stores (user_id, name, description, address, phone, is_active) VALUES ({$adminOwnerUser['id']}, 'Toko Uji Tanpa Layanan', 'Desc', 'Alamat', '08999999999', 1)");
$emptyStoreId = (int) $pdo->lastInsertId();

// Ambil katalog untuk emptyStoreId
$emptyCatalog = $dataProvider->getServicesCatalog($emptyStoreId);
assertTest(empty($emptyCatalog), "Katalog layanan toko tanpa layanan kosong");

// Buat user admin dummy sementara untuk toko kosong ini
$pdo->exec("INSERT INTO users (role_id, store_id, name, email, password, phone, is_active) VALUES (4, {$emptyStoreId}, 'Admin Toko Kosong', 'adm.kosong@test.com', 'dummy', '08999999998', 1)");
$emptyAdminUserId = (int) $pdo->lastInsertId();

$emptyPrompt = $service->buildSystemPrompt($emptyAdminUserId);
assertTest(str_contains($emptyPrompt, "Data layanan belum tersedia di database"), "System prompt toko kosong menyatakan data layanan belum tersedia di database");
assertTest(!str_contains($emptyPrompt, "KATALOG LAYANAN UMUM PLATFORM:\n- Cuci Komplit Reguler: Cuci + Kering"), "System prompt toko kosong TIDAK memuat katalog layanan hardcoded umum");

$resEmptyPrice = $service->sendMessage($emptyAdminUserId, "Berapa tarif cuci di toko ini?");
assertTest(str_contains(mb_strtolower($resEmptyPrice['reply']), "tidak ditemukan") || str_contains(mb_strtolower($resEmptyPrice['reply']), "belum tersedia"), "Jawaban AI untuk toko tanpa layanan menyatakan data tidak ditemukan / belum tersedia di database", $resEmptyPrice['reply']);

echo "\n--- 3. Uji (c): Admin Toko Tidak Mendapat Data Toko Lain (Services, WA Logs, Notif) ---\n";
$waSummaryToko1 = $dataProvider->getWhatsAppLogsSummary((int)$adminTokoUser['id'], 'admin_toko', 1);
assertTest($waSummaryToko1 !== null, "Admin toko 1 berhasil mendapatkan ringkasan log WA untuk tokonya");
assertTest($waSummaryToko1['cakupan'] === 'toko', "Cakupan WA summary admin toko adalah 'toko'");

// Pastikan tidak memuat log toko lain
$promptToko1 = $service->buildSystemPrompt((int)$adminTokoUser['id']);
assertTest(str_contains($promptToko1, "Ringkasan Notifikasi WhatsApp (toko)"), "System prompt admin toko 1 memuat ringkasan WA toko");
assertTest(!str_contains($promptToko1, "Ringkasan Notifikasi WhatsApp (platform)"), "System prompt admin toko 1 TIDAK memuat ringkasan WA platform");

echo "\n--- 4. Uji (d): Provider Response dan Kolom Sensitif Tidak Pernah Masuk ke Prompt ---\n";
assertTest(!str_contains($promptToko1, "provider_response"), "Prompt tidak pernah memuat kolom provider_response");
assertTest(!str_contains($promptToko1, "access_token"), "Prompt tidak pernah memuat access_token");
assertTest(!str_contains($promptToko1, "password"), "Prompt tidak pernah memuat kolom password");
assertTest(!str_contains($promptToko1, "remember_token"), "Prompt tidak pernah memuat remember_token");
assertTest(!str_contains($promptToko1, "google_id"), "Prompt tidak pernah memuat google_id");

echo "\n--- 5. Uji (e): Customer Tidak Mendapat Data whatsapp_logs ---\n";
$waSummaryCustomer = $dataProvider->getWhatsAppLogsSummary((int)$customerUser['id'], 'customer', null);
assertTest($waSummaryCustomer === null, "getWhatsAppLogsSummary mengembalikan null untuk role customer");

$customerPrompt = $service->buildSystemPrompt((int)$customerUser['id']);
assertTest(!str_contains($customerPrompt, "Ringkasan Notifikasi WhatsApp"), "System prompt customer TIDAK memuat blok log WhatsApp");
assertTest(!str_contains($customerPrompt, "DATA_OPERASIONAL_START"), "System prompt customer TIDAK memuat data operasional");

// Bersihkan data uji toko dummy & user dummy
$pdo->exec("DELETE FROM ai_chat_messages WHERE user_id = {$emptyAdminUserId}");
$pdo->exec("DELETE FROM users WHERE id = {$emptyAdminUserId}");
$pdo->exec("DELETE FROM stores WHERE id = {$emptyStoreId}");

// Bersihkan chat messages yang dihasilkan HANYA selama tes ini (id > maxIdBefore)
$pdo->exec("DELETE FROM ai_chat_messages WHERE id > {$maxIdBefore}");

// Verifikasi kebersihan database
$finalCounts = [];
foreach ($tables as $t) {
    $finalCounts[$t] = (int) $pdo->query("SELECT COUNT(*) FROM {$t}")->fetchColumn();
}

echo "\n==============================================================================\n";
echo "   HASIL VERIFIKASI ROW COUNT DATABASE SEBELUM VS SESUDAH TEST               \n";
echo "==============================================================================\n";
$isClean = true;
foreach ($tables as $t) {
    $diff = $finalCounts[$t] - $initialCounts[$t];
    echo sprintf("%-20s: Awal=%-5d | Akhir=%-5d | Selisih=%d\n", $t, $initialCounts[$t], $finalCounts[$t], $diff);
    if ($diff !== 0) {
        $isClean = false;
    }
}
assertTest($isClean, "Database 100% bersih kembali tanpa menyisakan data sampah uji");

echo "\n==============================================================================\n";
echo "HASIL AKHIR: {$passed} PASSED, {$failed} FAILED\n";
echo "==============================================================================\n";
