<?php
/**
 * Script para ejecutar la migración de email opcional - versión simplificada
 */

require_once __DIR__ . '/init.php';

try {
    global $pdo;
    
    echo "========================================\n";
    echo "Migración: Email Opcional y DNI Único\n";
    echo "========================================\n\n";
    
    // 1. Eliminar índice de email si existe
    echo "1. Eliminando índice idx_email (si existe)...\n";
    try {
        $pdo->exec("DROP INDEX idx_email ON candidatos");
        echo "   ✓ Índice eliminado\n\n";
    } catch (PDOException $e) {
        echo "   ⚠ Índice no existe o ya fue eliminado\n\n";
    }
    
    // 2. Modificar columna email para permitir NULL
    echo "2. Modificando columna email para permitir NULL...\n";
    $pdo->exec("ALTER TABLE candidatos MODIFY COLUMN email VARCHAR(150) NULL");
    echo "   ✓ Columna email ahora permite NULL\n\n";
    
    // 3. Eliminar índice actual de DNI
    echo "3. Eliminando índice actual de DNI...\n";
    try {
        $pdo->exec("DROP INDEX idx_dni ON candidatos");
        echo "   ✓ Índice eliminado\n\n";
    } catch (PDOException $e) {
        echo "   ⚠ Índice no existe\n\n";
    }
    
    // 4. Crear índice único en DNI
    echo "4. Creando índice ÚNICO en DNI...\n";
    $pdo->exec("CREATE UNIQUE INDEX idx_dni ON candidatos(dni)");
    echo "   ✓ Índice único creado\n\n";
    
    echo "========================================\n";
    echo "✓ Migración completada exitosamente\n";
    echo "========================================\n\n";
    
    // Verificar cambios
    echo "Verificando cambios en la tabla candidatos...\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM candidatos LIKE 'email'");
    $email_column = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "Columna email:\n";
    echo "  - Tipo: {$email_column['Type']}\n";
    echo "  - Permite NULL: {$email_column['Null']}\n";
    echo "  - Key: {$email_column['Key']}\n\n";
    
    echo "Verificando índices en DNI...\n";
    $stmt = $pdo->query("SHOW INDEXES FROM candidatos WHERE Column_name = 'dni'");
    $dni_indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Índices en DNI:\n";
    foreach ($dni_indexes as $idx) {
        $unique = $idx['Non_unique'] == 0 ? 'SÍ' : 'NO';
        echo "  - Nombre: {$idx['Key_name']}\n";
        echo "    Único: {$unique}\n\n";
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
