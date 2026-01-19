<?php
// ARCHIVO TEMPORAL DE DEBUG
// Mostrar todos los errores en pantalla
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

echo "<!DOCTYPE html>";
echo "<html><head><title>Debug Upload</title>";
echo "<style>body{font-family:monospace;background:#1e1e1e;color:#00ff00;padding:20px;}";
echo "h2{color:#ffff00;}pre{background:#2d2d2d;padding:10px;border-left:4px solid #00ff00;}</style>";
echo "</head><body>";

echo "<h2>🔍 DEBUG - Datos del Formulario</h2>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h3>✅ Método POST recibido</h3>";
    
    echo "<h3>📋 Datos POST completos:</h3>";
    echo "<pre>" . print_r($_POST, true) . "</pre>";
    
    echo "<h3>📁 Archivos recibidos:</h3>";
    echo "<pre>" . print_r($_FILES, true) . "</pre>";
    
    echo "<h3>🔑 Claves POST:</h3>";
    echo "<pre>" . print_r(array_keys($_POST), true) . "</pre>";
    
    if (isset($_POST['experiencia'])) {
        echo "<h3>💼 Experiencias Laborales:</h3>";
        echo "<pre>" . print_r($_POST['experiencia'], true) . "</pre>";
    }
    
    echo "<h3>🔗 Intentar procesar con upload.php:</h3>";
    echo "<p style='color:#ffaa00'>Redirige este formulario a upload.php para ver los logs...</p>";
    
} else {
    echo "<h3>❌ No hay datos POST</h3>";
    echo "<p>Método actual: " . $_SERVER['REQUEST_METHOD'] . "</p>";
}

// Mostrar errores de sesión si existen
if (isset($_SESSION['form_errors'])) {
    echo "<h3>⚠️ Errores de sesión:</h3>";
    echo "<pre>" . print_r($_SESSION['form_errors'], true) . "</pre>";
}

if (isset($_SESSION['form_data'])) {
    echo "<h3>📝 Datos de formulario en sesión:</h3>";
    echo "<pre>" . print_r($_SESSION['form_data'], true) . "</pre>";
}

echo "<hr><p style='color:#888'>Este archivo es temporal para debugging. Elimínalo cuando termines.</p>";
echo "</body></html>";
?>
