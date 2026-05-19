<?php
// =============================================================
// models/RecurringExpense.php — Acceso a datos de `recurring_expenses`
// =============================================================

class RecurringExpense
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findAll(int $bandId): array
    {
        $stmt = $this->db->prepare(
            "SELECT re.id, re.band_id, re.category, re.description, re.amount,
                    re.recurrence_type, re.next_due_date, re.is_active, re.created_by,
                    u.name as creator_name, re.created_at
               FROM recurring_expenses re
               LEFT JOIN users u ON re.created_by = u.id
              WHERE re.band_id = :band_id
              ORDER BY re.next_due_date ASC"
        );
        $stmt->execute([':band_id' => $bandId]);
        return $stmt->fetchAll() ?: [];
    }

    public function findById(int $id, int $bandId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM recurring_expenses WHERE id = :id AND band_id = :band_id LIMIT 1"
        );
        $stmt->execute([':id' => $id, ':band_id' => $bandId]);
        return $stmt->fetch() ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO recurring_expenses (band_id, category, description, amount, recurrence_type, next_due_date, is_active, created_by)
             VALUES (:band_id, :category, :description, :amount, :recurrence_type, :next_due_date, :is_active, :created_by)"
        );
        
        $stmt->execute([
            ':band_id'          => $data['band_id'],
            ':category'         => $data['category'],
            ':description'      => $data['description'],
            ':amount'           => $data['amount'],
            ':recurrence_type'  => $data['recurrence_type'],
            ':next_due_date'    => $data['next_due_date'],
            ':is_active'        => $data['is_active'] ?? 1,
            ':created_by'       => $data['created_by']
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data, int $bandId): void
    {
        $stmt = $this->db->prepare(
            "UPDATE recurring_expenses 
                SET category = :category,
                    description = :description,
                    amount = :amount,
                    recurrence_type = :recurrence_type,
                    next_due_date = :next_due_date,
                    is_active = :is_active
              WHERE id = :id AND band_id = :band_id"
        );
        
        $stmt->execute([
            ':category'         => $data['category'],
            ':description'      => $data['description'],
            ':amount'           => $data['amount'],
            ':recurrence_type'  => $data['recurrence_type'],
            ':next_due_date'    => $data['next_due_date'],
            ':is_active'        => $data['is_active'],
            ':id'               => $id,
            ':band_id'          => $bandId
        ]);
    }

    public function delete(int $id, int $bandId): void
    {
        $stmt = $this->db->prepare(
            "DELETE FROM recurring_expenses WHERE id = :id AND band_id = :band_id"
        );
        $stmt->execute([':id' => $id, ':band_id' => $bandId]);
    }

    /**
     * Busca gastos recurrentes activos que deban procesarse (next_due_date <= hoy)
     */
    public function findActiveDue(string $currentDate): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM recurring_expenses 
              WHERE is_active = 1 
                AND next_due_date <= :current_date"
        );
        $stmt->execute([':current_date' => $currentDate]);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Procesa y genera gastos a partir de las plantillas recurrentes vencidas.
     */
    public function processRecurringExpenses(int $bandId, string $currentDate): void
    {
        $dueExpenses = $this->findActiveDue($currentDate);
        if (empty($dueExpenses)) {
            return;
        }

        $expenseModel = new Expense();

        foreach ($dueExpenses as $re) {
            // Solo procesamos los que pertenecen a esta banda
            if ((int)$re['band_id'] !== $bandId) {
                continue;
            }

            $nextDue = new DateTime($re['next_due_date']);
            $today = new DateTime($currentDate);

            $this->db->beginTransaction();
            try {
                while ($nextDue <= $today) {
                    // Crear gasto real
                    $expenseModel->create([
                        'band_id'      => $re['band_id'],
                        'event_id'     => null,
                        'category'     => $re['category'],
                        'amount'       => $re['amount'],
                        'description'  => $re['description'] . ' (Gasto Recurrente)',
                        'expense_date' => $nextDue->format('Y-m-d'),
                        'is_paid'      => 0,
                        'receipt_url'  => null,
                        'created_by'   => $re['created_by']
                    ]);

                    // Incrementar fecha según recurrencia
                    if ($re['recurrence_type'] === 'weekly') {
                        $nextDue->modify('+7 days');
                    } elseif ($re['recurrence_type'] === 'monthly') {
                        $nextDue->modify('+1 month');
                    } elseif ($re['recurrence_type'] === 'yearly') {
                        $nextDue->modify('+1 year');
                    } else {
                        break; 
                    }
                }

                // Actualizar recurring_expenses con la nueva next_due_date
                $stmt = $this->db->prepare(
                    "UPDATE recurring_expenses 
                        SET next_due_date = :next_due_date 
                      WHERE id = :id"
                );
                $stmt->execute([
                    ':next_due_date' => $nextDue->format('Y-m-d'),
                    ':id'            => $re['id']
                ]);

                $this->db->commit();
            } catch (Exception $e) {
                $this->db->rollBack();
                error_log("Error al procesar gasto recurrente ID {$re['id']}: " . $e->getMessage());
            }
        }
    }
}
