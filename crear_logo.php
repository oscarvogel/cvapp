<?php
// Script para crear un logo temporal para la empresa
// Este archivo genera un logo simple en PNG para usar en los PDFs

// Verificar si GD está disponible
if (!extension_loaded('gd')) {
    die('La extensión GD no está disponible.');
}

// Crear imagen de 200x80 pixels
$width = 200;
$height = 80;
$image = imagecreatetruecolor($width, $height);

// Definir colores
$white = imagecolorallocate($image, 255, 255, 255);
$blue = imagecolorallocate($image, 41, 128, 185);
$dark_blue = imagecolorallocate($image, 52, 73, 94);

// Fondo blanco
imagefill($image, 0, 0, $white);

// Crear un rectángulo azul como fondo del logo
imagefilledrectangle($image, 10, 15, 190, 65, $blue);

// Agregar texto del logo
$font_size = 5; // Fuente built-in de GD (1-5)

// Título principal
imagestring($image, $font_size, 25, 25, 'GESTION DE CANDIDATOS', $white);

// Subtítulo
imagestring($image, 3, 45, 45, 'Sistema de CV Profesional', $white);

// Guardar la imagen
$logo_path = __DIR__ . '/../assets/images/logo_empresa.png';

// Crear directorio si no existe
$logo_dir = dirname($logo_path);
if (!is_dir($logo_dir)) {
    mkdir($logo_dir, 0755, true);
}

// Guardar imagen
if (imagepng($image, $logo_path)) {
    echo "Logo creado exitosamente en: $logo_path\n";
} else {
    echo "Error al crear el logo.\n";
}

// Limpiar memoria
imagedestroy($image);
?>