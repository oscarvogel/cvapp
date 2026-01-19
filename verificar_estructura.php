<?php
require_once __DIR__ . '/init.php';

echo "<h1>Verificación de Estructura de Base de Datos</h1>";

try {
    // Verificar columnas de la tabla candidatos
    $stmt = $pdo->query("DESCRIBE candidatos");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Columnas de la tabla 'candidatos':</h2>";
    echo "<table border='1' style='border-collapse: collapse; margin: 10px;'>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Nulo</th><th>Clave</th><th>Por defecto</th></tr>";
    
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($column['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Default']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Verificar tabla candidato_areas
    echo "<h2>Tabla candidato_areas:</h2>";
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM candidato_areas");
    $count = $stmt->fetch();
    echo "<p>Total de relaciones candidato-área: " . $count['total'] . "</p>";
    
    // Verificar áreas profesionales
    echo "<h2>Áreas Profesionales:</h2>";
    $stmt = $pdo->query("SELECT * FROM areas_profesionales WHERE activa = 1 ORDER BY orden, nombre");
    $areas = $stmt->fetchAll();
    
    echo "<ul>";
    foreach ($areas as $area) {
        echo "<li>" . htmlspecialchars($area['nombre']) . " (ID: " . $area['id'] . ")</li>";
    }
    echo "</ul>";
    
    echo "<h3 style='color: green;'>✅ Estructura verificada correctamente</h3>";
    
} catch (Exception $e) {
    echo "<h3 style='color: red;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</h3>";
}
?>