<?php
require_once __DIR__ . '/../init.php';
require_admin();

$id = safe_int($_GET['id'] ?? null, 0);
if ($id <= 0) {
    // Mostrar placeholder
    showPlaceholder();
    exit;
}

$stmt = $pdo->prepare('SELECT foto_nombre_original, foto_ruta FROM candidatos WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$row = $stmt->fetch();
if (!$row || empty($row['foto_ruta'])) {
    // Mostrar placeholder si no hay registro o no hay foto
    showPlaceholder();
    exit;
}

$fullPath = $APP_CONFIG['uploads_dir'] . DIRECTORY_SEPARATOR . $row['foto_ruta'];
if (!is_file($fullPath)) {
    // Mostrar placeholder si el archivo no existe
    showPlaceholder();
    exit;
}

$ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
$mime_types = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png'
];

$mime = $mime_types[$ext] ?? 'application/octet-stream';

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($fullPath));
header('Cache-Control: private, max-age=3600');

readfile($fullPath);
exit;

function showPlaceholder() {
    // Obtener iniciales del nombre si está disponible
    $nombre = $_GET['nombre'] ?? '';
    $iniciales = '';
    if ($nombre) {
        $palabras = explode(' ', trim($nombre));
        $iniciales = strtoupper(substr($palabras[0], 0, 1));
        if (count($palabras) > 1) {
            $iniciales .= strtoupper(substr($palabras[1], 0, 1));
        }
    } else {
        $iniciales = '?';
    }
    
    // Crear una imagen SVG como placeholder con iniciales
    $svg = '<?xml version="1.0" encoding="UTF-8"?>
    <svg width="96" height="96" viewBox="0 0 96 96" fill="none" xmlns="http://www.w3.org/2000/svg">
        <rect width="96" height="96" rx="48" fill="#E5E7EB"/>
        <text x="48" y="56" text-anchor="middle" fill="#6B7280" font-family="Arial, sans-serif" font-size="24" font-weight="600">' . $iniciales . '</text>
    </svg>';
    
    header('Content-Type: image/svg+xml');
    header('Cache-Control: private, max-age=300'); // Cache por 5 minutos
    echo $svg;
}
?>