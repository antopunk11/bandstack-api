<?php
// =============================================================
// models/User.php — Acceso a datos de la tabla `users`
// =============================================================

class User
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ---------------------------------------------------------
    // Obtiene todos los usuarios
    // ---------------------------------------------------------
    public function findAll(): array
    {
        $stmt = $this->db->query(
            "SELECT id, name, email, role, avatar_url, is_active, created_at 
               FROM users 
              ORDER BY created_at DESC"
        );
        return $stmt->fetchAll() ?: [];
    }

    // ---------------------------------------------------------
    // Busca un usuario activo por email
    // ---------------------------------------------------------
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT id, name, email, password_hash, role, is_active
               FROM users
              WHERE email = :email
              LIMIT 1"
        );
        $stmt->execute([':email' => strtolower(trim($email))]);

        $user = $stmt->fetch();
        return $user ?: null;
    }

    // ---------------------------------------------------------
    // Busca un usuario por ID (sin devolver el hash)
    // ---------------------------------------------------------
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT id, name, email, role, avatar_url, is_active, created_at
               FROM users
              WHERE id = :id
              LIMIT 1"
        );
        $stmt->execute([':id' => $id]);

        $user = $stmt->fetch();
        return $user ?: null;
    }

    // ---------------------------------------------------------
    // Guarda un refresh token hasheado en BD
    // ---------------------------------------------------------
    public function saveRefreshToken(int $userId, string $tokenHash, int $expiresAt): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO refresh_tokens (user_id, token_hash, expires_at)
             VALUES (:user_id, :token_hash, :expires_at)"
        );
        $stmt->execute([
            ':user_id'    => $userId,
            ':token_hash' => $tokenHash,
            ':expires_at' => date('Y-m-d H:i:s', $expiresAt),
        ]);
    }

    // ---------------------------------------------------------
    // Recupera y valida un refresh token
    // ---------------------------------------------------------
    public function findRefreshToken(string $tokenHash): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT rt.id, rt.user_id, rt.expires_at, rt.revoked,
                    u.name, u.email, u.role, u.is_active
               FROM refresh_tokens rt
               JOIN users u ON u.id = rt.user_id
              WHERE rt.token_hash = :hash
              LIMIT 1"
        );
        $stmt->execute([':hash' => $tokenHash]);

        $result = $stmt->fetch();
        return $result ?: null;
    }

    // ---------------------------------------------------------
    // Revoca un refresh token específico (logout)
    // ---------------------------------------------------------
    public function revokeRefreshToken(string $tokenHash): void
    {
        $stmt = $this->db->prepare(
            "UPDATE refresh_tokens SET revoked = 1 WHERE token_hash = :hash"
        );
        $stmt->execute([':hash' => $tokenHash]);
    }

    // ---------------------------------------------------------
    // Revoca TODOS los tokens de un usuario (logout everywhere)
    // ---------------------------------------------------------
    public function revokeAllUserTokens(int $userId): void
    {
        $stmt = $this->db->prepare(
            "UPDATE refresh_tokens SET revoked = 1 WHERE user_id = :user_id"
        );
        $stmt->execute([':user_id' => $userId]);
    }

    // ---------------------------------------------------------
    // Actualiza el hash de la contraseña de un usuario
    // ---------------------------------------------------------
    public function updatePasswordHash(int $userId, string $newHash): void
    {
        $stmt = $this->db->prepare(
            "UPDATE users SET password_hash = :hash WHERE id = :id"
        );
        $stmt->execute([':hash' => $newHash, ':id' => $userId]);
    }

    // ---------------------------------------------------------
    // Crea un nuevo usuario
    // ---------------------------------------------------------
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO users (name, email, password_hash, role, is_active)
             VALUES (:name, :email, :password_hash, :role, :is_active)"
        );
        $stmt->execute([
            ':name'          => $data['name'],
            ':email'         => strtolower(trim($data['email'])),
            ':password_hash' => password_hash($data['password'], PASSWORD_BCRYPT),
            ':role'          => $data['role'] ?? 'member',
            ':is_active'     => $data['is_active'] ?? 1,
        ]);
        return (int) $this->db->lastInsertId();
    }

    // ---------------------------------------------------------
    // Actualiza datos básicos de un usuario (rol, nombre, estado)
    // ---------------------------------------------------------
    public function update(int $id, array $data): void
    {
        $stmt = $this->db->prepare(
            "UPDATE users 
                SET name = :name, email = :email, role = :role, is_active = :is_active 
              WHERE id = :id"
        );
        $stmt->execute([
            ':id'        => $id,
            ':name'      => $data['name'],
            ':email'     => strtolower(trim($data['email'])),
            ':role'      => $data['role'],
            ':is_active' => (int) $data['is_active']
        ]);
    }

    // ---------------------------------------------------------
    // Elimina un usuario
    // ---------------------------------------------------------
    public function delete(int $id): void
    {
        $stmt = $this->db->prepare("DELETE FROM users WHERE id = :id");
        $stmt->execute([':id' => $id]);
    }
}
