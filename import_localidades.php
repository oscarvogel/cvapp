<?php
require_once __DIR__ . '/init.php';

$csvFile = __DIR__ . '/localidades_nea.csv';
if (!file_exists($csvFile)) {
    echo "CSV file not found: $csvFile\n";
    exit(1);
}

// Crear tabla si no existe
$createSql = "CREATE TABLE IF NOT EXISTS localidades_nea (
    id VARCHAR(50) PRIMARY KEY,
    provincia VARCHAR(255),
    nombre VARCHAR(255),
    lat DOUBLE,
    lon DOUBLE
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
$pdo->exec($createSql);

// Importar CSV
if (($handle = fopen($csvFile, 'r')) === false) {
    echo "No se pudo abrir el archivo CSV\n";
    exit(1);
}

// Leer encabezado
$header = fgetcsv($handle);

$pdo->beginTransaction();
$insert = $pdo->prepare("INSERT INTO localidades_nea (id, provincia, nombre, lat, lon) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE provincia=VALUES(provincia), nombre=VALUES(nombre), lat=VALUES(lat), lon=VALUES(lon)");
$count = 0;
while (($row = fgetcsv($handle)) !== false) {
    // Esperamos 5 columnas: id, provincia, nombre, lat, lon
    if (count($row) < 3) continue;
    $id = $row[0];
    $prov = $row[1] ?? null;
    $nombre = $row[2] ?? null;
    $lat = isset($row[3]) && $row[3] !== '' ? $row[3] : null;
    $lon = isset($row[4]) && $row[4] !== '' ? $row[4] : null;
    $insert->execute([$id, $prov, $nombre, $lat, $lon]);
    $count++;
}

$pdo->commit();
fclose($handle);

echo "Import completed. Rows processed: $count\n";
