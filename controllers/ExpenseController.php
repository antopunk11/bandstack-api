<?php
// =============================================================
// controllers/ExpenseController.php
// Endpoints: GET /expenses | POST /expenses | PUT /expenses | DELETE /expenses
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
        // Si llega por FormData, los datos estarán en $_POST en lugar del JSON body
        $body = !empty($_POST) ? $_POST : $this->getJsonBody();

        // Validación
        $errors = [];
        if (empty($body['category'])) $errors['category'] = 'La categoría del gasto es obligatoria.';
        if (empty($body['amount']) || !is_numeric($body['amount'])) $errors['amount'] = 'El importe debe ser un número válido.';
        if (empty($body['expense_date'])) $errors['expense_date'] = 'La fecha del gasto es obligatoria.';

        if (!empty($errors)) {
            Response::error('Datos de entrada inválidos.', 422, $errors);
        }

        // Procesar subida de foto del ticket
        $receiptUrl = null;
        if (isset($_FILES['receipt_file']) && $_FILES['receipt_file']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../uploads/receipts/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $ext = strtolower(pathinfo($_FILES['receipt_file']['name'], PATHINFO_EXTENSION));
            $filename = uniqid('rcpt_') . '.' . $ext;
            
            if (move_uploaded_file($_FILES['receipt_file']['tmp_name'], $uploadDir . $filename)) {
                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
                $scriptPath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
                $receiptUrl = $protocol . '://' . $_SERVER['HTTP_HOST'] . $scriptPath . '/uploads/receipts/' . $filename;
            }
        }

        $data = [
            'band_id'       => $user['band_id'],
            'event_id'      => !empty($body['event_id']) ? (int) $body['event_id'] : null,
            'category'      => trim($body['category']),
            'amount'        => (float) $body['amount'],
            'description'   => trim($body['description'] ?? ''),
            'expense_date'  => $body['expense_date'],
            'is_paid'       => isset($body['is_paid']) ? (int) $body['is_paid'] : 0,
            'receipt_url'   => $receiptUrl,
            'created_by'    => $user['id'],
        ];

        try {
            $expenseId = $this->expenseModel->create($data);
            // Opcional: devolver el gasto recién creado
            Response::success(['id' => $expenseId], 'Gasto registrado correctamente.', 201);
        } catch (Exception $e) {
            Response::error('Error al registrar el gasto: ' . $e->getMessage(), 500);
        }
    }

    // ---------------------------------------------------------
    // PUT /api/v1/expenses
    // Actualiza un gasto (Acceso: admin y member)
    // ---------------------------------------------------------
    public function update(): void
    {
        AuthMiddleware::handle();
        // Si llega por FormData (gracias al Method Spoofing), usamos $_POST
        $body = !empty($_POST) ? $_POST : $this->getJsonBody();
        $user = AuthMiddleware::getCurrentUser();

        if (empty($body['id'])) {
            Response::error('El ID del gasto es obligatorio.', 400);
        }

        $existing = $this->expenseModel->findById((int) $body['id'], $user['band_id']);
        if (!$existing) {
            Response::error('Gasto no encontrado.', 404);
        }

        // Comprobación de permisos: admin, superadmin, o el creador
        if (!in_array($user['role'], ['admin', 'superadmin']) && $existing['created_by'] !== $user['id']) {
            Response::error('No tienes permiso para editar este gasto.', 403);
        }

        // Procesar subida de nueva foto del ticket si se ha adjuntado
        $receiptUrl = $existing['receipt_url'];
        if (isset($_FILES['receipt_file']) && $_FILES['receipt_file']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../uploads/receipts/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $ext = strtolower(pathinfo($_FILES['receipt_file']['name'], PATHINFO_EXTENSION));
            $filename = uniqid('rcpt_') . '.' . $ext;
            
            if (move_uploaded_file($_FILES['receipt_file']['tmp_name'], $uploadDir . $filename)) {
                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
                $scriptPath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
                $receiptUrl = $protocol . '://' . $_SERVER['HTTP_HOST'] . $scriptPath . '/uploads/receipts/' . $filename;
            }
        }

        $data = [
            'event_id'     => array_key_exists('event_id', $body) ? ($body['event_id'] ? (int)$body['event_id'] : null) : $existing['event_id'],
            'category'     => $body['category'] ?? $existing['category'],
            'amount'       => isset($body['amount']) ? (float) $body['amount'] : (float)$existing['amount'],
            'description'  => isset($body['description']) ? trim($body['description']) : ($existing['description'] ?? ''),
            'expense_date' => $body['expense_date'] ?? $existing['expense_date'],
            'is_paid'      => isset($body['is_paid']) ? (int) $body['is_paid'] : (int)$existing['is_paid'],
            'receipt_url'  => $receiptUrl
        ];

        try {
            $this->expenseModel->update((int) $body['id'], $data, $user['band_id']);
            Response::success(null, 'Gasto actualizado correctamente.');
        } catch (Exception $e) {
            Response::error('Error al actualizar el gasto: ' . $e->getMessage(), 500);
        }
    }

    // ---------------------------------------------------------
    // DELETE /api/v1/expenses?id=X
    // Elimina un gasto (Acceso: admin, superadmin o creador)
    // ---------------------------------------------------------
    public function destroy(): void
    {
        AuthMiddleware::handle();
        $user = AuthMiddleware::getCurrentUser();

        $id = $_GET['id'] ?? null;
        if (!$id || !is_numeric($id)) {
            Response::error('El ID del gasto es obligatorio.', 400);
        }

        $existing = $this->expenseModel->findById((int) $id, $user['band_id']);
        if (!$existing) {
            Response::error('Gasto no encontrado.', 404);
        }

        // Comprobación de permisos: admin, superadmin, o el creador
        if (!in_array($user['role'], ['admin', 'superadmin']) && $existing['created_by'] !== $user['id']) {
            Response::error('No tienes permiso para eliminar este gasto.', 403);
        }

        try {
            $this->expenseModel->delete((int) $id, $user['band_id']);
            Response::success(null, 'Gasto eliminado correctamente.');
        } catch (Exception $e) {
            Response::error('Error al eliminar el gasto: ' . $e->getMessage(), 500);
        }
    }

    // ---- Helpers privados -----------------------------------
    private function getJsonBody(): array
    {
        $raw = file_get_contents('php://input');
        return json_decode($raw, true) ?? [];
    }
}