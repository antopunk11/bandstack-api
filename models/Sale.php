<?php
// =============================================================
// models/Sale.php — Registro de ventas y control atómico de stock
// =============================================================

class Sale
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function create(array $data, array $items): int
    {
        $this->db->beginTransaction();

        try {
            // 1. Cabecera del ticket
            $stmt = $this->db->prepare("INSERT INTO sales (band_id, event_id, total_amount, payment_method, sold_by) VALUES (:band_id, :event_id, :total, :method, :user)");
            $stmt->execute([
                ':band_id'  => $data['band_id'],
                ':event_id' => $data['event_id'],
                ':total'    => $data['total_amount'],
                ':method'   => $data['payment_method'],
                ':user'     => $data['created_by']
            ]);
            $saleId = (int) $this->db->lastInsertId();

            // Prepared statements reutilizables para los detalles
            $stmtItem  = $this->db->prepare("INSERT INTO sale_items (sale_id, variant_id, quantity, unit_price) VALUES (?, ?, ?, ?)");
            $stmtStock = $this->db->prepare("INSERT INTO stock_movements (variant_id, type, quantity, notes, created_by) VALUES (?, 'sale', ?, 'Venta TPV', ?)");
            $stmtVar   = $this->db->prepare("UPDATE variants SET stock = stock - ? WHERE id = ?");

            // 2. Procesar el carrito
            foreach ($items as $item) {
                $stmtItem->execute([$saleId, $item['variant_id'], $item['quantity'], $item['price']]);
                $stmtStock->execute([$item['variant_id'], -$item['quantity'], $data['created_by']]);
                $stmtVar->execute([$item['quantity'], $item['variant_id']]);
            }

            $this->db->commit();
            return $saleId;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}