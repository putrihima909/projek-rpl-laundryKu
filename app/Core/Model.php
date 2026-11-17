<?php

namespace App\Core;

use PDO;

abstract class Model
{
    protected string $table;
    protected string $primaryKey = 'id';
    protected ?PDO $db = null;

    public function __construct()
    {
        try {
            $this->db = Database::getInstance();
        } catch (\Exception $e) {
            // Gracefully handle if DB is not yet initialized or connected
            $this->db = null;
        }
    }

    public function all(): array
    {
        if (!$this->db) return [];
        $stmt = $this->db->query("SELECT * FROM {$this->table} ORDER BY {$this->primaryKey} DESC");
        return $stmt->fetchAll();
    }

    public function find(mixed $id): ?array
    {
        if (!$this->db) return null;
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function where(string $column, mixed $value): array
    {
        if (!$this->db) return [];
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE {$column} = :val");
        $stmt->execute(['val' => $value]);
        return $stmt->fetchAll();
    }

    public function create(array $data): ?int
    {
        if (!$this->db) return null;
        $fields = array_keys($data);
        $columns = implode(', ', $fields);
        $placeholders = ':' . implode(', :', $fields);

        $sql = "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($data);

        return (int) $this->db->lastInsertId();
    }

    public function update(mixed $id, array $data): bool
    {
        if (!$this->db) return false;
        $sets = [];
        foreach (array_keys($data) as $field) {
            $sets[] = "{$field} = :{$field}";
        }
        $setString = implode(', ', $sets);
        $data['id'] = $id;

        $sql = "UPDATE {$this->table} SET {$setString} WHERE {$this->primaryKey} = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($data);
    }

    public function delete(mixed $id): bool
    {
        if (!$this->db) return false;
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE {$this->primaryKey} = :id");
        return $stmt->execute(['id' => $id]);
    }

    public function getDb(): ?PDO
    {
        return $this->db;
    }
}
