<?php
// =============================================================
// models/Income.php — Acceso a datos de la tabla `incomes`
// =============================================================

class Income
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ---------------------------------------------------------
    // Obtiene todos los ingresos con el nombre del usuario creador
    // ---------------------------------------------------------
    public function findAll(int $bandId): array
    {
        $stmt = $this->db->prepare(
            "SELECT i.id, i.category, i.amount, i.description, i.income_date, u.name as user_name, i.created_by, i.created_at
               FROM incomes i
               LEFT JOIN users u ON i.created_by = u.id
              WHERE i.band_id = :band_id
              ORDER BY i.income_date DESC, i.id DESC"
        );
        $stmt->execute([':band_id' => $bandId]);
        return $stmt->fetchAll() ?: [];
    }

    // ---------------------------------------------------------
    // Busca un ingreso por ID
    // ---------------------------------------------------------
    public function findById(int $id, int $bandId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM incomes WHERE id = :id AND band_id = :band_id LIMIT 1"
        );
        $stmt->execute([':id' => $id, ':band_id' => $bandId]);
        return $stmt->fetch() ?: null;
    }

    // ---------------------------------------------------------
    // Inserta un nuevo ingreso
    // ---------------------------------------------------------
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO incomes (band_id, category, amount, description, income_date, created_by)
             VALUES (:band_id, :category, :amount, :description, :income_date, :created_by)"
        );
        
        $stmt->execute([
            ':band_id'      => $data['band_id'],
            ':category'     => $data['category'],
            ':amount'       => $data['amount'],
            ':description'  => $data['description'],
            ':income_date'  => $data['income_date'],
            ':created_by'   => $data['created_by']
        ]);

        return (int) $this->db->lastInsertId();
    }

    // ---------------------------------------------------------
    // Actualiza un ingreso por completo
    // ---------------------------------------------------------
    public function update(int $id, array $data, int $bandId): void
    {
        $stmt = $this->db->prepare(
            "UPDATE incomes 
                SET category = :category,
                    amount = :amount,
                    description = :description,
                    income_date = :income_date
              WHERE id = :id AND band_id = :band_id"
        );
        
        $stmt->execute([
            ':category'     => $data['category'],
            ':amount'       => $data['amount'],
            ':description'  => $data['description'],
            ':income_date'  => $data['income_date'],
            ':id'           => $id,
            ':band_id'      => $bandId
        ]);
    }

    // ---------------------------------------------------------
    // Elimina un ingreso
    // ---------------------------------------------------------
    public function delete(int $id, int $bandId): void
    {
        $stmt = $this->db->prepare(
            "DELETE FROM incomes WHERE id = :id AND band_id = :band_id"
        );
        $stmt->execute([':id' => $id, ':band_id' => $bandId]);
    }
}
