<?php
// =============================================================
// controllers/IncomeController.php
// Endpoints: GET /incomes | POST /incomes | PUT /incomes | DELETE /incomes
// =============================================================

class IncomeController
{
    private Income $incomeModel;

    public function __construct()
    {
        $this->incomeModel = new Income();
    }

    // ---------------------------------------------------------
    // GET /api/v1/incomes
    // Lista todos los ingresos de la banda
    // ---------------------------------------------------------
    public function index(): void
    {
        AuthMiddleware::handle();
        $user = AuthMiddleware::getCurrentUser();

        $incomes = $this->incomeModel->findAll($user['band_id']);
        Response::success($incomes);
    }

    // ---------------------------------------------------------
    // POST /api/v1/incomes
    // Crea un nuevo ingreso
    // ---------------------------------------------------------
    public function store(): void
    {
        AuthMiddleware::handle();
        $user = AuthMiddleware::getCurrentUser();
        $body = $this->getJsonBody();

        // Validación
        $errors = [];
        if (empty($body['category'])) {
            $errors['category'] = 'La categoría del ingreso es obligatoria.';
        }
        if (empty($body['amount']) || !is_numeric($body['amount']) || (float)$body['amount'] <= 0) {
            $errors['amount'] = 'El importe debe ser un número válido mayor que cero.';
        }
        if (empty($body['income_date'])) {
            $errors['income_date'] = 'La fecha del ingreso es obligatoria.';
        }
        if (empty($body['description'])) {
            $errors['description'] = 'La descripción es obligatoria.';
        }

        if (!empty($errors)) {
            Response::error('Datos de entrada inválidos.', 422, $errors);
        }

        $data = [
            'band_id'     => $user['band_id'],
            'category'    => trim($body['category']),
            'amount'      => (float) $body['amount'],
            'description' => trim($body['description']),
            'income_date' => $body['income_date'],
            'created_by'  => $user['id'],
        ];

        try {
            $incomeId = $this->incomeModel->create($data);
            Response::success(['id' => $incomeId], 'Ingreso registrado correctamente.', 201);
        } catch (Exception $e) {
            Response::error('Error al registrar el ingreso: ' . $e->getMessage(), 500);
        }
    }

    // ---------------------------------------------------------
    // PUT /api/v1/incomes
    // Actualiza un ingreso
    // ---------------------------------------------------------
    public function update(): void
    {
        AuthMiddleware::handle();
        $user = AuthMiddleware::getCurrentUser();
        $body = $this->getJsonBody();

        if (empty($body['id'])) {
            Response::error('El ID del ingreso es obligatorio.', 400);
        }

        $existing = $this->incomeModel->findById((int) $body['id'], $user['band_id']);
        if (!$existing) {
            Response::error('Ingreso no encontrado.', 404);
        }

        // Comprobación de permisos: admin, superadmin, o el creador
        if (!in_array($user['role'], ['admin', 'superadmin']) && (int)$existing['created_by'] !== (int)$user['id']) {
            Response::error('No tienes permiso para editar este ingreso.', 403);
        }

        // Validación
        $errors = [];
        if (isset($body['category']) && empty($body['category'])) {
            $errors['category'] = 'La categoría del ingreso no puede estar vacía.';
        }
        if (isset($body['amount']) && (!is_numeric($body['amount']) || (float)$body['amount'] <= 0)) {
            $errors['amount'] = 'El importe debe ser un número válido mayor que cero.';
        }
        if (isset($body['income_date']) && empty($body['income_date'])) {
            $errors['income_date'] = 'La fecha del ingreso no puede estar vacía.';
        }
        if (isset($body['description']) && empty($body['description'])) {
            $errors['description'] = 'La descripción no puede estar vacía.';
        }

        if (!empty($errors)) {
            Response::error('Datos de entrada inválidos.', 422, $errors);
        }

        $data = [
            'category'    => $body['category'] ?? $existing['category'],
            'amount'      => isset($body['amount']) ? (float) $body['amount'] : (float)$existing['amount'],
            'description' => isset($body['description']) ? trim($body['description']) : $existing['description'],
            'income_date' => $body['income_date'] ?? $existing['income_date'],
        ];

        try {
            $this->incomeModel->update((int) $body['id'], $data, $user['band_id']);
            Response::success(null, 'Ingreso actualizado correctamente.');
        } catch (Exception $e) {
            Response::error('Error al actualizar el ingreso: ' . $e->getMessage(), 500);
        }
    }

    // ---------------------------------------------------------
    // DELETE /api/v1/incomes?id=X
    // Elimina un ingreso
    // ---------------------------------------------------------
    public function destroy(): void
    {
        AuthMiddleware::handle();
        $user = AuthMiddleware::getCurrentUser();

        $id = $_GET['id'] ?? null;
        if (!$id || !is_numeric($id)) {
            Response::error('El ID del ingreso es obligatorio.', 400);
        }

        $existing = $this->incomeModel->findById((int) $id, $user['band_id']);
        if (!$existing) {
            Response::error('Ingreso no encontrado.', 404);
        }

        // Comprobación de permisos: admin, superadmin, o el creador
        if (!in_array($user['role'], ['admin', 'superadmin']) && (int)$existing['created_by'] !== (int)$user['id']) {
            Response::error('No tienes permiso para eliminar este ingreso.', 403);
        }

        try {
            $this->incomeModel->delete((int) $id, $user['band_id']);
            Response::success(null, 'Ingreso eliminado correctamente.');
        } catch (Exception $e) {
            Response::error('Error al eliminar el ingreso: ' . $e->getMessage(), 500);
        }
    }

    // ---- Helpers privados -----------------------------------
    private function getJsonBody(): array
    {
        $raw = file_get_contents('php://input');
        return json_decode($raw, true) ?? [];
    }
}
