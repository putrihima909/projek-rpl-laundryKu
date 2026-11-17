<?php

namespace App\Models;

use App\Core\Model;

class Store extends Model
{
    protected string $table = 'stores';

    public function __construct(?\PDO $db = null)
    {
        if (func_num_args() > 0) {
            $this->db = $db;
        } else {
            parent::__construct();
        }
    }

    /**
     * Get all active stores, optionally filtered and sorted.
     * Supports: search (name LIKE), min_rating (placeholder for future),
     * sort by distance using Haversine formula when user coords provided.
     */
    public function getActiveStoresFiltered(
        array $filters = [],
        ?float $userLat = null,
        ?float $userLng = null
    ): array {
        if (!$this->db) {
            return $this->getDemoStores();
        }

        // Build Haversine distance expression if user coords available
        $distanceExpr = ($userLat !== null && $userLng !== null)
            ? ", (6371 * ACOS(
                  COS(RADIANS(:ulat)) * COS(RADIANS(s.latitude))
                  * COS(RADIANS(s.longitude) - RADIANS(:ulng))
                  + SIN(RADIANS(:ulat2)) * SIN(RADIANS(s.latitude))
              )) AS distance_km"
            : ", NULL AS distance_km";

        $sql = "SELECT s.*,
                       COUNT(DISTINCT sv.id) AS service_count,
                       MIN(sv.price) AS min_price
                       {$distanceExpr}
                FROM stores s
                LEFT JOIN services sv ON sv.store_id = s.id AND sv.is_active = 1
                WHERE s.is_active = 1";

        $params = [];

        // Filter: search by name or address
        $search = trim($filters['search'] ?? '');
        if ($search !== '') {
            $sql .= " AND (s.name LIKE :search OR s.address LIKE :search_addr)";
            $params['search'] = '%' . $search . '%';
            $params['search_addr'] = '%' . $search . '%';
        }

        // Filter: min rating
        if (!empty($filters['min_rating']) && is_numeric($filters['min_rating'])) {
            $sql .= " AND s.rating >= :min_rating";
            $params['min_rating'] = (float)$filters['min_rating'];
        }

        // Filter: service type
        $serviceType = trim($filters['service_type'] ?? '');
        if ($serviceType !== '') {
            $sql .= " AND EXISTS (
                SELECT 1 FROM services sv2 
                WHERE sv2.store_id = s.id AND sv2.is_active = 1 
                  AND (sv2.unit = :stype OR sv2.name LIKE :stypename)
            )";
            $params['stype'] = $serviceType;
            $params['stypename'] = '%' . $serviceType . '%';
        }

        // Haversine params
        if ($userLat !== null && $userLng !== null) {
            $params['ulat']  = $userLat;
            $params['ulng']  = $userLng;
            $params['ulat2'] = $userLat;
        }

        $sql .= " GROUP BY s.id";

        // Sort
        $sort = $filters['sort'] ?? 'name';
        if ($sort === 'distance' && $userLat !== null) {
            $sql .= " ORDER BY distance_km ASC";
        } elseif ($sort === 'rating') {
            $sql .= " ORDER BY s.rating DESC, s.name ASC";
        } elseif ($sort === 'price_asc') {
            $sql .= " ORDER BY min_price ASC, s.name ASC";
        } else {
            $sql .= " ORDER BY s.name ASC";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Get active stores without filters (simple, for home page).
     */
    public function getActiveStores(): array
    {
        if (!$this->db) {
            return $this->getDemoStores();
        }

        $stmt = $this->db->query("SELECT * FROM stores WHERE is_active = 1 ORDER BY rating DESC, name ASC");
        return $stmt->fetchAll();
    }

    /**
     * Get featured stores (highest rating & service count) for the home page.
     */
    public function getFeaturedStores(int $limit = 4): array
    {
        if (!$this->db) {
            return $this->getDemoStores();
        }

        $stmt = $this->db->prepare(
            "SELECT s.*, COUNT(sv.id) AS service_count, MIN(sv.price) AS min_price
             FROM stores s
             LEFT JOIN services sv ON sv.store_id = s.id AND sv.is_active = 1
             WHERE s.is_active = 1
             GROUP BY s.id
             ORDER BY s.rating DESC, service_count DESC, s.name ASC
             LIMIT :lim"
        );
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Get a single store by ID (includes service info for detail page).
     */
    public function findWithServices(int $storeId): ?array
    {
        if (!$this->db) {
            $stores = $this->getDemoStores();
            foreach ($stores as $s) {
                if ($s['id'] === $storeId) {
                    $s['services'] = $this->getStoreServices($storeId);
                    return $s;
                }
            }
            return null;
        }

        $stmt = $this->db->prepare("SELECT * FROM stores WHERE id = :id AND is_active = 1 LIMIT 1");
        $stmt->execute(['id' => $storeId]);
        $store = $stmt->fetch();
        if (!$store) return null;

        $store['services'] = $this->getStoreServices($storeId);
        return $store;
    }

    /**
     * Get store by its ID regardless of active status (admin use).
     */
    public function getStoreById(int $id): ?array
    {
        if (!$this->db) return null;
        $stmt = $this->db->prepare("SELECT * FROM stores WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Get the store owned or managed by a specific user (owner_toko or admin_toko context).
     * Strictly resolves from user_id relation or users.store_id.
     */
    public function getStoreByOwner(int $userId): ?array
    {
        if (!$this->db) {
            // No-DB fallback: returns minimal skeleton so the app does not crash.
            // Fields that require real DB data (address, phone, rating, working_hours)
            // are explicitly null — views must use the null-coalescing operator (?? '').
            // This path is only reached when the database is unavailable (e.g. dev/test
            // environment without MySQL). It is NOT production data.
            if ($userId === 3 || $userId === 4) {
                return [
                    'id'            => 1,
                    'user_id'       => 3,
                    'name'          => 'CleanMaster Laundry Express',
                    'address'       => null,
                    'phone'         => null,
                    'rating'        => null,
                    'working_hours' => null,
                    'is_active'     => 1,
                ];
            }
            if ($userId === 7 || $userId === 8) {
                return [
                    'id'            => 2,
                    'user_id'       => 7,
                    'name'          => 'Fresh & Clean Laundry',
                    'address'       => null,
                    'phone'         => null,
                    'rating'        => null,
                    'working_hours' => null,
                    'is_active'     => 1,
                ];
            }
            return null;
        }

        $stmt = $this->db->prepare(
            "SELECT s.* FROM stores s 
             WHERE s.user_id = :uid 
                OR s.id = (SELECT store_id FROM users WHERE id = :uid2) 
             LIMIT 1"
        );
        $stmt->execute(['uid' => $userId, 'uid2' => $userId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Get all stores (including inactive) with owner info — for admin management.
     */
    public function getAllStoresAdmin(): array
    {
        if (!$this->db) {
            return $this->getDemoStores();
        }

        $sql = "SELECT s.*, u.name AS owner_name, u.email AS owner_email,
                       COUNT(sv.id) AS service_count
                FROM stores s
                JOIN users u ON s.user_id = u.id
                LEFT JOIN services sv ON sv.store_id = s.id AND sv.is_active = 1
                GROUP BY s.id
                ORDER BY s.id DESC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Get services of a store.
     */
    public function getStoreServices(int $storeId): array
    {
        if (!$this->db) {
            return [
                ['id' => 1, 'store_id' => 1, 'name' => 'Cuci Komplit Reguler', 'description' => 'Cuci, keringkan, dan setrika rapi', 'price' => '6000.00', 'unit' => 'kg', 'duration_hours' => 24, 'is_active' => 1],
                ['id' => 2, 'store_id' => 1, 'name' => 'Cuci Kilat / Express 3 Jam', 'description' => 'Selesai cepat dalam 3 jam', 'price' => '12000.00', 'unit' => 'kg', 'duration_hours' => 3, 'is_active' => 1],
                ['id' => 3, 'store_id' => 1, 'name' => 'Dry Cleaning Jas / Tuxedo', 'description' => 'Perawatan khusus pakaian berbahan halus', 'price' => '35000.00', 'unit' => 'pcs', 'duration_hours' => 48, 'is_active' => 1]
            ];
        }

        $stmt = $this->db->prepare("SELECT * FROM services WHERE store_id = :sid AND is_active = 1 ORDER BY price ASC");
        $stmt->execute(['sid' => $storeId]);
        return $stmt->fetchAll();
    }

    /**
     * Find a single service by ID.
     */
    public function findService(int $serviceId): ?array
    {
        if (!$this->db) {
            $services = $this->getStoreServices(1);
            foreach ($services as $s) {
                if ($s['id'] === $serviceId) return $s;
            }
            return null;
        }

        $stmt = $this->db->prepare("SELECT * FROM services WHERE id = :sid LIMIT 1");
        $stmt->execute(['sid' => $serviceId]);
        $service = $stmt->fetch();
        return $service ?: null;
    }

    // ---------------------------------------------------------------
    // Private demo fallback data
    // ---------------------------------------------------------------
    private function getDemoStores(): array
    {
        return [
            [
                'id' => 1,
                'user_id' => 3,
                'name' => 'CleanMaster Laundry Express',
                'description' => 'Layanan cuci komplit cepat dan harum bergaransi',
                'address' => 'Jl. Merdeka No. 45, Jakarta Selatan',
                'latitude' => -6.22974650,
                'longitude' => 106.81644900,
                'phone' => '081299887766',
                'rating' => 4.85,
                'working_hours' => '07:00 - 21:00',
                'is_active' => 1,
                'service_count' => 3,
                'min_price' => 6000,
                'distance_km' => null,
                'owner_name' => 'Hendra Wijaya',
            ],
            [
                'id' => 2,
                'user_id' => 7,
                'name' => 'Fresh & Clean Laundry',
                'description' => 'Spesialis cuci kiloan wangi dan perlengkapan rumah tangga',
                'address' => 'Jl. Senopati No. 88, Jakarta Selatan',
                'latitude' => -6.23650000,
                'longitude' => 106.80800000,
                'phone' => '081388776655',
                'rating' => 4.70,
                'working_hours' => '08:00 - 20:00',
                'is_active' => 1,
                'service_count' => 2,
                'min_price' => 5000,
                'distance_km' => null,
                'owner_name' => 'Surya Pratama',
            ]
        ];
    }
}
