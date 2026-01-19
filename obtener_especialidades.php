<?php
require_once __DIR__ . '/init.php';

header('Content-Type: application/json');

$area_id = safe_int($_GET['area_id'] ?? 0);

if ($area_id <= 0) {
    echo json_encode([]);
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT id, nombre, tipo_seleccion FROM especialidades_areas WHERE area_profesional_id = ? AND activa = 1 ORDER BY orden, nombre');
    $stmt->execute([$area_id]);
    $especialidades = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($especialidades);
} catch (Exception $e) {
    error_log("Error al obtener especialidades: " . $e->getMessage());
    echo json_encode([]);
}
?>