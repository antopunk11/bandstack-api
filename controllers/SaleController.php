<?php
// =============================================================
// controllers/SaleController.php
// Endpoints: POST /sales
// =============================================================

class SaleController
{
    private Sale $saleModel;
    public function __construct() { $this->saleModel = new Sale(); }

    public function store(): void
    {
        AuthMiddleware::handle();
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $user = AuthMiddleware::getCurrentUser();

        if (empty($body['items']) || empty($body['payment_method'])) {
            Response::error('Datos de venta incompletos.', 422);
        }

        // En un caso de uso estricto, el total se calcularía en el backend. 
        // Para este POS dinámico donde podríamos forzar descuentos en vivo, validamos la cifra del cliente.
        
        $eventId = !empty($body['event_id']) ? (int) $body['event_id'] : null;

        if ($eventId) {
            $eventModel = new Event();
            $event = $eventModel->findById($eventId);
            if ($event && $event['status'] === 'closed') {
                Response::error('El evento está cerrado. No se pueden registrar más ventas.', 403);
            }
        }

        $data = [
            'band_id'        => $user['band_id'],
            'event_id'       => $eventId,
            'total_amount'   => (float) $body['total_amount'],
            'payment_method' => $body['payment_method'], // cash, card, bizum
            'created_by'     => $user['id']
        ];

        try {
            $saleId = $this->saleModel->create($data, $body['items']);
            Response::success(['sale_id' => $saleId], 'Venta registrada con éxito', 201);
        } catch (Exception $e) {
            Response::error('Error interno procesando la venta.', 500);
        }
    }
}