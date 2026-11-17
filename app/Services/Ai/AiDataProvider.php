<?php

namespace App\Services\Ai;

use App\Core\Database;
use App\Models\Store;
use App\Models\Order;
use PDO;
use Throwable;

/**
 * Provider data agregat dan statistik bisnis untuk asisten AI (Gemini).
 * 
 * Prinsip Keamanan & Privasi:
 * 1. Read-only dengan prepared statements.
 * 2. Scoping ketat berbasis role dari server session.
 *    - owner & admin_owner: seluruh platform ($storeId = null)
 *    - owner_toko & admin_toko: toko milik user ($storeId resolved)
 *    - customer & kurir: ditolak (return null)
 * 3. HANYA mengembalikan angka agregat dan nama toko.
 *    DILARANG mengembalikan data pribadi user (nama, email, telepon, password, hash, token, google_id).
 * 4. Kriteria query omset dan pesanan sama persis dengan DashboardController.
 */
class AiDataProvider
{
    private ?PDO $db;

    public function __construct(?PDO $db = null)
    {
        if ($db !== null) {
            $this->db = $db;
        } else {
            try {
                $this->db = Database::getInstance();
            } catch (Throwable $e) {
                $this->db = null;
            }
        }
    }

    /**
     * Mengambil konteks data staff platform atau toko untuk AI prompt.
     *
     * @param int $userId ID pengguna yang sedang login
     * @param string $role Role pengguna ('owner', 'admin_owner', 'owner_toko', 'admin_toko')
     * @return array|null Mengembalikan null jika role tidak memiliki hak akses staff
     */
    public function getStaffContext(int $userId, string $role): ?array
    {
        if (!$this->db) {
            return null;
        }

        $storeId = null;
        $store = null;

        if ($role === 'owner' || $role === 'admin_owner') {
            $scope = 'platform';
            $storeId = null;
        } elseif ($role === 'owner_toko' || $role === 'admin_toko') {
            $scope = 'toko';
            $storeModel = new Store();
            $store = $storeModel->getStoreByOwner($userId);
            if (!$store) {
                return null;
            }
            $storeId = (int) $store['id'];
        } else {
            // Customer, Kurir, atau role lain tidak diizinkan mendapatkan konteks staff
            return null;
        }

        // --- 1. Statistik Pesanan ---
        $orderStats = $this->getOrderStats($storeId);

        // --- 2. Statistik Omset (Sesuai kriteria DashboardController: payment_status = 'paid') ---
        $revenueStats = $this->getRevenueStats($storeId);

        // --- 3. Pembayaran Kedaluwarsa Hari Ini ---
        $expiredPaymentsToday = $this->getExpiredPaymentsToday($storeId);

        // --- 4. Refund Pending dan Nilainya ---
        $refundStats = $this->getPendingRefundStats($storeId);

        // --- 5. Statistik Customer ---
        $customerStats = $this->getCustomerStats($storeId);

        // --- 6. Metrik Kartu Dashboard (SAMA PERSIS DENGAN KARTU UI DASHBOARD) ---
        $orderModel = new Order();
        $adminTokoCardStats = $storeId !== null ? $orderModel->getAdminTokoCardStats($storeId) : [];
        $ownerTokoCardStats = $storeId !== null ? $orderModel->getOwnerTokoCardStats($storeId) : [];
        $platformCardStats = $storeId === null ? $orderModel->getPlatformCardStats() : [];
        $perStatusAllTime = $orderModel->getOrdersCountPerStatusAllTime($storeId);

        // --- Susun Payload Konteks ---
        $context = [
            'cakupan' => $scope,
            'role' => $role,
            'store_id' => $storeId,
            'nama_toko' => $store ? (string) $store['name'] : null,

            // Metrik Kartu Dashboard Resmi
            'kartu_dashboard' => [
                'admin_toko' => $adminTokoCardStats,
                'owner_toko' => $ownerTokoCardStats,
                'platform'   => $platformCardStats,
            ],
            'pesanan_per_status_semua_waktu' => $perStatusAllTime,

            // Ringkasan Pesanan (kriteria tanggal server database CURDATE())
            'pesanan_hari_ini' => $orderStats['hari_ini'],
            'pesanan_kemarin' => $orderStats['kemarin'],
            'pesanan_7_hari_terakhir' => $orderStats['terakhir_7_hari'],
            'pesanan_bulan_ini' => $orderStats['bulan_ini'],
            'total_pesanan' => $orderStats['total'],
            'pesanan_per_status_hari_ini' => $orderStats['per_status_hari_ini'],
            'pesanan_aktif_sekarang' => $orderStats['aktif_sekarang'],
            'pesanan_menunggu_pembayaran' => $orderStats['menunggu_pembayaran'],
            'rata_rata_nilai_pesanan_bulan_ini' => $orderStats['rata_rata_nilai_bulan_ini'],

            // Ringkasan Omset Terbayar (payment_status = 'paid')
            'omset_hari_ini' => $revenueStats['hari_ini'],
            'omset_kemarin' => $revenueStats['kemarin'],
            'omset_7_hari_terakhir' => $revenueStats['terakhir_7_hari'],
            'omset_bulan_ini' => $revenueStats['bulan_ini'],
            'total_omset' => $revenueStats['total'],

            // Pembayaran & Refund
            'pembayaran_kedaluwarsa_hari_ini' => $expiredPaymentsToday,
            'refund_pending_jumlah' => $refundStats['jumlah'],
            'refund_pending_nilai' => $refundStats['nilai'],

            // Customer
            'customer_terdaftar' => $customerStats['terdaftar'],
            'customer_aktif' => $customerStats['aktif'],
            'customer_pesan_30_hari_terakhir' => $customerStats['pesan_30_hari_terakhir'],

            // Struktur terkelompok untuk kemudahan format prompt
            'pesanan' => $orderStats,
            'omset' => $revenueStats,
            'refund' => $refundStats,
            'customer' => $customerStats,
        ];

        // Khusus Platform vs Khusus Toko
        if ($storeId === null) {
            $storeCounts = $this->getStoreStatusCounts();
            $courierCounts = $this->getCourierStatusCounts();
            $userRoleCounts = $this->getUserCountByRole();
            $top3Stores = $this->getTop3StoresThisMonth();

            $context['jumlah_toko_aktif'] = $storeCounts['aktif'];
            $context['jumlah_toko_nonaktif'] = $storeCounts['nonaktif'];
            $context['jumlah_kurir'] = $courierCounts;
            $context['jumlah_user_per_role'] = $userRoleCounts;
            $context['top_3_toko_omset_bulan_ini'] = $top3Stores;

            $context['khusus_platform'] = [
                'toko' => $storeCounts,
                'kurir' => $courierCounts,
                'user_per_role' => $userRoleCounts,
                'top_3_toko_bulan_ini' => $top3Stores,
            ];
        } else {
            $serviceCount = $this->getActiveServiceCount($storeId);

            $context['jumlah_layanan_aktif'] = $serviceCount;

            $context['khusus_toko'] = [
                'nama_toko' => (string) $store['name'],
                'jumlah_layanan_aktif' => $serviceCount,
            ];
        }

        return $context;
    }

