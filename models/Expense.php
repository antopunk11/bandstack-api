<?php
// =============================================================
// models/Expense.php — Acceso a datos de la tabla `expenses`
// =============================================================

class Expense
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ---------------------------------------------------------
    // Obtiene todos los gastos con el nombre del evento y usuario
    // ---------------------------------------------------------
    public function findAll(int $bandId): array
    {
        $stmt = $this->db->prepare(
            "SELECT e.id, e.event_id, ev.name as event_name, e.category, e.amount,
                    e.description, e.expense_date, e.is_paid, e.receipt_url, u.name as user_name, e.created_by
               FROM expenses e
               LEFT JOIN events ev ON e.event_id = ev.id
               LEFT JOIN users u ON e.created_by = u.id
              WHERE e.band_id = :band_id
              ORDER BY e.expense_date DESC"
        );
        $stmt->execute([':band_id' => $bandId]);
        return $stmt->fetchAll() ?: [];
    }

    // ---------------------------------------------------------
    // Busca un gasto por ID
    // ---------------------------------------------------------
    public function findById(int $id, int $bandId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM expenses WHERE id = :id AND band_id = :band_id LIMIT 1"
        );
        $stmt->execute([':id' => $id, ':band_id' => $bandId]);
        return $stmt->fetch() ?: null;
    }

    // ---------------------------------------------------------
    // Inserta un nuevo gasto
    // ---------------------------------------------------------
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO expenses (band_id, event_id, category, amount, description, expense_date, is_paid, receipt_url, created_by)
             VALUES (:band_id, :event_id, :category, :amount, :description, :expense_date, :is_paid, :receipt_url, :created_by)"
        );
        
        $stmt->execute([
            ':band_id'      => $data['band_id'],
            ':event_id'     => $data['event_id'],
            ':category'     => $data['category'],
            ':amount'       => $data['amount'],
            ':description'  => $data['description'],
            ':expense_date' => $data['expense_date'],
            ':is_paid'      => $data['is_paid'] ?? 0,
            ':receipt_url'  => $data['receipt_url'] ?? null,
            ':created_by'   => $data['created_by']
        ]);

        return (int) $this->db->lastInsertId();
    }

    // ---------------------------------------------------------
    // Actualiza un gasto por completo
    // ---------------------------------------------------------
    public function update(int $id, array $data, int $bandId): void
    {
        $stmt = $this->db->prepare(
            "UPDATE expenses 
                SET event_id = :event_id,
                    category = :category,
                    amount = :amount,
                    description = :description,
                    expense_date = :expense_date,
                    is_paid = :is_paid,
                    receipt_url = :receipt_url
              WHERE id = :id AND band_id = :band_id"
        );
        
        $stmt->execute([
            ':event_id'     => $data['event_id'],
            ':category'     => $data['category'],
            ':amount'       => $data['amount'],
            ':description'  => $data['description'],
            ':expense_date' => $data['expense_date'],
            ':is_paid'      => $data['is_paid'] ?? 0,
            ':receipt_url'  => $data['receipt_url'],
            ':id'      => $id,
            ':band_id' => $bandId
        ]);
    }

    // ---------------------------------------------------------
    // Elimina un gasto
    // ---------------------------------------------------------
    public function delete(int $id, int $bandId): void
    {
        $stmt = $this->db->prepare(
            "DELETE FROM expenses WHERE id = :id AND band_id = :band_id"
        );
        $stmt->execute([':id' => $id, ':band_id' => $bandId]);
    }
}