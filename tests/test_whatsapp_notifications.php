<?php

/**
 * Automated Test Suite: Modul Notifikasi WhatsApp Otomatis (Tahap WhatsApp Gateway)
 * ==============================================================================
 * Menguji secara komprehensif:
 *  1.  PhoneHelper — Normalisasi nomor lokal/internasional ke format 628...
 *  2.  PhoneHelper — Validasi nomor ponsel Indonesia valid vs tidak valid
 *  3.  PhoneHelper — Format internasional (+628...) dan format nasional (0812-...)
 *  4.  WhatsAppClientInterface — Implementasi MockProvider & tracking riwayat pesan
 *  5.  CloudApiWhatsAppClient & GenericGateway — Graceful failure jika kredensial belum ada (tanpa crash)
 *  6.  WhatsAppTemplate — Render seluruh template pelanggan dengan placeholder aktual
 *  7.  WhatsAppTemplate — Render seluruh template owner toko dengan placeholder aktual
 *  8.  WhatsAppTemplate — Render seluruh template kurir & platform alert
 *  9.  Customer Event: Registrasi akun baru (customer_registered)
 * 10.  Customer & Store Event: Pembuatan pesanan baru (order_created & store_new_order)
 * 11.  Customer & Courier Event: Penugasan kurir (order_assigned_to_courier & courier_task_assigned)
 * 12.  Customer Event: Penimbangan pesanan selesai (order_weighed)
 * 13.  Customer Event: Transisi status pesanan (processing, ready, out_for_delivery, delivered)
 * 14.  Store Owner Event: Transisi status pesanan (store_status_changed)
 * 15.  Courier Event: Cucian selesai dan siap diambil di toko (courier_order_ready)
 * 16.  Pembatalan Pesanan: Notifikasi ke Customer, Owner Toko, dan Kurir
 * 17.  Customer & Store Event: Pembayaran berhasil terverifikasi (payment_success & store_payment_verified)
 * 18.  Customer Event: Pembayaran gagal/kedaluwarsa (payment_failed)
 * 19.  Refund Event: Pengajuan refund (refund_submitted & store_refund_requested)
 * 20.  Refund Event: Penyelesaian refund completed & failed
 * 21.  Platform Alert: Peringatan sistem kritis ke Platform Owner
 * 22.  Pencegahan Pesan Ganda (Idempotency Key): Pengiriman duplikat di-skip otomatis
 * 23.  Sistem Retry Otomatis: Provider gagal sementara berhasil ditransmisikan ulang
 * 24.  Retry Manual: retryFailed(logId) mengulangi pengiriman log berstatus failed
 * 25.  Database Logging: Tabel whatsapp_logs menyimpan detail lengkap tanpa token rahasia
 * 26.  Pemisahan Kegagalan: Transaksi database (order/payment/refund) tetap sukses meski provider WA error
 * 27.  RBAC & Multi-Store Isolation: Owner Toko A tidak menerima notifikasi pesanan Toko B
 * 28.  Persetujuan Pengguna (Opt-in / Opt-out): Pengiriman di-skip jika pengguna mematikan notifikasi WA
 * ==============================================================================
 */

// ── Bootstrap ──────────────────────────────────────────────────────────────
spl_autoload_register(function ($class) {
    $prefix   = 'App\\';
    $base_dir = __DIR__ . '/../app/';
    $len      = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relative_class = substr($class, $len);
    $file           = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($file)) require_once $file;
});

use App\Core\Database;
use App\Helpers\PhoneHelper;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Services\Payment\PaymentService;
use App\Services\WhatsApp\Providers\CloudApiWhatsAppClient;
use App\Services\WhatsApp\Providers\FonnteWhatsAppClient;
use App\Services\WhatsApp\Providers\GenericGatewayWhatsAppClient;
use App\Services\WhatsApp\Providers\MockWhatsAppClient;
use App\Services\WhatsApp\WhatsAppNotificationService;
use App\Services\WhatsApp\WhatsAppTemplate;

$passCount = 0;
$failCount = 0;

function assertTest(bool $condition, string $message): void
{
    global $passCount, $failCount;
    if ($condition) {
        $passCount++;
        echo "  [PASS] {$message}\n";
    } else {
        $failCount++;
        echo "  [FAIL] {$message}\n";
    }
}

