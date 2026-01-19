<?php
declare(strict_types=1);

// Simple safe diagnostics page to help debug HTTP 500 issues.
// It prints environment info but masks secrets.

header('Content-Type: text/plain; charset=utf-8');

echo "DEBUG INFO - safe output\n";
echo "======================\n\n";

// Basic PHP info
echo "PHP Version: " . PHP_VERSION . "\n";
echo "SAPI: " . PHP_SAPI . "\n";
echo "Loaded php.ini: " . (PHP_CONFIG_FILE_PATH ?: 'none') . "\n\n";

// Check common files
$root = __DIR__;
echo "Project root: {$root}\n";
echo ".env exists: " . (file_exists($root . '/.env') ? 'yes' : 'no') . "\n";
echo "vendor/autoload.php exists: " . (file_exists($root . '/vendor/autoload.php') ? 'yes' : 'no') . "\n";
echo "config.php exists: " . (file_exists($root . '/config.php') ? 'yes' : 'no') . "\n";
echo "\.env.example exists: " . (file_exists($root . '/.env.example') ? 'yes' : 'no') . "\n\n";

// PHP ini settings relevant to env loading
echo "PHP ini settings:\n";
echo "  variables_order=" . (ini_get('variables_order') ?: '(not set)') . "\n";
echo "  disable_functions=" . (ini_get('disable_functions') ?: '(none)') . "\n";
echo "  display_errors=" . (ini_get('display_errors') ?: '(not set)') . "\n\n";

// If .env exists, show keys but mask values
if (file_exists($root . '/.env')) {
    echo "Contents of .env (values masked):\n";
    $lines = file($root . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $k = $parts[0];
            $v = $parts[1];
            $v = trim($v);
            $masked = (strlen($v) > 0) ? '***' : '(empty)';
            echo "  {$k}={$masked}\n";
        } else {
            echo "  {$line}\n";
        }
    }
    echo "\n";
}

// Show getenv / $_ENV / $_SERVER for DB keys (mask password)
$keys = ['DB_HOST','DB_NAME','DB_USER','DB_PASS','BASE_URL'];
echo "Environment variables (getenv / _ENV / _SERVER) [password masked]:\n";
foreach ($keys as $k) {
    $g = getenv($k);
    $e = array_key_exists($k, $_ENV) ? $_ENV[$k] : null;
    $s = array_key_exists($k, $_SERVER) ? $_SERVER[$k] : null;
    $display = $g ?? $e ?? $s ?? '(not set)';
    if ($k === 'DB_PASS' && $display !== '(not set)') $display = '***';
    echo "  {$k}: getenv=" . ($g===false? '(false)' : $display) . "\n";
}
echo "\n";

// Check PDO extension and pdo_mysql
echo "Extensions: pdo=" . (extension_loaded('pdo') ? 'yes' : 'no') . ", pdo_mysql=" . (extension_loaded('pdo_mysql') ? 'yes' : 'no') . "\n\n";

// Check availability of getenv/putenv
echo "Function availability: getenv=" . (function_exists('getenv') ? 'yes' : 'no') . ", putenv=" . (function_exists('putenv') ? 'yes' : 'no') . "\n\n";

// Try to include config.php but catch errors
echo "Attempting to require config.php (caught)\n";
try {
    require_once $root . '/config.php';
    if (isset($APP_CONFIG) && is_array($APP_CONFIG)) {
        echo "  APP_CONFIG loaded.\n";
        if (isset($APP_CONFIG['db']) && is_array($APP_CONFIG['db'])) {
            $db = $APP_CONFIG['db'];
            $host = $db['host'] ?? '(not set)';
            $name = $db['name'] ?? '(not set)';
            $user = $db['user'] ?? '(not set)';
            echo "  APP_CONFIG.db.host={$host}\n";
            echo "  APP_CONFIG.db.name={$name}\n";
            echo "  APP_CONFIG.db.user={$user}\n";
            echo "  APP_CONFIG.db.pass=***\n";
        }
    } else {
        echo "  APP_CONFIG not set or not array.\n";
    }
} catch (Throwable $t) {
    echo "  Error requiring config.php: " . $t->getMessage() . "\n";
}

echo "\nDiagnostics complete. Remove this file from production after use.\n";
