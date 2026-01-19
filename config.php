<?php
declare(strict_types=1);

// Intent: Load configuration from .env (using vlucas/phpdotenv) with
// fallbacks to the previous hardcoded defaults.

// Try to load Composer autoloader and Dotenv if available.
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';

    if (class_exists(\Dotenv\Dotenv::class)) {
        try {
            \Dotenv\Dotenv::createImmutable(__DIR__)->safeLoad();
        } catch (Throwable $e) {
            // ignore loading errors; fallbacks below will apply
        }
    }
}

function env(string $key, $default = null) {
    $v = getenv($key);
    if ($v === false) {
        return $default;
    }
    return $v;
}

$APP_CONFIG = [
    'db' => [
        'host' => env('DB_HOST', 'notebook-oscar'),
        'name' => env('DB_NAME', 'c2650268_cv'),
        'user' => env('DB_USER', 'c2650268_cv'),
        'pass' => env('DB_PASS', '92bitusaBU'),
        'charset' => env('DB_CHARSET', 'utf8mb4'),
    ],
    'base_url' => env('BASE_URL', 'https://servinlgsm.com.ar/cvapp'),
    'uploads_dir' => (function(){
        $u = env('UPLOADS_DIR');
        if ($u) {
            // allow __DIR__ placeholder in .env
            return str_replace('__DIR__', __DIR__, $u);
        }
        return __DIR__ . '/uploads';
    })(),
    'max_upload_bytes' => (int)env('MAX_UPLOAD_BYTES', 5 * 1024 * 1024),
    'allowed_ext' => ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'gif', 'bmp'],
    'allowed_mime' => [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/octet-stream',
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/gif',
        'image/bmp',
        'image/x-ms-bmp'
    ],
    'session' => [
        'name' => env('SESSION_NAME', 'cvapp_sess'),
        'lifetime' => (int)env('SESSION_LIFETIME', 60 * 60 * 2),
        'same_site' => env('SESSION_SAME_SITE', 'Lax'),
        'secure' => filter_var(env('SESSION_SECURE', 'true'), FILTER_VALIDATE_BOOLEAN),
        'http_only' => filter_var(env('SESSION_HTTP_ONLY', 'true'), FILTER_VALIDATE_BOOLEAN),
        'gc_maxlifetime' => (int)env('GC_MAXLIFETIME', 60 * 60 * 2),
    ],
    'admin' => [
        'session_timeout' => (int)env('ADMIN_SESSION_TIMEOUT', 60 * 30),
    ],
    'timezone' => env('TIMEZONE', 'America/Argentina/Buenos_Aires')
];
?>