echo "\n" . str_repeat('=', 78) . "\n";
echo "       AUTOMATED TEST SUITE: SISTEM NOTIFIKASI WHATSAPP OTOMATIS\n";
echo str_repeat('=', 78) . "\n\n";

$db = Database::getInstance();

// Clean up prior test logs to ensure repeatability
$db->exec("DELETE FROM whatsapp_logs WHERE order_code LIKE 'ORD-TEST%' OR idempotency_key LIKE 'test:%' OR idempotency_key LIKE 'retry:%' OR idempotency_key LIKE 'fail:%' OR idempotency_key LIKE 'optin:%' OR idempotency_key LIKE 'cust:customer_registered:%' OR order_code = 'ORD-STORE-1' OR order_code = 'ORD-STORE-2'");

$mockClient = new MockWhatsAppClient();
MockWhatsAppClient::clear();
$waService = new WhatsAppNotificationService($mockClient);

// ── 1. PhoneHelper: Normalisasi & Validasi Nomor Telepon ──────────────────────
echo "--- 1. PhoneHelper: Normalisasi & Validasi Format Nomor Telepon ---\n";

assertTest(PhoneHelper::normalize('081234567890') === '6281234567890', 'Normalisasi prefix lokal 08 -> 628');
assertTest(PhoneHelper::normalize('+6281234567890') === '6281234567890', 'Normalisasi prefix internasional +628 -> 628');
assertTest(PhoneHelper::normalize('6281234567890') === '6281234567890', 'Normalisasi nomor yang sudah format 628 tetap utuh');
assertTest(PhoneHelper::normalize('0812-3456-7890') === '6281234567890', 'Pembersihan tanda strip/spasi pada nomor lokal');
assertTest(PhoneHelper::normalize('+62 812 3456 7890') === '6281234567890', 'Pembersihan spasi dan simbol plus pada nomor internasional');
assertTest(PhoneHelper::normalize('81234567890') === '6281234567890', 'Normalisasi nomor tanpa leading 0 atau +62 (812...)');

assertTest(PhoneHelper::isValid('081234567890') === true, 'Validasi nomor ponsel 081234567890 (valid)');
assertTest(PhoneHelper::isValid('+628987654321') === true, 'Validasi nomor ponsel +628987654321 (valid)');
assertTest(PhoneHelper::isValid('0215551234') === false, 'Validasi nomor telepon rumah/kantor 021 ditolak (bukan mobile 628)');
assertTest(PhoneHelper::isValid('12345') === false, 'Validasi nomor terlalu pendek ditolak');
assertTest(PhoneHelper::isValid('abcdef') === false, 'Validasi string bukan angka ditolak');
assertTest(PhoneHelper::isValid(null) === false, 'Validasi null ditolak');

assertTest(PhoneHelper::formatInternational('081234567890') === '+6281234567890', 'Format internasional menghasilkan leading +');
assertTest(str_starts_with(PhoneHelper::formatNational('6281234567890'), '0812-'), 'Format nasional menghasilkan prefix 0812-');

// ── 2. WhatsApp Provider Architecture ───────────────────────────────────────
echo "\n--- 2. WhatsApp Provider Architecture & Fallback Safety ---\n";

// 2.1 Fonnte WhatsApp Client
$fonnteUnconfigured = new FonnteWhatsAppClient(['token' => '']);
assertTest($fonnteUnconfigured->isConfigured() === false, 'FonnteWhatsAppClient mengenali status jika token kosong');
assertTest($fonnteUnconfigured->getMaskedToken() === '(Belum dikonfigurasi)', 'FonnteWhatsAppClient menampilkan label belum dikonfigurasi pada token kosong');
$fonnteEmptyRes = $fonnteUnconfigured->sendMessage('081234567890', 'Uji coba');
assertTest($fonnteEmptyRes['success'] === false && str_contains($fonnteEmptyRes['error'], 'belum dikonfigurasi'), 'Fonnte sendMessage mengembalikan error deskriptif jika token belum diisi');
$fonnteDevStatus = $fonnteUnconfigured->checkDeviceStatus();
assertTest($fonnteDevStatus['configured'] === false && $fonnteDevStatus['connected'] === false, 'Fonnte checkDeviceStatus mengembalikan status unconfigured jika token kosong');

