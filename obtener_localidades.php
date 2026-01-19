<?php
require_once __DIR__ . '/init.php';

header('Content-Type: application/json; charset=utf-8');

$q = trim($_GET['q'] ?? '');
if ($q === '') {
    echo json_encode([]);
    exit;
}

// Buscar por nombre o provincia (case-insensitive)
$param = '%' . $q . '%';
$stmt = $pdo->prepare("SELECT id, nombre, provincia FROM localidades_nea WHERE nombre LIKE :q1 OR provincia LIKE :q2 ORDER BY nombre LIMIT 20");
$stmt->execute([':q1' => $param, ':q2' => $param]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$out = [];
foreach ($rows as $r) {
    $out[] = [
        'id' => $r['id'],
        'nombre' => $r['nombre'],
        'provincia' => $r['provincia'],
        'label' => trim($r['nombre'] . ', ' . $r['provincia'])
    ];
}

echo json_encode($out, JSON_UNESCAPED_UNICODE);
