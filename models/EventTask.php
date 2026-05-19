<?php
// =============================================================
// models/EventTask.php — Modelo de tareas y checklist de evento
// =============================================================

class EventTask
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ---------------------------------------------------------
    // Obtiene todas las tareas vinculadas a un evento
    // ---------------------------------------------------------
    public function findAllByEvent(int $eventId): array
    {
        $stmt = $this->db->prepare(
            "SELECT et.*, u.name as assigned_to_name, c.name as created_by_name
               FROM event_tasks et
          LEFT JOIN users u ON u.id = et.assigned_to
          LEFT JOIN users c ON c.id = et.created_by
              WHERE et.event_id = :event_id
              ORDER BY et.is_completed ASC, et.due_date ASC, et.id ASC"
        );
        $stmt->execute([':event_id' => $eventId]);
        return $stmt->fetchAll() ?: [];
    }

    // ---------------------------------------------------------
    // Obtiene una tarea concreta por ID
    // ---------------------------------------------------------
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT et.*, e.band_id
               FROM event_tasks et
               JOIN events e ON e.id = et.event_id
              WHERE et.id = :id"
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    // ---------------------------------------------------------
    // Crea una nueva tarea
    // ---------------------------------------------------------
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO event_tasks (event_id, title, description, due_date, assigned_to, created_by)
             VALUES (:event_id, :title, :description, :due_date, :assigned_to, :created_by)"
        );
        $stmt->execute([
            ':event_id'    => $data['event_id'],
            ':title'       => $data['title'],
            ':description' => $data['description'] ?? null,
            ':due_date'    => $data['due_date'] ?? null,
            ':assigned_to' => $data['assigned_to'] ?? null,
            ':created_by'  => $data['created_by']
        ]);
        return (int) $this->db->lastInsertId();
    }

    // ---------------------------------------------------------
    // Actualiza una tarea existente
    // ---------------------------------------------------------
    public function update(int $id, array $data): void
    {
        $stmt = $this->db->prepare(
            "UPDATE event_tasks
                SET title = :title,
                    description = :description,
                    due_date = :due_date,
                    assigned_to = :assigned_to,
                    is_completed = :is_completed
              WHERE id = :id"
        );
        $stmt->execute([
            ':id'           => $id,
            ':title'        => $data['title'],
            ':description'  => $data['description'] ?? null,
            ':due_date'     => $data['due_date'] ?? null,
            ':assigned_to'  => $data['assigned_to'] ?? null,
            ':is_completed' => $data['is_completed'] ? 1 : 0
        ]);
    }

    // ---------------------------------------------------------
    // Elimina una tarea
    // ---------------------------------------------------------
    public function delete(int $id): void
    {
        $stmt = $this->db->prepare("DELETE FROM event_tasks WHERE id = :id");
        $stmt->execute([':id' => $id]);
    }
}