$fonnteConfigured = new FonnteWhatsAppClient(['token' => 'fonnte_secret_token_12345678_abcd']);
assertTest($fonnteConfigured->isConfigured() === true, 'FonnteWhatsAppClient mengenali status terkonfigurasi');
assertTest(str_starts_with($fonnteConfigured->getMaskedToken(), 'fonn') && str_ends_with($fonnteConfigured->getMaskedToken(), 'abcd'), 'Fonnte getMaskedToken menyembunyikan bagian tengah token dengan bintang');
assertTest(!str_contains($fonnteConfigured->getMaskedToken(), 'secret_token'), 'Fonnte getMaskedToken tidak mengekspos token utuh');

// Fonnte API Response Parsing Simulations
$mockSuccessBody = json_encode(['id' => ['fonnte_msg_001'], 'process' => 'pending', 'status' => true, 'target' => ['6281234567890']]);
$resOk = $fonnteConfigured->parseSendResponse(200, $mockSuccessBody);
assertTest($resOk['success'] === true && $resOk['status'] === 'sent' && $resOk['message_id'] === 'fonnte_msg_001', 'Fonnte parseSendResponse menangani respons SUKSES dari Fonnte API');

$mockInvalidTokenBody = json_encode(['status' => false, 'reason' => 'token invalid']);
$resInvalid = $fonnteConfigured->parseSendResponse(200, $mockInvalidTokenBody);
assertTest($resInvalid['success'] === false && $resInvalid['status'] === 'failed' && str_contains($resInvalid['error'], 'token invalid'), 'Fonnte parseSendResponse menangani respons TOKEN INVALID');

$mockDisconnectBody = json_encode(['status' => false, 'reason' => 'device disconnect']);
$resDisconnect = $fonnteConfigured->parseSendResponse(200, $mockDisconnectBody);
assertTest($resDisconnect['success'] === false && str_contains($resDisconnect['error'], 'device disconnect'), 'Fonnte parseSendResponse menangani respons DEVICE DISCONNECT');

$resTimeout = $fonnteConfigured->parseSendResponse(0, '', 'Operation timed out after 30000 milliseconds with 0 bytes received', 28);
assertTest($resTimeout['success'] === false && $resTimeout['http_code'] === 408, 'Fonnte parseSendResponse menangani cURL timeout dengan HTTP 408');

$mockDeviceOk = json_encode(['device' => '6281234567890', 'device_status' => 'connect', 'name' => 'Laundry Bot', 'package' => 'Pro', 'quota' => '1000', 'status' => true]);
$devResOk = $fonnteConfigured->parseDeviceResponse(200, $mockDeviceOk);
assertTest($devResOk['connected'] === true && $devResOk['quota'] === '1000', 'Fonnte parseDeviceResponse menangani status perangkat CONNECTED');

$mockDeviceInvalid = json_encode(['status' => false, 'reason' => 'token invalid']);
$devResFail = $fonnteConfigured->parseDeviceResponse(200, $mockDeviceInvalid);
assertTest($devResFail['connected'] === false && $devResFail['reason'] === 'token invalid', 'Fonnte parseDeviceResponse menangani status perangkat TOKEN INVALID');

// 2.2 Cloud API & Generic Gateway
$cloudApi = new CloudApiWhatsAppClient([
    'api_url' => 'https://graph.facebook.com/v19.0',
    'phone_number_id' => '',
    'access_token' => '',
]);
assertTest($cloudApi->isConfigured() === false, 'CloudApiWhatsAppClient mengenali status jika kredensial belum diisi');
$cloudRes = $cloudApi->sendMessage('6281234567890', 'Test');
assertTest($cloudRes['success'] === false && str_contains($cloudRes['error'], 'not configured'), 'Cloud API mengembalikan pesan kegagalan aman tanpa crash saat kredensial kosong');

