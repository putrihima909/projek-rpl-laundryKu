<?php

namespace App\Models;

use App\Core\Model;
use App\Helpers\Auth;

class User extends Model
{
    protected string $table = 'users';

    public function allWithRoles(): array
    {
        if (!$this->db) return [];
        $sql = "SELECT u.*, r.name as role_name, r.display_name 
                FROM users u 
                LEFT JOIN roles r ON u.role_id = r.id 
                ORDER BY u.id DESC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    public function findByEmail(string $email): ?array
    {
        if (!$this->db) return null;
        $sql = "SELECT u.*, r.name as role_name, r.display_name 
                FROM users u 
                LEFT JOIN roles r ON u.role_id = r.id 
                WHERE u.email = :email LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function findByGoogleId(string $googleId): ?array
    {
        if (!$this->db) return null;
        $sql = "SELECT u.*, r.name as role_name, r.display_name 
                FROM users u 
                LEFT JOIN roles r ON u.role_id = r.id 
                WHERE u.google_id = :gid LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['gid' => $googleId]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function findWithRole(int $id): ?array
    {
        if (!$this->db) return null;
        $sql = "SELECT u.*, r.name as role_name, r.display_name 
                FROM users u 
                LEFT JOIN roles r ON u.role_id = r.id 
                WHERE u.id = :id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function findOrCreateGoogleUser(array $gUser): array
    {
        if (!$this->db) {
            return [
                'id' => 99,
                'role_id' => 6,
                'google_id' => $gUser['id'] ?? 'google_demo_123',
                'name' => $gUser['name'] ?? 'Google Customer',
                'email' => $gUser['email'] ?? 'google.customer@laundry.test',
                'role_name' => Auth::ROLE_CUSTOMER,
                'display_name' => 'Customer',
                'avatar' => $gUser['picture'] ?? '',
                'is_active' => 1
            ];
        }

        // 1. Try finding by Google ID
        $user = $this->findByGoogleId($gUser['id']);
        if ($user) {
            return $user;
        }

        // 2. Try finding by Email & link Google ID
        $userByEmail = $this->findByEmail($gUser['email']);
        if ($userByEmail) {
            $this->update($userByEmail['id'], [
                'google_id' => $gUser['id'],
                'avatar' => $gUser['picture'] ?? $userByEmail['avatar']
            ]);
            return $this->findWithRole($userByEmail['id']);
        }

        // 3. Create new user automatically as CUSTOMER role (id = 6 for customer)
        $newUserId = $this->create([
            'role_id' => 6, // Customer Role Fixed
            'google_id' => $gUser['id'],
            'name' => $gUser['name'],
            'email' => $gUser['email'],
            'password' => password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT),
            'avatar' => $gUser['picture'] ?? '',
            'is_active' => 1
        ]);

        return $this->findWithRole($newUserId);
    }

    public function createUser(array $data): ?int
    {
        if (isset($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
        }
        $userId = $this->create($data);

        if ($userId && !empty($data['phone'])) {
            try {
                $waService = new \App\Services\WhatsApp\WhatsAppNotificationService();
                $waService->sendCustomerNotification($userId, 'customer_registered', [
                    'name' => $data['name'] ?? 'Pelanggan',
                    'email' => $data['email'] ?? '',
                    'customer_phone' => $data['phone'],
                ]);
            } catch (\Throwable $e) {
                error_log("[User::createUser] WhatsApp welcome dispatch error: " . $e->getMessage());
            }
        }

        return $userId;
    }

    public function updateUser(int $id, array $data): bool
    {
        if (isset($data['password']) && !empty($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
        } else {
            unset($data['password']);
        }
        return $this->update($id, $data);
    }

    public function toggleStatus(int $id): bool
    {
        if (!$this->db) return false;
        $user = $this->find($id);
        if (!$user) return false;

        $newStatus = $user['is_active'] ? 0 : 1;
        return $this->update($id, ['is_active' => $newStatus]);
    }
}
