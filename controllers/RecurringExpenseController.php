<?php
// =============================================================
// controllers/RecurringExpenseController.php
// Endpoints: GET /recurring-expenses | POST /recurring-expenses | PUT /recurring-expenses | DELETE /recurring-expenses
// =============================================================

class RecurringExpenseController
{
    private RecurringExpense $recurringModel;

    public function __construct()
    {
        $this->recurringModel = new RecurringExpense();
    }

    public function index(): void
    {
        AuthMiddleware::handle();
        $user = AuthMiddleware::getCurrentUser();
        $recurrings = $this->recurringModel->findAll($user['band_id']);
        Response::success($recurrings);
    }

    public function store(): void
    {
        AuthMiddleware::handle();
        $user = AuthMiddleware::getCurrentUser();
        $body = $this->getJsonBody();

        $errors = [];
        if (empty($body['category'])) $errors['category'] = 'La categoría es obligatoria.';
        if (empty($body['description'])) $errors['description'] = 'La descripción es obligatoria.';
        if (empty($body['amount']) || !is_numeric($body['amount'])) $errors['amount'] = 'El importe debe ser un número válido.';
        if (empty($body['recurrence_type'])) $errors['recurrence_type'] = 'El tipo de recurrencia es obligatorio.';
        if (empty($body['next_due_date'])) $errors['next_due_date'] = 'La fecha de próximo vencimiento es obligatoria.';

        if (!empty($errors)) {
            Response::error('Datos de entrada inválidos.', 422, $errors);
        }

        $data = [
            'band_id'          => $user['band_id'],
            'category'         => trim($body['category']),
            'description'      => trim($body['description']),
            'amount'           => (float) $body['amount'],
            'recurrence_type'  => trim($body['recurrence_type']),
            'next_due_date'    => $body['next_due_date'],
            'is_active'        => isset($body['is_active']) ? (int) $body['is_active'] : 1,
            'created_by'       => $user['id']
        ];

        try {
            $id = $this->recurringModel->create($data);
            Response::success(['id' => $id], 'Gasto recurrente registrado correctamente.', 201);
        } catch (Exception $e) {
            Response::error('Error al registrar el gasto recurrente: ' . $e->getMessage(), 500);
        }
    }

    public function update(): void
    {
        AuthMiddleware::handle();
        $user = AuthMiddleware::getCurrentUser();
        $body = $this->getJsonBody();

        if (empty($body['id'])) {
            Response::error('El ID del gasto recurrente es obligatorio.', 400);
        }

        $existing = $this->recurringModel->findById((int) $body['id'], $user['band_id']);
        if (!$existing) {
            Response::error('Gasto recurrente no encontrado.', 404);
        }

        $data = [
            'category'         => $body['category'] ?? $existing['category'],
            'description'      => isset($body['description']) ? trim($body['description']) : $existing['description'],
            'amount'           => isset($body['amount']) ? (float) $body['amount'] : (float)$existing['amount'],
            'recurrence_type'  => $body['recurrence_type'] ?? $existing['recurrence_type'],
            'next_due_date'    => $body['next_due_date'] ?? $existing['next_due_date'],
            'is_active'        => isset($body['is_active']) ? (int) $body['is_active'] : (int)$existing['is_active']
        ];

        try {
            $this->recurringModel->update((int) $body['id'], $data, $user['band_id']);
            Response::success(null, 'Gasto recurrente actualizado correctamente.');
        } catch (Exception $e) {
            Response::error('Error al actualizar el gasto recurrente: ' . $e->getMessage(), 500);
        }
    }

    public function destroy(): void
    {
        AuthMiddleware::handle();
        $user = AuthMiddleware::getCurrentUser();

        $id = $_GET['id'] ?? null;
        if (!$id || !is_numeric($id)) {
            Response::error('El ID es obligatorio.', 400);
        }

        $existing = $this->recurringModel->findById((int) $id, $user['band_id']);
        if (!$existing) {
            Response::error('Gasto recurrente no encontrado.', 404);
        }

        try {
            $this->recurringModel->delete((int) $id, $user['band_id']);
            Response::success(null, 'Gasto recurrente eliminado correctamente.');
        } catch (Exception $e) {
            Response::error('Error al eliminar el gasto recurrente: ' . $e->getMessage(), 500);
        }
    }

    private function getJsonBody(): array
    {
        $raw = file_get_contents('php://input');
        return json_decode($raw, true) ?? [];
    }
}
