<?php
// =============================================================
// index.php — Front Controller (único punto de entrada de la API)
// Todas las peticiones entran aquí gracias a .htaccess
// =============================================================

// Evitamos cualquier espacio o salida previa
//ob_start(); 

//$allowedOrigin = "https://bandstack-client.vercel.app";

//header("Access-Control-Allow-Origin: $allowedOrigin");
//header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
//header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
//header("Access-Control-Allow-Credentials: true");

// Si es OPTIONS, cortamos la ejecución AQUÍ mismo
//if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
//    header("HTTP/1.1 200 OK");
//    ob_end_clean();
//    exit;
//}
//ob_end_flush();

declare(strict_types=1);

// --- Zona horaria ------------------------------------------
date_default_timezone_set('Europe/Madrid');

// --- Autoload simple (sin Composer) -------------------------
spl_autoload_register(function (string $class): void {
    $map = [
        // Config
        'Database'        => __DIR__ . '/config/Database.php',
        // Helpers
        'JWT'             => __DIR__ . '/helpers/JWT.php',
        'Response'        => __DIR__ . '/helpers/Response.php',
        // Middleware
        'AuthMiddleware'  => __DIR__ . '/middleware/AuthMiddleware.php',
        // Models
        'Band'            => __DIR__ . '/models/Band.php',
        'User'            => __DIR__ . '/models/User.php',
        'Product'         => __DIR__ . '/models/Product.php',
        'Variant'         => __DIR__ . '/models/Variant.php',
        'StockMovement'   => __DIR__ . '/models/StockMovement.php',
        'Event'           => __DIR__ . '/models/Event.php',
        'Sale'            => __DIR__ . '/models/Sale.php',
        'Expense'         => __DIR__ . '/models/Expense.php',
        'RecurringExpense'=> __DIR__ . '/models/RecurringExpense.php',
        'Income'          => __DIR__ . '/models/Income.php',
        'EventTask'       => __DIR__ . '/models/EventTask.php',
        // Controllers
        'BandController'  => __DIR__ . '/controllers/BandController.php',
        'AuthController'  => __DIR__ . '/controllers/AuthController.php',
        'UserController'  => __DIR__ . '/controllers/UserController.php',
        'ProductController' => __DIR__ . '/controllers/ProductController.php',
        'VariantController' => __DIR__ . '/controllers/VariantController.php',
        'StockMovementController' => __DIR__ . '/controllers/StockMovementController.php',
        'EventController' => __DIR__ . '/controllers/EventController.php',
        'SaleController'  => __DIR__ . '/controllers/SaleController.php',
        'ExpenseController'=> __DIR__ . '/controllers/ExpenseController.php',
        'RecurringExpenseController' => __DIR__ . '/controllers/RecurringExpenseController.php',
        'IncomeController' => __DIR__ . '/controllers/IncomeController.php',
        'TaskController'   => __DIR__ . '/controllers/TaskController.php',
    ];

    if (isset($map[$class])) {
        require_once $map[$class];
    }
});

// --- Configuración base ------------------------------------
require_once __DIR__ . '/config/config.php';

// --- CORS --------------------------------------------------
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, CORS_ALLOWED_ORIGINS, true)) {
    header("Access-Control-Allow-Origin: {$origin}");
}
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Max-Age: 86400');
header('Content-Type: application/json; charset=utf-8');

// Preflight OPTIONS — respuesta inmediata
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// --- Routing -----------------------------------------------
$method = $_SERVER['REQUEST_METHOD'];

// Soporte para "Method Spoofing" (Permite enviar archivos por PUT usando POST)
if ($method === 'POST' && isset($_POST['_method'])) {
    $method = strtoupper($_POST['_method']);
}

$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Normalizar: ignorar la subcarpeta de instalación y quitar el /api/v1
$uri = urldecode($uri);
$uri = preg_replace('#^.*?/api/v1#', '', $uri);
$uri = rtrim($uri, '/') ?: '/';