$genericGw = new GenericGatewayWhatsAppClient(['endpoint' => '', 'api_key' => '']);
assertTest($genericGw->isConfigured() === false, 'GenericGatewayWhatsAppClient mengenali status belum terkonfigurasi');
$gwRes = $genericGw->sendMessage('6281234567890', 'Test');
assertTest($gwRes['success'] === false && str_contains($gwRes['error'], 'not configured'), 'Generic Gateway mengembalikan pesan kegagalan aman tanpa crash');

assertTest($mockClient->getName() === 'mock', 'MockWhatsAppClient provider name adalah mock');
$mockRes = $mockClient->sendMessage('6281234567890', 'Halo dari Unit Test');
assertTest($mockRes['success'] === true && !empty($mockRes['message_id']), 'MockWhatsAppClient berhasil mengirim dan memberikan message_id');

// ── 3. WhatsApp Templates Rendering ─────────────────────────────────────────
echo "\n--- 3. WhatsApp Template Engine: Contextual Placeholders ---\n";

$sampleData = [
    'customer_name' => 'Putri Ayu',
    'customer_phone' => '081234567895',
    'order_code' => 'ORD-20261030-999',
    'store_name' => 'CleanMaster Laundry Express',
    'service_name' => 'Cuci Komplit Reguler',
    'total_amount' => 45000,
    'estimated_amount' => 45000,
    'estimated_weight' => 5.0,
    'actual_weight' => 4.5,
    'courier_name' => 'Agus Express',
    'courier_phone' => '081234567894',
    'owner_name' => 'Hendra Wijaya',
    'amount' => 45000,
    'payment_method' => 'QRIS',
    'reason' => 'Perubahan jadwal pelanggan',
    'gateway_reference' => 'QRIS-REF-998877',
    'notes' => 'Harap jemput sebelum jam 12',
    'address' => 'Jl. Merdeka No. 10, Jakarta Selatan',
];

$msgCreated = WhatsAppTemplate::render('order_created', $sampleData);
assertTest(str_contains($msgCreated, 'Putri Ayu') && str_contains($msgCreated, 'ORD-20261030-999') && str_contains($msgCreated, '45.000'), 'Template order_created menyertakan nama, kode pesanan, dan nominal');

$msgPayment = WhatsAppTemplate::render('payment_success', $sampleData);
assertTest(str_contains($msgPayment, 'berhasil diverifikasi') && str_contains($msgPayment, 'QRIS'), 'Template payment_success menyertakan konfirmasi dan metode QRIS');

$msgRefund = WhatsAppTemplate::render('refund_completed', $sampleData);
assertTest(str_contains($msgRefund, 'QRIS-REF-998877') && str_contains($msgRefund, 'berhasil diselesaikan'), 'Template refund_completed menyertakan gateway_reference');

$msgStoreNew = WhatsAppTemplate::render('store_new_order', $sampleData);
assertTest(str_contains($msgStoreNew, 'Hendra Wijaya') && str_contains($msgStoreNew, 'Pesanan baru masuk'), 'Template store_new_order menyapa nama Owner Toko');

$msgCourierTask = WhatsAppTemplate::render('courier_task_assigned', $sampleData);
assertTest(str_contains($msgCourierTask, 'Agus Express') && str_contains($msgCourierTask, 'Jl. Merdeka No. 10'), 'Template courier_task_assigned menyertakan alamat penjemputan');

// ── 4. Customer Event Triggers ──────────────────────────────────────────────
echo "\n--- 4. Customer Events: Siklus Hidup Pesanan & Pembayaran ---\n";

MockWhatsAppClient::clear();

// 4.1 Registrasi User
$custRes = $waService->sendCustomerNotification(6, 'customer_registered', [
    'name' => 'putri ngawi',
    'email' => 'putri@test.com',
    'customer_phone' => '081234567895'
]);
assertTest($custRes['success'] === true && $custRes['status'] === 'sent', 'Event customer_registered terkirim ke customer #6');
$lastMsg = MockWhatsAppClient::getLastMessage();
assertTest(str_contains($lastMsg['to'], '6281234567895') && str_contains($lastMsg['message'], 'Selamat datang di LaundryKu'), 'Pesan selamat datang berisi nomor tujuan terformat dan teks sambutan');