    /**
     * Hitung statistik jumlah pesanan dalam berbagai rentang waktu server database.
     */
    private function getOrderStats(?int $storeId): array
    {
        $params = [];
        $storeFilter = '';
        if ($storeId !== null) {
            $storeFilter = ' AND store_id = :sid';
            $params['sid'] = $storeId;
        }

        // Hari ini
        [$todayStart, $todayEnd] = MetricsService::getTodayRange();
        $params['today_start'] = $todayStart;
        $params['today_end'] = $todayEnd;
        $sql = "SELECT COUNT(*) FROM orders WHERE created_at >= :today_start AND created_at < :today_end{$storeFilter}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $hariIni = (int) $stmt->fetchColumn();

        // Kemarin (24 jam sebelum hari ini berdasarkan Asia/Jakarta)
        $tz = MetricsService::getTimezone();
        $kemarinStart = (new \DateTime('yesterday', $tz))->format('Y-m-d 00:00:00');
        $params['kemarin_start'] = $kemarinStart;
        $params['kemarin_end'] = $todayStart;
        $sql = "SELECT COUNT(*) FROM orders WHERE created_at >= :kemarin_start AND created_at < :today_end_k{$storeFilter}";
        // Bersihkan parameter khusus untuk menghindari tabrakan
        $paramsKemarin = ['kemarin_start' => $kemarinStart, 'today_end_k' => $todayStart];
        if ($storeId !== null) {
            $paramsKemarin['sid'] = $storeId;
        }
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM orders WHERE created_at >= :kemarin_start AND created_at < :today_end_k{$storeFilter}");
        $stmt->execute($paramsKemarin);
        $kemarin = (int) $stmt->fetchColumn();

        // 7 hari terakhir (inklusif hari ini sampai 7 hari lalu)
        $tujuhHariStart = (new \DateTime('-7 days', $tz))->format('Y-m-d 00:00:00');
        $params7 = ['tujuh_start' => $tujuhHariStart];
        if ($storeId !== null) {
            $params7['sid'] = $storeId;
        }
        $sql = "SELECT COUNT(*) FROM orders WHERE created_at >= :tujuh_start{$storeFilter}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params7);
        $terakhir7Hari = (int) $stmt->fetchColumn();

        // Bulan ini
        [$monthStart, $monthEnd] = MetricsService::getMonthRange();
        $paramsMonth = ['m_start' => $monthStart, 'm_end' => $monthEnd];
        if ($storeId !== null) {
            $paramsMonth['sid'] = $storeId;
        }
        $sql = "SELECT COUNT(*) FROM orders WHERE created_at >= :m_start AND created_at < :m_end{$storeFilter}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($paramsMonth);
        $bulanIni = (int) $stmt->fetchColumn();

        // Total pesanan sepanjang masa (sesuai DashboardController)
        $paramsTotal = [];
        if ($storeId !== null) {
            $paramsTotal['sid'] = $storeId;
        }
        $sqlTotal = $storeId !== null
            ? "SELECT COUNT(*) FROM orders WHERE store_id = :sid"
            : "SELECT COUNT(*) FROM orders";
        $stmt = $this->db->prepare($sqlTotal);
        $stmt->execute($paramsTotal);
        $totalOrders = (int) $stmt->fetchColumn();

        // Pesanan per status hari ini
        $paramsStatus = ['today_start' => $todayStart, 'today_end' => $todayEnd];
        if ($storeId !== null) {
            $paramsStatus['sid'] = $storeId;
        }
        $sql = "SELECT status, COUNT(*) AS cnt FROM orders WHERE created_at >= :today_start AND created_at < :today_end{$storeFilter} GROUP BY status";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($paramsStatus);
        $perStatus = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $perStatus[$row['status']] = (int) $row['cnt'];
        }

