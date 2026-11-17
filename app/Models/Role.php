<?php

namespace App\Models;

use App\Core\Model;

class Role extends Model
{
    protected string $table = 'roles';

    public function getAllRoles(): array
    {
        if (!$this->db) {
            return [
                ['id' => 1, 'name' => 'owner', 'display_name' => 'Owner Platform'],
                ['id' => 2, 'name' => 'admin_owner', 'display_name' => 'Admin Owner'],
                ['id' => 3, 'name' => 'owner_toko', 'display_name' => 'Owner Toko'],
                ['id' => 4, 'name' => 'admin_toko', 'display_name' => 'Admin Toko'],
                ['id' => 5, 'name' => 'kurir', 'display_name' => 'Kurir / Jasa Antar'],
                ['id' => 6, 'name' => 'customer', 'display_name' => 'Customer'],
            ];
        }

        $stmt = $this->db->query("SELECT * FROM roles ORDER BY id ASC");
        return $stmt->fetchAll();
    }
}
