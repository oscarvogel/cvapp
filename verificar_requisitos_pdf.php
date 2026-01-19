<?php
echo "=== VERIFICACIÓN DE REQUISITOS PARA PDF ===\n\n";

// Verificar versión de PHP
echo "PHP Version: " . PHP_VERSION . "\n";
if (version_compare(PHP_VERSION, '7.4.0') >= 0) {
    echo "✓ Versión PHP compatible (7.4+)\n";
} else {
    echo "✗ Se requiere PHP 7.4 o superior\n";
}

echo "\n=== EXTENSIONES PHP ===\n";

// Extensiones requeridas
$required_extensions = ['mbstring', 'pdo', 'pdo_mysql'];
$optional_extensions = ['gd', 'imagick', 'zlib', 'curl'];

foreach ($required_extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "✓ $ext: INSTALADA (requerida)\n";
    } else {
        echo "✗ $ext: FALTANTE (requerida)\n";
    }
}

foreach ($optional_extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "✓ $ext: INSTALADA (opcional)\n";
    } else {
        echo "○ $ext: NO INSTALADA (opcional)\n";
    }
}

echo "\n=== CONFIGURACIÓN PHP ===\n";
echo "Memory Limit: " . ini_get('memory_limit') . "\n";
echo "Max Execution Time: " . ini_get('max_execution_time') . " segundos\n";
echo "Upload Max Filesize: " . ini_get('upload_max_filesize') . "\n";
echo "Post Max Size: " . ini_get('post_max_size') . "\n";

echo "\n=== VERIFICAR TCPDF ===\n";

// Verificar si TCPDF está disponible
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
    if (class_exists('TCPDF')) {
        echo "✓ TCPDF instalado vía Composer\n";
    } else {
        echo "○ TCPDF no encontrado en Composer\n";
    }
} else {
    echo "○ Composer autoload no encontrado\n";
}

if (file_exists(__DIR__ . '/libs/tcpdf/tcpdf.php')) {
    require_once __DIR__ . '/libs/tcpdf/tcpdf.php';
    if (class_exists('TCPDF')) {
        echo "✓ TCPDF instalado manualmente en /libs/\n";
    } else {
        echo "○ TCPDF no funcional en /libs/\n";
    }
} else {
    echo "○ TCPDF no encontrado en /libs/tcpdf/\n";
}

echo "\n=== VERIFICAR DIRECTORIOS ===\n";

$directories = [
    'assets/images' => __DIR__ . '/assets/images',
    'uploads' => __DIR__ . '/uploads',
    'vendor' => __DIR__ . '/vendor',
    'libs' => __DIR__ . '/libs'
];

foreach ($directories as $name => $path) {
    if (is_dir($path)) {
        $writable = is_writable($path) ? 'escribible' : 'solo lectura';
        echo "✓ $name: existe ($writable)\n";
    } else {
        echo "○ $name: no existe\n";
    }
}

echo "\n=== RECOMENDACIONES ===\n";

if (!class_exists('TCPDF')) {
    echo "📋 Para habilitar PDF profesional:\n";
    echo "   1. Instalar TCPDF con Composer: composer require tecnickcom/tcpdf\n";
    echo "   2. O descargar manualmente en /libs/tcpdf/\n";
    echo "   3. Ver INSTALACION_IMPRESION_PDF.md para más detalles\n\n";
}

if (!extension_loaded('gd') && !extension_loaded('imagick')) {
    echo "📋 Para mejorar manejo de imágenes:\n";
    echo "   1. Instalar extensión GD o ImageMagick en PHP\n";
    echo "   2. Esto permitirá redimensionar fotos automáticamente\n\n";
}

echo "=== FIN DE VERIFICACIÓN ===\n";
?>