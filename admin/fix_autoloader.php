<?php
/**
 * Script para verificar y regenerar el autoloader de Composer en el servidor
 */

echo "<h2>Verificación y Reparación del Autoloader de Composer</h2>";

$vendor_dir = __DIR__ . '/../vendor';
$autoload_file = $vendor_dir . '/autoload.php';
$composer_dir = $vendor_dir . '/composer';

echo "<h3>1. Verificando estructura de directorios</h3>";

if (!is_dir($vendor_dir)) {
    die("<p style='color:red'>✗ ERROR: No existe el directorio vendor/</p>");
}
echo "<p style='color:green'>✓ Directorio vendor/ existe</p>";

if (!is_dir($composer_dir)) {
    die("<p style='color:red'>✗ ERROR: No existe el directorio vendor/composer/</p>");
}
echo "<p style='color:green'>✓ Directorio vendor/composer/ existe</p>";

echo "<h3>2. Verificando archivos del autoloader</h3>";

$archivos_requeridos = [
    'autoload.php' => $autoload_file,
    'composer/autoload_real.php' => $composer_dir . '/autoload_real.php',
    'composer/autoload_static.php' => $composer_dir . '/autoload_static.php',
    'composer/ClassLoader.php' => $composer_dir . '/ClassLoader.php',
    'composer/autoload_classmap.php' => $composer_dir . '/autoload_classmap.php',
    'composer/autoload_namespaces.php' => $composer_dir . '/autoload_namespaces.php',
    'composer/autoload_psr4.php' => $composer_dir . '/autoload_psr4.php',
];

$archivos_faltantes = [];
foreach ($archivos_requeridos as $nombre => $ruta) {
    if (!file_exists($ruta)) {
        echo "<p style='color:red'>✗ Falta: $nombre</p>";
        $archivos_faltantes[] = $nombre;
    } else {
        $size = filesize($ruta);
        echo "<p style='color:green'>✓ Existe: $nombre (tamaño: $size bytes)</p>";
        
        // Verificar que no esté vacío o corrupto
        if ($size < 10) {
            echo "<p style='color:orange'>⚠ Advertencia: Archivo muy pequeño, podría estar corrupto</p>";
        }
    }
}

echo "<h3>3. Intentando cargar autoloader</h3>";

if (!empty($archivos_faltantes)) {
    echo "<p style='color:red'>✗ No se puede probar el autoloader porque faltan archivos</p>";
    echo "<h3>Solución</h3>";
    echo "<p>Debes subir los siguientes archivos desde tu instalación local:</p>";
    echo "<ul>";
    foreach ($archivos_faltantes as $archivo) {
        echo "<li>$archivo</li>";
    }
    echo "</ul>";
} else {
    try {
        // Leer el contenido de autoload.php para ver qué clase espera
        $autoload_content = file_get_contents($autoload_file);
        
        echo "<h4>Contenido de autoload.php:</h4>";
        echo "<pre style='background:#f0f0f0;padding:10px;overflow:auto;max-height:300px'>";
        echo htmlspecialchars($autoload_content);
        echo "</pre>";
        
        // Intentar extraer el nombre de la clase
        if (preg_match('/return\s+(\w+)::getLoader/', $autoload_content, $matches)) {
            $clase_esperada = $matches[1];
            echo "<p>Clase del autoloader esperada: <strong>$clase_esperada</strong></p>";
            
            // Verificar si existe en autoload_real.php
            $autoload_real_content = file_get_contents($composer_dir . '/autoload_real.php');
            if (strpos($autoload_real_content, "class $clase_esperada") !== false) {
                echo "<p style='color:green'>✓ La clase existe en autoload_real.php</p>";
            } else {
                echo "<p style='color:red'>✗ ERROR: La clase NO existe en autoload_real.php</p>";
                echo "<h4>Contenido de autoload_real.php:</h4>";
                echo "<pre style='background:#f0f0f0;padding:10px;overflow:auto;max-height:300px'>";
                echo htmlspecialchars($autoload_real_content);
                echo "</pre>";
            }
        }
        
        // Intentar cargar
        ob_start();
        $loader = require $autoload_file;
        $output = ob_get_clean();
        
        if ($output) {
            echo "<p>Salida durante la carga:</p>";
            echo "<pre>$output</pre>";
        }
        
        if ($loader && is_object($loader)) {
            echo "<p style='color:green;font-size:18px;font-weight:bold'>✓✓✓ AUTOLOADER CARGADO EXITOSAMENTE ✓✓✓</p>";
            echo "<p>Tipo de objeto: " . get_class($loader) . "</p>";
            
            // Verificar si TCPDF está disponible
            if (class_exists('TCPDF')) {
                echo "<p style='color:green;font-size:16px'>✓ TCPDF está disponible y funcionando</p>";
                echo "<p><strong>El problema del PDF debería estar resuelto ahora.</strong></p>";
                echo "<p><a href='test_candidatos.php'>Ir a probar la generación de PDF</a></p>";
            } else {
                echo "<p style='color:orange'>⚠ TCPDF no está disponible. Puede que no esté instalado.</p>";
                echo "<p>Ejecuta: <code>composer require tecnickcom/tcpdf</code></p>";
            }
        } else {
            echo "<p style='color:red'>✗ El autoloader no devolvió un objeto válido</p>";
        }
        
    } catch (Exception $e) {
        echo "<p style='color:red'>✗ ERROR al cargar autoloader:</p>";
        echo "<pre style='background:#ffe0e0;padding:10px;border:1px solid red'>";
        echo htmlspecialchars($e->getMessage());
        echo "\n\nStack trace:\n";
        echo htmlspecialchars($e->getTraceAsString());
        echo "</pre>";
    } catch (Error $e) {
        echo "<p style='color:red'>✗ ERROR FATAL al cargar autoloader:</p>";
        echo "<pre style='background:#ffe0e0;padding:10px;border:1px solid red'>";
        echo htmlspecialchars($e->getMessage());
        echo "\n\nArchivo: " . htmlspecialchars($e->getFile());
        echo "\nLínea: " . $e->getLine();
        echo "\n\nStack trace:\n";
        echo htmlspecialchars($e->getTraceAsString());
        echo "</pre>";
        
        echo "<h3>Solución</h3>";
        echo "<p>El autoloader está corrupto. Necesitas:</p>";
        echo "<ol>";
        echo "<li>Si tienes acceso SSH al servidor, ejecuta: <code>cd /home/c2650268/public_html/cvapp && composer dump-autoload</code></li>";
        echo "<li>Si NO tienes SSH, sube TODOS los archivos de la carpeta <code>vendor/composer/</code> desde tu instalación local</li>";
        echo "<li>También asegúrate de subir <code>vendor/autoload.php</code></li>";
        echo "</ol>";
    }
}

echo "<hr>";
echo "<h3>4. Información del sistema</h3>";
echo "<p>PHP Version: " . PHP_VERSION . "</p>";
echo "<p>Ruta actual: " . __DIR__ . "</p>";
echo "<p>Ruta vendor: " . realpath($vendor_dir) . "</p>";
?>
