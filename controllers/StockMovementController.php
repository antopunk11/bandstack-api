<?php
// =============================================================
// controllers/StockMovementController.php
// Endpoints: POST /stock-movements
// =============================================================

class StockMovementController
{
    private StockMovement $stockModel;

    public function __construct()
    {
        $this->stockModel = new StockMovement();
    }

    // ---------------------------------------------------------
    // POST /api/v1/stock-movements
    // Añade o resta stock a una variante (Acceso: solo admin)
    // ---------------------------------------------------------
    public function store(): void
    {
        AuthMiddleware::handle();
        AuthMiddleware::requireRole('admin');

        $body = $this->getJsonBody();
        $user = AuthMiddleware::getCurrentUser();

        // Validación básica
        if (empty($body['variant_id']) || empty($body['quantity']) || empty($body['type'])) {
            Response::error('Faltan datos obligatorios (variant_id, quantity, type).', 422);
        }

        $validTypes = ['purchase', 'sale', 'gift', 'adjustment', 'return'];
        if (!in_array($body['type'], $validTypes, true)) {
            Response::error("El tipo de movimiento no es válido.", 422);
        }

        $data = [
            'variant_id' => (int) $body['variant_id'],
            'type'       => $body['type'],
            'quantity'   => (int) $body['quantity'], // Positivo para añadir, negativo para restar
            'notes'      => $body['notes'] ?? null,
            'created_by' => $user['id'],
        ];

        try {
            $movementId = $this->stockModel->create($data);
            Response::success(['movement_id' => $movementId], 'Stock actualizado correctamente.', 201);
        } catch (Exception $e) {
            Response::error('Error interno al actualizar el stock.', 500);
        }
    }

    private function getJsonBody(): array
    {
        $raw = file_get_contents('php://input');
        return json_decode($raw, true) ?? [];
    }
}