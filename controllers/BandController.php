<?php
// =============================================================
// controllers/BandController.php
// Endpoints: GET /bands | POST /bands | PUT /bands | DELETE /bands
// =============================================================

class BandController
{
    private Band $bandModel;

    public function __construct()
    {
        $this->bandModel = new Band();
    }

    public function index(): void
    {
        AuthMiddleware::handle();
        AuthMiddleware::requireRole('superadmin');
        
        $bands = $this->bandModel->findAll();
        Response::success($bands);
    }

    public function store(): void
    {
        AuthMiddleware::handle();
        AuthMiddleware::requireRole('superadmin');

        $body = $this->getJsonBody();
        if (empty($body['name'])) {
            Response::error('El nombre de la banda es obligatorio.', 422);
        }

        try {
            $bandId = $this->bandModel->create(['name' => trim($body['name'])]);
            $newBand = $this->bandModel->findById($bandId);
            Response::success($newBand, 'Banda creada correctamente.', 201);
        } catch (Exception $e) {
            Response::error('Error al crear la banda.', 500);
        }
    }

    public function update(): void
    {
        AuthMiddleware::handle();
        AuthMiddleware::requireRole('superadmin');

        $body = $this->getJsonBody();
        if (empty($body['id'])) Response::error('El ID de la banda es obligatorio.', 400);
        if (empty($body['name'])) Response::error('El nombre de la banda es obligatorio.', 422);

        try {
            $this->bandModel->update((int) $body['id'], ['name' => trim($body['name'])]);
            $updatedBand = $this->bandModel->findById((int) $body['id']);
            Response::success($updatedBand, 'Banda actualizada correctamente.');
        } catch (Exception $e) {
            Response::error('Error al actualizar la banda.', 500);
        }
    }

    public function destroy(): void
    {
        AuthMiddleware::handle();
        AuthMiddleware::requireRole('superadmin');

        $id = $_GET['id'] ?? null;
        if (!$id || !is_numeric($id)) Response::error('El ID es obligatorio.', 400);

        if ((int)$id === 1) Response::error('Por seguridad, no se puede eliminar la Banda Principal del sistema.', 403);

        try {
            $this->bandModel->delete((int) $id);
            Response::success(null, 'Banda eliminada correctamente.');
        } catch (PDOException $e) {
            Response::error('Error al eliminar la banda. Asegúrate de que no tenga usuarios o datos asociados.', 500);
        }
    }

    private function getJsonBody(): array
    {
        $raw = file_get_contents('php://input');
        return json_decode($raw, true) ?? [];
    }
}