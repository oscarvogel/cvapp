<?php
// ============================================
// CONEXIÓN A BASE DE DATOS PARA ADMIN (soporte .env)
// ============================================

// Simple .env loader (no dependencias externas)
function load_dotenv($path) {
    if (!file_exists($path)) return [];
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $vars = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') === false) continue;
        [$key, $val] = explode('=', $line, 2);
        $key = trim($key);
        $val = trim($val);
        // Remove optional quotes
        if ((substr($val,0,1) === '"' && substr($val,-1) === '"') || (substr($val,0,1) === "'" && substr($val,-1) === "'")) {
            $val = substr($val,1,-1);
        }
        $vars[$key] = $val;
    }
    return $vars;
}

// Load .env from project root (two levels up from this file)
$envPath = realpath(__DIR__ . '/../../.env') ?: (__DIR__ . '/../../.env');
$env = load_dotenv($envPath);

// Select environment (development or production)
$appEnv = $env['APP_ENV'] ?? getenv('APP_ENV') ?: 'production';

// Default values (fallback)
$defaults = [
    'DB_HOST' => 'localhost',
    'DB_NAME' => 'c2650268_precios',
    'DB_USER' => 'c2650268_precios',
    'DB_PASS' => ''
];

// Choose config based on APP_ENV; allow DEV_ / PROD_ prefixes
if ($appEnv === 'development') {
    $host = $env['DEV_DB_HOST'] ?? $env['DB_HOST'] ?? $defaults['DB_HOST'];
    $dbname = $env['DEV_DB_NAME'] ?? $env['DB_NAME'] ?? $defaults['DB_NAME'];
    $username = $env['DEV_DB_USER'] ?? $env['DB_USER'] ?? $defaults['DB_USER'];
    $password = $env['DEV_DB_PASS'] ?? $env['DB_PASS'] ?? $defaults['DB_PASS'];
} else {
    $host = $env['PROD_DB_HOST'] ?? $env['DB_HOST'] ?? $defaults['DB_HOST'];
    $dbname = $env['PROD_DB_NAME'] ?? $env['DB_NAME'] ?? $defaults['DB_NAME'];
    $username = $env['PROD_DB_USER'] ?? $env['DB_USER'] ?? $defaults['DB_USER'];
    $password = $env['PROD_DB_PASS'] ?? $env['DB_PASS'] ?? $defaults['DB_PASS'];
}

try {
    $pdo = new PDO("mysql:host={$host};dbname={$dbname};charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch (PDOException $e) {
    error_log("Error de conexión DB Admin: " . $e->getMessage());
    header("Content-Type: application/json");
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => "Error de conexión a la base de datos.",
        "debug" => $e->getMessage()
    ]);
    exit();
}
?>