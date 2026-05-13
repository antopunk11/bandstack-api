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
    public function findAll(int $bandId): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, name, venue, city, country, event_date, type, status, cache_amount, created_at
               FROM events
              WHERE band_id = :band_id
              ORDER BY event_date DESC"
        );
        $stmt->execute([':band_id' => $bandId]);
        return $stmt->fetchAll() ?: [];
    }

    // ---------------------------------------------------------
    // Busca un evento por ID
    // ---------------------------------------------------------
    public function findById(int $id, int $bandId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT id, name, venue, city, country, event_date, type, status, cache_amount, created_at
               FROM events
              WHERE id = :id AND band_id = :band_id
              LIMIT 1"
        );
        $stmt->execute([':id' => $id, ':band_id' => $bandId]);

        $event = $stmt->fetch();
        return $event ?: null;
    }

    // ---------------------------------------------------------
    // Crea un nuevo evento
    // ---------------------------------------------------------
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO events (band_id, name, venue, event_date, type, cache_amount, created_by)
             VALUES (:band_id, :name, :venue, :event_date, :type, :cache_amount, :created_by)"
        );
        
        $stmt->execute([
            ':band_id'      => $data['band_id'],
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

        // 3. Total de gastos asociados al evento
        // Asumimos que la tabla se llama 'expenses' y tiene 'amount' y 'event_id'
        $stmtExpenses = $this->db->prepare(
            "SELECT COALESCE(SUM(amount), 0) as total_expenses
               FROM expenses
              WHERE event_id = :event_id"
        );
        $stmtExpenses->execute([':event_id' => $eventId]);
        $totalExpenses = $stmtExpenses->fetchColumn() ?: 0.0;

        return [
            'totals'   => $totals,
            'products' => $products,
            'expenses' => (float) $totalExpenses
        ];
    }

    // ---------------------------------------------------------
    // Obtiene un resumen financiero global (Histórico de Gira)
    // ---------------------------------------------------------
    public function getGlobalSummary(int $bandId): array
    {
        // 1. Totales históricos por método de pago
        $stmtTotals = $this->db->prepare(
            "SELECT payment_method, COUNT(id) as tickets, COALESCE(SUM(total_amount), 0) as total
               FROM sales
              WHERE band_id = :band_id
              GROUP BY payment_method"
        );
        $stmtTotals->execute([':band_id' => $bandId]);
        $totals = $stmtTotals->fetchAll();

        // 2. Top 10 Productos más vendidos históricamente
        $stmtProducts = $this->db->prepare(
            "SELECT p.name, v.attribute, SUM(si.quantity) as qty, COALESCE(SUM(si.quantity * si.unit_price), 0) as revenue
               FROM sale_items si
               JOIN sales s ON s.id = si.sale_id
               JOIN variants v ON v.id = si.variant_id
               JOIN products p ON p.id = v.product_id
              WHERE s.band_id = :band_id
              GROUP BY v.id
              ORDER BY qty DESC
              LIMIT 10"
        );
        $stmtProducts->execute([':band_id' => $bandId]);
        $products = $stmtProducts->fetchAll();

        // 3. Total de gastos históricos
        $stmtExpenses = $this->db->prepare(
            "SELECT COALESCE(SUM(amount), 0) as total_expenses FROM expenses WHERE band_id = :band_id"
        );
        $stmtExpenses->execute([':band_id' => $bandId]);
        $totalExpenses = $stmtExpenses->fetchColumn() ?: 0.0;

        // 4. Total de caché histórico cobrado y pendiente por la banda
        $stmtCache = $this->db->prepare(
            "SELECT 
                COALESCE(SUM(CASE WHEN event_date <= CURDATE() THEN cache_amount ELSE 0 END), 0) as collected_cache,
                COALESCE(SUM(CASE WHEN event_date > CURDATE() THEN cache_amount ELSE 0 END), 0) as pending_cache
             FROM events WHERE band_id = :band_id"
        );
        $stmtCache->execute([':band_id' => $bandId]);
        $cacheData = $stmtCache->fetch();
        $totalCollectedCache = $cacheData['collected_cache'] ?? 0.0;
        $totalPendingCache = $cacheData['pending_cache'] ?? 0.0;

        return [
            'totals'   => $totals,
            'products' => $products,
            'expenses' => (float) $totalExpenses,
            'cache'    => (float) $totalCollectedCache, // Se mantiene por retrocompatibilidad
            'collected_cache' => (float) $totalCollectedCache,
            'pending_cache'   => (float) $totalPendingCache
        ];
    }
}