# Instalación y Configuración de Impresión PDF Profesional

## Descripción
Este documento describe cómo instalar y configurar la funcionalidad de impresión profesional de CVs en formato PDF utilizando TCPDF.

## Requisitos
- PHP 7.4 o superior
- Composer (recomendado) o instalación manual
- Extensión GD o ImageMagick para manejo de imágenes
- Extensión mbstring para soporte de caracteres UTF-8

## Opción 1: Instalación con Composer (Recomendado)

### 1. Instalar Composer (si no está instalado)
Descargar desde: https://getcomposer.org/

### 2. Instalar TCPDF
Ejecutar en el directorio raíz del proyecto:
```bash
composer require tecnickcom/tcpdf
```

### 3. Verificar la instalación
Después de la instalación, deberías tener:
```
cvapp/
├── vendor/
│   └── tecnickcom/
│       └── tcpdf/
├── composer.json
└── composer.lock
```

## Opción 2: Instalación Manual

### 1. Descargar TCPDF
- Ir a: https://github.com/tecnickcom/TCPDF/releases
- Descargar la última versión estable
- Extraer en el directorio `libs/tcpdf/` del proyecto

### 2. Estructura esperada
```
cvapp/
└── libs/
    └── tcpdf/
        ├── tcpdf.php
        ├── config/
        ├── fonts/
        └── examples/
```

## Configuración del Sistema

### 1. Verificar extensiones PHP requeridas
Crear un archivo temporal `check_requirements.php`:

```php
<?php
echo "Verificando requisitos para PDF...\n";

// Verificar extensiones
$required = ['gd', 'mbstring', 'zlib'];
foreach ($required as $ext) {
    if (extension_loaded($ext)) {
        echo "✓ Extensión $ext: INSTALADA\n";
    } else {
        echo "✗ Extensión $ext: FALTANTE\n";
    }
}

// Verificar versión PHP
echo "Versión PHP: " . PHP_VERSION . "\n";
if (version_compare(PHP_VERSION, '7.4.0') >= 0) {
    echo "✓ Versión PHP compatible\n";
} else {
    echo "✗ Se requiere PHP 7.4 o superior\n";
}

// Verificar límites de memoria
echo "Límite de memoria: " . ini_get('memory_limit') . "\n";
echo "Límite de tiempo: " . ini_get('max_execution_time') . "s\n";
?>
```

### 2. Configurar límites (opcional)
En `.htaccess` o configuración del servidor:
```apache
php_value memory_limit 128M
php_value max_execution_time 60
```

## Archivos Creados

### 1. `/admin/generar_pdf.php`
Controlador principal para generar PDFs de candidatos.

### 2. `/admin/pdf_template.php`
Template profesional para el diseño del CV en PDF.

### 3. Actualización en `/admin/candidato-detalle.php`
- Nuevo botón "Generar PDF Profesional"
- Mantiene funcionalidad de impresión web existente

## Funcionalidades Incluidas

### ✅ Generación de PDF Profesional
- **Diseño corporativo** con colores personalizables
- **Header profesional** con logo y datos de la empresa
- **Sección de datos personales** con foto del candidato
- **Información demográfica** completa
- **Experiencia laboral** detallada
- **Especialidades por área** organizadas
- **Formación profesional** y habilidades
- **Footer con fecha de generación**

### ✅ Características Técnicas
- **Soporte completo UTF-8** para caracteres especiales
- **Imágenes optimizadas** con redimensionamiento automático
- **Control de saltos de página** para mejor presentación
- **Encabezados y pies de página** en todas las páginas
- **Metadatos del PDF** (título, autor, etc.)

### ✅ Personalización
- **Colores corporativos** fácilmente modificables
- **Logo de la empresa** personalizable
- **Información de contacto** de la empresa
- **Formato de fechas** y números localizados

## Uso

### Desde la Interfaz Web
1. Ir a **Admin > Candidatos > Ver Detalle**
2. Hacer clic en **"Generar PDF Profesional"**
3. El PDF se descarga automáticamente

### Programáticamente
```php
// Incluir el generador
require_once 'generar_pdf.php';

// Generar PDF para un candidato específico
$pdf = new GeneradorPDFCandidato();
$pdf->generarPDF($candidato_id);
```

## Personalización Avanzada

### Cambiar Colores Corporativos
Editar en `pdf_template.php`:
```php
// Colores principales
$color_primario = [41, 128, 185];    // Azul corporativo
$color_secundario = [52, 73, 94];    // Gris oscuro
$color_acento = [46, 204, 113];      // Verde para destacados
```

### Modificar Logo de Empresa
1. Reemplazar `assets/images/logo_empresa.png`
2. Ajustar dimensiones en `pdf_template.php` si es necesario

### Personalizar Información de la Empresa
Editar en `pdf_template.php`:
```php
$empresa_info = [
    'nombre' => 'Tu Empresa',
    'telefono' => '+1 234 567 8900',
    'email' => 'contacto@tuempresa.com',
    'direccion' => 'Dirección de tu empresa'
];
```

## Solución de Problemas

### Error: "Class TCPDF not found"
- Verificar que TCPDF esté instalado correctamente
- Comprobar la ruta de inclusión del archivo

### Error de memoria insuficiente
```php
// Añadir al inicio del script
ini_set('memory_limit', '256M');
```

### Problemas con caracteres especiales
- Verificar que los archivos estén guardados en UTF-8
- Comprobar la configuración de la base de datos (utf8mb4)

### Imágenes no se muestran
- Verificar permisos de lectura en el directorio de imágenes
- Comprobar que la extensión GD esté instalada

## Mantenimiento

### Actualizar TCPDF
Con Composer:
```bash
composer update tecnickcom/tcpdf
```

### Optimización de Rendimiento
- Implementar caché de PDFs generados
- Optimizar imágenes antes de incluir en PDF
- Configurar límites de memoria adecuados

## Consideraciones de Seguridad

### Validación de Entrada
- El sistema valida que el usuario tenga permisos para ver el candidato
- Verificación de token CSRF incluida
- Sanitización de datos antes de incluir en PDF

### Archivos Temporales
- Los PDFs se generan en memoria y se envían directamente
- No se almacenan archivos temporales en el servidor

## Soporte

Para problemas técnicos:
1. Verificar los logs de PHP (`php_error.log`)
2. Comprobar permisos de archivos y directorios
3. Verificar configuración de la base de datos
4. Consultar la documentación oficial de TCPDF: https://tcpdf.org/

## Licencia

TCPDF está disponible bajo licencia LGPL v3.
Consultar: https://github.com/tecnickcom/TCPDF/blob/main/LICENSE.TXT