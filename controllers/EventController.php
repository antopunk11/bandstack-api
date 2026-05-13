<?php
// =============================================================
// controllers/EventController.php
// Endpoints: GET /events | POST /events
// =============================================================

class EventController
{
    private Event $eventModel;

    public function __construct()
    {
        $this->eventModel = new Event();
    }

    // ---------------------------------------------------------
    // GET /api/v1/events
    // Lista los eventos (Acceso: admin y member)
    // ---------------------------------------------------------
    public function index(): void
    {
        AuthMiddleware::handle();
        $events = $this->eventModel->findAll();
        Response::success($events);
    }

    // ---------------------------------------------------------
    // POST /api/v1/events
    // Abre un nuevo evento (Acceso: solo admin)
    // ---------------------------------------------------------
    public function store(): void
    {
        AuthMiddleware::handle();
        AuthMiddleware::requireRole('admin');

        $body = $this->getJsonBody();
        $user = AuthMiddleware::getCurrentUser();

        $errors = [];
        if (empty($body['name'])) {
            $errors['name'] = 'El nombre del evento es obligatorio.';
        }
        if (empty($body['event_date'])) {
            $errors['event_date'] = 'La fecha del evento es obligatoria.';
        }

        if (!empty($errors)) {
            Response::error('Datos de entrada inválidos.', 422, $errors);
        }

        $data = [
            'name'       => trim($body['name']),
            'venue'      => $body['venue'] ?? null,
            'event_date' => $body['event_date'],
            'type'       => $body['type'] ?? 'concert',
            'cache_amount' => (float) ($body['cache_amount'] ?? 0),
            'created_by' => $user['id'],
        ];

        try {
            $eventId = $this->eventModel->create($data);
            $newEvent = $this->eventModel->findById($eventId);
            Response::success($newEvent, 'Evento creado correctamente.', 201);
        } catch (Exception $e) {
            Response::error('Error al guardar el evento en la base de datos.', 500);
        }
    }

    // ---------------------------------------------------------
    // PUT /api/v1/events
    // Actualiza un evento (Acceso: solo admin)
    // ---------------------------------------------------------
    public function update(): void
    {
        AuthMiddleware::handle();
        AuthMiddleware::requireRole('admin');

        $body = $this->getJsonBody();
        
        if (empty($body['id'])) {
            Response::error('El ID del evento es obligatorio.', 400);
        }

        $errors = [];
        if (empty($body['name'])) $errors['name'] = 'El nombre del evento es obligatorio.';
        if (empty($body['event_date'])) $errors['event_date'] = 'La fecha del evento es obligatoria.';

        if (!empty($errors)) {
            Response::error('Datos de entrada inválidos.', 422, $errors);
        }

        $data = [
            'name'         => trim($body['name']),
            'venue'        => $body['venue'] ?? null,
            'event_date'   => $body['event_date'],
            'type'         => $body['type'] ?? 'concert',
            'cache_amount' => (float) ($body['cache_amount'] ?? 0)
        ];

        try {
            $this->eventModel->update((int) $body['id'], $data);
            $updatedEvent = $this->eventModel->findById((int) $body['id']);
            Response::success($updatedEvent, 'Evento actualizado correctamente.');
        } catch (Exception $e) {
            Response::error('Error al actualizar el evento.', 500);
        }
    }

    // ---------------------------------------------------------
    // DELETE /api/v1/events?id=X
    // Elimina un evento (Acceso: solo admin)
    // ---------------------------------------------------------
    public function destroy(): void
    {
        AuthMiddleware::handle();
        AuthMiddleware::requireRole('admin');

        $eventId = $_GET['id'] ?? null;
        if (!$eventId || !is_numeric($eventId)) Response::error('El parámetro id es obligatorio.', 400);

        try {
            $this->eventModel->delete((int) $eventId);
            Response::success(null, 'Evento eliminado correctamente.');
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') Response::error('No se puede eliminar porque ya tiene ventas registradas.', 409);
            Response::error('Error al eliminar el evento.', 500);
        }
    }

    // ---------------------------------------------------------
    // POST /api/v1/events/close
    // Cierra un evento (Acceso: solo admin)
    // ---------------------------------------------------------
    public function close(): void
    {
        AuthMiddleware::handle();
        AuthMiddleware::requireRole('admin');
        
        $body = $this->getJsonBody();
        $eventId = $body['event_id'] ?? null;
        
        if (!$eventId || !is_numeric($eventId)) {
            Response::error('El parámetro event_id es obligatorio.', 400);
        }

        $event = $this->eventModel->findById((int) $eventId);
        if (!$event) {
            Response::error('Evento no encontrado.', 404);
        }

        $this->eventModel->updateStatus((int) $eventId, 'closed');
        Response::success(null, 'Evento cerrado correctamente.');
    }

    // ---------------------------------------------------------
    // GET /api/v1/events/summary?event_id=X
    // Obtiene la liquidación del evento
    // ---------------------------------------------------------
    public function summary(): void
    {
        AuthMiddleware::handle();
        
        $eventId = $_GET['event_id'] ?? null;
        
        // Si no se envía event_id o es 'global', cargamos el histórico
        if (!$eventId || $eventId === 'global') {
            $summary = $this->eventModel->getGlobalSummary();
            Response::success(['event' => null, 'summary' => $summary]);
            return;
        }

        if (!is_numeric($eventId)) {
            Response::error('El parámetro event_id debe ser numérico.', 400);
        }

        $event = $this->eventModel->findById((int) $eventId);
        if (!$event) {
            Response::error('Evento no encontrado.', 404);
        }

        $summary = $this->eventModel->getSummary((int) $eventId);
        Response::success(['event' => $event, 'summary' => $summary]);
    }

    private function getJsonBody(): array
    {
        $raw = file_get_contents('php://input');
        return json_decode($raw, true) ?? [];
    }
}