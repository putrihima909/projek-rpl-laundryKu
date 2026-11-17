<?php

/**
 * Automated Test Suite: Real-Time Courier Tracking Polling & Distance Matrix (Tahap 8B)
 * ====================================================================================
 * Skenario Uji:
 *  1. DistanceCalculator — Haversine koordinat identik menghasilkan 0.0 km
 *  2. DistanceCalculator — Haversine Monas ke Bundaran HI (~2.2 km)
 *  3. DistanceCalculator — Haversine Jakarta ke Bandung (~115 - 125 km)
 *  4. DistanceCalculator — Kalkulasi durasi tempuh motor kurir (30 km/jam) akurat
 *  5. DistanceCalculator — estimate() mengembalikan array terstruktur lengkap
 *  6. DistanceCalculator — Format jarak < 1 km ditampilkan dalam meter ('X m')
 *  7. DistanceCalculator — Format durasi tempuh rapi ('X menit' atau 'X jam Y menit')
 *  8. Polling Endpoint — GET /orders/{id}/courier-location return 404 untuk order tidak valid
 *  9. Polling Endpoint — Otorisasi isolasi: Customer lain ditolak dengan 403 Forbidden
 * 10. Polling Endpoint — Customer pemilik pesanan berhasil mendapatkan respons 200 OK
 * 11. Polling Endpoint — Payload JSON berisi latitude, longitude, order_status, updated_at
 * 12. Polling Endpoint — is_tracking_active bernilai TRUE pada status courier_on_the_way
 * 13. Polling Endpoint — is_tracking_active bernilai TRUE pada status out_for_delivery
 * 14. Polling Endpoint — is_tracking_active bernilai FALSE pada status arrived_at_store / delivered
 * 15. Store Detail — Integrasi estimasi jarak ke toko via DistanceCalculator
 * 16. Order Detail — Integrasi estimasi jarak antara toko dan alamat customer
 */

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

// Start session early before any echo/output
\App\Helpers\Session::start();

use App\Services\DistanceCalculator;
use App\Models\Order;
use App\Models\CourierLocation;
use App\Controllers\OrderController;
use App\Helpers\Auth;
use App\Helpers\Session;

class TrackingAndDistanceTestSuite
{
    private \PDO $pdo;
    private Order $orderModel;
    private CourierLocation $courierLocModel;
    private int $orderOwnerCustomerId = 6;
    private int $unauthorizedCustomerId = 999;
    private int $testOrderId = 1;
    private int $passed = 0;
    private int $failed = 0;

