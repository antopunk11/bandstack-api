<?php
// =============================================================
// controllers/VariantController.php
// Endpoints: GET /variants?product_id=X | POST /variants
// =============================================================

class VariantController
{
    private Variant $variantModel;

    public function __construct()
    {
        $this->variantModel = new Variant();
    }

    // ---------------------------------------------------------
    // GET /api/v1/variants?product_id=1
    // Lista variantes de un producto (Acceso: admin y member)
    // ---------------------------------------------------------
    public function index(): void
    {
        AuthMiddleware::handle();

        $productId = $_GET['product_id'] ?? null;

        if (!$productId || !is_numeric($productId)) {
            Response::error('El parámetro product_id es obligatorio en la consulta.', 400);
        }

        $variants = $this->variantModel->findByProductId((int) $productId);
        Response::success($variants);
    }

    // ---------------------------------------------------------
    // POST /api/v1/variants
    // Crea una nueva variante (Acceso: solo admin)
    // ---------------------------------------------------------
    public function store(): void
    {
        AuthMiddleware::handle();
        AuthMiddleware::requireRole('admin'); 

        $body = $this->getJsonBody();

        // 1. Validación básica
        $errors = [];
        if (empty($body['product_id'])) {
            $errors['product_id'] = 'El ID del producto es obligatorio.';
        }
        if (empty($body['attribute'])) {
            $errors['attribute'] = 'El atributo (talla, formato, etc.) es obligatorio.';
        }

        if (!empty($errors)) {
            Response::error('Datos de entrada inválidos.', 422, $errors);
        }

        // 2. Preparar payload seguro
        $data = [
            'product_id'     => (int) $body['product_id'],
            'sku'            => !empty($body['sku']) ? trim($body['sku']) : null,
            'attribute'      => trim($body['attribute']),
            'price_override' => isset($body['price_override']) ? (float) $body['price_override'] : null,
        ];

        // 3. Crear registro
        $variantId = $this->variantModel->create($data);
        $newVariant = $this->variantModel->findById($variantId);
        
        Response::success($newVariant, 'Variante creada correctamente.', 201);
    }

    private function getJsonBody(): array
    {
        $raw = file_get_contents('php://input');
        return json_decode($raw, true) ?? [];
    }
}