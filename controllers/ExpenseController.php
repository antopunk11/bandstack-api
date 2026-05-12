<?php
// =============================================================
// controllers/ExpenseController.php
// Endpoints: GET /expenses | POST /expenses
// =============================================================

class ExpenseController
{
    private Expense $expenseModel;

    public function __construct()
    {
        $this->expenseModel = new Expense();
    }

    public function index(): void
    {
        AuthMiddleware::handle();
        $expenses = $this->expenseModel->findAll();
        Response::success($expenses);
    }

    public function store(): void
    {
        AuthMiddleware::handle();
        // Acceso permitido tanto a 'admin' como a 'member'

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $user = AuthMiddleware::getCurrentUser();

        if (empty($body['category']) || empty($body['amount']) || empty($body['expense_date'])) {
            Response::error('La categoría, la cantidad y la fecha son obligatorias.', 422);
        }

        $data = [
            'event_id'     => !empty($body['event_id']) ? (int) $body['event_id'] : null,
            'category'     => trim($body['category']),
            'amount'       => (float) $body['amount'],
            'description'  => $body['description'] ?? null,
            'expense_date' => $body['expense_date'],
            'created_by'   => $user['id'],
        ];

        try {
            $expenseId = $this->expenseModel->create($data);
            Response::success(['id' => $expenseId], 'Gasto registrado correctamente.', 201);
        } catch (Exception $e) {
            Response::error('Error al guardar el gasto.', 500);
        }
    }
}