        // Pesanan aktif sekarang (belum delivered dan belum cancelled)
        $paramsAktif = [];
        if ($storeId !== null) {
            $paramsAktif['sid'] = $storeId;
        }
        $sql = "SELECT COUNT(*) FROM orders WHERE status NOT IN ('delivered', 'cancelled'){$storeFilter}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($paramsAktif);
        $aktifSekarang = (int) $stmt->fetchColumn();

        // Pesanan menunggu pembayaran
        $paramsWait = [];
        if ($storeId !== null) {
            $paramsWait['sid'] = $storeId;
        }
        $sql = "SELECT COUNT(*) FROM orders WHERE status = 'waiting_payment'{$storeFilter}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($paramsWait);
        $menungguPembayaran = (int) $stmt->fetchColumn();

        // Rata-rata nilai pesanan bulan ini
        $paramsAvg = ['m_start' => $monthStart, 'm_end' => $monthEnd];
        if ($storeId !== null) {
            $paramsAvg['sid'] = $storeId;
        }
        $sql = "SELECT COALESCE(AVG(total_amount), 0) FROM orders WHERE created_at >= :m_start AND created_at < :m_end{$storeFilter}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($paramsAvg);
        $rataRataNilaiBulanIni = (float) $stmt->fetchColumn();

        return [
            'hari_ini' => $hariIni,
            'kemarin' => $kemarin,
            'terakhir_7_hari' => $terakhir7Hari,
            'bulan_ini' => $bulanIni,
            'total' => $totalOrders,
            'per_status_hari_ini' => $perStatus,
            'aktif_sekarang' => $aktifSekarang,
            'menunggu_pembayaran' => $menungguPembayaran,
            'rata_rata_nilai_bulan_ini' => round($rataRataNilaiBulanIni, 2),
        ];
    }

    /**
     * Hitung omset terbayar (payment_status = 'paid') sesuai kriteria DashboardController.
     */
    private function getRevenueStats(?int $storeId): array
    {
        [$todayStart, $todayEnd] = MetricsService::getTodayRange();
        $tz = MetricsService::getTimezone();
        $kemarinStart = (new \DateTime('yesterday', $tz))->format('Y-m-d 00:00:00');
        $tujuhHariStart = (new \DateTime('-7 days', $tz))->format('Y-m-d 00:00:00');
        [$monthStart, $monthEnd] = MetricsService::getMonthRange();

        $storeFilter = '';
        if ($storeId !== null) {
            $storeFilter = ' AND store_id = :sid';
        }

        // Hari ini
        $paramsToday = ['today_start' => $todayStart, 'today_end' => $todayEnd];
        if ($storeId !== null) $paramsToday['sid'] = $storeId;
        $sql = "SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE payment_status = 'paid' AND created_at >= :today_start AND created_at < :today_end{$storeFilter}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($paramsToday);
        $hariIni = (float) $stmt->fetchColumn();

        // Kemarin
        $paramsKemarin = ['kemarin_start' => $kemarinStart, 'today_end' => $todayStart];
        if ($storeId !== null) $paramsKemarin['sid'] = $storeId;
        $sql = "SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE payment_status = 'paid' AND created_at >= :kemarin_start AND created_at < :today_end{$storeFilter}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($paramsKemarin);
        $kemarin = (float) $stmt->fetchColumn();

        // 7 hari terakhir
        $params7 = ['tujuh_start' => $tujuhHariStart];
        if ($storeId !== null) $params7['sid'] = $storeId;
        $sql = "SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE payment_status = 'paid' AND created_at >= :tujuh_start{$storeFilter}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params7);
        $terakhir7Hari = (float) $stmt->fetchColumn();

        // Bulan ini
        $paramsMonth = ['m_start' => $monthStart, 'm_end' => $monthEnd];
        if ($storeId !== null) $paramsMonth['sid'] = $storeId;
        $sql = "SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE payment_status = 'paid' AND created_at >= :m_start AND created_at < :m_end{$storeFilter}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($paramsMonth);
        $bulanIni = (float) $stmt->fetchColumn();

        // Total omset terbayar sepanjang masa (sesuai DashboardController::owner/adminOwner/adminToko)
        $paramsTotal = [];
        if ($storeId !== null) $paramsTotal['sid'] = $storeId;
        $sqlTotal = $storeId !== null
            ? "SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE store_id = :sid AND payment_status = 'paid'"
            : "SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE payment_status = 'paid'";
        $stmt = $this->db->prepare($sqlTotal);
        $stmt->execute($paramsTotal);
        $totalOmset = (float) $stmt->fetchColumn();

        return [
            'hari_ini' => round($hariIni, 2),
            'kemarin' => round($kemarin, 2),
            'terakhir_7_hari' => round($terakhir7Hari, 2),
            'bulan_ini' => round($bulanIni, 2),
            'total' => round($totalOmset, 2),
        ];
    }

    /**
     * Hitung jumlah pembayaran yang kedaluwarsa hari ini.
     */
    private function getExpiredPaymentsToday(?int $storeId): int
    {
        if ($storeId !== null) {
            $sql = "SELECT COUNT(*) FROM payments p
                    JOIN orders o ON o.id = p.order_id
                    WHERE o.store_id = :sid 
                      AND p.status = 'expired' 
                      AND (DATE(p.updated_at) = CURDATE() OR DATE(p.expired_at) = CURDATE())";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['sid' => $storeId]);
        } else {
            $sql = "SELECT COUNT(*) FROM payments
                    WHERE status = 'expired' 
                      AND (DATE(updated_at) = CURDATE() OR DATE(expired_at) = CURDATE())";
            $stmt = $this->db->query($sql);
        }

        return (int) $stmt->fetchColumn();
    }

    /**
     * Hitung jumlah refund berstatus pending dan total nilainya.
     */
    private function getPendingRefundStats(?int $storeId): array
    {
        if ($storeId !== null) {
            $sql = "SELECT COUNT(*) AS cnt, COALESCE(SUM(r.amount), 0) AS total
                    FROM refunds r
                    JOIN orders o ON o.id = r.order_id
                    WHERE o.store_id = :sid AND r.status = 'pending'";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['sid' => $storeId]);
        } else {
            $sql = "SELECT COUNT(*) AS cnt, COALESCE(SUM(amount), 0) AS total
                    FROM refunds
                    WHERE status = 'pending'";
            $stmt = $this->db->query($sql);
        }

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return [
            'jumlah' => (int) ($row['cnt'] ?? 0),
            'nilai' => round((float) ($row['total'] ?? 0.0), 2),
        ];
    }

    /**
     * Hitung statistik customer (terdaftar, aktif, dan yang membuat pesanan 30 hari terakhir).
     */
    private function getCustomerStats(?int $storeId): array
    {
        if ($storeId !== null) {
            // Untuk toko: customer yang pernah order di toko ini
            $sqlTerdaftar = "SELECT COUNT(DISTINCT customer_id) FROM orders WHERE store_id = :sid";
            $stmt = $this->db->prepare($sqlTerdaftar);
            $stmt->execute(['sid' => $storeId]);
            $terdaftar = (int) $stmt->fetchColumn();

            $sqlAktif = "SELECT COUNT(DISTINCT o.customer_id) 
                         FROM orders o 
                         JOIN users u ON o.customer_id = u.id 
                         WHERE o.store_id = :sid AND u.is_active = 1";
            $stmt = $this->db->prepare($sqlAktif);
            $stmt->execute(['sid' => $storeId]);
            $aktif = (int) $stmt->fetchColumn();

            $sqlPesan30Hari = "SELECT COUNT(DISTINCT customer_id) 
                              FROM orders 
                              WHERE store_id = :sid AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
            $stmt = $this->db->prepare($sqlPesan30Hari);
            $stmt->execute(['sid' => $storeId]);
            $pesan30Hari = (int) $stmt->fetchColumn();
        } else {
            // Untuk platform: seluruh pengguna bertipe customer
            $sqlTerdaftar = "SELECT COUNT(*) FROM users u JOIN roles r ON u.role_id = r.id WHERE r.name = 'customer'";
            $terdaftar = (int) $this->db->query($sqlTerdaftar)->fetchColumn();

            $sqlAktif = "SELECT COUNT(*) FROM users u JOIN roles r ON u.role_id = r.id WHERE r.name = 'customer' AND u.is_active = 1";
            $aktif = (int) $this->db->query($sqlAktif)->fetchColumn();

            $sqlPesan30Hari = "SELECT COUNT(DISTINCT customer_id) 
                              FROM orders 
                              WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
            $pesan30Hari = (int) $this->db->query($sqlPesan30Hari)->fetchColumn();
        }

        return [
            'terdaftar' => $terdaftar,
            'aktif' => $aktif,
            'pesan_30_hari_terakhir' => $pesan30Hari,
        ];
    }

    /**
     * Khusus Platform: Hitung toko aktif dan nonaktif (sesuai DashboardController).
     */
    private function getStoreStatusCounts(): array
    {
        $aktif = (int) $this->db->query("SELECT COUNT(*) FROM stores WHERE is_active = 1")->fetchColumn();
        $nonaktif = (int) $this->db->query("SELECT COUNT(*) FROM stores WHERE is_active = 0")->fetchColumn();

        return [
            'aktif' => $aktif,
            'nonaktif' => $nonaktif,
            'total' => $aktif + $nonaktif,
        ];
    }

    /**
     * Khusus Platform: Hitung jumlah kurir berdasarkan status tugas (available, busy, offline).
     */
    private function getCourierStatusCounts(): array
    {
        $sql = "SELECT 
                    COALESCE(SUM(CASE WHEN COALESCE(cl.duty_status, 'available') = 'available' THEN 1 ELSE 0 END), 0) AS available,
                    COALESCE(SUM(CASE WHEN cl.duty_status = 'busy' THEN 1 ELSE 0 END), 0) AS busy,
                    COALESCE(SUM(CASE WHEN cl.duty_status = 'offline' THEN 1 ELSE 0 END), 0) AS offline
                FROM users u
                JOIN roles r ON u.role_id = r.id AND r.name = 'kurir'
                LEFT JOIN courier_locations cl ON u.id = cl.courier_id";
        $stmt = $this->db->query($sql);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $available = (int) ($row['available'] ?? 0);
        $busy = (int) ($row['busy'] ?? 0);
        $offline = (int) ($row['offline'] ?? 0);

        return [
            'available' => $available,
            'busy' => $busy,
            'offline' => $offline,
            'total' => $available + $busy + $offline,
        ];
    }

    /**
     * Khusus Platform: Hitung jumlah user per role.
     */
    private function getUserCountByRole(): array
    {
        $sql = "SELECT r.name AS role, COUNT(*) AS cnt 
                FROM users u 
                JOIN roles r ON u.role_id = r.id 
                GROUP BY r.name";
        $stmt = $this->db->query($sql);
        $result = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $result[$row['role']] = (int) $row['cnt'];
        }
        return $result;
    }

    /**
     * Khusus Platform: Ambil 3 toko dengan omset tertinggi bulan ini (nama toko dan omset saja).
     * DILARANG menyertakan nama pemilik, email, atau telepon.
     */
    private function getTop3StoresThisMonth(): array
    {
        [$monthStart, $monthEnd] = MetricsService::getMonthRange();
        $sql = "SELECT s.name AS nama_toko, COALESCE(SUM(o.total_amount), 0) AS omset
                FROM stores s
                LEFT JOIN orders o ON s.id = o.store_id 
                    AND o.payment_status = 'paid' 
                    AND o.created_at >= :m_start 
                    AND o.created_at < :m_end
                GROUP BY s.id, s.name
                ORDER BY omset DESC
                LIMIT 3";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['m_start' => $monthStart, 'm_end' => $monthEnd]);
        $stores = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $stores[] = [
                'nama_toko' => (string) $row['nama_toko'],
                'omset' => round((float) $row['omset'], 2),
            ];
        }
        return $stores;
    }

    /**
     * Khusus Toko: Hitung jumlah layanan aktif toko tersebut.
     */
    private function getActiveServiceCount(int $storeId): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM services WHERE store_id = :sid AND is_active = 1");
        $stmt->execute(['sid' => $storeId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Mengambil daftar layanan aktif beserta harga riil dari tabel `services`.
     * Scoped:
     * - Jika $storeId terdefinisi: hanya layanan milik toko tersebut.
     * - Jika $storeId null (owner/admin_owner): seluruh layanan aktif di platform beserta nama tokonya.
     *
     * Kolom allowlist: s.id, s.store_id, s.name, s.price, s.unit, s.duration_hours, st.name AS store_name
     */
    public function getServicesCatalog(?int $storeId = null): array
    {
        if (!$this->db) {
            return [];
        }

        try {
            if ($storeId !== null) {
                $sql = "SELECT s.id, s.store_id, s.name, s.price, s.unit, s.duration_hours, st.name AS store_name
                        FROM services s
                        JOIN stores st ON s.store_id = st.id
                        WHERE s.store_id = :sid AND s.is_active = 1
                        ORDER BY s.price ASC";
                $stmt = $this->db->prepare($sql);
                $stmt->execute(['sid' => $storeId]);
            } else {
                $sql = "SELECT s.id, s.store_id, s.name, s.price, s.unit, s.duration_hours, st.name AS store_name
                        FROM services s
                        JOIN stores st ON s.store_id = st.id
                        WHERE s.is_active = 1
                        ORDER BY st.name ASC, s.price ASC";
                $stmt = $this->db->query($sql);
            }

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * Mengambil ringkasan log WhatsApp.
     * HANYA untuk role staff berwenang:
     * - owner & admin_owner: seluruh platform
     * - admin_toko: difilter strictly berdasarkan store_id
     * - role lain / customer: DITOLAK (return null)
     *
     * KEAMANAN KETAT:
     * - DILARANG menyertakan provider_response mentah, token, id_pesan, atau payload privat.
     * - Kolom allowlist: ringkasan jumlah status (sent, failed, test_sent), event_type, waktu terakhir.
     */
    public function getWhatsAppLogsSummary(int $userId, string $role, ?int $storeId = null): ?array
    {
        if (!$this->db) {
            return null;
        }

        $allowedRoles = ['owner', 'admin_owner', 'admin_toko'];
        $normalizedRole = strtolower(trim($role));
        if (!in_array($normalizedRole, $allowedRoles, true)) {
            return null; // Ditolak untuk customer, kurir, atau role lain
        }

        try {
            $conditions = [];
            $params = [];

            if ($normalizedRole === 'admin_toko') {
                if ($storeId === null) {
                    $storeObj = (new Store())->getStoreByOwner($userId);
                    $storeId = !empty($storeObj['id']) ? (int)$storeObj['id'] : 0;
                }
                $conditions[] = "store_id = :sid";
                $params['sid'] = $storeId;
            }

            $where = !empty($conditions) ? "WHERE " . implode(' AND ', $conditions) : "";

            // 1. Hitung agregasi status pengiriman
            $sqlStatus = "SELECT 
                            COUNT(*) as total_logs,
                            SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as total_sent,
                            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as total_failed,
                            SUM(CASE WHEN status = 'test_sent' THEN 1 ELSE 0 END) as total_test_sent
                          FROM whatsapp_logs {$where}";
            $stmt = $this->db->prepare($sqlStatus);
            $stmt->execute($params);
            $summary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

            // 2. Ambil 5 aktivitas notifikasi terakhir (tanpa provider_response, tanpa phone privat, tanpa token)
            $sqlRecent = "SELECT event_type, recipient_role, status, created_at 
                          FROM whatsapp_logs {$where} 
                          ORDER BY id DESC LIMIT 5";
            $stmtRecent = $this->db->prepare($sqlRecent);
            $stmtRecent->execute($params);
            $recent = $stmtRecent->fetchAll(PDO::FETCH_ASSOC) ?: [];

            return [
                'cakupan' => ($normalizedRole === 'admin_toko') ? 'toko' : 'platform',
                'total_logs' => (int)($summary['total_logs'] ?? 0),
                'total_sent' => (int)($summary['total_sent'] ?? 0),
                'total_failed' => (int)($summary['total_failed'] ?? 0),
                'total_test_sent' => (int)($summary['total_test_sent'] ?? 0),
                'aktivitas_terakhir' => $recent,
            ];
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Mengambil daftar notifikasi terbaru HANYA milik user yang sedang login.
     * Scoped ketat: WHERE user_id = :uid
     * Allowlist kolom: title, message, is_read, type, created_at
     */
    public function getUserNotifications(int $userId, int $limit = 5): array
    {
        if (!$this->db || $userId <= 0) {
            return [];
        }

        try {
            $sql = "SELECT title, message, is_read, type, created_at 
                    FROM notifications 
                    WHERE user_id = :uid 
                    ORDER BY id DESC LIMIT :lim";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }
}
