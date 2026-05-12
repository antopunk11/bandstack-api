<?php
// =============================================================
// helpers/Response.php — Respuestas JSON estandarizadas
// Formato: { success, message, data?, errors? }
// =============================================================

class Response
{
    public static function json(mixed $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function success(mixed $data = null, string $message = 'OK', int $statusCode = 200): void
    {
        $body = ['success' => true, 'message' => $message];
        if ($data !== null) {
            $body['data'] = $data;
        }
        self::json($body, $statusCode);
    }

    public static function created(mixed $data = null, string $message = 'Recurso creado.'): void
    {
        self::success($data, $message, 201);
    }

    public static function error(string $message, int $statusCode = 400, array $errors = []): void
    {
        $body = ['success' => false, 'message' => $message];
        if (!empty($errors)) {
            $body['errors'] = $errors;
        }
        self::json($body, $statusCode);
    }

    public static function unauthorized(string $message = 'No autenticado.'): void
    {
        self::error($message, 401);
    }

    public static function forbidden(string $message = 'Sin permisos para esta acción.'): void
    {
        self::error($message, 403);
    }

    public static function notFound(string $message = 'Recurso no encontrado.'): void
    {
        self::error($message, 404);
    }

    public static function serverError(string $message = 'Error interno del servidor.'): void
    {
        self::error($message, 500);
    }
}
