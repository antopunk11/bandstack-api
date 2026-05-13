<?php
// =============================================================
// middleware/AuthMiddleware.php — Protección de rutas con JWT
// =============================================================

class AuthMiddleware
{
    private static ?array $currentUser = null;

    // ---------------------------------------------------------
    // Extrae y valida el token del header Authorization.
    // Inyecta los datos del usuario en $currentUser.
    // Llama a Response::unauthorized() y sale si es inválido.
    // ---------------------------------------------------------
    public static function handle(): void
    {
        $token = self::extractToken();

        if (!$token) {
            Response::unauthorized('Token de autenticación requerido.');
        }

        try {
            $payload = JWT::validate($token);

            if (($payload['type'] ?? '') !== 'access') {
                Response::unauthorized('Tipo de token inválido.');
            }

            // Consultamos la base de datos para obtener el band_id actualizado.
            // Así evitamos forzar a todos los usuarios a iniciar sesión de nuevo.
            $db = Database::getInstance();
            $stmt = $db->prepare("SELECT band_id FROM users WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => (int) $payload['sub']]);
            $bandId = $stmt->fetchColumn() ?: 1;

            // Guardar usuario en memoria para uso posterior
            self::$currentUser = [
                'id'   => (int) $payload['sub'],
                'role' => $payload['role'],
                'name' => $payload['name'],
                'band_id' => (int) $bandId,
            ];

        } catch (InvalidArgumentException $e) {
            Response::unauthorized($e->getMessage());
        }
    }

    // ---------------------------------------------------------
    // Verifica que el usuario autenticado tenga el rol requerido.
    // Debe llamarse DESPUÉS de handle().
    // ---------------------------------------------------------
    public static function requireRole(string ...$roles): void
    {
        $user = self::getCurrentUser();

        if (!in_array($user['role'], $roles, true)) {
            Response::forbidden('Tu rol no tiene acceso a esta operación.');
        }
    }

    // ---------------------------------------------------------
    // Devuelve el payload del usuario autenticado
    // ---------------------------------------------------------
    public static function getCurrentUser(): array
    {
        if (self::$currentUser === null) {
            Response::unauthorized('No hay sesión activa.');
        }
        return self::$currentUser;
    }

    // ---------------------------------------------------------
    // Extrae el Bearer token del header Authorization
    // ---------------------------------------------------------
    private static function extractToken(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION']
               ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
               ?? null;

        if (!$header && function_exists('apache_request_headers')) {
            $header = apache_request_headers()['Authorization'] ?? null;
        }

        if (!$header) {
            return null;
        }

        if (preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }
}
