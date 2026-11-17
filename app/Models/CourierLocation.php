<?php

namespace App\Models;

use App\Core\Model;

class CourierLocation extends Model
{
    protected string $table = 'courier_locations';

    public function getCourierLocation(int $courierId): array
    {
        if (!$this->db) {
            return [
                'courier_id' => $courierId,
                'duty_status' => 'available',
                'latitude' => '-6.24430000',
                'longitude' => '106.79300000',
                'updated_at' => date('Y-m-d H:i:s')
            ];
        }

        $stmt = $this->db->prepare("SELECT * FROM courier_locations WHERE courier_id = :cid LIMIT 1");
        $stmt->execute(['cid' => $courierId]);
        $loc = $stmt->fetch();

        if (!$loc) {
            $this->create([
                'courier_id' => $courierId,
                'duty_status' => 'available',
                'latitude' => -6.24430000,
                'longitude' => 106.79300000
            ]);
            return $this->getCourierLocation($courierId);
        }

        return $loc;
    }

    public function updateDutyStatus(int $courierId, string $dutyStatus): bool
    {
        if (!in_array($dutyStatus, ['available', 'busy', 'offline'], true)) {
            return false;
        }

        if (!$this->db) return true;

        $existing = $this->getCourierLocation($courierId);
        $stmt = $this->db->prepare("UPDATE courier_locations SET duty_status = :ds, updated_at = NOW() WHERE courier_id = :cid");
        return $stmt->execute(['ds' => $dutyStatus, 'cid' => $courierId]);
    }

    public function updateCoordinates(int $courierId, float $latitude, float $longitude, ?int $orderId = null): bool
    {
        if (!$this->db) return true;

        $this->getCourierLocation($courierId);
        if ($orderId !== null && $orderId > 0) {
            $stmt = $this->db->prepare("UPDATE courier_locations SET latitude = :lat, longitude = :lng, order_id = :oid, updated_at = NOW() WHERE courier_id = :cid");
            return $stmt->execute(['lat' => $latitude, 'lng' => $longitude, 'oid' => $orderId, 'cid' => $courierId]);
        }

        $stmt = $this->db->prepare("UPDATE courier_locations SET latitude = :lat, longitude = :lng, updated_at = NOW() WHERE courier_id = :cid");
        return $stmt->execute(['lat' => $latitude, 'lng' => $longitude, 'cid' => $courierId]);
    }

    public function getLatestLocationForOrder(int $orderId, ?int $courierId = null): ?array
    {
        if (!$this->db) {
            return [
                'order_id' => $orderId,
                'courier_id' => $courierId,
                'latitude' => '-6.24430000',
                'longitude' => '106.79300000',
                'duty_status' => 'busy',
                'updated_at' => date('Y-m-d H:i:s')
            ];
        }

        // 1. Cari record terbaru berdasarkan order_id
        if ($orderId > 0) {
            $stmt = $this->db->prepare("SELECT * FROM courier_locations WHERE order_id = :oid ORDER BY updated_at DESC, id DESC LIMIT 1");
            $stmt->execute(['oid' => $orderId]);
            $loc = $stmt->fetch();
            if ($loc) {
                return $loc;
            }
        }

        // 2. Fallback: ambil record kurir yang ditugaskan untuk order ini
        if ($courierId !== null && $courierId > 0) {
            $stmt = $this->db->prepare("SELECT * FROM courier_locations WHERE courier_id = :cid ORDER BY updated_at DESC, id DESC LIMIT 1");
            $stmt->execute(['cid' => $courierId]);
            $loc = $stmt->fetch();
            if ($loc) {
                return $loc;
            }
        }

        return null;
    }

    public function getAvailableCouriers(): array
    {
        if (!$this->db) {
            return [
                ['id' => 5, 'name' => 'Agus Express', 'phone' => '081234567894', 'duty_status' => 'available', 'latitude' => '-6.24430000', 'longitude' => '106.79300000']
            ];
        }

        $sql = "SELECT u.id, u.name, u.phone, cl.duty_status, cl.latitude, cl.longitude 
                FROM users u 
                LEFT JOIN courier_locations cl ON u.id = cl.courier_id 
                WHERE u.role_id = 5 AND u.is_active = 1 
                ORDER BY u.name ASC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }
}
