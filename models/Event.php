<?php
// =============================================================
// models/Event.php — Acceso a datos de la tabla `events`
// =============================================================

class Event
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ---------------------------------------------------------
    // Obtiene todos los eventos ordenados por fecha descendente
    // ---------------------------------------------------------
    public function findAll(): array
    {
        $stmt = $this->db->query(
            "SELECT id, name, venue, city, country, event_date, type, status, cache_amount, created_at
               FROM events
              ORDER BY event_date DESC"
        );
        return $stmt->fetchAll() ?: [];
    }

    // ---------------------------------------------------------
    // Busca un evento por ID
    // ---------------------------------------------------------
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT id, name, venue, city, country, event_date, type, status, cache_amount, created_at
               FROM events
              WHERE id = :id
              LIMIT 1"
        );
        $stmt->execute([':id' => $id]);

        $event = $stmt->fetch();
        return $event ?: null;
    }

    // ---------------------------------------------------------
    // Crea un nuevo evento
    // ---------------------------------------------------------
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO events (name, venue, event_date, type, cache_amount, created_by)
             VALUES (:name, :venue, :event_date, :type, :cache_amount, :created_by)"
        );
        
        $stmt->execute([
            ':name'         => $data['name'],
            ':venue'        => $data['venue'],
            ':event_date'   => $data['event_date'],
            ':type'         => $data['type'],
            ':cache_amount' => $data['cache_amount'],
            ':created_by'   => $data['created_by'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    // ---------------------------------------------------------
    // Actualiza el estado de un evento
    // ---------------------------------------------------------
    public function updateStatus(int $id, string $status): void
    {
        $stmt = $this->db->prepare(
            "UPDATE events SET status = :status WHERE id = :id"
        );
        $stmt->execute([':status' => $status, ':id' => $id]);
    }

    // ---------------------------------------------------------
    // Actualiza un evento existente
    // ---------------------------------------------------------
    public function update(int $id, array $data): void
    {
        $stmt = $this->db->prepare(
            "UPDATE events 
                SET name = :name, venue = :venue, event_date = :event_date, type = :type, cache_amount = :cache_amount 
              WHERE id = :id"
        );
        $stmt->execute([
            ':id'           => $id,
            ':name'         => $data['name'],
            ':venue'        => $data['venue'],
            ':event_date'   => $data['event_date'],
            ':type'         => $data['type'],
            ':cache_amount' => $data['cache_amount']
        ]);
    }

    // ---------------------------------------------------------
    // Elimina un evento
    // ---------------------------------------------------------
    public function delete(int $id): void
    {
        $stmt = $this->db->prepare("DELETE FROM events WHERE id = :id");
        $stmt->execute([':id' => $id]);
    }

    // ---------------------------------------------------------
    // Obtiene un resumen financiero de un evento
    // ---------------------------------------------------------
    public function getSummary(int $eventId): array
    {
        // 1. Totales por método de pago
        $stmtTotals = $this->db->prepare(
            "SELECT payment_method, COUNT(id) as tickets, COALESCE(SUM(total_amount), 0) as total
               FROM sales
              WHERE event_id = :event_id
              GROUP BY payment_method"
        );
        $stmtTotals->execute([':event_id' => $eventId]);
        $totals = $stmtTotals->fetchAll();

        // 2. Productos más vendidos
        $stmtProducts = $this->db->prepare(
            "SELECT p.name, v.attribute, SUM(si.quantity) as qty, COALESCE(SUM(si.quantity * si.unit_price), 0) as revenue
               FROM sale_items si
               JOIN sales s ON s.id = si.sale_id
               JOIN variants v ON v.id = si.variant_id
               JOIN products p ON p.id = v.product_id
              WHERE s.event_id = :event_id
              GROUP BY v.id
              ORDER BY qty DESC"
        );
        $stmtProducts->execute([':event_id' => $eventId]);
        $products = $stmtProducts->fetchAll();

        return [
            'totals'   => $totals,
            'products' => $products
        ];
    }
}