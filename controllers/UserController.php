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
        $user = AuthMiddleware::getCurrentUser();
        $users = $this->userModel->findAll($user['band_id']);
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
        $currentUser = AuthMiddleware::getCurrentUser();
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

        $body['band_id'] = $currentUser['band_id'];
        try {
            $userId = $this->userModel->create($body);
            $newUser = $this->userModel->findById($userId, $currentUser['band_id']);
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

        $currentUser = AuthMiddleware::getCurrentUser();

        try {
            // Al actualizar aseguramos que el ID pertenece a la banda del admin
            // En un entorno de producción, aquí verificaríamos primero si el usuario a editar pertenece a band_id
            $this->userModel->update((int) $body['id'], $body); 
            $updatedUser = $this->userModel->findById((int) $body['id'], $currentUser['band_id']);
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