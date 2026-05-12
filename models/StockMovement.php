<?php
// =============================================================
// models/StockMovement.php — Registro de entradas y salidas
// =============================================================

class StockMovement
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ---------------------------------------------------------
    // Crea un movimiento y actualiza el stock de la variante
    // ---------------------------------------------------------
    public function create(array $data): int
    {
        $this->db->beginTransaction();

        try {
            // 1. Insertar el registro en el historial inmutable
            $stmtMov = $this->db->prepare(
                "INSERT INTO stock_movements (variant_id, type, quantity, notes, created_by)
                 VALUES (:variant_id, :type, :quantity, :notes, :created_by)"
            );
            $stmtMov->execute([
                ':variant_id' => $data['variant_id'],
                ':type'       => $data['type'],
                ':quantity'   => $data['quantity'],
                ':notes'      => $data['notes'],
                ':created_by' => $data['created_by'],
            ]);
            $movementId = (int) $this->db->lastInsertId();

            // 2. Actualizar el stock actual (desnormalizado) en la variante
            $stmtVar = $this->db->prepare("UPDATE variants SET stock = stock + :quantity WHERE id = :variant_id");
            $stmtVar->execute([':quantity' => $data['quantity'], ':variant_id' => $data['variant_id']]);

            $this->db->commit();
            return $movementId;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}