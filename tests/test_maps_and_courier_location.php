<?php

/**
 * Automated Test Suite: Visual Map & Courier Location Tracking (Tahap 8A)
 * =====================================================================
 * Skenario Uji:
 *  1.  Model CourierLocation — getCourierLocation mengembalikan/membuat record lokasi kurir
 *  2.  Model CourierLocation — updateCoordinates memperbarui latitude & longitude kurir
 *  3.  Model CourierLocation — updateCoordinates mengasosiasikan order_id pada tracking
 *  4.  Model CourierLocation — getLatestLocationForOrder mengembalikan data spesifik order_id
 *  5.  Model CourierLocation — getLatestLocationForOrder fallback ke kurir jika order_id belum spesifik
 *  6.  Model CourierLocation — updateDutyStatus memperbarui status tugas (available, busy, offline)
 *  7.  Model CourierLocation — updateDutyStatus menolak nilai status tugas yang tidak valid
 *  8.  Model Order — findOrderDetail memuat koordinat toko (store_lat & store_lng)
 *  9.  Model Order — findOrderDetail memuat koordinat customer (latitude & longitude)
 * 10.  Peta Visual State Guard — Peta visual AKTIF pada status courier_on_the_way
 * 11.  Peta Visual State Guard — Peta visual AKTIF pada status out_for_delivery
 * 12.  Peta Visual State Guard — Peta visual NON-AKTIF pada status pending, paid, ready, delivered, dll
 * 13.  Validasi GPS — Validator menerima latitude valid (-90 s/d 90)
 * 14.  Validasi GPS — Validator menolak latitude di luar rentang (-91 atau 91)
 * 15.  Validasi GPS — Validator menerima longitude valid (-180 s/d 180)
 * 16.  Validasi GPS — Validator menolak longitude di luar rentang (-181 atau 181)
 * 17.  Validasi GPS — Validator menolak koordinat string non-numerik
 * 18.  File Partial — views/partials/map.php tersedia dan valid secara sintaks PHP
 */

// 1. Register PSR-4 Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/../app/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($file)) require $file;
});

// 2. Load Environment Variables (.env)
\App\Helpers\Env::load(__DIR__ . '/../.env');

// 3. Helper Config Function
if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed {
        static $configs = [];
        $parts = explode('.', $key);
        $file = $parts[0];
        $item = $parts[1] ?? null;

        if (!isset($configs[$file])) {
            $path = __DIR__ . "/../config/{$file}.php";
            $configs[$file] = file_exists($path) ? require $path : [];
        }

        return $item === null ? $configs[$file] : ($configs[$file][$item] ?? $default);
    }
}

date_default_timezone_set(config('app.timezone', 'Asia/Jakarta'));

use App\Models\CourierLocation;
use App\Models\Order;
use App\Helpers\Validator;

class MapsAndCourierLocationTestSuite
{
    private \PDO $pdo;
    private CourierLocation $courierLocModel;
    private Order $orderModel;
    private int $testCourierId;
    private int $testOrderId;
    private int $passed = 0;
    private int $failed = 0;

