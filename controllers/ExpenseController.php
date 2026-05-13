<?php
// =============================================================
// controllers/ExpenseController.php
// Endpoints: GET /expenses | POST /expenses | PUT /expenses
// =============================================================

class ExpenseController
{
    private Expense $expenseModel;

    public function __construct()
    {
        $this->expenseModel = new Expense();
    }

    // ---------------------------------------------------------
    // GET /api/v1/expenses
    // Lista todos los gastos de la banda (Acceso: admin y member)
    // ---------------------------------------------------------
    public function index(): void
    {
        AuthMiddleware::handle();
        $user = AuthMiddleware::getCurrentUser();
        $expenses = $this->expenseModel->findAll($user['band_id']);
        Response::success($expenses);
    }

    // ---------------------------------------------------------
    // POST /api/v1/expenses
    // Crea un nuevo gasto (Acceso: admin y member)
    // ---------------------------------------------------------
    public function store(): void
    {
        AuthMiddleware::handle();
        $user = AuthMiddleware::getCurrentUser();
        $body = $this->getJsonBody();

        // Validación
        $errors = [];
        if (empty($body['category'])) $errors['category'] = 'La categoría del gasto es obligatoria.';
        if (empty($body['amount']) || !is_numeric($body['amount'])) $errors['amount'] = 'El importe debe ser un número válido.';
        if (empty($body['expense_date'])) $errors['expense_date'] = 'La fecha del gasto es obligatoria.';

        if (!empty($errors)) {
            Response::error('Datos de entrada inválidos.', 422, $errors);
        }

        $data = [
            'band_id'       => $user['band_id'],
            'event_id'      => !empty($body['event_id']) ? (int) $body['event_id'] : null,
            'category'      => trim($body['category']),
            'amount'        => (float) $body['amount'],
            'description'   => $body['description'] ?? null,
            'expense_date'  => $body['expense_date'],
            'is_paid'       => isset($body['is_paid']) ? (int) $body['is_paid'] : 0,
            'created_by'    => $user['id'],
        ];

        try {
            $expenseId = $this->expenseModel->create($data);
            // Opcional: devolver el gasto recién creado
            Response::success(['id' => $expenseId], 'Gasto registrado correctamente.', 201);
        } catch (Exception $e) {
            Response::error('Error al registrar el gasto.', 500);
        }
    }

    // ---------------------------------------------------------
    // PUT /api/v1/expenses
    // Actualiza un gasto (Acceso: admin y member)
    // ---------------------------------------------------------
    public function update(): void
    {
        AuthMiddleware::handle();
        $body = $this->getJsonBody();
        $user = AuthMiddleware::getCurrentUser();

        if (empty($body['id'])) {
            Response::error('El ID del gasto es obligatorio.', 400);
        }

        $data = [
            'is_paid' => isset($body['is_paid']) ? (int) $body['is_paid'] : 0
        ];

        try {
            $this->expenseModel->update((int) $body['id'], $data, $user['band_id']);
            Response::success(null, 'Gasto actualizado correctamente.');
        } catch (Exception $e) {
            Response::error('Error al actualizar el gasto.', 500);
        }
    }

    // ---- Helpers privados -----------------------------------
    private function getJsonBody(): array
    {
        $raw = file_get_contents('php://input');
        return json_decode($raw, true) ?? [];
    }
}