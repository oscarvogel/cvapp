<?php
declare(strict_types=1);

// Simple DB connection test that uses config.php (which reads .env)
require_once __DIR__ . '/config.php';

// Expect $APP_CONFIG to be defined by config.php
if (!isset($APP_CONFIG['db'])) {
    echo "ERROR: configuración de DB no encontrada en \$APP_CONFIG\n";
    exit(1);
}

$db = $APP_CONFIG['db'];
$host = $db['host'] ?? 'localhost';
$name = $db['name'] ?? '';
$user = $db['user'] ?? '';
$pass = $db['pass'] ?? '';
$charset = $db['charset'] ?? 'utf8mb4';

$dsn = "mysql:host={$host};dbname={$name};charset={$charset}";

echo "Probando conexión a la base de datos...\n";
echo "DSN: {$dsn}\n";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    echo "Conexión OK. Versión MySQL: " . $pdo->getAttribute(PDO::ATTR_SERVER_VERSION) . "\n";
    exit(0);
} catch (PDOException $e) {
    echo "ERROR: No se pudo conectar a la base de datos:\n";
    echo $e->getMessage() . "\n";
    exit(2);
}
