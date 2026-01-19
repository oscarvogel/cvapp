<?php
require_once __DIR__ . '/init.php';

header('Content-Type: application/json');

$especialidad_id = safe_int($_GET['especialidad_id'] ?? 0);

if ($especialidad_id <= 0) {
    echo json_encode([]);
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT id, nombre, descripcion, orden FROM niveles_especialidades WHERE especialidad_id = ? AND activo = 1 ORDER BY orden, nombre');
    $stmt->execute([$especialidad_id]);
    $niveles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($niveles);
} catch (Exception $e) {
    error_log("Error al obtener niveles: " . $e->getMessage());
    echo json_encode([]);
}
?>
