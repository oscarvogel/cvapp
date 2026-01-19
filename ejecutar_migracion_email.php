<?php
/**
 * Script para ejecutar la migración de email opcional
 * Ejecutar desde línea de comandos: php ejecutar_migracion_email.php
 */

require_once __DIR__ . '/init.php';

try {
    global $pdo;
    
    echo "========================================\n";
    echo "Migración: Email Opcional y DNI Único\n";
    echo "========================================\n\n";
    
    // Leer el archivo SQL
    $sql_file = __DIR__ . '/migracion_email_opcional.sql';
    
    if (!file_exists($sql_file)) {
        die("ERROR: No se encuentra el archivo de migración: {$sql_file}\n");
    }
    
    $sql = file_get_contents($sql_file);
    
    echo "Ejecutando migración...\n\n";
    
    // Ejecutar cada statement por separado
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($stmt) {
            return !empty($stmt) && !preg_match('/^--/', $stmt);
        }
    );
    
    foreach ($statements as $statement) {
        if (empty($statement)) continue;
        
        // Mostrar statement (primeras 100 caracteres)
        $preview = substr($statement, 0, 100);
        echo "Ejecutando: " . str_replace(["\n", "\r"], ' ', $preview) . "...\n";
        
        try {
            $pdo->exec($statement);
            echo "✓ OK\n\n";
        } catch (PDOException $e) {
            // Algunos errores son esperados (como DROP INDEX si no existe)
            if (strpos($e->getMessage(), "check that it exists") !== false || 
                strpos($e->getMessage(), "Can't DROP") !== false) {
                echo "⚠ Advertencia (ignorada): {$e->getMessage()}\n\n";
            } else {
                throw $e;
            }
        }
    }
    
    echo "========================================\n";
    echo "✓ Migración completada exitosamente\n";
    echo "========================================\n\n";
    
    // Verificar cambios
    echo "Verificando cambios en la tabla candidatos...\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM candidatos LIKE 'email'");
    $email_column = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "Columna email:\n";
    echo "  - Tipo: {$email_column['Type']}\n";
    echo "  - NULL: {$email_column['Null']}\n";
    echo "  - Key: {$email_column['Key']}\n\n";
    
    echo "Verificando índices...\n";
    $stmt = $pdo->query("SHOW INDEXES FROM candidatos WHERE Column_name = 'dni'");
    $dni_indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Índices en DNI:\n";
    foreach ($dni_indexes as $idx) {
        echo "  - Nombre: {$idx['Key_name']}\n";
        echo "    No único: {$idx['Non_unique']}\n\n";
    }
    
} catch (Exception $e) {
    echo "\n========================================\n";
    echo "✗ ERROR en la migración\n";
    echo "========================================\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "Archivo: " . $e->getFile() . "\n";
    echo "Línea: " . $e->getLine() . "\n";
    exit(1);
}
?>
