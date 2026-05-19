<?php
// =============================================================
// controllers/AuthController.php
// Endpoints: POST /login | POST /refresh | POST /logout | GET /me
// =============================================================

class AuthController
{
    private User $userModel;

    public function __construct()
    {
        $this->userModel = new User();
    }

    // ---------------------------------------------------------
    // POST /api/v1/auth/login
    // Body: { "email": "...", "password": "..." }
    // ---------------------------------------------------------
    public function login(): void
    {
        $body = $this->getJsonBody();

        // Validación básica
        $errors = [];
        if (empty($body['email'])) {
            $errors['email'] = 'El email es obligatorio.';
        } elseif (!filter_var($body['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'El email no es válido.';
        }
        if (empty($body['password'])) {
            $errors['password'] = 'La contraseña es obligatoria.';
        }

        if (!empty($errors)) {
            Response::error('Datos de entrada inválidos.', 422, $errors);
        }

        // Buscar usuario
        $user = $this->userModel->findByEmail($body['email']);

        // Timing-safe: verificar siempre aunque no exista el usuario
        $dummyHash = '$2y$12$invaliddummyhashfortimingprotection0000000000000000000';
        $hash = $user['password_hash'] ?? $dummyHash;

        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        if (!password_verify($body['password'], $hash) || !$user) {
            $this->userModel->logAccess(
                $user ? (int) $user['id'] : null,
                trim($body['email']),
                $ipAddress,
                $userAgent,
                'failed'
            );
            Response::error('Credenciales incorrectas.', 401);
        }

        if (!$user['is_active']) {
            $this->userModel->logAccess(
                (int) $user['id'],
                trim($body['email']),
                $ipAddress,
                $userAgent,
                'failed'
            );
            Response::error('Tu cuenta está desactivada. Contacta con el admin.', 403);
        }

        // Registrar acceso exitoso
        $this->userModel->logAccess(
            (int) $user['id'],
            trim($body['email']),
            $ipAddress,
            $userAgent,
            'success'
        );

        // Generar tokens
        $accessToken  = JWT::generateAccessToken([
            'sub'  => $user['id'],
            'role' => $user['role'],
            'name' => $user['name'],
        ]);
        $refreshToken = JWT::generateRefreshToken();
        $refreshExpiry = time() + JWT_REFRESH_EXPIRY;

        // Guardar refresh token hasheado
        $this->userModel->saveRefreshToken(
            $user['id'],
            JWT::hashRefreshToken($refreshToken),
            $refreshExpiry
        );

        // Renovar hash si bcrypt reporta necesidad de rehash
        if (password_needs_rehash($user['password_hash'], PASSWORD_BCRYPT, ['cost' => BCRYPT_COST])) {
            $newHash = password_hash($body['password'], PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
            $this->userModel->updatePasswordHash($user['id'], $newHash);
        }

        Response::success([
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type'    => 'Bearer',
            'expires_in'    => JWT_ACCESS_EXPIRY,
            'user' => [
                'id'            => $user['id'],
                'name'          => $user['name'],
                'email'         => $user['email'],
                'role'          => $user['role'],
                'band_id'       => $user['band_id'] ?? null,
                'band_name'     => $user['band_name'] ?? null,
                'band_logo_url' => $user['band_logo_url'] ?? null,
            ],
        ], 'Login correcto.');
    }

    // ---------------------------------------------------------
    // POST /api/v1/auth/refresh
    // Body: { "refresh_token": "..." }
    // Rota el refresh token (Refresh Token Rotation)
    // ---------------------------------------------------------
    public function refresh(): void
    {
        $body = $this->getJsonBody();

        if (empty($body['refresh_token'])) {
            Response::error('refresh_token es obligatorio.', 422);
        }

        $tokenHash = JWT::hashRefreshToken($body['refresh_token']);
        $record    = $this->userModel->findRefreshToken($tokenHash);

        if (!$record) {
            Response::unauthorized('Refresh token inválido o no encontrado.');
        }

        if ($record['revoked']) {
            // Posible robo de token — revocar todos los del usuario
            $this->userModel->revokeAllUserTokens($record['user_id']);
            Response::unauthorized('Token ya utilizado. Sesión terminada por seguridad.');
        }

        if (strtotime($record['expires_at']) < time()) {
            Response::unauthorized('Refresh token expirado. Inicia sesión de nuevo.');
        }

        if (!$record['is_active']) {
            Response::error('Tu cuenta está desactivada.', 403);
        }

        // Revocar el token usado (rotación)
        $this->userModel->revokeRefreshToken($tokenHash);

        // Emitir nuevos tokens
        $newAccessToken  = JWT::generateAccessToken([
            'sub'  => $record['user_id'],
            'role' => $record['role'],
            'name' => $record['name'],
        ]);
        $newRefreshToken = JWT::generateRefreshToken();
        $newRefreshExpiry = time() + JWT_REFRESH_EXPIRY;

        $this->userModel->saveRefreshToken(
            $record['user_id'],
            JWT::hashRefreshToken($newRefreshToken),
            $newRefreshExpiry
        );

        Response::success([
            'access_token'  => $newAccessToken,
            'refresh_token' => $newRefreshToken,
            'token_type'    => 'Bearer',
            'expires_in'    => JWT_ACCESS_EXPIRY,
        ], 'Token renovado.');
    }

    // ---------------------------------------------------------
    // POST /api/v1/auth/logout
    // Header: Authorization: Bearer <access_token>
    // Body: { "refresh_token": "..." }
    // ---------------------------------------------------------
    public function logout(): void
    {
        AuthMiddleware::handle();

        $body = $this->getJsonBody();

        if (!empty($body['refresh_token'])) {
            $tokenHash = JWT::hashRefreshToken($body['refresh_token']);
            $this->userModel->revokeRefreshToken($tokenHash);
        }

        Response::success(null, 'Sesión cerrada correctamente.');
    }

    // ---------------------------------------------------------
    // GET /api/v1/auth/me
    // Header: Authorization: Bearer <access_token>
    // ---------------------------------------------------------
    public function me(): void
    {
        AuthMiddleware::handle();
        $payload = AuthMiddleware::getCurrentUser();

        $user = $this->userModel->findById($payload['id']);

        if (!$user) {
            Response::notFound('Usuario no encontrado.');
        }

        Response::success($user);
    }

    public function changePassword(): void
    {
        AuthMiddleware::handle();
        $user = AuthMiddleware::getCurrentUser();
        $body = $this->getJsonBody();

        if (empty($body['current_password']) || empty($body['new_password'])) {
            Response::error('La contraseña actual y la nueva contraseña son obligatorias.', 422);
        }

        $currentHash = $this->userModel->getPasswordHash($user['id']);
        if (!$currentHash || !password_verify($body['current_password'], $currentHash)) {
            Response::error('La contraseña actual es incorrecta.', 401);
        }

        if (strlen($body['new_password']) < 6) {
            Response::error('La nueva contraseña debe tener al menos 6 caracteres.', 422);
        }

        $newHash = password_hash($body['new_password'], PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
        $this->userModel->updatePasswordHash($user['id'], $newHash);

        Response::success(null, 'Contraseña actualizada correctamente.');
    }

    public function accessLogs(): void
    {
        AuthMiddleware::handle();
        AuthMiddleware::requireRole('admin');
        $user = AuthMiddleware::getCurrentUser();

        $logs = $this->userModel->getAccessLogs($user['band_id']);
        Response::success($logs);
    }

    // ---- Helpers privados -----------------------------------

    private function getJsonBody(): array
    {
        $raw = file_get_contents('php://input');
        return json_decode($raw, true) ?? [];
    }
}
