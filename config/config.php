<?php
// =============================================================
// config/config.php — Configuración central de la API
// =============================================================

// --- Entorno -----------------------------------------------
define('APP_ENV', getenv('APP_ENV') ?: 'production'); // 'development' | 'production'
define('APP_DEBUG', APP_ENV === 'development');

// --- Base URL (ajusta en hosting) --------------------------
define('API_VERSION', 'v1');
define('BASE_URL', 'https://tudominio.com/api');

// --- JWT ---------------------------------------------------
define('JWT_SECRET', getenv('JWT_SECRET') ?: 'CAMBIA_ESTE_SECRET_EN_PRODUCCION_min32chars!!');
define('JWT_ACCESS_EXPIRY',  60 * 15);          // 15 minutos (segundos)
define('JWT_REFRESH_EXPIRY', 60 * 60 * 24 * 7); // 7 días (segundos)
define('JWT_ALGORITHM', 'HS256');
define('JWT_ISSUER', 'bandstack-api');

// --- CORS --------------------------------------------------
define('CORS_ALLOWED_ORIGINS', [
    'http://localhost:4200',         // Angular dev
    'bandstack-client.vercel.app',   // Producción
]);

// --- Password hashing -------------------------------------
define('BCRYPT_COST', 12);
