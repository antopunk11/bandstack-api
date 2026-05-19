<?php
// =============================================================
// controllers/TaskController.php — Endpoints para gestionar tareas
// =============================================================

class TaskController
{
    private EventTask $taskModel;
    private Event $eventModel;

    public function __construct()
    {
        $this->taskModel = new EventTask();
        $this->eventModel = new Event();
    }

    // ---------------------------------------------------------
    // GET /api/v1/tasks?event_id=X
    // Lista todas las tareas de un evento (admin y member de la misma banda)
    // ---------------------------------------------------------
    public function index(): void
    {
        AuthMiddleware::handle();
        $user = AuthMiddleware::getCurrentUser();

        $eventId = $_GET['event_id'] ?? null;
        if (!$eventId || !is_numeric($eventId)) {
            Response::error('El parámetro event_id es obligatorio.', 400);
        }

        // Verificar que el evento pertenece a la banda del usuario
        $event = $this->eventModel->findById((int) $eventId, $user['band_id']);
        if (!$event) {
            Response::error('Evento no encontrado o no autorizado.', 404);
        }

        try {
            $tasks = $this->taskModel->findAllByEvent((int) $eventId);
            Response::success($tasks);
        } catch (Exception $e) {
            Response::error('Error al obtener las tareas: ' . $e->getMessage(), 500);
        }
    }

    // ---------------------------------------------------------
    // POST /api/v1/tasks
    // Crea una tarea (Acceso: admin y member)
    // ---------------------------------------------------------
    public function store(): void
    {
        AuthMiddleware::handle();
        $user = AuthMiddleware::getCurrentUser();
        $body = $this->getJsonBody();

        $errors = [];
        if (empty($body['event_id'])) {
            $errors['event_id'] = 'El event_id es obligatorio.';
        }
        if (empty($body['title'])) {
            $errors['title'] = 'El título es obligatorio.';
        }

        if (!empty($errors)) {
            Response::error('Datos de entrada inválidos.', 422, $errors);
        }

        // Verificar pertenencia del evento
        $event = $this->eventModel->findById((int) $body['event_id'], $user['band_id']);
        if (!$event) {
            Response::error('Evento no encontrado o no autorizado.', 404);
        }

        $data = [
            'event_id'    => (int) $body['event_id'],
            'title'       => trim($body['title']),
            'description' => !empty($body['description']) ? trim($body['description']) : null,
            'due_date'    => !empty($body['due_date']) ? $body['due_date'] : null,
            'assigned_to' => !empty($body['assigned_to']) ? (int) $body['assigned_to'] : null,
            'created_by'  => $user['id']
        ];

        try {
            $taskId = $this->taskModel->create($data);
            $newTask = $this->taskModel->findById($taskId);
            Response::success($newTask, 'Tarea creada correctamente.', 201);
        } catch (Exception $e) {
            Response::error('Error al crear la tarea: ' . $e->getMessage(), 500);
        }
    }

    // ---------------------------------------------------------
    // PUT /api/v1/tasks
    // Actualiza una tarea (Acceso: admin y member)
    // ---------------------------------------------------------
    public function update(): void
    {
        AuthMiddleware::handle();
        $user = AuthMiddleware::getCurrentUser();
        $body = $this->getJsonBody();

        if (empty($body['id'])) {
            Response::error('El ID de la tarea es obligatorio.', 400);
        }

        $id = (int) $body['id'];
        $task = $this->taskModel->findById($id);
        if (!$task) {
            Response::error('Tarea no encontrada.', 404);
        }

        // Verificar que la tarea pertenece a la banda del usuario
        if ((int)$task['band_id'] !== (int)$user['band_id']) {
            Response::error('Acceso no autorizado.', 403);
        }

        $errors = [];
        if (empty($body['title'])) {
            $errors['title'] = 'El título es obligatorio.';
        }

        if (!empty($errors)) {
            Response::error('Datos de entrada inválidos.', 422, $errors);
        }

        $data = [
            'title'        => trim($body['title']),
            'description'  => !empty($body['description']) ? trim($body['description']) : null,
            'due_date'     => !empty($body['due_date']) ? $body['due_date'] : null,
            'assigned_to'  => !empty($body['assigned_to']) ? (int) $body['assigned_to'] : null,
            'is_completed' => !empty($body['is_completed'])
        ];

        try {
            $this->taskModel->update($id, $data);
            $updatedTask = $this->taskModel->findById($id);
            Response::success($updatedTask, 'Tarea actualizada correctamente.');
        } catch (Exception $e) {
            Response::error('Error al actualizar la tarea: ' . $e->getMessage(), 500);
        }
    }

    // ---------------------------------------------------------
    // DELETE /api/v1/tasks?id=X
    // Elimina una tarea (Acceso: admin y member)
    // ---------------------------------------------------------
    public function destroy(): void
    {
        AuthMiddleware::handle();
        $user = AuthMiddleware::getCurrentUser();

        $id = $_GET['id'] ?? null;
        if (!$id || !is_numeric($id)) {
            Response::error('El parámetro id es obligatorio.', 400);
        }

        $id = (int) $id;
        $task = $this->taskModel->findById($id);
        if (!$task) {
            Response::error('Tarea no encontrada.', 404);
        }

        // Verificar que la tarea pertenece a la banda del usuario
        if ((int)$task['band_id'] !== (int)$user['band_id']) {
            Response::error('Acceso no autorizado.', 403);
        }

        try {
            $this->taskModel->delete($id);
            Response::success(null, 'Tarea eliminada correctamente.');
        } catch (Exception $e) {
            Response::error('Error al eliminar la tarea: ' . $e->getMessage(), 500);
        }
    }

    private function getJsonBody(): array
    {
        $raw = file_get_contents('php://input');
        return json_decode($raw, true) ?? [];
    }
}
