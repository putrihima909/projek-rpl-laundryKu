<?php

namespace App\Services\Finance;

use App\Core\Database;
use PDO;

class OmzetTransparencyService
{
    protected ?PDO $db = null;

    public function __construct(?PDO $db = null)
    {
        if (func_num_args() > 0) {
            $this->db = $db;
        } else {
            try {
                $this->db = Database::getInstance();
            } catch (\Throwable $e) {
                $this->db = null;
            }
        }
    }

    /**
     * Mengambil data transparansi perhitungan omzet secara detail dan akurat.
     *
     * @param int|null $storeId Jika null, cakupan seluruh platform (Owner Platform).
     *                          Jika diisi integer, cakupan terisolasi ke toko tersebut (Owner Toko).
     */
    public function getOmzetTransparencyData(?int $storeId = null): array
    {
        if (!$this->db) {
            return $this->getEmptyStructure($storeId);
        }

        $storeFilter = $storeId !== null ? "AND o.store_id = :sid" : "";
        $storeFilterSolo = $storeId !== null ? "WHERE o.store_id = :sid" : "";
        $storeJoinPay = $storeId !== null ? "JOIN orders o ON p.order_id = o.id WHERE o.store_id = :sid" : "WHERE 1=1";
        $storeJoinRef = $storeId !== null ? "JOIN orders o ON r.order_id = o.id WHERE o.store_id = :sid" : "WHERE 1=1";

        $params = $storeId !== null ? ['sid' => $storeId] : [];

        // 1. OMZET TERBAYAR AKTIF (orders.payment_status = 'paid')
        $sqlPaid = "SELECT COUNT(*) as cnt, COALESCE(SUM(o.total_amount), 0) as total_amt
                    FROM orders o
                    WHERE o.payment_status = 'paid' {$storeFilter}";
        $stmt = $this->db->prepare($sqlPaid);
        $stmt->execute($params);
        $paidData = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['cnt' => 0, 'total_amt' => 0.0];

        // 2. PEMBAYARAN GATEWAY AKTUAL (payments.status = 'paid')
        $sqlPay = "SELECT p.payment_method, COUNT(p.id) as cnt, COALESCE(SUM(p.amount), 0) as total_amt
                   FROM payments p
                   {$storeJoinPay} AND p.status = 'paid'
                   GROUP BY p.payment_method";
        $stmt = $this->db->prepare($sqlPay);
        $stmt->execute($params);
        $gatewayRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalGatewayPaid = 0.0;
        $totalGatewayCount = 0;
        $gatewayMethods = [];
        foreach ($gatewayRows as $gr) {
            $amt = (float)$gr['total_amt'];
            $c = (int)$gr['cnt'];
            $totalGatewayPaid += $amt;
            $totalGatewayCount += $c;
            $gatewayMethods[$gr['payment_method']] = [
                'count' => $c,
                'amount' => $amt
            ];
        }

        // 3. REFUND DETAIL DARI TABEL refunds
        $sqlRefunds = "SELECT r.status, COUNT(r.id) as cnt, COALESCE(SUM(r.amount), 0) as total_amt
                       FROM refunds r
                       {$storeJoinRef}
                       GROUP BY r.status";
        $stmt = $this->db->prepare($sqlRefunds);
        $stmt->execute($params);
        $refundRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $refundSummary = [
            'completed' => ['count' => 0, 'amount' => 0.0],
            'pending' => ['count' => 0, 'amount' => 0.0],
            'processing' => ['count' => 0, 'amount' => 0.0],
            'failed' => ['count' => 0, 'amount' => 0.0],
        ];
        foreach ($refundRows as $rr) {
            $st = $rr['status'];
            if (isset($refundSummary[$st])) {
                $refundSummary[$st]['count'] = (int)$rr['cnt'];
                $refundSummary[$st]['amount'] = (float)$rr['total_amt'];
            }
        }

        // 4. TRANSAKSI YANG DIKECUALIKAN DARI OMZET TERBAYAR
        // A. Unpaid (menunggu bayar)
        $sqlUnpaid = "SELECT COUNT(*) as cnt, COALESCE(SUM(o.total_amount), 0) as total_amt
                      FROM orders o
                      WHERE o.payment_status = 'unpaid' {$storeFilter}";
        $stmt = $this->db->prepare($sqlUnpaid);
        $stmt->execute($params);
        $unpaidData = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['cnt' => 0, 'total_amt' => 0.0];

        // B. Refunded (dibatalkan & dialihkan ke refund)
        $sqlRefunded = "SELECT COUNT(*) as cnt, COALESCE(SUM(o.total_amount), 0) as total_amt
                        FROM orders o
                        WHERE o.payment_status = 'refunded' {$storeFilter}";
        $stmt = $this->db->prepare($sqlRefunded);
        $stmt->execute($params);
        $refundedData = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['cnt' => 0, 'total_amt' => 0.0];

        // C. Cancelled
        $sqlCancelled = "SELECT COUNT(*) as cnt, COALESCE(SUM(o.total_amount), 0) as total_amt
                         FROM orders o
                         WHERE o.status = 'cancelled' AND o.payment_status != 'refunded' {$storeFilter}";
        $stmt = $this->db->prepare($sqlCancelled);
        $stmt->execute($params);
        $cancelledData = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['cnt' => 0, 'total_amt' => 0.0];

        // 5. DAFTAR TRANSAKSI PEMBENTUK OMZET TERBAYAR (orders WHERE payment_status = 'paid')
        $sqlPaidList = "SELECT o.id, o.order_code, o.created_at, o.total_amount, o.payment_method, o.status,
                               s.name as store_name
                        FROM orders o
                        LEFT JOIN stores s ON o.store_id = s.id
                        WHERE o.payment_status = 'paid' {$storeFilter}
                        ORDER BY o.id DESC";
        $stmt = $this->db->prepare($sqlPaidList);
        $stmt->execute($params);
        $paidTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 6. DAFTAR PESANAN REFUNDED BESERTA STATUS REFUND TERKAIT
        $sqlRefundList = "SELECT o.id, o.order_code, o.created_at, o.total_amount,
                                 r.id as refund_id, r.amount as refund_amount, r.status as refund_status,
                                 r.reason, s.name as store_name
                          FROM orders o
                          LEFT JOIN refunds r ON o.id = r.order_id
                          LEFT JOIN stores s ON o.store_id = s.id
                          WHERE o.payment_status = 'refunded' {$storeFilter}
                          ORDER BY o.id DESC";
        $stmt = $this->db->prepare($sqlRefundList);
        $stmt->execute($params);
        $refundedTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'store_id' => $storeId,
            'is_platform' => $storeId === null,
            // 1. Omzet Terbayar Aktif
            'omzet_terbayar_aktif' => [
                'nominal' => (float)$paidData['total_amt'],
                'count' => (int)$paidData['cnt'],
                'formula' => "SUM(orders.total_amount) WHERE payment_status = 'paid'",
                'penjelasan' => "Akumulasi nilai pesanan lunas saat ini. Tidak dikurangi refund lagi karena pesanan yang direfund telah otomatis dikeluarkan dari status 'paid'."
            ],
            // 2. Pembayaran Gateway Aktual
            'pembayaran_gateway' => [
                'nominal' => $totalGatewayPaid,
                'count' => $totalGatewayCount,
                'methods' => $gatewayMethods,
                'penjelasan' => "Total uang yang benar-benar tercatat masuk dan terverifikasi oleh gateway payment (misal QRIS). Selisih dengan order paid dapat berasal dari transaksi pesanan yang diverifikasi manual/tunai."
            ],
            // 3. Refund Completed
            'refund_selesai' => [
                'nominal' => $refundSummary['completed']['amount'],
                'count' => $refundSummary['completed']['count'],
                'penjelasan' => "Total dana yang telah tuntas dikembalikan ke saldo/rekening pelanggan."
            ],
            // 4. Refund Belum Selesai (Dipisahkan)
            'refund_belum_selesai' => [
                'pending' => $refundSummary['pending'],
                'processing' => $refundSummary['processing'],
                'failed' => $refundSummary['failed'],
                'total_nominal' => $refundSummary['pending']['amount'] + $refundSummary['processing']['amount'] + $refundSummary['failed']['amount'],
                'total_count' => $refundSummary['pending']['count'] + $refundSummary['processing']['count'] + $refundSummary['failed']['count'],
                'penjelasan' => "Dana permohonan pengembalian yang masih berstatus menunggu verifikasi (pending), sedang diproses (processing), atau gagal transfer (failed). Dana ini BELUM keluar dari kas bisnis."
            ],
            // 5. Transaksi Dikecualikan
            'transaksi_dikecualikan' => [
                'unpaid' => [
                    'count' => (int)$unpaidData['cnt'],
                    'nominal' => (float)$unpaidData['total_amt'],
                    'alasan' => "Pesanan belum dibayar oleh pelanggan sehingga belum menjadi uang masuk."
                ],
                'refunded' => [
                    'count' => (int)$refundedData['cnt'],
                    'nominal' => (float)$refundedData['total_amt'],
                    'alasan' => "Pesanan dibatalkan/diretur dan status pembayarannya dialihkan ke 'refunded', sehingga tidak dihitung dalam omzet lunas aktif."
                ],
                'cancelled' => [
                    'count' => (int)$cancelledData['cnt'],
                    'nominal' => (float)$cancelledData['total_amt'],
                    'alasan' => "Pesanan dibatalkan sebelum proses pembayaran berlangsung."
                ]
            ],
            // 6. Transaksi Sumber Pembentuk Omzet
            'transaksi_pembentuk_omzet' => $paidTransactions,
            // 7. Transaksi Refunded
            'transaksi_refunded' => $refundedTransactions
        ];
    }

    /**
     * Mengambil laporan rincian omzet secara lengkap dengan filter, summary statistik, breakdown metode, grafik harian & layanan.
     *
     * @param int|null $storeId
     * @param array $filters ['start_date', 'end_date', 'payment_status', 'payment_method', 'period']
     * @return array
     */
    public function getDetailedReport(?int $storeId = null, array $filters = []): array
    {
        if (!$this->db) {
            return [
                'summary' => [
                    'total_omset' => 0.0,
                    'total_transaksi' => 0,
                    'rata_rata' => 0.0,
                    'omset_hari_ini' => 0.0,
                    'omset_minggu_ini' => 0.0,
                    'omset_bulan_ini' => 0.0,
                ],
                'transactions' => [],
                'method_breakdown' => [],
                'chart_daily' => ['labels' => [], 'data' => []],
                'chart_services' => ['labels' => [], 'data' => []],
                'filters_applied' => $filters,
            ];
        }

        // Tentukan rentang tanggal default jika ada shortcut 'period'
        $period = $filters['period'] ?? '';
        $startDate = $filters['start_date'] ?? '';
        $endDate = $filters['end_date'] ?? '';
        $statusFilter = $filters['payment_status'] ?? 'paid'; // Default paid (lunas) sesuai kaidah omzet
        $methodFilter = $filters['payment_method'] ?? '';

        $today = date('Y-m-d');
        if (!empty($period)) {
            switch ($period) {
                case 'today':
                    $startDate = $today;
                    $endDate = $today;
                    break;
                case 'yesterday':
                    $yesterday = date('Y-m-d', strtotime('-1 day'));
                    $startDate = $yesterday;
                    $endDate = $yesterday;
                    break;
                case '7days':
                    $startDate = date('Y-m-d', strtotime('-6 days'));
                    $endDate = $today;
                    break;
                case 'this_month':
                    $startDate = date('Y-m-01');
                    $endDate = date('Y-m-t');
                    break;
                case 'last_month':
                    $startDate = date('Y-m-01', strtotime('first day of last month'));
                    $endDate = date('Y-m-t', strtotime('last day of last month'));
                    break;
                case 'this_year':
                    $startDate = date('Y-01-01');
                    $endDate = date('Y-12-31');
                    break;
            }
        }

        // Base where clauses & parameters
        $whereClauses = [];
        $params = [];

        if ($storeId !== null) {
            $whereClauses[] = "o.store_id = :sid";
            $params['sid'] = $storeId;
        }

        // Status Filter
        if (!empty($statusFilter)) {
            $whereClauses[] = "o.payment_status = :status";
            $params['status'] = $statusFilter;
        }

        // Payment Method Filter
        if (!empty($methodFilter)) {
            $whereClauses[] = "o.payment_method = :pmethod";
            $params['pmethod'] = $methodFilter;
        }

        // Date Range Filter
        if (!empty($startDate)) {
            $whereClauses[] = "DATE(o.created_at) >= :start_date";
            $params['start_date'] = $startDate;
        }
        if (!empty($endDate)) {
            $whereClauses[] = "DATE(o.created_at) <= :end_date";
            $params['end_date'] = $endDate;
        }

        $whereSql = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";

        // 1. STATISTIK RINGKASAN SESUAI FILTER
        $sqlSummary = "SELECT 
                          COUNT(o.id) as total_transaksi,
                          COALESCE(SUM(o.total_amount), 0) as total_omset,
                          COALESCE(AVG(o.total_amount), 0) as rata_rata
                       FROM orders o
                       {$whereSql}";
        $stmt = $this->db->prepare($sqlSummary);
        $stmt->execute($params);
        $filteredSummary = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['total_transaksi' => 0, 'total_omset' => 0.0, 'rata_rata' => 0.0];

        // 2. STATISTIK PERIODE GLOBAL TOKO (Hari Ini, Minggu Ini, Bulan Ini)
        // Dihitung dari transaksi yang sah (payment_status = 'paid') milik toko ini
        $baseStoreWhere = $storeId !== null ? "WHERE o.store_id = :base_sid AND o.payment_status = 'paid'" : "WHERE o.payment_status = 'paid'";
        $baseParams = $storeId !== null ? ['base_sid' => $storeId] : [];

        $sqlPeriods = "SELECT
                          COALESCE(SUM(CASE WHEN DATE(o.created_at) = CURDATE() THEN o.total_amount ELSE 0 END), 0) as omset_hari_ini,
                          COALESCE(SUM(CASE WHEN YEARWEEK(o.created_at, 1) = YEARWEEK(CURDATE(), 1) THEN o.total_amount ELSE 0 END), 0) as omset_minggu_ini,
                          COALESCE(SUM(CASE WHEN YEAR(o.created_at) = YEAR(CURDATE()) AND MONTH(o.created_at) = MONTH(CURDATE()) THEN o.total_amount ELSE 0 END), 0) as omset_bulan_ini
                       FROM orders o
                       {$baseStoreWhere}";
        $stmt = $this->db->prepare($sqlPeriods);
        $stmt->execute($baseParams);
        $periodsSummary = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['omset_hari_ini' => 0.0, 'omset_minggu_ini' => 0.0, 'omset_bulan_ini' => 0.0];

        // 3. DAFTAR TRANSAKSI RINCIAN
        $sqlTransactions = "SELECT 
                                o.id,
                                o.order_code,
                                o.created_at,
                                DATE_FORMAT(o.created_at, '%d-%m-%Y %H:%i') as tanggal_formatted,
                                DATE(o.created_at) as order_date,
                                COALESCE(u.name, 'Pelanggan') as customer_name,
                                COALESCE(u.phone, '-') as customer_phone,
                                COALESCE(s.name, 'Layanan Standar') as service_name,
                                COALESCE(s.unit, 'Kg') as service_unit,
                                COALESCE(o.actual_weight, o.estimated_weight, 0) as weight,
                                o.unit_price,
                                COALESCE(o.subtotal, (COALESCE(o.actual_weight, o.estimated_weight, 0) * o.unit_price)) as subtotal,
                                o.additional_fee,
                                o.discount,
                                o.total_amount,
                                o.payment_method,
                                o.payment_status,
                                o.status as order_status,
                                st.name as store_name,
                                COALESCE(p.gateway, 'Kasir / Direct') as processed_by
                            FROM orders o
                            LEFT JOIN users u ON o.customer_id = u.id
                            LEFT JOIN services s ON o.service_id = s.id
                            LEFT JOIN stores st ON o.store_id = st.id
                            LEFT JOIN payments p ON p.order_id = o.id AND p.status = 'paid'
                            {$whereSql}
                            ORDER BY o.created_at DESC";
        $stmt = $this->db->prepare($sqlTransactions);
        $stmt->execute($params);
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 4. REKAP METODE PEMBAYARAN SESUAI FILTER
        $sqlMethods = "SELECT 
                          o.payment_method,
                          COUNT(o.id) as cnt,
                          COALESCE(SUM(o.total_amount), 0) as total_amt
                       FROM orders o
                       {$whereSql}
                       GROUP BY o.payment_method";
        $stmt = $this->db->prepare($sqlMethods);
        $stmt->execute($params);
        $methodRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $methodBreakdown = [];
        foreach ($methodRows as $mr) {
            $methodBreakdown[] = [
                'method' => strtoupper($mr['payment_method']),
                'count' => (int)$mr['cnt'],
                'amount' => (float)$mr['total_amt'],
            ];
        }

        // 5. DATA GRAFIK HARIAN OMSET
        $sqlChartDaily = "SELECT 
                             DATE(o.created_at) as order_date,
                             DATE_FORMAT(o.created_at, '%d %b') as date_label,
                             COALESCE(SUM(o.total_amount), 0) as daily_omset,
                             COUNT(o.id) as daily_count
                          FROM orders o
                          {$whereSql}
                          GROUP BY DATE(o.created_at), DATE_FORMAT(o.created_at, '%d %b')
                          ORDER BY DATE(o.created_at) ASC";
        $stmt = $this->db->prepare($sqlChartDaily);
        $stmt->execute($params);
        $dailyRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $chartDailyLabels = [];
        $chartDailyData = [];
        foreach ($dailyRows as $dr) {
            $chartDailyLabels[] = $dr['date_label'];
            $chartDailyData[] = (float)$dr['daily_omset'];
        }

        // 6. DATA GRAFIK OMSET BERDASARKAN LAYANAN
        $sqlChartServices = "SELECT 
                                COALESCE(s.name, 'Layanan Lain') as service_name,
                                COALESCE(SUM(o.total_amount), 0) as service_omset,
                                COUNT(o.id) as service_count
                             FROM orders o
                             LEFT JOIN services s ON o.service_id = s.id
                             {$whereSql}
                             GROUP BY s.id, s.name
                             ORDER BY service_omset DESC";
        $stmt = $this->db->prepare($sqlChartServices);
        $stmt->execute($params);
        $serviceRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $chartServiceLabels = [];
        $chartServiceData = [];
        foreach ($serviceRows as $sr) {
            $chartServiceLabels[] = $sr['service_name'];
            $chartServiceData[] = (float)$sr['service_omset'];
        }

        return [
            'summary' => [
                'total_omset' => (float)$filteredSummary['total_omset'],
                'total_transaksi' => (int)$filteredSummary['total_transaksi'],
                'rata_rata' => (float)$filteredSummary['rata_rata'],
                'omset_hari_ini' => (float)$periodsSummary['omset_hari_ini'],
                'omset_minggu_ini' => (float)$periodsSummary['omset_minggu_ini'],
                'omset_bulan_ini' => (float)$periodsSummary['omset_bulan_ini'],
            ],
            'transactions' => $transactions,
            'method_breakdown' => $methodBreakdown,
            'chart_daily' => [
                'labels' => $chartDailyLabels,
                'data' => $chartDailyData,
            ],
            'chart_services' => [
                'labels' => $chartServiceLabels,
                'data' => $chartServiceData,
            ],
            'filters_applied' => [
                'period' => $period,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'payment_status' => $statusFilter,
                'payment_method' => $methodFilter,
            ]
        ];
    }

    private function getEmptyStructure(?int $storeId): array
    {
        return [
            'store_id' => $storeId,
            'is_platform' => $storeId === null,
            'omzet_terbayar_aktif' => ['nominal' => 0.0, 'count' => 0, 'formula' => '', 'penjelasan' => ''],
            'pembayaran_gateway' => ['nominal' => 0.0, 'count' => 0, 'methods' => [], 'penjelasan' => ''],
            'refund_selesai' => ['nominal' => 0.0, 'count' => 0, 'penjelasan' => ''],
            'refund_belum_selesai' => [
                'pending' => ['count' => 0, 'amount' => 0.0],
                'processing' => ['count' => 0, 'amount' => 0.0],
                'failed' => ['count' => 0, 'amount' => 0.0],
                'total_nominal' => 0.0,
                'total_count' => 0,
                'penjelasan' => ''
            ],
            'transaksi_dikecualikan' => [
                'unpaid' => ['count' => 0, 'nominal' => 0.0, 'alasan' => ''],
                'refunded' => ['count' => 0, 'nominal' => 0.0, 'alasan' => ''],
                'cancelled' => ['count' => 0, 'nominal' => 0.0, 'alasan' => '']
            ],
            'transaksi_pembentuk_omzet' => [],
            'transaksi_refunded' => []
        ];
    }
}

