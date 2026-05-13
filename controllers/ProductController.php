<?php
// =============================================================
// controllers/ProductController.php
// Endpoints: GET /products | POST /products
// =============================================================

class ProductController
{
    private Product $productModel;

    public function __construct()
    {
        $this->productModel = new Product();
    }

    // ---------------------------------------------------------
    // GET /api/v1/products
    // Lista todos los productos (Acceso: admin y member)
    // ---------------------------------------------------------
    public function index(): void
    {
        AuthMiddleware::handle();

        $user = AuthMiddleware::getCurrentUser();
        $products = $this->productModel->findAll($user['band_id']);
        Response::success($products);
    }

    // ---------------------------------------------------------
    // POST /api/v1/products
    // Crea un nuevo producto (Acceso: solo admin)
    // ---------------------------------------------------------
    public function store(): void
    {
        AuthMiddleware::handle();
        AuthMiddleware::requireRole('admin'); // Bloqueo de RBAC (Role-Based Access Control)

        $body = $this->getJsonBody();
        $user = AuthMiddleware::getCurrentUser();

        // 1. Validación básica
        $errors = [];
        if (empty($body['name'])) {
            $errors['name'] = 'El nombre del producto es obligatorio.';
        }
        if (empty($body['category_id'])) {
            $errors['category_id'] = 'La categoría es obligatoria.';
        }

        if (!empty($errors)) {
            Response::error('Datos de entrada inválidos.', 422, $errors);
        }

        // 2. Preparar el payload aplicando valores por defecto seguros
        $data = [
            'band_id'         => $user['band_id'],
            'category_id'     => (int) $body['category_id'],
            'name'            => trim($body['name']),
            'description'     => $body['description'] ?? null,
            'base_price'      => (float) ($body['base_price'] ?? 0),
            'cost_price'      => (float) ($body['cost_price'] ?? 0),
            'low_stock_alert' => (int) ($body['low_stock_alert'] ?? 5),
            'image_url'       => $body['image_url'] ?? null,
            'created_by'      => $user['id'], // Atamos el creador al usuario logueado
        ];

        try {
            // 3. Crear registro
            $productId = $this->productModel->create($data);
            $newProduct = $this->productModel->findById($productId, $user['band_id']);
            
            Response::success($newProduct, 'Producto creado correctamente.', 201);
        } catch (PDOException $e) {
            // Error típico si se envía un category_id que no existe
            Response::error('Error al guardar en la base de datos (revisa la categoría).', 500);
        }
    }

    // ---------------------------------------------------------
    // PUT /api/v1/products
    // Actualiza un producto (Acceso: solo admin)
    // ---------------------------------------------------------
    public function update(): void
    {
        AuthMiddleware::handle();
        AuthMiddleware::requireRole('admin');

        $body = $this->getJsonBody();
        $user = AuthMiddleware::getCurrentUser();
        if (empty($body['id'])) Response::error('El ID del producto es obligatorio.', 400);
        if (empty($body['name'])) Response::error('El nombre es obligatorio.', 422);

        $data = [
            'category_id' => (int) ($body['category_id'] ?? 1),
            'name'        => trim($body['name']),
            'description' => $body['description'] ?? null,
            'base_price'  => (float) ($body['base_price'] ?? 0),
            'cost_price'  => (float) ($body['cost_price'] ?? 0)
        ];

        try {
            $this->productModel->update((int) $body['id'], $data);
            $updatedProduct = $this->productModel->findById((int) $body['id'], $user['band_id']);
            Response::success($updatedProduct, 'Producto actualizado correctamente.');
        } catch (Exception $e) {
            Response::error('Error al actualizar el producto.', 500);
        }
    }

    // ---------------------------------------------------------
    // DELETE /api/v1/products?id=X
    // Elimina un producto (Acceso: solo admin)
    // ---------------------------------------------------------
    public function destroy(): void
    {
        AuthMiddleware::handle();
        AuthMiddleware::requireRole('admin');

        $id = $_GET['id'] ?? null;
        if (!$id || !is_numeric($id)) Response::error('El ID es obligatorio.', 400);

        try {
            $this->productModel->delete((int) $id);
            Response::success(null, 'Producto eliminado correctamente.');
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') Response::error('No se puede eliminar porque ya tiene stock o ventas registradas.', 409);
            Response::error('Error al eliminar el producto.', 500);
        }
    }

    // ---- Helpers privados -----------------------------------
    private function getJsonBody(): array
    {
        $raw = file_get_contents('php://input');
        return json_decode($raw, true) ?? [];
    }
}