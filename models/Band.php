<?php
// =============================================================
// models/Band.php — Acceso a datos de la tabla `bands` (Tenants)
// =============================================================

class Band
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findAll(): array
    {
        $stmt = $this->db->query("SELECT id, name, logo_url, created_at FROM bands ORDER BY created_at ASC");
        return $stmt->fetchAll() ?: [];
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT id, name, logo_url, created_at FROM bands WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $band = $stmt->fetch();
        return $band ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("INSERT INTO bands (name, logo_url) VALUES (:name, :logo_url)");
        $stmt->execute([
            ':name'     => $data['name'],
            ':logo_url' => $data['logo_url'] ?? null
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        if (array_key_exists('logo_url', $data)) {
            $stmt = $this->db->prepare("UPDATE bands SET name = :name, logo_url = :logo_url WHERE id = :id");
            $stmt->execute([
                ':name'     => $data['name'],
                ':logo_url' => $data['logo_url'],
                ':id'       => $id
            ]);
        } else {
            $stmt = $this->db->prepare("UPDATE bands SET name = :name WHERE id = :id");
            $stmt->execute([':name' => $data['name'], ':id' => $id]);
        }
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare("DELETE FROM bands WHERE id = :id");
        $stmt->execute([':id' => $id]);
    }
}