    public function __construct()
    {
        $dbConfig = config('database');
        $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}";
        $this->pdo = new \PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);

        $this->orderModel = new Order();
        $this->courierLocModel = new CourierLocation();

        // Pastikan order #1 ada dan customer_id diketahui
        $stmt = $this->pdo->query("SELECT id, customer_id FROM orders LIMIT 1");
        $ord = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($ord) {
            $this->testOrderId = (int)$ord['id'];
            $this->orderOwnerCustomerId = (int)$ord['customer_id'];
        }

        // Ambil kurir
        $stmt = $this->pdo->query("SELECT id FROM users WHERE role_id = 5 LIMIT 1");
        $courier = $stmt->fetch(\PDO::FETCH_ASSOC);
        $courierId = $courier ? (int)$courier['id'] : 5;

        // Pastikan order memiliki courier_id
        $this->pdo->prepare("UPDATE orders SET courier_id = :cid WHERE id = :oid")
            ->execute(['cid' => $courierId, 'oid' => $this->testOrderId]);

        // Simpan posisi kurir
        $this->courierLocModel->updateCoordinates($courierId, -6.21550000, 106.83020000, $this->testOrderId);
    }

    public function run(): void
    {
        echo "\n======================================================================\n";
        echo "   TEST SUITE: TRACKING REAL-TIME POLLING & DISTANCE MATRIX (8B)\n";
        echo "======================================================================\n\n";

        $this->testHaversineIdenticalCoords();
        $this->testHaversineMonasToBundaranHi();
        $this->testHaversineJakartaToBandung();
        $this->testDurationMinutes();
        $this->testEstimateArrayStructure();
        $this->testEstimateSubKilometerFormatting();
        $this->testEstimateHoursFormatting();
        $this->testPollingEndpointNotFound();
        $this->testPollingEndpointCustomerIsolation();
        $this->testPollingEndpointOwnerSuccess();
        $this->testPollingEndpointPayloadStructure();
        $this->testTrackingActiveFlagCourierOnTheWay();
        $this->testTrackingActiveFlagOutForDelivery();
        $this->testTrackingActiveFlagArrivedOrDelivered();
        $this->testStoreDetailDistanceIntegration();
        $this->testOrderDetailDistanceIntegration();

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

    // 1. Koordinat identik
    private function testHaversineIdenticalCoords(): void
    {
        $dist = DistanceCalculator::calculateHaversine(-6.2088, 106.8456, -6.2088, 106.8456);
        $this->recordResult(
            "DistanceCalculator: Koordinat identik menghasilkan jarak 0.0 km",
            $dist === 0.0,
            "Distance: {$dist} km"
        );
    }

    // 2. Monas ke Bundaran HI (~2.2 km)
    private function testHaversineMonasToBundaranHi(): void
    {
        // Monas: -6.175392, 106.827153
        // Bundaran HI: -6.195048, 106.823048
        $dist = DistanceCalculator::calculateHaversine(-6.175392, 106.827153, -6.195048, 106.823048);
        $ok = $dist >= 2.0 && $dist <= 2.5;
        $this->recordResult(
            "DistanceCalculator: Jarak Monas ke Bundaran HI terhitung realistis (~2.2 km)",
            $ok,
            "Distance: {$dist} km"
        );
    }

    // 3. Jakarta ke Bandung (~115 - 125 km)
    private function testHaversineJakartaToBandung(): void
    {
        // Jakarta: -6.2088, 106.8456
        // Bandung: -6.9175, 107.6191
        $dist = DistanceCalculator::calculateHaversine(-6.2088, 106.8456, -6.9175, 107.6191);
        $ok = $dist >= 115.0 && $dist <= 130.0;
        $this->recordResult(
            "DistanceCalculator: Jarak antar kota (Jakarta - Bandung) akurat (~120 km)",
            $ok,
            "Distance: {$dist} km"
        );
    }

    // 4. Kalkulasi durasi tempuh
    private function testDurationMinutes(): void
    {
        // Jarak 2.4 km dengan detour 1.25x = 3.0 km jalan. Kecepatan 30 km/jam = 0.1 jam = 6 menit
        $mins = DistanceCalculator::calculateDurationMinutes(2.4, 30.0);
        $ok = ($mins === 6);
        $this->recordResult(
            "DistanceCalculator: Kalkulasi durasi tempuh motor kurir 30 km/jam akurat (2.4 km -> 6 menit)",
            $ok,
            "Duration: {$mins} menit"
        );
    }

    // 5. Array estimate terstruktur
    private function testEstimateArrayStructure(): void
    {
        $est = DistanceCalculator::estimate(-6.2297465, 106.8164490, -6.2443, 106.7930);
        $ok = isset($est['distance_km'], $est['duration_minutes'], $est['formatted_distance'], $est['formatted_duration'], $est['provider']);
        $this->recordResult(
            "DistanceCalculator: estimate() mengembalikan array terstruktur lengkap",
            $ok,
            "Distance: {$est['formatted_distance']}, Durasi: {$est['formatted_duration']}, Provider: {$est['provider']}"
        );
    }

    // 6. Sub-kilometer formatting
    private function testEstimateSubKilometerFormatting(): void
    {
        // Jarak sangat dekat (~500 meter)
        $est = DistanceCalculator::estimate(-6.2000, 106.8000, -6.2040, 106.8000);
        $ok = str_ends_with($est['formatted_distance'], ' m');
        $this->recordResult(
            "DistanceCalculator: Jarak di bawah 1 km diformat dalam meter ('X m')",
            $ok,
            "Formatted: {$est['formatted_distance']}"
        );
    }

    // 7. Durasi dalam jam
    private function testEstimateHoursFormatting(): void
    {
        // Jarak 50 km (kurang lebih 125 menit)
        $est = DistanceCalculator::estimate(-6.2000, 106.8000, -6.6000, 106.8000);
        $ok = str_contains($est['formatted_duration'], 'jam');
        $this->recordResult(
            "DistanceCalculator: Durasi tempuh >= 60 menit diformat mencakup satuan jam",
            $ok,
            "Formatted: {$est['formatted_duration']}"
        );
    }

    // 8. Polling endpoint: 404 order not found
    private function testPollingEndpointNotFound(): void
    {
        $controller = new OrderController();
        ob_start();
        $controller->courierLocationJson(99999999);
        $output = ob_get_clean();
        $json = json_decode($output, true);

        $ok = is_array($json) && ($json['status'] ?? '') === 'error';
        $this->recordResult(
            "Polling Endpoint: Order ID tidak ditemukan menghasilkan error 404",
            $ok,
            "Message: " . ($json['message'] ?? 'N/A')
        );
    }

    // 9. Polling endpoint: customer isolation (403)
    private function testPollingEndpointCustomerIsolation(): void
    {
        // Simulasikan login sebagai Customer B (bukan pemilik order)
        Auth::login([
            'id' => 99999, // Customer asing
            'name' => 'Customer Asing',
            'email' => 'asing@customer.test',
            'role_id' => 6,
            'role_name' => 'customer'
        ]);

        $controller = new OrderController();
        ob_start();
        $controller->courierLocationJson($this->testOrderId);
        $output = ob_get_clean();
        $json = json_decode($output, true);

        $ok = is_array($json) && ($json['status'] ?? '') === 'error' && str_contains($json['message'] ?? '', 'Akses ditolak');
        $this->recordResult(
            "Polling Endpoint: Customer lain diblokir saat mencoba polling order customer lain (403 Forbidden)",
            $ok,
            "Security response: " . ($json['message'] ?? 'N/A')
        );
    }

    // 10. Polling endpoint: customer owner success (200)
    private function testPollingEndpointOwnerSuccess(): void
    {
        // Simulasikan login sebagai Customer pemilik order
        Auth::login([
            'id' => $this->orderOwnerCustomerId,
            'name' => 'Customer Pemilik Order',
            'email' => 'owner_order@customer.test',
            'role_id' => 6,
            'role_name' => 'customer'
        ]);

        $controller = new OrderController();
        ob_start();
        $controller->courierLocationJson($this->testOrderId);
        $output = ob_get_clean();
        $json = json_decode($output, true);

        $ok = is_array($json) && ($json['status'] ?? '') === 'success' && !empty($json['data']);
        $this->recordResult(
            "Polling Endpoint: Customer pemilik pesanan sukses mendapatkan JSON posisi kurir",
            $ok,
            "Response status: " . ($json['status'] ?? 'N/A')
        );
    }

    // 11. Polling payload structure
    private function testPollingEndpointPayloadStructure(): void
    {
        Auth::login([
            'id' => $this->orderOwnerCustomerId,
            'name' => 'Customer Pemilik Order',
            'email' => 'owner_order@customer.test',
            'role_id' => 6,
            'role_name' => 'customer'
        ]);

        $controller = new OrderController();
        ob_start();
        $controller->courierLocationJson($this->testOrderId);
        $output = ob_get_clean();
        $json = json_decode($output, true);

        $data = $json['data'] ?? [];
        $ok = isset($data['latitude'], $data['longitude'], $data['order_status'], $data['is_tracking_active'], $data['updated_at']);
        $this->recordResult(
            "Polling Endpoint: Payload data JSON memuat koordinat GPS, status order, and tracking flag",
            $ok,
            "Lat: " . ($data['latitude'] ?? 'N/A') . ", Lng: " . ($data['longitude'] ?? 'N/A') . ", Updated: " . ($data['updated_at'] ?? 'N/A')
        );
    }

    // 12. is_tracking_active TRUE on courier_on_the_way
    private function testTrackingActiveFlagCourierOnTheWay(): void
    {
        $this->pdo->prepare("UPDATE orders SET status = 'courier_on_the_way' WHERE id = :oid")
            ->execute(['oid' => $this->testOrderId]);

        Auth::login([
            'id' => $this->orderOwnerCustomerId,
            'name' => 'Customer Pemilik Order',
            'email' => 'owner_order@customer.test',
            'role_id' => 6,
            'role_name' => 'customer'
        ]);

        $controller = new OrderController();
        ob_start();
        $controller->courierLocationJson($this->testOrderId);
        $output = ob_get_clean();
        $json = json_decode($output, true);

        $ok = ($json['data']['is_tracking_active'] ?? false) === true;
        $this->recordResult(
            "Polling Endpoint: Flag is_tracking_active bernilai TRUE saat status 'courier_on_the_way'",
            $ok
        );
    }

    // 13. is_tracking_active TRUE on out_for_delivery
    private function testTrackingActiveFlagOutForDelivery(): void
    {
        $this->pdo->prepare("UPDATE orders SET status = 'out_for_delivery' WHERE id = :oid")
            ->execute(['oid' => $this->testOrderId]);

        Auth::login([
            'id' => $this->orderOwnerCustomerId,
            'name' => 'Customer Pemilik Order',
            'email' => 'owner_order@customer.test',
            'role_id' => 6,
            'role_name' => 'customer'
        ]);

        $controller = new OrderController();
        ob_start();
        $controller->courierLocationJson($this->testOrderId);
        $output = ob_get_clean();
        $json = json_decode($output, true);

        $ok = ($json['data']['is_tracking_active'] ?? false) === true;
        $this->recordResult(
            "Polling Endpoint: Flag is_tracking_active bernilai TRUE saat status 'out_for_delivery'",
            $ok
        );
    }

    // 14. is_tracking_active FALSE on delivered / arrived
    private function testTrackingActiveFlagArrivedOrDelivered(): void
    {
        $this->pdo->prepare("UPDATE orders SET status = 'delivered' WHERE id = :oid")
            ->execute(['oid' => $this->testOrderId]);

        Auth::login([
            'id' => $this->orderOwnerCustomerId,
            'name' => 'Customer Pemilik Order',
            'email' => 'owner_order@customer.test',
            'role_id' => 6,
            'role_name' => 'customer'
        ]);

        $controller = new OrderController();
        ob_start();
        $controller->courierLocationJson($this->testOrderId);
        $output = ob_get_clean();
        $json = json_decode($output, true);

        $ok = ($json['data']['is_tracking_active'] ?? true) === false;
        $this->recordResult(
            "Polling Endpoint: Flag is_tracking_active bernilai FALSE saat status 'delivered' (perjalanan selesai)",
            $ok,
            "Client dapat menghentikan polling otomatis (clearInterval)"
        );
    }

    // 15. Store Detail distance integration
    private function testStoreDetailDistanceIntegration(): void
    {
        // Store 1 coords
        $stmt = $this->pdo->query("SELECT latitude, longitude FROM stores WHERE id = 1");
        $store = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        $customerLat = -6.24430000;
        $customerLng = 106.79300000;

        $est = DistanceCalculator::estimate($customerLat, $customerLng, (float)$store['latitude'], (float)$store['longitude']);
        $ok = $est['distance_km'] > 0 && !empty($est['formatted_distance']);
        $this->recordResult(
            "Store Detail: DistanceCalculator terintegrasi untuk menghitung jarak customer ke toko",
            $ok,
            "Jarak ke Toko 1: {$est['formatted_distance']} ({$est['formatted_duration']})"
        );
    }

    // 16. Order Detail distance integration
    private function testOrderDetailDistanceIntegration(): void
    {
        $order = $this->orderModel->findOrderDetail($this->testOrderId);
        $ok = false;
        if (!empty($order['store_lat']) && !empty($order['store_lng']) && !empty($order['latitude']) && !empty($order['longitude'])) {
            $est = DistanceCalculator::estimate(
                (float)$order['latitude'],
                (float)$order['longitude'],
                (float)$order['store_lat'],
                (float)$order['store_lng']
            );
            $ok = $est['distance_km'] > 0;
        }

        $this->recordResult(
            "Order Detail: Estimasi jarak toko ke alamat penjemputan berhasil dihitung",
            $ok,
            "Estimasi rute: " . ($est['formatted_distance'] ?? 'N/A') . " (~" . ($est['formatted_duration'] ?? 'N/A') . ")"
        );
    }
}

// Jalankan Test Suite
$suite = new TrackingAndDistanceTestSuite();
$suite->run();
