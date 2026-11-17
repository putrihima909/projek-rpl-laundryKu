<?php

namespace App\Models;

use App\Core\Model;

class Service extends Model
{
    protected string $table = 'services';

    /**
     * Get ALL services (active + inactive) belonging to a specific store.
     * Used by admin/owner to manage their own store's services.
     */
    public function getByStore(int $storeId): array
    {
        if (!$this->db) {
            return [
                ['id' => 1, 'store_id' => 1, 'name' => 'Cuci Komplit Reguler',    'description' => 'Cuci, keringkan, dan setrika rapi',          'price' => '6000.00',  'unit' => 'kg',  'duration_hours' => 24, 'is_active' => 1],
                ['id' => 2, 'store_id' => 1, 'name' => 'Cuci Kilat / Express 3 Jam', 'description' => 'Selesai cepat dalam 3 jam',                'price' => '12000.00', 'unit' => 'kg',  'duration_hours' => 3,  'is_active' => 1],
                ['id' => 3, 'store_id' => 1, 'name' => 'Dry Cleaning Jas / Tuxedo', 'description' => 'Perawatan khusus pakaian berbahan halus',   'price' => '35000.00', 'unit' => 'pcs', 'duration_hours' => 48, 'is_active' => 1],
            ];
        }

        $stmt = $this->db->prepare(
            "SELECT * FROM services WHERE store_id = :sid ORDER BY is_active DESC, price ASC"
        );
        $stmt->execute(['sid' => $storeId]);
        return $stmt->fetchAll();
    }

    /**
     * Find a service by ID, but ONLY if it belongs to the given store.
     * This is the core ownership check — never bypass this in the controller.
     */
    public function findWithOwnerCheck(int $serviceId, int $storeId): ?array
    {
        if (!$this->db) {
            // Demo fallback
            if ($serviceId <= 3 && $storeId === 1) {
                $services = $this->getByStore(1);
                foreach ($services as $s) {
                    if ((int)$s['id'] === $serviceId) return $s;
                }
            }
            return null;
        }

        $stmt = $this->db->prepare(
            "SELECT * FROM services WHERE id = :id AND store_id = :sid LIMIT 1"
        );
        $stmt->execute(['id' => $serviceId, 'sid' => $storeId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Create a new service for a specific store.
     */
    public function createService(int $storeId, array $data): ?int
    {
        if (!$this->db) return 99; // Demo mode

        $payload = [
            'store_id'       => $storeId,
            'name'           => $data['name'],
            'description'    => $data['description'] ?? null,
            'price'          => $data['price'],
            'unit'           => $data['unit'],
            'duration_hours' => (int)($data['duration_hours'] ?? 24),
            'is_active'      => isset($data['is_active']) ? 1 : 0,
        ];

        return $this->create($payload);
    }

    /**
     * Update a service — ownership must be checked by caller BEFORE calling this.
     */
    public function updateService(int $serviceId, array $data): bool
    {
        if (!$this->db) return true; // Demo mode

        $payload = [
            'name'           => $data['name'],
            'description'    => $data['description'] ?? null,
            'price'          => $data['price'],
            'unit'           => $data['unit'],
            'duration_hours' => (int)($data['duration_hours'] ?? 24),
            'is_active'      => isset($data['is_active']) ? 1 : 0,
        ];

        return $this->update($serviceId, $payload);
    }

    /**
     * Delete a service — ownership must be checked by caller BEFORE calling this.
     */
    public function deleteService(int $serviceId): bool
    {
        if (!$this->db) return true; // Demo mode
        return $this->delete($serviceId);
    }

    /**
     * Available service units.
     */
    public static function getUnits(): array
    {
        return ['kg', 'pcs', 'meter', 'pasang', 'set'];
    }
}
