<?php
require_once __DIR__ . '/../init.php';
require_admin();

$id = safe_int($_GET['id'] ?? null, 0);
if ($id <= 0) {
    http_response_code(400);
    exit('Solicitud inválida');
}

$stmt = $pdo->prepare('SELECT cv_nombre_original, cv_ruta FROM candidatos WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$row = $stmt->fetch();
if (!$row) {
    http_response_code(404);
    exit('No encontrado');
}

$fullPath = $APP_CONFIG['uploads_dir'] . DIRECTORY_SEPARATOR . $row['cv_ruta'];
if (!is_file($fullPath)) {
    http_response_code(404);
    exit('Archivo no existe');
}

$original = $row['cv_nombre_original'];
$downloadName = str_replace(['"', "\r", "\n"], '', $original);
$filesize = filesize($fullPath);

header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $downloadName . '"; filename*=UTF-8\'\'' . rawurlencode($downloadName));
header('Content-Length: ' . $filesize);
header('Cache-Control: no-cache');

readfile($fullPath);
exit;
