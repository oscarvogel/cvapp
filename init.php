<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

// Zona horaria
date_default_timezone_set($APP_CONFIG['timezone']);

// Ajustes de sesión
ini_set('session.gc_maxlifetime', (string)$APP_CONFIG['session']['gc_maxlifetime']);
if (PHP_VERSION_ID >= 70300) {
    session_set_cookie_params([
        'lifetime' => $APP_CONFIG['session']['lifetime'],
        'path' => '/',
        'domain' => '',
        'secure' => $APP_CONFIG['session']['secure'],
        'httponly' => $APP_CONFIG['session']['http_only'],
        'samesite' => $APP_CONFIG['session']['same_site'],
    ]);
} else {
    // fallback sin SameSite en versiones viejas
    session_set_cookie_params($APP_CONFIG['session']['lifetime'], '/');
}
session_name($APP_CONFIG['session']['name']);
session_start();

require_once __DIR__ . '/functions.php';

// HTML de assets para forzar mayúsculas en inputs (puede ser incluido en plantillas)
// Calcular la URL base de la aplicación (por ejemplo "/cvapp") a partir del DOCUMENT_ROOT
// para que los assets se sirvan correctamente aunque la app esté en un subdirectorio.
$docRoot = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
$appDir  = str_replace('\\', '/', realpath(__DIR__));
$baseUrl = '/' . trim(str_replace($docRoot, '', $appDir), '/');
if ($baseUrl === '/') {
    // Si la aplicación está en la raíz del servidor, dejar base vacío para usar rutas absolutas desde /assets
    $baseUrl = '';
}
define('APP_BASE_URL', $baseUrl);
// Include upper-case assets and SweetAlert2 (for nicer alerts)
define('UPPER_ASSETS_HTML', "\n<link rel=\"stylesheet\" href=\"" . APP_BASE_URL . "/assets/css/upper.css\">\n<script src=\"" . APP_BASE_URL . "/assets/js/upper.js\" defer></script>\n<!-- SweetAlert2 -->\n<link rel=\"stylesheet\" href=\"https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css\">\n<script src=\"https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js\" defer></script>\n");

// Conexión PDO
try {
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s',
        $APP_CONFIG['db']['host'],
        $APP_CONFIG['db']['name'],
        $APP_CONFIG['db']['charset']
    );
    $pdo = new PDO($dsn, $APP_CONFIG['db']['user'], $APP_CONFIG['db']['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    exit('Error de conexión a la base de datos.');
}

// Asegurar carpeta uploads
if (!is_dir($APP_CONFIG['uploads_dir'])) {
    @mkdir($APP_CONFIG['uploads_dir'], 0755, true);
}
?>