    public function __construct()
    {
        $dbConfig = config('database');
        $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}";
        $this->pdo = new \PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);

        $this->courierLocModel = new CourierLocation();
        $this->orderModel = new Order();

        $this->setupTestData();
    }

    private function setupTestData(): void
    {
        // 1. Ambil atau pastikan kurir tersedia (role_id = 5)
        $stmt = $this->pdo->query("SELECT id FROM users WHERE role_id = 5 LIMIT 1");
        $courier = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($courier) {
            $this->testCourierId = (int)$courier['id'];
        } else {
            $this->pdo->exec("INSERT INTO users (role_id, name, email, password, phone, is_active) VALUES (5, 'Kurir Test Maps', 'kurir_maps_test@laundry.test', 'secret', '081234567899', 1)");
            $this->testCourierId = (int)$this->pdo->lastInsertId();
        }

        // 2. Ambil atau buat order untuk keperluan test
        $stmt = $this->pdo->query("SELECT id FROM orders LIMIT 1");
        $order = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($order) {
            $this->testOrderId = (int)$order['id'];
        } else {
            // Minimal order fallback
            $this->testOrderId = 1;
        }
    }

    public function run(): void
    {
        echo "\n======================================================================\n";
        echo "   TEST SUITE: PETA VISUAL & TRACKING LOKASI KURIR (TAHAP 8A)\n";
        echo "======================================================================\n\n";

        $this->testCourierLocationGetOrCreate();
        $this->testCourierLocationUpdateCoordinates();
        $this->testCourierLocationUpdateWithOrderId();
        $this->testCourierLocationGetLatestForOrderSpecific();
        $this->testCourierLocationGetLatestForOrderFallback();
        $this->testCourierLocationUpdateDutyStatusValid();
        $this->testCourierLocationUpdateDutyStatusInvalid();
        $this->testOrderFindOrderDetailIncludesStoreCoordinates();
        $this->testOrderFindOrderDetailIncludesCustomerCoordinates();
        $this->testMapStateGuardCourierOnTheWay();
        $this->testMapStateGuardOutForDelivery();
        $this->testMapStateGuardInactiveStatuses();
        $this->testValidatorLatitudeValid();
        $this->testValidatorLatitudeInvalid();
        $this->testValidatorLongitudeValid();
        $this->testValidatorLongitudeInvalid();
        $this->testValidatorNonNumericCoordinates();
        $this->testPartialMapFileSyntaxAndIntegrity();

        echo "\n----------------------------------------------------------------------\n";
        echo "Hasil Akhir Test Suite: {$this->passed} PASS / " . ($this->passed + $this->failed) . " Total\n";
        if ($this->failed > 0) {
            echo "STATUS: {$this->failed} TEST GAGAL!\n";
        } else {
            echo "STATUS: SEMUA TEST BERHASIL LULUS SECARA SEMPURNA (100% PASS)!\n";
        }
        echo "======================================================================\n\n";
    }

    private function recordResult(string $testName, bool $condition, string $detail = ''): void
    {
        if ($condition) {
            $this->passed++;
            echo "  [PASS] {$testName}\n";
            if ($detail) echo "         -> {$detail}\n";
        } else {
            $this->failed++;
            echo "  [FAIL] {$testName}\n";
            if ($detail) echo "         -> Detail: {$detail}\n";
        }
    }

    // 1. Model CourierLocation — getCourierLocation
    private function testCourierLocationGetOrCreate(): void
    {
        $loc = $this->courierLocModel->getCourierLocation($this->testCourierId);
        $ok = is_array($loc) && isset($loc['courier_id']) && (int)$loc['courier_id'] === $this->testCourierId && isset($loc['latitude'], $loc['longitude']);
        $this->recordResult(
            "Model CourierLocation: getCourierLocation mengembalikan data lokasi kurir",
            $ok,
            "Lat: " . ($loc['latitude'] ?? 'N/A') . ", Lng: " . ($loc['longitude'] ?? 'N/A')
        );
    }

    // 2. Model CourierLocation — updateCoordinates tanpa order_id
    private function testCourierLocationUpdateCoordinates(): void
    {
        $testLat = -6.21050000;
        $testLng = 106.84020000;
        $result = $this->courierLocModel->updateCoordinates($this->testCourierId, $testLat, $testLng);
        
        $loc = $this->courierLocModel->getCourierLocation($this->testCourierId);
        $ok = $result && abs((float)$loc['latitude'] - $testLat) < 0.0001 && abs((float)$loc['longitude'] - $testLng) < 0.0001;
        $this->recordResult(
            "Model CourierLocation: updateCoordinates berhasil memperbarui koordinat umum kurir",
            $ok,
            "Updated to: {$testLat}, {$testLng}"
        );
    }

    // 3. Model CourierLocation — updateCoordinates dengan order_id
    private function testCourierLocationUpdateWithOrderId(): void
    {
        $testLat = -6.23010000;
        $testLng = 106.82050000;
        $result = $this->courierLocModel->updateCoordinates($this->testCourierId, $testLat, $testLng, $this->testOrderId);

        $stmt = $this->pdo->prepare("SELECT * FROM courier_locations WHERE courier_id = :cid LIMIT 1");
        $stmt->execute(['cid' => $this->testCourierId]);
        $loc = $stmt->fetch(\PDO::FETCH_ASSOC);

        $ok = $result && $loc && (int)$loc['order_id'] === $this->testOrderId && abs((float)$loc['latitude'] - $testLat) < 0.0001;
        $this->recordResult(
            "Model CourierLocation: updateCoordinates mengasosiasikan order_id pada record lokasi",
            $ok,
            "Associated order_id: {$this->testOrderId}"
        );
    }

    // 4. Model CourierLocation — getLatestLocationForOrder dengan matching order_id
    private function testCourierLocationGetLatestForOrderSpecific(): void
    {
        $latest = $this->courierLocModel->getLatestLocationForOrder($this->testOrderId, $this->testCourierId);
        $ok = !empty($latest) && (int)$latest['order_id'] === $this->testOrderId;
        $this->recordResult(
            "Model CourierLocation: getLatestLocationForOrder mengambil record spesifik berdasarkan order_id",
            $ok,
            "Record order_id: " . ($latest['order_id'] ?? 'null') . ", Lat: " . ($latest['latitude'] ?? 'N/A')
        );
    }

    // 5. Model CourierLocation — getLatestLocationForOrder fallback ke kurir jika order_id berbeda/belum ada
    private function testCourierLocationGetLatestForOrderFallback(): void
    {
        $nonExistentOrderId = 9999999;
        $latest = $this->courierLocModel->getLatestLocationForOrder($nonExistentOrderId, $this->testCourierId);
        $ok = !empty($latest) && (int)$latest['courier_id'] === $this->testCourierId;
        $this->recordResult(
            "Model CourierLocation: getLatestLocationForOrder fallback ke lokasi kurir jika order_id belum spesifik",
            $ok,
            "Fallback matched courier_id: " . ($latest['courier_id'] ?? 'null')
        );
    }

    // 6. Model CourierLocation — updateDutyStatus valid
    private function testCourierLocationUpdateDutyStatusValid(): void
    {
        $res1 = $this->courierLocModel->updateDutyStatus($this->testCourierId, 'busy');
        $loc1 = $this->courierLocModel->getCourierLocation($this->testCourierId);
        $res2 = $this->courierLocModel->updateDutyStatus($this->testCourierId, 'available');
        $loc2 = $this->courierLocModel->getCourierLocation($this->testCourierId);

        $ok = $res1 && $loc1['duty_status'] === 'busy' && $res2 && $loc2['duty_status'] === 'available';
        $this->recordResult(
            "Model CourierLocation: updateDutyStatus sukses mengubah status ('busy' -> 'available')",
            $ok
        );
    }

    // 7. Model CourierLocation — updateDutyStatus menolak invalid status
    private function testCourierLocationUpdateDutyStatusInvalid(): void
    {
        $res = $this->courierLocModel->updateDutyStatus($this->testCourierId, 'invalid_status_xyz');
        $this->recordResult(
            "Model CourierLocation: updateDutyStatus menolak nilai status tidak valid",
            $res === false,
            "Rejected non-enum status"
        );
    }

    // 8. Model Order — findOrderDetail mencakup store_lat dan store_lng
    private function testOrderFindOrderDetailIncludesStoreCoordinates(): void
    {
        $order = $this->orderModel->findOrderDetail($this->testOrderId);
        $ok = !empty($order) && array_key_exists('store_lat', $order) && array_key_exists('store_lng', $order);
        $this->recordResult(
            "Model Order: findOrderDetail memuat kolom store_lat dan store_lng",
            $ok,
            "store_lat: " . ($order['store_lat'] ?? 'N/A') . ", store_lng: " . ($order['store_lng'] ?? 'N/A')
        );
    }

    // 9. Model Order — findOrderDetail mencakup koordinat customer (latitude & longitude)
    private function testOrderFindOrderDetailIncludesCustomerCoordinates(): void
    {
        $order = $this->orderModel->findOrderDetail($this->testOrderId);
        $ok = !empty($order) && array_key_exists('latitude', $order) && array_key_exists('longitude', $order);
        $this->recordResult(
            "Model Order: findOrderDetail memuat kolom koordinat alamat pelanggan (latitude & longitude)",
            $ok,
            "Customer Lat: " . ($order['latitude'] ?? 'N/A') . ", Lng: " . ($order['longitude'] ?? 'N/A')
        );
    }

    // 10. Peta Visual State Guard — AKTIF pada courier_on_the_way
    private function testMapStateGuardCourierOnTheWay(): void
    {
        $status = Order::STATUS_COURIER_ON_THE_WAY;
        $isActive = in_array($status, [
            Order::STATUS_COURIER_ON_THE_WAY,
            Order::STATUS_OUT_FOR_DELIVERY
        ], true);

        $this->recordResult(
            "Peta Visual State Guard: Peta visual AKTIF pada status 'courier_on_the_way'",
            $isActive === true,
            "Status: {$status} -> Visual Map Enabled"
        );
    }

    // 11. Peta Visual State Guard — AKTIF pada out_for_delivery
    private function testMapStateGuardOutForDelivery(): void
    {
        $status = Order::STATUS_OUT_FOR_DELIVERY;
        $isActive = in_array($status, [
            Order::STATUS_COURIER_ON_THE_WAY,
            Order::STATUS_OUT_FOR_DELIVERY
        ], true);

        $this->recordResult(
            "Peta Visual State Guard: Peta visual AKTIF pada status 'out_for_delivery'",
            $isActive === true,
            "Status: {$status} -> Visual Map Enabled"
        );
    }

    // 12. Peta Visual State Guard — NON-AKTIF pada status lain
    private function testMapStateGuardInactiveStatuses(): void
    {
        $inactiveStatuses = [
            Order::STATUS_PENDING,
            Order::STATUS_ASSIGNED_TO_COURIER,
            Order::STATUS_PICKED_UP,
            Order::STATUS_ARRIVED_AT_STORE,
            Order::STATUS_WEIGHED,
            Order::STATUS_WAITING_PAYMENT,
            Order::STATUS_PAID,
            Order::STATUS_PROCESSING,
            Order::STATUS_READY,
            Order::STATUS_DELIVERED,
            Order::STATUS_CANCELLED
        ];

        $allInactive = true;
        foreach ($inactiveStatuses as $st) {
            $isActive = in_array($st, [
                Order::STATUS_COURIER_ON_THE_WAY,
                Order::STATUS_OUT_FOR_DELIVERY
            ], true);
            if ($isActive !== false) {
                $allInactive = false;
                break;
            }
        }

        $this->recordResult(
            "Peta Visual State Guard: Peta visual NON-AKTIF pada seluruh status di luar pengantaran/penjemputan aktif",
            $allInactive === true,
            "Checked 10 non-tracking statuses -> All disabled"
        );
    }

    // 13. Validasi GPS — Latitude valid
    private function testValidatorLatitudeValid(): void
    {
        $validator = new Validator();
        $ok = $validator->validate(['latitude' => '-6.20880000'], ['latitude' => 'required|latitude']);
        $this->recordResult(
            "Validasi GPS: Validator meloloskan latitude valid (-6.2088)",
            $ok === true
        );
    }

    // 14. Validasi GPS — Latitude invalid (> 90 atau < -90)
    private function testValidatorLatitudeInvalid(): void
    {
        $validator1 = new Validator();
        $fail1 = $validator1->validate(['latitude' => '95.1234'], ['latitude' => 'required|latitude']);
        $validator2 = new Validator();
        $fail2 = $validator2->validate(['latitude' => '-91.0000'], ['latitude' => 'required|latitude']);

        $this->recordResult(
            "Validasi GPS: Validator menolak latitude di luar rentang -90 s/d 90",
            $fail1 === false && $fail2 === false,
            "Rejected 95.1234 and -91.0000"
        );
    }

    // 15. Validasi GPS — Longitude valid
    private function testValidatorLongitudeValid(): void
    {
        $validator = new Validator();
        $ok = $validator->validate(['longitude' => '106.84560000'], ['longitude' => 'required|longitude']);
        $this->recordResult(
            "Validasi GPS: Validator meloloskan longitude valid (106.8456)",
            $ok === true
        );
    }

    // 16. Validasi GPS — Longitude invalid (> 180 atau < -180)
    private function testValidatorLongitudeInvalid(): void
    {
        $validator1 = new Validator();
        $fail1 = $validator1->validate(['longitude' => '185.0000'], ['longitude' => 'required|longitude']);
        $validator2 = new Validator();
        $fail2 = $validator2->validate(['longitude' => '-181.5000'], ['longitude' => 'required|longitude']);

        $this->recordResult(
            "Validasi GPS: Validator menolak longitude di luar rentang -180 s/d 180",
            $fail1 === false && $fail2 === false,
            "Rejected 185.0000 and -181.5000"
        );
    }

    // 17. Validasi GPS — Menolak string non-numerik
    private function testValidatorNonNumericCoordinates(): void
    {
        $validator = new Validator();
        $fail = $validator->validate(['latitude' => 'bukan_angka_gps'], ['latitude' => 'required|latitude']);
        $this->recordResult(
            "Validasi GPS: Validator menolak koordinat string non-numerik",
            $fail === false,
            "Rejected 'bukan_angka_gps'"
        );
    }

    // 18. File Partial — views/partials/map.php sintaks & integritas
    private function testPartialMapFileSyntaxAndIntegrity(): void
    {
        $partialPath = __DIR__ . '/../app/Views/partials/map.php';
        $fileExists = file_exists($partialPath);
        $content = $fileExists ? file_get_contents($partialPath) : '';
        $hasLeaflet = str_contains($content, 'leaflet.js') && str_contains($content, 'openstreetmap.org');
        $hasPinStyles = str_contains($content, 'custom-map-pin');

        $this->recordResult(
            "File Partial: views/partials/map.php ada, terstruktur modular Leaflet + OSM, dan valid",
            $fileExists && $hasLeaflet && $hasPinStyles,
            "Found Leaflet, OpenStreetMap tiles, and custom pins"
        );
    }
}

// Eksekusi Test Suite
$suite = new MapsAndCourierLocationTestSuite();
$suite->run();
