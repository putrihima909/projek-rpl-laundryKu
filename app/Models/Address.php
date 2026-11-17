<?php

namespace App\Models;

use App\Core\Model;

class Address extends Model
{
    protected string $table = 'addresses';

    public function getUserAddresses(int $userId): array
    {
        if (!$this->db) {
            return [
                [
                    'id' => 1,
                    'user_id' => $userId,
                    'title' => 'Rumah Utama',
                    'address_line' => 'Jl. Mawar Indah No. 12',
                    'province' => 'DKI Jakarta',
                    'city' => 'Jakarta Selatan',
                    'district' => 'Kebayoran Baru',
                    'village' => 'Gandaria Utara',
                    'rt' => '003',
                    'rw' => '005',
                    'detail_address' => 'Rumah pagar hitam sebelah warung',
                    'latitude' => '-6.24430000',
                    'longitude' => '106.79300000',
                    'is_primary' => 1
                ]
            ];
        }

        $stmt = $this->db->prepare("SELECT * FROM addresses WHERE user_id = :uid ORDER BY is_primary DESC, id DESC");
        $stmt->execute(['uid' => $userId]);
        return $stmt->fetchAll();
    }

    public function getPrimaryAddress(int $userId): ?array
    {
        if (!$this->db) {
            $addresses = $this->getUserAddresses($userId);
            return $addresses[0] ?? null;
        }

        $stmt = $this->db->prepare("SELECT * FROM addresses WHERE user_id = :uid AND is_primary = 1 LIMIT 1");
        $stmt->execute(['uid' => $userId]);
        $address = $stmt->fetch();
        return $address ?: null;
    }

    public function findAddress(int $id, int $userId): ?array
    {
        if (!$this->db) {
            $addresses = $this->getUserAddresses($userId);
            foreach ($addresses as $a) {
                if ($a['id'] === $id) return $a;
            }
            return null;
        }

        $stmt = $this->db->prepare("SELECT * FROM addresses WHERE id = :id AND user_id = :uid LIMIT 1");
        $stmt->execute(['id' => $id, 'uid' => $userId]);
        $address = $stmt->fetch();
        return $address ?: null;
    }

    public function createAddress(array $data): ?int
    {
        if (!$this->db) return 1;

        if (!empty($data['is_primary'])) {
            $this->resetPrimaryFlag($data['user_id']);
        }

        return $this->create($data);
    }

    public function updateAddress(int $id, int $userId, array $data): bool
    {
        if (!$this->db) return true;

        if (!empty($data['is_primary'])) {
            $this->resetPrimaryFlag($userId);
        }

        $stmt = $this->db->prepare("UPDATE addresses SET title = :title, address_line = :address_line, province = :province, city = :city, district = :district, village = :village, rt = :rt, rw = :rw, detail_address = :detail_address, latitude = :latitude, longitude = :longitude, is_primary = :is_primary WHERE id = :id AND user_id = :user_id");
        $data['id'] = $id;
        $data['user_id'] = $userId;
        return $stmt->execute($data);
    }

    public function deleteAddress(int $id, int $userId): bool
    {
        if (!$this->db) return true;
        $stmt = $this->db->prepare("DELETE FROM addresses WHERE id = :id AND user_id = :uid");
        return $stmt->execute(['id' => $id, 'uid' => $userId]);
    }

    public function setPrimary(int $id, int $userId): bool
    {
        if (!$this->db) return true;
        $this->resetPrimaryFlag($userId);
        $stmt = $this->db->prepare("UPDATE addresses SET is_primary = 1 WHERE id = :id AND user_id = :uid");
        return $stmt->execute(['id' => $id, 'uid' => $userId]);
    }

    private function resetPrimaryFlag(int $userId): void
    {
        if ($this->db) {
            $stmt = $this->db->prepare("UPDATE addresses SET is_primary = 0 WHERE user_id = :uid");
            $stmt->execute(['uid' => $userId]);
        }
    }
}