// 4.2 Pembuatan Pesanan Baru
$orderDataTest = array_merge($sampleData, ['order_code' => 'ORD-TEST-001', 'customer_id' => 6, 'store_id' => 1]);
$resOrderCreated = $waService->sendCustomerNotification(6, 'order_created', $orderDataTest);
assertTest($resOrderCreated['success'] === true, 'Event order_created terkirim ke pelanggan');

// 4.3 Penugasan Kurir
$resCourierAssigned = $waService->sendCustomerNotification(6, 'order_assigned_to_courier', $orderDataTest);
assertTest($resCourierAssigned['success'] === true, 'Event order_assigned_to_courier terkirim ke pelanggan');

// 4.4 Penimbangan
$resWeighed = $waService->sendCustomerNotification(6, 'order_weighed', array_merge($orderDataTest, ['actual_weight' => 4.2, 'total_amount' => 42000]));
assertTest($resWeighed['success'] === true && str_contains(MockWhatsAppClient::getLastMessage()['message'], '4.2 kg'), 'Event order_weighed mengabarkan berat aktual dan total final');

// 4.5 Menunggu Pembayaran
$resWaitPay = $waService->sendCustomerNotification(6, 'order_waiting_payment', $orderDataTest);
assertTest($resWaitPay['success'] === true, 'Event order_waiting_payment terkirim ke pelanggan');

// 4.6 Pembayaran Berhasil
$resPaySuccess = $waService->sendCustomerNotification(6, 'payment_success', array_merge($orderDataTest, ['amount' => 42000, 'payment_method' => 'QRIS']));
assertTest($resPaySuccess['success'] === true && str_contains(MockWhatsAppClient::getLastMessage()['message'], '42.000'), 'Event payment_success mengonfirmasi nominal lunas');

// 4.7 Pembayaran Gagal
$resPayFailed = $waService->sendCustomerNotification(6, 'payment_failed', array_merge($orderDataTest, ['amount' => 42000]));
assertTest($resPayFailed['success'] === true, 'Event payment_failed mengabarkan instruksi pembayaran ulang');

// 4.8 Status Operasional (processing, ready, out_for_delivery, delivered)
$statuses = ['order_processing', 'order_ready', 'order_out_for_delivery', 'order_delivered'];
foreach ($statuses as $st) {
    $r = $waService->sendCustomerNotification(6, $st, $orderDataTest);
    assertTest($r['success'] === true, "Event {$st} terkirim ke pelanggan");
}

// 4.9 Pembatalan Pesanan
$resCancel = $waService->sendCustomerNotification(6, 'order_cancelled', array_merge($orderDataTest, ['notes' => 'Dibatalkan oleh pelanggan']));
assertTest($resCancel['success'] === true, 'Event order_cancelled terkirim ke pelanggan');

// 4.10 Pengajuan Refund & Penyelesaian Refund
$resRefundSubmit = $waService->sendCustomerNotification(6, 'refund_submitted', array_merge($orderDataTest, ['amount' => 42000, 'reason' => 'Batal cuci']));
assertTest($resRefundSubmit['success'] === true, 'Event refund_submitted terkirim ke pelanggan');

$resRefundDone = $waService->sendCustomerNotification(6, 'refund_completed', array_merge($orderDataTest, ['amount' => 42000, 'gateway_reference' => 'MOCK-REF-777']));
assertTest($resRefundDone['success'] === true, 'Event refund_completed terkirim ke pelanggan');

// ── 5. Store Owner Event Triggers ───────────────────────────────────────────
echo "\n--- 5. Store Owner Events: Notifikasi Operasional Toko ---\n";

MockWhatsAppClient::clear();

// 5.1 Pesanan baru masuk untuk Toko 1 (CleanMaster - Owner ID: 3, Hendra Wijaya)
$resStoreNew = $waService->sendStoreOwnerNotification(1, 'store_new_order', $orderDataTest);
assertTest($resStoreNew['success'] === true, 'Event store_new_order terkirim ke Owner Toko 1');
$lastStoreMsg = MockWhatsAppClient::getLastMessage();
assertTest(str_contains($lastStoreMsg['to'], '6281234567892') || str_contains($lastStoreMsg['to'], '6281299887766'), 'Penerima nomor WhatsApp sesuai dengan nomor Owner/Store CleanMaster');