// --- Rutas registradas ------------------------------------
//  [METHOD, pattern, Controller, method]
$routes = [
    ['POST',   '/auth/login',   'AuthController', 'login'],
    ['POST',   '/auth/refresh', 'AuthController', 'refresh'],
    ['POST',   '/auth/logout',  'AuthController', 'logout'],
    ['GET',    '/auth/me',      'AuthController', 'me'],
    ['POST',   '/auth/change-password', 'AuthController', 'changePassword'],
    ['GET',    '/auth/access-logs',     'AuthController', 'accessLogs'],

    // Próximas fases — descomenta según se implementen:
    // --- Configuración de Banda ---
    ['GET',  '/bands/settings',  'BandController', 'getSettings'],
    ['PUT',  '/bands/settings',  'BandController', 'updateSettings'],

    ['GET',  '/bands',           'BandController', 'index'],
    ['POST', '/bands',           'BandController', 'store'],
    ['PUT',  '/bands',           'BandController', 'update'],
    ['DELETE','/bands',          'BandController', 'destroy'],
    ['GET',  '/users',           'UserController', 'index'],
    ['POST', '/users',           'UserController', 'store'],
    ['PUT',  '/users',           'UserController', 'update'],
    ['DELETE','/users',          'UserController', 'destroy'],
    ['GET',  '/products',        'ProductController', 'index'],
    ['POST', '/products',        'ProductController', 'store'],
    ['PUT',  '/products',        'ProductController', 'update'],
    ['DELETE','/products',       'ProductController', 'destroy'],
    ['GET',  '/variants',        'VariantController', 'index'],
    ['POST', '/variants',        'VariantController', 'store'],
    ['POST', '/stock-movements', 'StockMovementController', 'store'],
    ['GET',  '/events',          'EventController', 'index'],
    ['POST', '/events',          'EventController', 'store'],
    ['PUT',  '/events',          'EventController', 'update'],
    ['DELETE','/events',         'EventController', 'destroy'],
    ['POST', '/events/close',    'EventController', 'close'],
    ['GET',  '/events/summary',  'EventController', 'summary'],
    ['GET',  '/events/monthly-flow', 'EventController', 'monthlyFlow'],
    ['POST', '/sales',           'SaleController', 'store'],
    ['GET',    '/expenses',     'ExpenseController', 'index'],
    ['POST',   '/expenses',     'ExpenseController', 'store'],
    ['PUT',    '/expenses',     'ExpenseController', 'update'],
    ['POST',   '/expenses/bulk-pay', 'ExpenseController', 'bulkPay'],
    ['DELETE', '/expenses',     'ExpenseController', 'destroy'],

    // --- Gastos Recurrentes ---
    ['GET',    '/recurring-expenses',    'RecurringExpenseController', 'index'],
    ['POST',   '/recurring-expenses',    'RecurringExpenseController', 'store'],
    ['PUT',    '/recurring-expenses',    'RecurringExpenseController', 'update'],
    ['DELETE', '/recurring-expenses',    'RecurringExpenseController', 'destroy'],

    // --- Ingresos Extra ---
    ['GET',    '/incomes',    'IncomeController', 'index'],
    ['POST',   '/incomes',    'IncomeController', 'store'],
    ['PUT',    '/incomes',    'IncomeController', 'update'],
    ['DELETE', '/incomes',    'IncomeController', 'destroy'],

    // --- Tareas de Evento ---
    ['GET',    '/tasks',    'TaskController', 'index'],
    ['POST',   '/tasks',    'TaskController', 'store'],
    ['PUT',    '/tasks',    'TaskController', 'update'],
    ['DELETE', '/tasks',    'TaskController', 'destroy'],
];

foreach ($routes as [$routeMethod, $pattern, $controller, $action]) {
    if ($method === $routeMethod && $uri === $pattern) {
        $instance = new $controller();
        $instance->$action();
        exit;
    }
}

// --- 404 --------------------------------------------------
http_response_code(404);
echo json_encode([
    'success' => false,
    'message' => "Ruta [{$method}] {$uri} no encontrada.",
]);
