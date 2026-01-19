<?php
// Crear un logo simple en base64 (SVG) para el PDF
$svg_logo = '<?xml version="1.0" encoding="UTF-8"?>
<svg width="200" height="80" xmlns="http://www.w3.org/2000/svg">
  <!-- Fondo azul -->
  <rect x="10" y="15" width="180" height="50" fill="#2980b9" rx="5"/>
  
  <!-- Texto principal -->
  <text x="100" y="32" font-family="Arial, sans-serif" font-size="14" font-weight="bold" 
        text-anchor="middle" fill="white">GESTIÓN DE CANDIDATOS</text>
  
  <!-- Subtítulo -->
  <text x="100" y="50" font-family="Arial, sans-serif" font-size="10" 
        text-anchor="middle" fill="white">Sistema de CV Profesional</text>
  
  <!-- Icono decorativo -->
  <circle cx="25" cy="40" r="8" fill="white" opacity="0.3"/>
  <circle cx="175" cy="40" r="8" fill="white" opacity="0.3"/>
</svg>';

// Crear directorio si no existe
$assets_dir = __DIR__ . '/assets/images';
if (!is_dir($assets_dir)) {
    mkdir($assets_dir, 0755, true);
}

// Guardar como SVG
$svg_path = $assets_dir . '/logo_empresa.svg';
if (file_put_contents($svg_path, $svg_logo)) {
    echo "✓ Logo SVG creado: $svg_path\n";
} else {
    echo "✗ Error creando logo SVG\n";
}

// Crear también un PNG simple (placeholder de texto)
$png_content = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAMgAAABQCAYAAABBbJ9+AAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAAAdgAAAHYBTnsmCAAAABl0RVh0U29mdHdhcmUAd3d3Lmlua3NjYXBlLm9yZ5vuPBoAABUKSURBVHic7Z17dFTVwcZ/+5xzZpI5yWQymRBIQhISEkJ4hPeThLe8BAQrr1ZFqVqXVVu1Wlu7bK3t8qlVW59dbVetD0QFFVsVEEEFRAVBQHkECCRAHpCQhCQkIZnJzJmzv/1jJpNMZiYJYUKAzm+tzJqZc+fs85399/a3v70PIYRAREQEN5AtKyLiK0QEERFxQUREERE3REREFBFxQ0REERFxQUREFBFxQ0REFBFxQ0REFBFxQ0REFBFxQ0REFBFxQ0REFBFxQ0REFBFxQ0REFBFxQ0REFBFxQ0REFBFxQ0REFBFxQUREFBFxQ0REFBFxQ0REFBFxQ0REFBFxQ0REFBFxQ0REFBFxQ0REFBFxQ0REFBFxQUREERFxQ0REFBFxQ0REFBFxQ0REFBFxQ0REFBFxQ0REFBFxQ0REFBFxQ0REFBFxQUREERFxQ0REFBFxQ0REFBFxQ0REFBFxQ0REFBFxQ0REFBFxQ0REFBFxQUREERFxQ0REFBFxQ0REFBFxQ0REFBFxQ0REFBFxQ0REFBFxQ0REFBFxQUREERFxQ0REFBFxQ0REFBFxQ0REFBFxQ0REFBFxQ0REFBFxQUREERFxQ0REFBFxQ0REFBFxQ0REFBFxQ0REFBFxQ0REFBFxQUREERFxQ0REFBFxQ0REFBFxQ0REFBFxQ0REFBFxQ0REFBFxQUREERFxQUREFBFxQ0REFBFxQ0REFBFxQUREERFxQ0REFBFxQ0REFBFxQ0REFBFxQ0REFBFxQUREERFxQ0REFBFxQ0REFBFxQ0REFBFxQ0REFBFxQ0REFBFxQUREERFxQ0REFBFxQ0REFBFxQ0REFBFxQ0REFBFxQ0REFBFxQUREERFxQ0REFBFxQ0REFBFxQ0REFBFxQ0REFBFxQ0REFBFxQUREERFxQ0REFBFxQ0REFBFxQ0REFBFxQ0REFBFxQ0REFBFxQUREERFxQ0REFBFxQ0REFBFxQ0REFBFxQ0REFBFxQ0REFBFxQUREERFxQ0REFBFxQ0REFBFxQ0REFBFxQ0REFBFxQ0REFBFxQUREERFxQ0REFBFxQ0REFBFxQ0REFBFxQ0REFBFxQ0REFBFxQUREERFxQ0REFBFxQ0REFBFxQ0REFBFxQ0REFBFxQ0REFBFxQUREERFxQ0REFBFxQ0REFBFxQ0REFBFxQ0REFBFxQ0REFBFxQUREERF');

// Intentar crear PNG si no existe
$png_path = $assets_dir . '/logo_empresa.png';
if (!file_exists($png_path)) {
    // Crear un archivo PNG de placeholder básico
    $placeholder_text = "GESTIÓN\nCANDIDATOS";
    
    // Si GD está disponible, crear imagen
    if (extension_loaded('gd')) {
        $img = imagecreate(200, 80);
        $bg = imagecolorallocate($img, 41, 128, 185); // Azul
        $text_color = imagecolorallocate($img, 255, 255, 255); // Blanco
        
        imagestring($img, 5, 45, 20, 'GESTION', $text_color);
        imagestring($img, 5, 30, 40, 'CANDIDATOS', $text_color);
        
        if (imagepng($img, $png_path)) {
            echo "✓ Logo PNG creado con GD: $png_path\n";
        }
        imagedestroy($img);
    } else {
        // Crear archivo de texto como placeholder
        $placeholder_content = "Reemplazar con logo real de la empresa (200x80px)";
        if (file_put_contents($png_path . '.txt', $placeholder_content)) {
            echo "○ Placeholder de logo creado: {$png_path}.txt\n";
        }
    }
}

echo "\n=== ARCHIVOS CREADOS ===\n";
echo "✓ $svg_path\n";
if (file_exists($png_path)) {
    echo "✓ $png_path\n";
} else {
    echo "○ $png_path (usar SVG como alternativa)\n";
}

echo "\n=== INSTRUCCIONES ===\n";
echo "1. Reemplaza logo_empresa.png con el logo real de tu empresa\n";
echo "2. Tamaño recomendado: 200x80 pixels\n";
echo "3. Formatos soportados: PNG, JPG, SVG\n";
echo "4. El sistema usará automáticamente el logo disponible\n";
?>