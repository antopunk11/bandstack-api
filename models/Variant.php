<?php
// =============================================================
// models/Variant.php — Acceso a datos de la tabla `variants`
// =============================================================

class Variant
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ---------------------------------------------------------
    // Obtiene todas las variantes activas de un producto
    // ---------------------------------------------------------
    public function findByProductId(int $productId): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, product_id, sku, attribute, stock, price_override, is_active
               FROM variants
              WHERE product_id = :product_id AND is_active = 1
              ORDER BY attribute ASC"
        );
        $stmt->execute([':product_id' => $productId]);
        
        return $stmt->fetchAll() ?: [];
    }

    // ---------------------------------------------------------
    // Busca una variante por ID
    // ---------------------------------------------------------
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT id, product_id, sku, attribute, stock, price_override, is_active
               FROM variants
              WHERE id = :id
              LIMIT 1"
        );
        $stmt->execute([':id' => $id]);

        $variant = $stmt->fetch();
        return $variant ?: null;
    }

    // ---------------------------------------------------------
    // Inserta una nueva variante
    // Nota: El stock por defecto es 0, y deberá modificarse 
    // creando registros en `stock_movements`.
    // ---------------------------------------------------------
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO variants 
                (product_id, sku, attribute, price_override)
             VALUES 
                (:product_id, :sku, :attribute, :price_override)"
        );
        
        $stmt->execute([
            ':product_id'     => $data['product_id'],
            ':sku'            => $data['sku'],
            ':attribute'      => $data['attribute'],
            ':price_override' => $data['price_override']
        ]);

        return (int) $this->db->lastInsertId();
    }
}