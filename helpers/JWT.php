<?php
// =============================================================
// helpers/JWT.php — Implementación HS256 sin dependencias externas
// =============================================================

class JWT
{
    // ---------------------------------------------------------
    // Genera un access token (payload ligero, corta expiración)
    // ---------------------------------------------------------
    public static function generateAccessToken(array $payload): string
    {
        $payload = array_merge($payload, [
            'iss' => JWT_ISSUER,
            'iat' => time(),
            'exp' => time() + JWT_ACCESS_EXPIRY,
            'type' => 'access',
        ]);

        return self::encode($payload);
    }

    // ---------------------------------------------------------
    // Genera un refresh token opaco (string aleatorio seguro)
    // El token real se almacena hasheado en la BD.
    // ---------------------------------------------------------
    public static function generateRefreshToken(): string
    {
        return bin2hex(random_bytes(64)); // 128 chars hex
    }

    // ---------------------------------------------------------
    // Valida y decodifica un access token.
    // Lanza InvalidArgumentException si no es válido.
    // ---------------------------------------------------------
    public static function validate(string $token): array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            throw new InvalidArgumentException('Token malformado.');
        }

        [$headerB64, $payloadB64, $signature] = $parts;

        // Verificar firma
        $expectedSignature = self::sign("{$headerB64}.{$payloadB64}");
        if (!hash_equals($expectedSignature, $signature)) {
            throw new InvalidArgumentException('Firma del token inválida.');
        }

        // Decodificar payload
        $payload = json_decode(self::base64UrlDecode($payloadB64), true);

        if (!$payload) {
            throw new InvalidArgumentException('Payload del token inválido.');
        }

        // Verificar expiración
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            throw new InvalidArgumentException('Token expirado.');
        }

        // Verificar issuer
        if (($payload['iss'] ?? '') !== JWT_ISSUER) {
            throw new InvalidArgumentException('Issuer del token inválido.');
        }

        return $payload;
    }

    // ---------------------------------------------------------
    // Hashea un refresh token para almacenamiento seguro en BD
    // ---------------------------------------------------------
    public static function hashRefreshToken(string $token): string
    {
        return hash('sha256', $token);
    }

    // ---- Métodos privados -----------------------------------

    private static function encode(array $payload): string
    {
        $header = ['alg' => JWT_ALGORITHM, 'typ' => 'JWT'];

        $headerB64  = self::base64UrlEncode(json_encode($header));
        $payloadB64 = self::base64UrlEncode(json_encode($payload));
        $signature  = self::sign("{$headerB64}.{$payloadB64}");

        return "{$headerB64}.{$payloadB64}.{$signature}";
    }

    private static function sign(string $data): string
    {
        return self::base64UrlEncode(
            hash_hmac('sha256', $data, JWT_SECRET, true)
        );
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', 3 - (3 + strlen($data)) % 4));
    }
}
