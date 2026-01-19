<?php
declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');
echo "ENV RAW DIAGNOSTIC\n===================\n\n";
$root = __DIR__;
$file = $root . '/.env';
echo "Path: {$file}\n";
if (!file_exists($file)) {
    echo ".env: NOT FOUND\n";
    exit(0);
}

$stat = @stat($file);
echo ".env size: " . filesize($file) . " bytes\n";
echo ".env perms (oct): " . sprintf('%o', fileperms($file) & 0777) . "\n";
if ($stat && isset($stat['uid']) && function_exists('posix_getpwuid')) {
    $pw = posix_getpwuid($stat['uid']);
    echo ".env owner: " . ($pw['name'] ?? $stat['uid']) . " (uid={$stat['uid']})\n";
}
echo "\nfile_get_contents(.env):\n";
$content = @file_get_contents($file);
if ($content === false) {
    echo "(unable to read .env)\n";
} else {
    // show raw with visible newlines and hex of first bytes
    $first = substr($content, 0, 4);
    echo "first bytes (hex): " . bin2hex($first) . "\n\n";
    // show content with line numbers
    $lines = explode("\n", $content);
    foreach ($lines as $i => $ln) {
        $n = $i + 1;
        echo sprintf("%03d: %s\n", $n, $ln);
    }
}

echo "\ngetenv('DB_HOST') => ";
$g = getenv('DB_HOST');
var_export($g);
echo "\nfunction_exists getenv/putenv: getenv=" . (function_exists('getenv') ? 'yes' : 'no') . ", putenv=" . (function_exists('putenv') ? 'yes' : 'no') . "\n";
echo "\nAttempting to require config.php to trigger .env parsing fallback...\n";
try {
    require_once __DIR__ . '/config.php';
    echo "require config.php: OK\n";
    if (isset($APP_CONFIG['db'])) {
        $db = $APP_CONFIG['db'];
        echo "APP_CONFIG.db.host=" . ($db['host'] ?? '(not set)') . "\n";
        echo "APP_CONFIG.db.name=" . ($db['name'] ?? '(not set)') . "\n";
        echo "APP_CONFIG.db.user=" . ($db['user'] ?? '(not set)') . "\n";
        echo "APP_CONFIG.db.pass=***\n";
    } else {
        echo "APP_CONFIG.db not set.\n";
    }
    $g2 = getenv('DB_HOST');
    echo "getenv('DB_HOST') after requiring config.php => "; var_export($g2); echo "\n";
} catch (Throwable $t) {
    echo "Error requiring config.php: " . $t->getMessage() . "\n";
}

echo "\nDone. Remove this file after diagnosis.\n";