// 5.2 Pembayaran pelanggan terkonfirmasi
$resStorePay = $waService->sendStoreOwnerNotification(1, 'store_payment_verified', $orderDataTest);
assertTest($resStorePay['success'] === true, 'Event store_payment_verified terkirim ke Owner Toko');

// 5.3 Perubahan status operasional toko
$resStoreSt = $waService->sendStoreOwnerNotification(1, 'store_status_changed', array_merge($orderDataTest, ['status_label' => 'Cucian Selesai Dicuci']));
assertTest($resStoreSt['success'] === true, 'Event store_status_changed terkirim ke Owner Toko');

// 5.4 Pesanan dibatalkan
$resStoreCancel = $waService->sendStoreOwnerNotification(1, 'store_order_cancelled', array_merge($orderDataTest, ['notes' => 'Pembatalan sebelum kurir tiba']));
assertTest($resStoreCancel['success'] === true, 'Event store_order_cancelled terkirim ke Owner Toko');

// 5.5 Refund diajukan & diproses
$resStoreRefundReq = $waService->sendStoreOwnerNotification(1, 'store_refund_requested', $orderDataTest);
assertTest($resStoreRefundReq['success'] === true, 'Event store_refund_requested terkirim ke Owner Toko');

$resStoreRefundDone = $waService->sendStoreOwnerNotification(1, 'store_refund_processed', array_merge($orderDataTest, ['refund_status' => 'completed']));
assertTest($resStoreRefundDone['success'] === true, 'Event store_refund_processed terkirim ke Owner Toko');

// ── 6. Courier Event Triggers ───────────────────────────────────────────────
echo "\n--- 6. Courier Events: Penugasan & Tugas Pengantaran ---\n";

MockWhatsAppClient::clear();

// Kurir ID: 5 (Agus Express, 081234567894)
$resCrTask = $waService->sendCourierNotification(5, 'courier_task_assigned', $orderDataTest);
assertTest($resCrTask['success'] === true, 'Event courier_task_assigned terkirim ke Kurir #5');
$lastCrMsg = MockWhatsAppClient::getLastMessage();
assertTest(str_contains($lastCrMsg['to'], '6281234567894') && str_contains($lastCrMsg['message'], 'Agus Express'), 'Pesan terkirim ke nomor ponsel Kurir Agus Express');

$resCrReady = $waService->sendCourierNotification(5, 'courier_order_ready', $orderDataTest);
assertTest($resCrReady['success'] === true, 'Event courier_order_ready terkirim ke Kurir');

$resCrCancel = $waService->sendCourierNotification(5, 'courier_task_cancelled', $orderDataTest);
assertTest($resCrCancel['success'] === true, 'Event courier_task_cancelled terkirim ke Kurir');

// ── 7. Platform Owner Alert Trigger ─────────────────────────────────────────
echo "\n--- 7. Platform Owner Alert: Peringatan Sistem Kritis ---\n";

$resPlatform = $waService->sendPlatformOwnerAlert('GATEWAY_TIMEOUT', 'Midtrans/QRIS webhook simulator returned 504 Gateway Timeout', [
    'detail' => '3 consecutive webhook retries failed'
]);
assertTest($resPlatform['success'] === true && ($resPlatform['dispatched_count'] ?? 0) >= 1, 'Event platform_alert terkirim ke Owner Platform');

// ── 8. Idempotency: Pencegahan Pesan Ganda ──────────────────────────────────
echo "\n--- 8. Idempotency: Pencegahan Pesan Ganda ---\n";

$idempotentKey = 'test:idempotency:unique:ord999:status_paid';
MockWhatsAppClient::clear();

$firstSend = $waService->sendCustomerNotification(6, 'payment_success', $orderDataTest, $idempotentKey);
assertTest($firstSend['success'] === true && $firstSend['status'] === 'sent', 'Pengiriman pertama dengan kunci idempoten sukses (sent)');

$secondSend = $waService->sendCustomerNotification(6, 'payment_success', $orderDataTest, $idempotentKey);
assertTest($secondSend['status'] === 'skipped' && str_contains($secondSend['message'], 'sudah pernah terkirim'), 'Pengiriman kedua dengan kunci idempoten yang sama di-skip (tidak dobel kirim)');

