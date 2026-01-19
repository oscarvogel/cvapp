<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Verificación de Migración - Niveles</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .box { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .success { color: #10b981; font-weight: bold; }
        .error { color: #ef4444; font-weight: bold; }
        .warning { color: #f59e0b; font-weight: bold; }
        pre { background: #f8f8f8; padding: 10px; border-radius: 4px; overflow: auto; }
        h2 { color: #1f2937; border-bottom: 2px solid #3b82f6; padding-bottom: 10px; }
        .status-icon { font-size: 24px; margin-right: 10px; }
    </style>
</head>
<body>
    <h1>🔍 Verificación del Sistema de Niveles</h1>

<?php
require_once __DIR__ . '/init.php';

$problemas = [];
$warnings = [];

// 1. Verificar tabla niveles_especialidades
echo '<div class="box">';
echo '<h2>1. Tabla niveles_especialidades</h2>';
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'niveles_especialidades'");
    if ($stmt->rowCount() > 0) {
        echo '<p class="success"><span class="status-icon">✅</span>La tabla niveles_especialidades existe</p>';
        
        // Contar registros
        $stmt = $pdo->query("SELECT COUNT(*) FROM niveles_especialidades");
        $count = $stmt->fetchColumn();
        echo "<p>Total de niveles configurados: <strong>{$count}</strong></p>";
        
        if ($count === 0) {
            $warnings[] = "No hay niveles configurados. Ve a admin/areas.php para agregar niveles.";
        }
    } else {
        $problemas[] = "La tabla niveles_especialidades NO existe";
        echo '<p class="error"><span class="status-icon">❌</span>La tabla niveles_especialidades NO existe</p>';
        echo '<p><strong>Solución:</strong> Ejecuta el archivo <code>migracion_niveles_especialidades.sql</code></p>';
    }
} catch (Exception $e) {
    $problemas[] = "Error al verificar niveles_especialidades: " . $e->getMessage();
    echo '<p class="error"><span class="status-icon">❌</span>Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
}
echo '</div>';

// 2. Verificar columna nivel_id en candidato_especialidades
echo '<div class="box">';
echo '<h2>2. Columna nivel_id en candidato_especialidades</h2>';
try {
    $stmt = $pdo->query("DESCRIBE candidato_especialidades");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $nivel_id_exists = false;
    $nivel_exists = false;
    
    foreach ($columns as $col) {
        if ($col['Field'] === 'nivel_id') {
            $nivel_id_exists = true;
        }
        if ($col['Field'] === 'nivel') {
            $nivel_exists = true;
        }
    }
    
    if ($nivel_id_exists) {
        echo '<p class="success"><span class="status-icon">✅</span>La columna nivel_id existe (correcto)</p>';
    } else {
        $problemas[] = "La columna nivel_id NO existe en candidato_especialidades";
        echo '<p class="error"><span class="status-icon">❌</span>La columna nivel_id NO existe</p>';
        echo '<p><strong>Solución:</strong> Ejecuta el archivo <code>migracion_niveles_especialidades.sql</code></p>';
    }
    
    if ($nivel_exists) {
        $warnings[] = "Todavía existe la columna 'nivel' antigua (ENUM). Esto no es un error crítico pero deberías migrar los datos.";
        echo '<p class="warning"><span class="status-icon">⚠️</span>La columna nivel (antigua) todavía existe</p>';
        echo '<p>Esto significa que la migración no se completó correctamente.</p>';
    }
    
    echo '<h3>Estructura actual de candidato_especialidades:</h3>';
    echo '<pre>';
    foreach ($columns as $col) {
        echo "- {$col['Field']}: {$col['Type']}" . ($col['Null'] === 'YES' ? ' (NULL)' : ' (NOT NULL)') . "\n";
    }
    echo '</pre>';
    
} catch (Exception $e) {
    $problemas[] = "Error al verificar candidato_especialidades: " . $e->getMessage();
    echo '<p class="error"><span class="status-icon">❌</span>Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
}
echo '</div>';

// 3. Verificar datos de ejemplo
echo '<div class="box">';
echo '<h2>3. Datos de Especialidades (Ejemplo)</h2>';
try {
    $stmt = $pdo->query("
        SELECT 
            ce.id,
            ce.candidato_id,
            ce.especialidad_id,
            ce.nivel_id,
            ne.nombre as nivel_nombre,
            ea.nombre as especialidad_nombre
        FROM candidato_especialidades ce
        INNER JOIN especialidades_areas ea ON ce.especialidad_id = ea.id
        LEFT JOIN niveles_especialidades ne ON ce.nivel_id = ne.id
        LIMIT 5
    ");
    $datos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($datos) > 0) {
        echo '<p class="success"><span class="status-icon">✅</span>Hay ' . count($datos) . ' especialidades de candidatos</p>';
        echo '<table border="1" cellpadding="8" style="border-collapse: collapse; width: 100%;">';
        echo '<tr><th>Candidato ID</th><th>Especialidad</th><th>Nivel ID</th><th>Nivel Nombre</th></tr>';
        foreach ($datos as $row) {
            $nivel_status = $row['nivel_id'] ? 
                ($row['nivel_nombre'] ? '<span class="success">✓ ' . htmlspecialchars($row['nivel_nombre']) . '</span>' : '<span class="error">✗ ID inválido</span>') :
                '<span class="warning">Sin nivel</span>';
            echo '<tr>';
            echo '<td>' . $row['candidato_id'] . '</td>';
            echo '<td>' . htmlspecialchars($row['especialidad_nombre']) . '</td>';
            echo '<td>' . ($row['nivel_id'] ?: 'NULL') . '</td>';
            echo '<td>' . $nivel_status . '</td>';
            echo '</tr>';
        }
        echo '</table>';
        
        // Verificar si hay niveles NULL
        $stmt = $pdo->query("SELECT COUNT(*) FROM candidato_especialidades WHERE nivel_id IS NULL");
        $null_count = $stmt->fetchColumn();
        if ($null_count > 0) {
            $warnings[] = "Hay {$null_count} especialidades sin nivel asignado";
            echo "<p class=\"warning\"><span class=\"status-icon\">⚠️</span>Hay {$null_count} especialidades sin nivel asignado</p>";
        }
    } else {
        echo '<p class="warning"><span class="status-icon">⚠️</span>No hay especialidades de candidatos registradas aún</p>';
    }
} catch (Exception $e) {
    $problemas[] = "Error al consultar datos: " . $e->getMessage();
    echo '<p class="error"><span class="status-icon">❌</span>Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
}
echo '</div>';

// 4. Niveles disponibles
echo '<div class="box">';
echo '<h2>4. Niveles Disponibles por Especialidad</h2>';
try {
    $stmt = $pdo->query("
        SELECT 
            ea.nombre as especialidad,
            ne.nombre as nivel,
            ne.orden,
            ne.activo
        FROM niveles_especialidades ne
        INNER JOIN especialidades_areas ea ON ne.especialidad_id = ea.id
        ORDER BY ea.nombre, ne.orden
    ");
    $niveles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($niveles) > 0) {
        echo '<p class="success"><span class="status-icon">✅</span>Hay ' . count($niveles) . ' niveles configurados</p>';
        
        $especialidad_actual = '';
        foreach ($niveles as $nivel) {
            if ($especialidad_actual !== $nivel['especialidad']) {
                if ($especialidad_actual !== '') echo '</ul>';
                echo '<h4 style="margin-top: 15px; color: #1f2937;">' . htmlspecialchars($nivel['especialidad']) . '</h4>';
                echo '<ul>';
                $especialidad_actual = $nivel['especialidad'];
            }
            $status = $nivel['activo'] ? '✓' : '✗';
            $style = $nivel['activo'] ? 'color: #10b981;' : 'color: #ef4444;';
            echo '<li style="' . $style . '">' . $status . ' ' . htmlspecialchars($nivel['nivel']) . ' (orden: ' . $nivel['orden'] . ')</li>';
        }
        echo '</ul>';
    } else {
        echo '<p class="warning"><span class="status-icon">⚠️</span>No hay niveles configurados</p>';
        echo '<p><strong>Acción requerida:</strong> Ve a <a href="admin/areas.php">admin/areas.php</a> para configurar niveles</p>';
    }
} catch (Exception $e) {
    echo '<p class="error"><span class="status-icon">❌</span>Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
}
echo '</div>';

// Resumen final
echo '<div class="box" style="background: ' . (empty($problemas) ? '#d1fae5' : '#fee2e2') . ';">';
echo '<h2>📊 Resumen Final</h2>';

if (empty($problemas)) {
    echo '<p class="success" style="font-size: 18px;"><span class="status-icon">✅</span>¡Sistema correctamente configurado!</p>';
    
    if (!empty($warnings)) {
        echo '<h3 style="color: #f59e0b;">Advertencias:</h3>';
        echo '<ul>';
        foreach ($warnings as $warning) {
            echo '<li class="warning">' . htmlspecialchars($warning) . '</li>';
        }
        echo '</ul>';
    }
} else {
    echo '<p class="error" style="font-size: 18px;"><span class="status-icon">❌</span>Se encontraron problemas:</p>';
    echo '<ol>';
    foreach ($problemas as $problema) {
        echo '<li class="error">' . htmlspecialchars($problema) . '</li>';
    }
    echo '</ol>';
    
    echo '<div style="margin-top: 20px; padding: 15px; background: white; border-left: 4px solid #3b82f6;">';
    echo '<h3 style="margin-top: 0;">🔧 Pasos para Solucionar:</h3>';
    echo '<ol>';
    echo '<li>Accede a phpMyAdmin o tu cliente MySQL</li>';
    echo '<li>Selecciona tu base de datos</li>';
    echo '<li>Importa el archivo: <code>migracion_niveles_especialidades.sql</code></li>';
    echo '<li>Recarga esta página para verificar</li>';
    echo '</ol>';
    echo '<p><strong>O ejecuta desde terminal:</strong></p>';
    echo '<pre>mysql -u usuario -p nombre_base_datos < migracion_niveles_especialidades.sql</pre>';
    echo '</div>';
}
echo '</div>';
?>

<div style="text-align: center; margin-top: 30px; padding: 20px; background: white; border-radius: 8px;">
    <p><a href="admin/areas.php" style="display: inline-block; background: #3b82f6; color: white; padding: 10px 20px; text-decoration: none; border-radius: 6px; font-weight: bold;">Ir a Gestión de Áreas</a></p>
    <p style="margin-top: 10px;"><a href="admin/candidato-detalle.php?id=1" style="color: #3b82f6;">Ver Detalle de Candidato (ID: 1)</a></p>
</div>

</body>
</html>
