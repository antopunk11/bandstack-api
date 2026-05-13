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
                    e.description, e.expense_date, u.name as user_name
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
    // Inserta un nuevo gasto
    // ---------------------------------------------------------
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO expenses (band_id, event_id, category, amount, description, expense_date, created_by)
             VALUES (:band_id, :event_id, :category, :amount, :description, :expense_date, :created_by)"
        );
        
        $stmt->execute([
            ':band_id'      => $data['band_id'],
            ':event_id'     => $data['event_id'],
            ':category'     => $data['category'],
            ':amount'       => $data['amount'],
            ':description'  => $data['description'],
            ':expense_date' => $data['expense_date'],
            ':created_by'   => $data['created_by']
        ]);

        return (int) $this->db->lastInsertId();
    }
}