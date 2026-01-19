<?php
declare(strict_types=1);

// Intent: Load configuration from .env (using vlucas/phpdotenv) with
// fallbacks to the previous hardcoded defaults.

// Try to load Composer autoloader and Dotenv if available.
// Avoid fatal errors when vendor/ is present but incomplete (common when vendor wasn't installed).
$vendorAutoload = __DIR__ . '/vendor/autoload.php';
// Try to include composer autoload without causing a fatal error if vendor is incomplete.
if (file_exists($vendorAutoload)) {
    $included = @include_once $vendorAutoload;
    if ($included === false) {
        // include failed (incomplete vendor). Do not throw; fallback to manual .env parsing below.
    } else {
        if (class_exists(\Dotenv\Dotenv::class)) {
            try {
                \Dotenv\Dotenv::createImmutable(__DIR__)->safeLoad();
            } catch (Throwable $e) {
                // ignore loading errors; fallbacks below will apply
            }
        }
    }
}

// Fallback: some shared hostings may not have vendor installed or Dotenv
// may not load environment variables as expected. If a .env file exists
// parse it manually and set variables that are not already defined.
$envFile = __DIR__ . '/.env';
// parsed env values (prefer these before getenv)
$PARSED_ENV = [];
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }

        // Support KEY=VALUE (simple parsing)
        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) {
            continue;
        }

        $key = trim($parts[0]);
        $value = trim($parts[1]);

        // Remove surrounding quotes if present (compatible with PHP < 8)
        if (strlen($value) >= 2) {
            $first = $value[0];
            $last = $value[strlen($value) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }

        // store parsed value and also set in ENV arrays; do not overwrite existing superglobals
        $PARSED_ENV[$key] = $value;
        if (!array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $value;
        }
        if (!array_key_exists($key, $_SERVER)) {
            $_SERVER[$key] = $value;
        }
        // try putenv as best-effort (may be ignored by some FPM configs)
        if (function_exists('putenv')) {
            @putenv("{$key}={$value}");
        }
    }
}

function env(string $key, $default = null) {
    global $PARSED_ENV;
    if (isset($PARSED_ENV) && array_key_exists($key, $PARSED_ENV)) {
        return $PARSED_ENV[$key];
    }
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