assertTest(count(MockWhatsAppClient::getSentMessages()) === 1, 'Hanya 1 pesan fisik yang terkirim ke provider (terlindungi dari duplikasi)');

// ── 9. Retry Mechanism & Fault Tolerance ────────────────────────────────────
echo "\n--- 9. Retry Mechanism: Pemulihan Otomatis & Manual ---\n";

MockWhatsAppClient::clear();
MockWhatsAppClient::setTemporaryFailures(2); // Provider gagal 2 kali, lalu sukses pada percobaan ke-3

$retrySend = $waService->sendCustomerNotification(6, 'order_processing', $orderDataTest, 'retry:test:key:' . uniqid());
assertTest($retrySend['success'] === true && $retrySend['attempts'] === 3, 'Service otomatis melakukan retry hingga percobaan ke-3 dan sukses');

// Manual Retry via retryFailed($logId)
MockWhatsAppClient::clear();
MockWhatsAppClient::setSimulateFailure(true, 'Total network outage');

$failSend = $waService->sendCustomerNotification(6, 'order_ready', $orderDataTest, 'fail:test:key:' . uniqid());
assertTest($failSend['success'] === false && $failSend['status'] === 'failed', 'Pengiriman tercatat gagal saat provider mengalami hard outage');
$failedLogId = $failSend['log_id'] ?? null;
assertTest($failedLogId !== null && $failedLogId > 0, 'Kegagalan tetap tersimpan di tabel whatsapp_logs');

// Pulihkan provider dan jalankan manual retry
MockWhatsAppClient::setSimulateFailure(false);
$retryManual = $waService->retryFailed($failedLogId);
assertTest($retryManual['success'] === true && $retryManual['status'] === 'sent', 'Manual retryFailed() berhasil mengubah status log menjadi sent');

// ── 10. Database Logging & Security Inspection ──────────────────────────────
echo "\n--- 10. Database Logging & Security (Tanpa Token Rahasia) ---\n";

$stmt = $db->prepare("SELECT * FROM whatsapp_logs WHERE id = :id LIMIT 1");
$stmt->execute(['id' => $failedLogId]);
$logRow = $stmt->fetch(PDO::FETCH_ASSOC);

assertTest(!empty($logRow['recipient_phone']), 'Log menyimpan recipient_phone');
assertTest(!empty($logRow['event_type']), 'Log menyimpan event_type');
assertTest(!empty($logRow['message']), 'Log menyimpan message template aktual');
assertTest($logRow['attempts'] >= 2, 'Log mencatat jumlah percobaan akumulatif');
assertTest(!str_contains(strtolower($logRow['provider_response'] ?? ''), 'access_token') && !str_contains(strtolower($logRow['provider_response'] ?? ''), 'api_key'), 'Log TIDAK menyimpan access_token atau api_key rahasia');

// ── 11. Decoupling: Transaksi Database Tetap Sukses Meski WA Gagal ───────────
echo "\n--- 11. Decoupling: Transaksi Tetap Berjalan Aman Saat WhatsApp Error ---\n";

MockWhatsAppClient::setSimulateFailure(true, 'Fatal WhatsApp socket error');

// Uji coba pembuatan pesanan saat WhatsApp mati
$orderModel = new Order();
$dummyOrderData = [
    'customer_id' => 6,
    'store_id' => 1,
    'service_id' => 1,
    'address_id' => 1,
    'courier_id' => null,
    'estimated_weight' => 2.0,
    'estimated_amount' => 12000.0,
    'unit_price' => 6000.0,
    'total_amount' => 12000.0,
    'notes' => 'Uji isolasi transaksi',
    'payment_method' => 'cash',
    'payment_status' => 'unpaid',
    'status' => Order::STATUS_PENDING,
];

$newOrderId = null;
$orderCreatedSafely = false;
try {
    $newOrderId = $orderModel->createOrder($dummyOrderData);
    $orderCreatedSafely = ($newOrderId > 0);
} catch (\Throwable $e) {
    $orderCreatedSafely = false;
}

assertTest($orderCreatedSafely === true, 'Pesanan berhasil disimpan ke database meskipun WhatsApp provider fatal error');

