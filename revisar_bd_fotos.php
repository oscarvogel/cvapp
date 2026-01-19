<?php
// Revisar estructura de la tabla candidatos

echo "=== REVISIÓN ESTRUCTURA BD ===\n\n";

require_once __DIR__ . '/config.php';

try {
    $pdo = new PDO(
        "mysql:host={$APP_CONFIG['db']['host']};dbname={$APP_CONFIG['db']['name']};charset={$APP_CONFIG['db']['charset']}",
        $APP_CONFIG['db']['user'],
        $APP_CONFIG['db']['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    echo "✅ Conectado a la base de datos\n\n";
    
    // Verificar estructura de tabla candidatos
    echo "1. Estructura de tabla candidatos:\n";
    $stmt = $pdo->prepare("DESCRIBE candidatos");
    $stmt->execute();
    $columnas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($columnas as $columna) {
        echo "   - {$columna['Field']} ({$columna['Type']})";
        if ($columna['Null'] == 'NO') echo " NOT NULL";
        if (!empty($columna['Default'])) echo " DEFAULT '{$columna['Default']}'";
        echo "\n";
    }
    
    echo "\n2. Candidatos con archivos (primeros 5):\n";
    
    // Buscar candidatos - ajustar nombres de columnas
    $columnas_disponibles = array_column($columnas, 'Field');
    
    $nombre_col = in_array('nombres', $columnas_disponibles) ? 'nombres' : 
                  (in_array('nombre', $columnas_disponibles) ? 'nombre' : 'id');
    $apellido_col = in_array('apellidos', $columnas_disponibles) ? 'apellidos' : 
                   (in_array('apellido', $columnas_disponibles) ? 'apellido' : 'id');
    
    $stmt = $pdo->prepare("SELECT id, $nombre_col, $apellido_col, foto_ruta FROM candidatos WHERE foto_ruta IS NOT NULL AND foto_ruta != '' LIMIT 5");
    $stmt->execute();
    $candidatos_con_foto = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($candidatos_con_foto)) {
        echo "⚠️  No hay candidatos con foto_ruta\n";
        
        // Mostrar algunos candidatos
        $stmt = $pdo->prepare("SELECT id, $nombre_col, $apellido_col, foto_ruta FROM candidatos LIMIT 3");
        $stmt->execute();
        $todos_candidatos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "\nPrimeros candidatos:\n";
        foreach ($todos_candidatos as $candidato) {
            echo "   ID: {$candidato['id']} - Foto: " . ($candidato['foto_ruta'] ?: 'NULL') . "\n";
        }
        
    } else {
        echo "✅ Candidatos con foto:\n";
        foreach ($candidatos_con_foto as $candidato) {
            echo "   ID: {$candidato['id']} - Foto: {$candidato['foto_ruta']}\n";
        }
    }
    
    echo "\n3. Archivos en uploads vs BD:\n";
    
    $uploads_dir = $APP_CONFIG['uploads_dir'];
    $archivos_uploads = [];
    
    if (is_dir($uploads_dir)) {
        foreach (glob($uploads_dir . '/*') as $archivo) {
            if (is_file($archivo)) {
                $nombre = basename($archivo);
                $ext = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'bmp'])) {
                    $archivos_uploads[] = $nombre;
                }
            }
        }
    }
    
    echo "Archivos de imagen en uploads: " . count($archivos_uploads) . "\n";
    foreach ($archivos_uploads as $archivo) {
        echo "   📸 $archivo\n";
        
        // Verificar si algún candidato usa esta foto
        $stmt = $pdo->prepare("SELECT id, $nombre_col FROM candidatos WHERE foto_ruta = ? OR foto_ruta = ?");
        $stmt->execute([$archivo, 'uploads/' . $archivo]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($usuario) {
            echo "      ✅ Usado por candidato ID: {$usuario['id']}\n";
        } else {
            echo "      ⚠️  No asignado a ningún candidato\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== FIN REVISIÓN ===\n";
?>