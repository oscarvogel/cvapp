<?php
// Script de debug para verificar qué error está ocurriendo en la generación del PDF

error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "<h2>Debug - Generación de PDF</h2>";

// 1. Verificar ID del candidato
if (!isset($_GET['id'])) {
    die("ERROR: No se proporcionó ID de candidato. Usar: debug_pdf.php?id=XX");
}

$candidato_id = (int) $_GET['id'];
echo "<p>✓ ID de candidato: $candidato_id</p>";

// 2. Verificar que init.php funciona
try {
    require_once __DIR__ . '/../init.php';
    echo "<p>✓ init.php cargado correctamente</p>";
} catch (Exception $e) {
    die("<p>✗ ERROR en init.php: " . $e->getMessage() . "</p>");
}

// 3. Verificar que tenemos conexión a la base de datos
try {
    $test_query = $pdo->query("SELECT 1");
    echo "<p>✓ Conexión a base de datos OK</p>";
} catch (Exception $e) {
    die("<p>✗ ERROR de conexión a BD: " . $e->getMessage() . "</p>");
}

// 4. Verificar que el candidato existe
try {
    $stmt = $pdo->prepare("SELECT id, nombre FROM candidatos WHERE id = ?");
    $stmt->execute([$candidato_id]);
    $candidato = $stmt->fetch();
    
    if (!$candidato) {
        die("<p>✗ ERROR: Candidato con ID $candidato_id no existe</p>");
    }
    echo "<p>✓ Candidato encontrado: {$candidato['nombre']}</p>";
} catch (Exception $e) {
    die("<p>✗ ERROR al buscar candidato: " . $e->getMessage() . "</p>");
}

// 5. Verificar que vendor/autoload.php existe
$autoload_path = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload_path)) {
    die("<p>✗ ERROR: vendor/autoload.php no existe en: $autoload_path</p>");
}
echo "<p>✓ vendor/autoload.php existe</p>";

// 6. Cargar TCPDF
try {
    require_once $autoload_path;
    echo "<p>✓ vendor/autoload.php cargado</p>";
} catch (Exception $e) {
    die("<p>✗ ERROR al cargar vendor/autoload.php: " . $e->getMessage() . "</p>");
}

// 7. Verificar que TCPDF está disponible
if (!class_exists('TCPDF')) {
    die("<p>✗ ERROR: La clase TCPDF no existe</p>");
}
echo "<p>✓ Clase TCPDF disponible</p>";

// 8. Intentar crear instancia de TCPDF
try {
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    echo "<p>✓ Instancia de TCPDF creada correctamente</p>";
} catch (Exception $e) {
    die("<p>✗ ERROR al crear instancia TCPDF: " . $e->getMessage() . "</p>");
}

// 9. Verificar directorio de cache de TCPDF
$tcpdf_cache = K_PATH_CACHE;
if (!is_dir($tcpdf_cache)) {
    echo "<p>⚠ Advertencia: Directorio de cache TCPDF no existe: $tcpdf_cache</p>";
    echo "<p>Intentando crear directorio...</p>";
    try {
        if (!mkdir($tcpdf_cache, 0755, true)) {
            echo "<p>✗ No se pudo crear el directorio de cache</p>";
        } else {
            echo "<p>✓ Directorio de cache creado</p>";
        }
    } catch (Exception $e) {
        echo "<p>✗ Error al crear directorio: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p>✓ Directorio de cache TCPDF existe: $tcpdf_cache</p>";
}

// 10. Verificar permisos de escritura
if (!is_writable($tcpdf_cache)) {
    echo "<p>⚠ Advertencia: Directorio de cache no es escribible</p>";
} else {
    echo "<p>✓ Directorio de cache es escribible</p>";
}

// 11. Probar generación de PDF simple
echo "<h3>Prueba de generación de PDF simple</h3>";
try {
    $pdf->SetCreator('Test');
    $pdf->SetAuthor('Test');
    $pdf->SetTitle('Test PDF');
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(TRUE, 25);
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 10, 'Prueba de PDF - Todo funciona correctamente', 0, 1);
    
    echo "<p>✓ PDF preparado correctamente</p>";
    echo "<p><strong>Todo parece estar OK. El problema podría estar en:</strong></p>";
    echo "<ul>";
    echo "<li>Alguna consulta SQL específica en generar_pdf.php</li>";
    echo "<li>Imagen de foto del candidato que no se puede cargar</li>";
    echo "<li>Logo de empresa que no se puede cargar</li>";
    echo "<li>Timeout por exceso de datos</li>";
    echo "</ul>";
    
    echo "<p><a href='generar_pdf.php?id=$candidato_id' target='_blank'>Intentar generar PDF real ahora</a></p>";
    
} catch (Exception $e) {
    echo "<p>✗ ERROR en prueba de PDF: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<hr>";
echo "<p>Revisar también el log de errores de PHP en el servidor para más información.</p>";
?>