// Clean up dummy test order safely
if ($newOrderId) {
    $db->prepare("DELETE FROM order_status_histories WHERE order_id = :id")->execute(['id' => $newOrderId]);
    $db->prepare("DELETE FROM orders WHERE id = :id")->execute(['id' => $newOrderId]);
}

MockWhatsAppClient::setSimulateFailure(false);

// ── 12. Multi-Store Isolation (RBAC) ────────────────────────────────────────
echo "\n--- 12. RBAC & Multi-Store Recipient Isolation ---\n";

// Store 1 (CleanMaster) Owner ID = 3
// Store 2 (Fresh & Clean) Owner ID = 7
MockWhatsAppClient::clear();

$orderStore1 = array_merge($sampleData, ['store_id' => 1, 'order_code' => 'ORD-STORE-1']);
$waService->sendStoreOwnerNotification(1, 'store_new_order', $orderStore1);
$sentStore1 = MockWhatsAppClient::getLastMessage();

$orderStore2 = array_merge($sampleData, ['store_id' => 2, 'order_code' => 'ORD-STORE-2']);
$waService->sendStoreOwnerNotification(2, 'store_new_order', $orderStore2);
$sentStore2 = MockWhatsAppClient::getLastMessage();

assertTest($sentStore1['to'] !== $sentStore2['to'], 'Nomor tujuan Owner Toko 1 BERBEDA dengan nomor tujuan Owner Toko 2 (terisolasi penuh)');

// ── 13. Persetujuan Pengguna (Opt-in / Opt-out) ──────────────────────────────
echo "\n--- 13. Persetujuan Pengguna (Opt-in / Opt-out) ---\n";

// Nonaktifkan notifikasi WA untuk user #6
$db->prepare("UPDATE users SET wa_notifications_enabled = 0 WHERE id = 6")->execute();

MockWhatsAppClient::clear();
$optOutRes = $waService->sendCustomerNotification(6, 'order_created', $orderDataTest);
assertTest($optOutRes['status'] === 'skipped' && str_contains($optOutRes['message'], 'opt-out'), 'Notifikasi WhatsApp di-skip dengan status opt-out');
assertTest(count(MockWhatsAppClient::getSentMessages()) === 0, 'Tidak ada pesan yang dikirim ke provider saat pengguna opt-out');

// Kembalikan opt-in untuk user #6
$db->prepare("UPDATE users SET wa_notifications_enabled = 1 WHERE id = 6")->execute();
$optInRes = $waService->sendCustomerNotification(6, 'order_created', $orderDataTest, 'optin:test:key:' . uniqid());
assertTest($optInRes['status'] === 'sent', 'Notifikasi kembali terkirim saat pengguna mengaktifkan kembali (opt-in)');

// ── 14. WhatsAppController & Admin Dashboard ───────────────────────────────
echo "\n--- 14. WhatsAppController & Admin Dashboard Management ---\n";

$stats = $waService->getStats();
assertTest(isset($stats['total'], $stats['sent'], $stats['failed']), 'getStats() menghasilkan metrik agregasi total, sent, dan failed');
assertTest(is_array($waService->getLogs(5, 0)), 'getLogs() menghasilkan array riwayat log');

$waController = new \App\Controllers\WhatsAppController();
assertTest($waController instanceof \App\Core\Controller, 'WhatsAppController berhasil diinisialisasi untuk routing dashboard');

// Clean up test logs
$db->exec("DELETE FROM whatsapp_logs WHERE order_code LIKE 'ORD-TEST%' OR idempotency_key LIKE 'test:%' OR idempotency_key LIKE 'retry:%' OR idempotency_key LIKE 'fail:%' OR idempotency_key LIKE 'optin:%' OR idempotency_key LIKE 'cust:customer_registered:%' OR order_code = 'ORD-STORE-1' OR order_code = 'ORD-STORE-2'");

// ── Rekapitulasi ────────────────────────────────────────────────────────────
echo "\n" . str_repeat('=', 78) . "\n";
echo sprintf("HASIL AKHIR TEST NOTIFIKASI WHATSAPP: %d PASS, %d FAIL\n", $passCount, $failCount);
echo str_repeat('=', 78) . "\n";

exit($failCount === 0 ? 0 : 1);
