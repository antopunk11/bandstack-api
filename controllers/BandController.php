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

        $body = !empty($_POST) ? $_POST : $this->getJsonBody();
        if (empty($body['name'])) {
            Response::error('El nombre de la banda es obligatorio.', 422);
        }

        // Procesar subida de logo
        $logoUrl = null;
        if (isset($_FILES['logo_file']) && $_FILES['logo_file']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../uploads/logos/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $ext = strtolower(pathinfo($_FILES['logo_file']['name'], PATHINFO_EXTENSION));
            $filename = uniqid('logo_') . '.' . $ext;
            
            if (move_uploaded_file($_FILES['logo_file']['tmp_name'], $uploadDir . $filename)) {
                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
                $scriptPath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
                $logoUrl = $protocol . '://' . $_SERVER['HTTP_HOST'] . $scriptPath . '/uploads/logos/' . $filename;
            }
        }

        try {
            $data = ['name' => trim($body['name'])];
            if ($logoUrl) $data['logo_url'] = $logoUrl;

            $bandId = $this->bandModel->create($data);
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

        // Si llega por FormData (gracias al Method Spoofing), usamos $_POST
        $body = !empty($_POST) ? $_POST : $this->getJsonBody();
        if (empty($body['id'])) Response::error('El ID de la banda es obligatorio.', 400);
        if (empty($body['name'])) Response::error('El nombre de la banda es obligatorio.', 422);

        // Procesar subida de un nuevo logo si se adjunta
        $logoUrl = null;
        if (isset($_FILES['logo_file']) && $_FILES['logo_file']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../uploads/logos/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $ext = strtolower(pathinfo($_FILES['logo_file']['name'], PATHINFO_EXTENSION));
            $filename = uniqid('logo_') . '.' . $ext;
            
            if (move_uploaded_file($_FILES['logo_file']['tmp_name'], $uploadDir . $filename)) {
                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
                $scriptPath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
                $logoUrl = $protocol . '://' . $_SERVER['HTTP_HOST'] . $scriptPath . '/uploads/logos/' . $filename;
            }
        }

        try {
            $data = ['name' => trim($body['name'])];
            if ($logoUrl) $data['logo_url'] = $logoUrl;

            $this->bandModel->update((int) $body['id'], $data);
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

    public function getSettings(): void
    {
        AuthMiddleware::handle();
        $user = AuthMiddleware::getCurrentUser();

        $band = $this->bandModel->findById($user['band_id']);
        if (!$band) {
            Response::notFound('Banda no encontrada.');
        }

        $settings = $band['settings'] ?? [];
        Response::success($settings);
    }

    public function updateSettings(): void
    {
        AuthMiddleware::handle();
        AuthMiddleware::requireRole('admin');
        $user = AuthMiddleware::getCurrentUser();

        $body = $this->getJsonBody();
        
        // Validación mínima: si viene mileage_price, convertirlo a float
        $settings = [];
        if (isset($body['mileage_price'])) {
            $settings['mileage_price'] = (float) $body['mileage_price'];
        }
        // Guardamos otros posibles ajustes que existan para no sobreescribir con array vacío
        $band = $this->bandModel->findById($user['band_id']);
        $currentSettings = $band['settings'] ?? [];
        
        $newSettings = array_merge($currentSettings, $settings);

        try {
            $this->bandModel->updateSettings($user['band_id'], $newSettings);
            Response::success($newSettings, 'Ajustes actualizados correctamente.');
        } catch (Exception $e) {
            Response::error('Error al actualizar los ajustes de la banda.', 500);
        }
    }

    private function getJsonBody(): array
    {
        $raw = file_get_contents('php://input');
        return json_decode($raw, true) ?? [];
    }
}