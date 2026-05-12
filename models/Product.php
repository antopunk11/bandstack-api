<?php
// =============================================================
// models/Product.php — Acceso a datos de la tabla `products`
// =============================================================

class Product
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ---------------------------------------------------------
    // Obtiene todos los productos activos con su categoría
    // ---------------------------------------------------------
    public function findAll(): array
    {
        $stmt = $this->db->query(
            "SELECT p.id, p.category_id, c.name as category_name, p.name, 
                    p.description, p.base_price, p.cost_price, 
                    p.low_stock_alert, p.image_url, p.is_active
               FROM products p
               JOIN categories c ON p.category_id = c.id
              WHERE p.is_active = 1
              ORDER BY p.name ASC"
        );
        return $stmt->fetchAll() ?: [];
    }

    // ---------------------------------------------------------
    // Busca un producto por ID
    // ---------------------------------------------------------
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT p.id, p.category_id, c.name as category_name, p.name, 
                    p.description, p.base_price, p.cost_price, 
                    p.low_stock_alert, p.image_url, p.is_active
               FROM products p
               JOIN categories c ON p.category_id = c.id
              WHERE p.id = :id
              LIMIT 1"
        );
        $stmt->execute([':id' => $id]);

        $product = $stmt->fetch();
        return $product ?: null;
    }

    // ---------------------------------------------------------
    // Inserta un nuevo producto
    // ---------------------------------------------------------
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO products 
                (category_id, name, description, base_price, cost_price, low_stock_alert, image_url, created_by)
             VALUES 
                (:category_id, :name, :description, :base_price, :cost_price, :low_stock_alert, :image_url, :created_by)"
        );
        
        $stmt->execute([
            ':category_id'     => $data['category_id'],
            ':name'            => $data['name'],
            ':description'     => $data['description'],
            ':base_price'      => $data['base_price'],
            ':cost_price'      => $data['cost_price'],
            ':low_stock_alert' => $data['low_stock_alert'],
            ':image_url'       => $data['image_url'],
            ':created_by'      => $data['created_by'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    // ---------------------------------------------------------
    // Actualiza un producto existente
    // ---------------------------------------------------------
    public function update(int $id, array $data): void
    {
        $stmt = $this->db->prepare(
            "UPDATE products 
                SET category_id = :category_id, name = :name, description = :description, 
                    base_price = :base_price, cost_price = :cost_price
              WHERE id = :id"
        );
        $stmt->execute([
            ':id'          => $id,
            ':category_id' => $data['category_id'],
            ':name'        => $data['name'],
            ':description' => $data['description'],
            ':base_price'  => $data['base_price'],
            ':cost_price'  => $data['cost_price']
        ]);
    }

    // ---------------------------------------------------------
    // Elimina un producto
    // ---------------------------------------------------------
    public function delete(int $id): void
    {
        $stmt = $this->db->prepare("DELETE FROM products WHERE id = :id");
        $stmt->execute([':id' => $id]);
    }
}