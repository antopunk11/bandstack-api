<?php
// =============================================================
// controllers/UserController.php
// Endpoints: GET /users | POST /users | PUT /users | DELETE /users
// =============================================================

class UserController
{
    private User $userModel;

    public function __construct()
    {
        $this->userModel = new User();
    }

    // ---------------------------------------------------------
    // GET /api/v1/users
    // Lista todos los usuarios (Acceso: admin y member)
    // ---------------------------------------------------------
    public function index(): void
    {
        AuthMiddleware::handle();
        $users = $this->userModel->findAll();
        Response::success($users);
    }

    // ---------------------------------------------------------
    // POST /api/v1/users
    // Crea un nuevo usuario (Acceso: solo admin)
    // ---------------------------------------------------------
    public function store(): void
    {
        AuthMiddleware::handle();
        AuthMiddleware::requireRole('admin');

        $body = $this->getJsonBody();
        $errors = [];

        if (empty($body['name'])) $errors['name'] = 'El nombre es obligatorio.';
        if (empty($body['email'])) $errors['email'] = 'El correo electrónico es obligatorio.';
        if (empty($body['password'])) $errors['password'] = 'La contraseña es obligatoria.';

        // Comprobar si el email ya existe
        if (!empty($body['email']) && $this->userModel->findByEmail($body['email'])) {
            $errors['email'] = 'Este correo electrónico ya está registrado.';
        }

        if (!empty($errors)) {
            Response::error('Datos de entrada inválidos.', 422, $errors);
        }

        try {
            $userId = $this->userModel->create($body);
            $newUser = $this->userModel->findById($userId);
            Response::success($newUser, 'Usuario creado correctamente.', 201);
        } catch (Exception $e) {
            Response::error('Error al crear el usuario.', 500);
        }
    }

    // ---------------------------------------------------------
    // PUT /api/v1/users
    // Actualiza un usuario (Acceso: solo admin)
    // ---------------------------------------------------------
    public function update(): void
    {
        AuthMiddleware::handle();
        AuthMiddleware::requireRole('admin');

        $body = $this->getJsonBody();
        if (empty($body['id'])) Response::error('El ID del usuario es obligatorio.', 400);

        try {
            $this->userModel->update((int) $body['id'], $body);
            $updatedUser = $this->userModel->findById((int) $body['id']);
            Response::success($updatedUser, 'Usuario actualizado correctamente.');
        } catch (Exception $e) {
            Response::error('Error al actualizar el usuario.', 500);
        }
    }

    // ---------------------------------------------------------
    // DELETE /api/v1/users?id=X
    // Elimina un usuario (Acceso: solo admin)
    // ---------------------------------------------------------
    public function destroy(): void
    {
        AuthMiddleware::handle();
        AuthMiddleware::requireRole('admin');

        $id = $_GET['id'] ?? null;
        if (!$id || !is_numeric($id)) Response::error('El ID es obligatorio.', 400);

        $this->userModel->delete((int) $id);
        Response::success(null, 'Usuario eliminado correctamente.');
    }

    // ---- Helpers privados -----------------------------------
    private function getJsonBody(): array
    {
        $raw = file_get_contents('php://input');
        return json_decode($raw, true) ?? [];
    }
}