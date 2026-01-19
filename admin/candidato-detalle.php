<?php
require_once __DIR__ . '/../init.php';
require_login();

$candidato_id = safe_int($_GET['id'] ?? 0);
if ($candidato_id <= 0) {
    header('Location: dashboard.php?error=' . urlencode('ID de candidato inválido'));
    exit;
}

$err = $success = '';

// Verificar qué columnas existen en la tabla candidatos
try {
    $columns_check = $pdo->query("SHOW COLUMNS FROM candidatos");
    $existing_columns = [];
    while ($col = $columns_check->fetch()) {
        $existing_columns[] = $col['Field'];
    }
} catch (Exception $e) {
    $existing_columns = ['id', 'nombre', 'email', 'telefono', 'experiencia', 'foto_ruta', 'fecha_carga', 'observaciones'];
}

// Determinar si las nuevas columnas demográficas existen
$has_demographic_fields = in_array('dni', $existing_columns) && 
                         in_array('edad', $existing_columns) && 
                         in_array('estado_civil', $existing_columns);

// Procesar actualización de datos
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action']) && $_POST['action'] === 'update_candidato') {
    if (!csrf_verify($_POST['csrf'] ?? null)) {
        $err = 'Token de seguridad inválido. Recarga la página.';
    } else {
  // Validar y obtener datos del formulario
  // Normalizar campos de texto a mayúsculas antes de validar/guardar
  $nombre = safe_trim($_POST['nombre'] ?? '');
  $nombre = mb_strtoupper($nombre, 'UTF-8');
  $email = safe_trim($_POST['email'] ?? '');
  $telefono = safe_trim($_POST['telefono'] ?? '');
  $telefono = mb_strtoupper($telefono, 'UTF-8');
          // Experiencia puede ser opcional en la edición; si no se envía, tratamos como 0
        $experiencia = 0;
        if (isset($_POST['experiencia']) && $_POST['experiencia'] !== '') {
          $experiencia = safe_int($_POST['experiencia'], -1);
        }
        $estado_id = safe_int($_POST['estado_id'] ?? null, 1);
  $observaciones = safe_trim($_POST['observaciones'] ?? '');
  $observaciones = mb_strtoupper($observaciones, 'UTF-8');

        // Validaciones básicas
        if ($nombre === '' || mb_strlen($nombre) > 100) {
            $err = 'Nombre inválido.';
        } elseif (!valid_email($email) || mb_strlen($email) > 150) {
            $err = 'Email inválido.';
      } elseif (!valid_phone($telefono) || mb_strlen($telefono) > 30) {
      $err = 'Teléfono inválido.';
    } elseif (!is_int($experiencia) || $experiencia < 0 || $experiencia > 50) {
      $err = 'Años de experiencia inválidos.';
    }
        
        // Validaciones para campos demográficos si existen
        if ($has_demographic_fields && $err === '') {
            $dni = safe_trim($_POST['dni'] ?? '');
            $dni = mb_strtoupper($dni, 'UTF-8');
            $edad = safe_int($_POST['edad'] ?? null, -1);
            $estado_civil = safe_trim($_POST['estado_civil'] ?? '');
            $estado_civil = mb_strtoupper($estado_civil, 'UTF-8');
            $hijos = safe_int($_POST['hijos'] ?? null, -1);
            $edad_hijos = safe_trim($_POST['edad_hijos'] ?? '');
            $edad_hijos = mb_strtoupper($edad_hijos, 'UTF-8');
            $nacionalidad = safe_trim($_POST['nacionalidad'] ?? '');
            $nacionalidad = mb_strtoupper($nacionalidad, 'UTF-8');
            $lugar_residencia = safe_trim($_POST['lugar_residencia'] ?? '');
            $lugar_residencia = mb_strtoupper($lugar_residencia, 'UTF-8');
            $ocupacion_actual = safe_trim($_POST['ocupacion_actual'] ?? '');
            $ocupacion_actual = mb_strtoupper($ocupacion_actual, 'UTF-8');
            $ocupacion_padre = safe_trim($_POST['ocupacion_padre'] ?? '');
            $ocupacion_padre = mb_strtoupper($ocupacion_padre, 'UTF-8');
            $ocupacion_madre = safe_trim($_POST['ocupacion_madre'] ?? '');
            $ocupacion_madre = mb_strtoupper($ocupacion_madre, 'UTF-8');
            
      if ($dni === '' || mb_strlen($dni) > 20 || !preg_match('/^[0-9A-Za-z\-\.]+$/', $dni)) {
                $err = 'DNI inválido.';
      } elseif (!is_int($edad) || $edad <= 18 || $edad >= 80) {
        $err = 'Edad inválida. Debe ser mayor de 18 y menor de 80 años.';
      } elseif (!in_array(mb_strtoupper($estado_civil, 'UTF-8'), array_map(function($v){ return mb_strtoupper($v, 'UTF-8'); }, ['Soltero', 'Casado', 'Divorciado', 'Viudo', 'Unión libre']))) {
                $err = 'Estado civil inválido.';
            } elseif ($hijos < 0 || $hijos > 20) {
                $err = 'Número de hijos inválido.';
            } elseif ($hijos > 0 && $edad_hijos === '') {
                $err = 'Debes especificar las edades de los hijos.';
            } elseif ($nacionalidad === '' || mb_strlen($nacionalidad) > 255) {
                $err = 'Nacionalidad inválida.';
            } elseif ($lugar_residencia === '' || mb_strlen($lugar_residencia) > 255) {
                $err = 'Lugar de residencia inválido.';
            } elseif ($ocupacion_actual === '' || mb_strlen($ocupacion_actual) > 100) {
                $err = 'Ocupación actual inválida.';
            } elseif ($ocupacion_padre === '' || mb_strlen($ocupacion_padre) > 100) {
                $err = 'Ocupación del padre inválida.';
            } elseif ($ocupacion_madre === '' || mb_strlen($ocupacion_madre) > 100) {
                $err = 'Ocupación de la madre inválida.';
            }
        }
        
        if ($err === '') {
            // Verificar que el email no esté usado por otro candidato
            $stmt = $pdo->prepare('SELECT id FROM candidatos WHERE email = ? AND id != ? LIMIT 1');
            $stmt->execute([$email, $candidato_id]);
            if ($stmt->fetch()) {
                $err = 'El email ya está siendo usado por otro candidato.';
            } else {
                try {
                    $pdo->beginTransaction();
                    
                    // Actualizar campos básicos
                    if ($has_demographic_fields) {
                        $stmt = $pdo->prepare('UPDATE candidatos SET 
                            nombre = ?, email = ?, telefono = ?, experiencia = ?, estado_id = ?, observaciones = ?, 
                            dni = ?, edad = ?, estado_civil = ?, hijos = ?, edad_hijos = ?, nacionalidad = ?, 
                            lugar_residencia = ?, ocupacion_actual = ?, ocupacion_padre = ?, ocupacion_madre = ?, 
                            fecha_estado = NOW()
                            WHERE id = ?');
                        
                        $stmt->execute([
                            $nombre, $email, $telefono, $experiencia, $estado_id, $observaciones,
                            $dni, $edad, $estado_civil, $hijos, $edad_hijos, $nacionalidad,
                            $lugar_residencia, $ocupacion_actual, $ocupacion_padre, $ocupacion_madre,
                            $candidato_id
                        ]);
                    } else {
                        $stmt = $pdo->prepare('UPDATE candidatos SET 
                            nombre = ?, email = ?, telefono = ?, experiencia = ?, estado_id = ?, observaciones = ?, fecha_estado = NOW()
                            WHERE id = ?');
                        
                        $stmt->execute([$nombre, $email, $telefono, $experiencia, $estado_id, $observaciones, $candidato_id]);
                    }
                    
                    // Actualizar áreas profesionales si se enviaron
                    if (isset($_POST['areas']) && is_array($_POST['areas'])) {
                        // Eliminar áreas actuales
                        $stmt = $pdo->prepare('DELETE FROM candidato_areas WHERE candidato_id = ?');
                        $stmt->execute([$candidato_id]);
                        
                        // Insertar nuevas áreas (máximo 2)
                        $areas = array_slice($_POST['areas'], 0, 2);
                        $stmt_area = $pdo->prepare('INSERT INTO candidato_areas (candidato_id, area_profesional_id) VALUES (?, ?)');
                        
                        foreach ($areas as $area_id) {
                            $area_id = safe_int($area_id);
                            if ($area_id > 0) {
                                $stmt_area->execute([$candidato_id, $area_id]);
                            }
                        }
                    }
                    
                    // Actualizar formación profesional si existe la tabla
                    if ($has_demographic_fields) {
                        // Verificar si existe la tabla formacion_profesional
                        try {
                            $pdo->query("SELECT 1 FROM formacion_profesional LIMIT 1");
                            $has_formation_fields = true;
                        } catch (Exception $e) {
                            $has_formation_fields = false;
                        }
                        
                        if ($has_formation_fields) {
                            $nivel_educativo = safe_trim($_POST['nivel_educativo'] ?? '');
                            $carreras_titulos = safe_trim($_POST['carreras_titulos'] ?? '');
                            $cursos_capacitaciones = safe_trim($_POST['cursos_capacitaciones'] ?? '');
                            
              if (!empty($nivel_educativo)) {
                $nivel_educativo = mb_strtoupper($nivel_educativo, 'UTF-8');
                                // Verificar si ya existe un registro de formación para este candidato
                                $stmt_check = $pdo->prepare('SELECT id FROM formacion_profesional WHERE candidato_id = ?');
                                $stmt_check->execute([$candidato_id]);
                                
                                if ($stmt_check->fetch()) {
                                    // Actualizar registro existente
                                    $stmt_form = $pdo->prepare('UPDATE formacion_profesional SET 
                                        nivel_educativo = ?, carreras_titulos = ?, cursos_capacitaciones = ? 
                                        WHERE candidato_id = ?');
                                    $stmt_form->execute([$nivel_educativo, $carreras_titulos, $cursos_capacitaciones, $candidato_id]);
                                } else {
                                    // Insertar nuevo registro
                                    $stmt_form = $pdo->prepare('INSERT INTO formacion_profesional 
                                        (candidato_id, nivel_educativo, carreras_titulos, cursos_capacitaciones) 
                                        VALUES (?, ?, ?, ?)');
                                    $stmt_form->execute([$candidato_id, $nivel_educativo, $carreras_titulos, $cursos_capacitaciones]);
                                }
                            }
                        }
                    }
                    
                    $pdo->commit();
                    $success = 'Datos del candidato actualizados correctamente.';
                    
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $err = 'Error al actualizar los datos: ' . $e->getMessage();
                }
            }
        }
    }
}

// Obtener datos completos del candidato
$select_fields = [
    'c.id', 'c.nombre', 'c.email', 'c.telefono', 'c.experiencia',
    'c.foto_nombre_original', 'c.foto_ruta', 'c.fecha_carga', 'c.observaciones',
    'COALESCE(c.estado_id, 1) as estado_id',
    'COALESCE(e.nombre, "Pendiente") as estado_nombre',
    'COALESCE(e.color, "#f59e0b") as estado_color'
];

// Agregar campos demográficos si existen
if ($has_demographic_fields) {
    $select_fields = array_merge($select_fields, [
        'c.dni', 'c.edad', 'c.estado_civil', 'c.hijos', 'c.edad_hijos', 
        'c.nacionalidad', 'c.lugar_residencia', 'c.ocupacion_actual', 
        'c.ocupacion_padre', 'c.ocupacion_madre'
    ]);
}

$sql = 'SELECT ' . implode(', ', $select_fields) . '
        FROM candidatos c 
        LEFT JOIN estados_cv e ON c.estado_id = e.id 
        WHERE c.id = ?';

$stmt = $pdo->prepare($sql);
$stmt->execute([$candidato_id]);
$candidato = $stmt->fetch();

if (!$candidato) {
    header('Location: dashboard.php?error=' . urlencode('Candidato no encontrado'));
    exit;
}

// Obtener áreas del candidato
$stmt = $pdo->prepare('SELECT ap.id, ap.nombre 
    FROM candidato_areas ca 
    INNER JOIN areas_profesionales ap ON ca.area_profesional_id = ap.id 
    WHERE ca.candidato_id = ?');
$stmt->execute([$candidato_id]);
$areas_candidato = $stmt->fetchAll();

// Obtener especialidades del candidato con su nivel
$stmt = $pdo->prepare('SELECT 
    ce.id,
    ce.especialidad_id,
    ce.nivel_id,
    ne.nombre as nivel_nombre,
    ne.descripcion as nivel_descripcion,
    ea.nombre as especialidad_nombre,
    ea.area_profesional_id,
    ap.nombre as area_nombre
    FROM candidato_especialidades ce
    INNER JOIN especialidades_areas ea ON ce.especialidad_id = ea.id
    INNER JOIN areas_profesionales ap ON ea.area_profesional_id = ap.id
    LEFT JOIN niveles_especialidades ne ON ce.nivel_id = ne.id
    WHERE ce.candidato_id = ?
    ORDER BY ap.nombre, ea.nombre');
$stmt->execute([$candidato_id]);
$especialidades_candidato = $stmt->fetchAll();

// Agrupar especialidades por área
$especialidades_por_area = [];
foreach ($especialidades_candidato as $esp) {
    $area_id = $esp['area_profesional_id'];
    if (!isset($especialidades_por_area[$area_id])) {
        $especialidades_por_area[$area_id] = [
            'area_nombre' => $esp['area_nombre'],
            'especialidades' => []
        ];
    }
    $especialidades_por_area[$area_id]['especialidades'][] = $esp;
}

// Obtener todas las áreas profesionales para el selector
$stmt = $pdo->query('SELECT id, nombre FROM areas_profesionales WHERE activa = 1 ORDER BY orden, nombre');
$todas_areas = $stmt->fetchAll();

// Obtener todos los estados disponibles
$stmt = $pdo->query('SELECT id, nombre, color FROM estados_cv WHERE activo = 1 ORDER BY orden, nombre');
$estados = $stmt->fetchAll();

// Obtener formación profesional si existe la tabla
$formacion_profesional = null;
if ($has_demographic_fields) {
    try {
        $pdo->query("SELECT 1 FROM formacion_profesional LIMIT 1");
        $has_formation_fields = true;
        
        $stmt = $pdo->prepare('SELECT * FROM formacion_profesional WHERE candidato_id = ?');
        $stmt->execute([$candidato_id]);
        $formacion_profesional = $stmt->fetch();
    } catch (Exception $e) {
        $has_formation_fields = false;
    }
}

// Obtener experiencia laboral
$stmt = $pdo->prepare('SELECT * FROM experiencia_laboral WHERE candidato_id = ? ORDER BY fecha_desde DESC');
$stmt->execute([$candidato_id]);
$experiencias_laborales = $stmt->fetchAll();

// Obtener habilidades y disponibilidad
$stmt = $pdo->prepare('SELECT * FROM habilidades_disponibilidad WHERE candidato_id = ?');
$stmt->execute([$candidato_id]);
$habilidades_disponibilidad = $stmt->fetch();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Detalle del Candidato | Panel de CVs</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="icon" type="image/x-icon" href="../assets/images/logo.ico">
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
    body { font-family: 'Inter', sans-serif; }
    
    /* Estilos para el panel fijo */
    .sticky-panel {
      position: sticky;
      top: 1rem;
    }
    
    /* Asegurar que la foto se muestre correctamente */
    .candidate-photo {
      object-fit: cover;
      background-color: #f3f4f6; /* bg-gray-100 */
    }
    
    /* Estilos profesionales para impresión web */
    @media print {
      @page {
        size: A4;
        margin: 1.5cm 2cm;
      }
      
      * {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
      }
      
      html, body {
        background: white !important;
        height: auto !important;
        min-height: auto !important;
        overflow: visible !important;
        font-size: 11pt !important;
        line-height: 1.4 !important;
        font-family: 'Times New Roman', serif !important;
      }
      
      /* Ocultar elementos no necesarios para el CV */
      .no-print,
      header {
        display: none !important;
      }
      
      /* Ocultar el formulario de edición */
      form[method="post"] {
        display: none !important;
      }
      
      /* Ocultar alertas */
      .bg-emerald-50,
      .bg-red-50 {
        display: none !important;
      }
      
      /* Mostrar solo el panel de información como CV */
      main {
        max-width: 100% !important;
        padding: 10px !important;
        margin: 0 !important;
      }
      
      /* Forzar grid a bloque simple */
      .print-layout {
        display: block !important;
        grid-template-columns: 1fr !important;
      }
      
      /* Ocultar la columna del formulario */
      .lg\:col-span-2 {
        display: none !important;
      }
      
      /* El panel lateral (CV) debe ser visible y ocupar todo el ancho */
      .lg\:col-span-1 {
        display: block !important;
        width: 100% !important;
        max-width: 100% !important;
        grid-column: 1 !important;
        visibility: visible !important;
        opacity: 1 !important;
      }
      
      .sticky-panel {
        position: static !important;
        background: white !important;
        border: none !important;
        box-shadow: none !important;
        border-radius: 0 !important;
        padding: 10px !important;
        visibility: visible !important;
        opacity: 1 !important;
      }
      
      /* Forzar visibilidad de todo el contenido del CV */
      .sticky-panel > *,
      .sticky-panel div,
      .sticky-panel span,
      .sticky-panel p,
      .sticky-panel h2,
      .sticky-panel h3,
      .sticky-panel h4,
      .sticky-panel h5 {
        display: block !important;
        visibility: visible !important;
        opacity: 1 !important;
      }
      
      /* Forzar que los flex se muestren */
      .flex {
        display: flex !important;
      }
      
      /* Forzar que elementos inline-flex se muestren */
      .inline-flex {
        display: inline-flex !important;
      }
      
      /* Remover cualquier opacidad o transparencia */
      * {
        opacity: 1 !important;
      }
      
      .bg-white\/70,
      .bg-white\/80,
      [class*="bg-white/"] {
        background: white !important;
        opacity: 1 !important;
      }
      
      /* Header del CV con foto y nombre */
      .text-center.mb-6 {
        border-bottom: 3px solid #2563eb;
        padding-bottom: 20px;
        margin-bottom: 20px !important;
      }
      
      .candidate-photo {
        width: 100px !important;
        height: 100px !important;
        border: 3px solid #2563eb !important;
        margin-bottom: 10px !important;
      }
      
      /* Nombre del candidato */
      .text-center h2 {
        font-size: 24pt !important;
        font-weight: bold !important;
        color: #1e40af !important;
        margin: 10px 0 5px 0 !important;
      }
      
      /* Badge de estado */
      .text-center .inline-flex {
        font-size: 10pt !important;
        padding: 4px 12px !important;
      }
      
      /* Secciones del CV */
      .space-y-4 {
        margin-top: 0 !important;
      }
      
      .flex.items-center.gap-3,
      .flex.items-start.gap-3 {
        margin-bottom: 12px !important;
        padding: 8px 0 !important;
        border-bottom: 1px solid #e5e7eb;
      }
      
      .flex.items-center.gap-3 svg,
      .flex.items-start.gap-3 svg {
        color: #2563eb !important;
        flex-shrink: 0;
      }
      
      /* Secciones con títulos */
      .pt-4.border-t {
        margin-top: 15px !important;
        padding-top: 15px !important;
        border-top: 2px solid #e5e7eb !important;
        page-break-inside: avoid;
      }
      
      .pt-4 h4 {
        font-size: 13pt !important;
        font-weight: bold !important;
        color: #1e40af !important;
        margin-bottom: 8px !important;
      }
      
      /* Especialidades */
      .space-y-1\.5 > div {
        margin-bottom: 4px !important;
      }
      
      /* Experiencia Laboral */
      .bg-gray-50.p-3 {
        background: #f9fafb !important;
        border: 1px solid #e5e7eb !important;
        border-radius: 6px !important;
        padding: 12px !important;
        margin-bottom: 10px !important;
        page-break-inside: avoid;
      }
      
      .bg-gray-50 h5 {
        font-size: 11pt !important;
        font-weight: bold !important;
        color: #111827 !important;
      }
      
      /* Badges y etiquetas */
      .inline-flex.items-center.px-2,
      .inline-flex.items-center.px-1\.5,
      span[class*="bg-"] {
        border: 1px solid currentColor !important;
        font-size: 9pt !important;
        padding: 2px 8px !important;
        border-radius: 4px !important;
      }
      
      /* Colores de badges para impresión */
      .bg-blue-100 {
        background: #dbeafe !important;
        color: #1e40af !important;
      }
      
      .bg-indigo-100 {
        background: #e0e7ff !important;
        color: #3730a3 !important;
      }
      
      .bg-green-100 {
        background: #d1fae5 !important;
        color: #065f46 !important;
      }
      
      .bg-yellow-100 {
        background: #fef3c7 !important;
        color: #92400e !important;
      }
      
      .bg-orange-100 {
        background: #ffedd5 !important;
        color: #9a3412 !important;
      }
      
      .bg-purple-100 {
        background: #ede9fe !important;
        color: #5b21b6 !important;
      }
      
      .bg-red-100 {
        background: #fee2e2 !important;
        color: #991b1b !important;
      }
      
      /* Texto y espaciado */
      .text-xs {
        font-size: 9pt !important;
      }
      
      .text-sm {
        font-size: 10pt !important;
      }
      
      /* Fecha de registro al final */
      .text-xs.text-gray-500 {
        font-size: 8pt !important;
        color: #6b7280 !important;
        text-align: right !important;
        margin-top: 20px !important;
      }
      
      /* Evitar saltos de página inapropiados */
      h4, h5 {
        page-break-after: avoid;
      }
      
      /* Ocultar elementos decorativos */
      .shadow-xl,
      .shadow-lg,
      .shadow-md,
      .backdrop-blur-sm,
      .backdrop-blur-md,
      .rounded-2xl,
      .rounded-xl {
        box-shadow: none !important;
        backdrop-filter: none !important;
      }
    }
  </style>
  <?= defined('UPPER_ASSETS_HTML') ? UPPER_ASSETS_HTML : '' ?>
</head>
<body class="bg-gradient-to-br from-slate-50 to-blue-50 min-h-screen">

<header class="bg-white/80 backdrop-blur-md border-b border-gray-200 shadow-sm sticky top-0 z-10">
  <div class="max-w-7xl mx-auto px-4 py-4">
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-4">
        <img src="../assets/images/logo_fg.png" alt="Logo" class="h-10 w-auto">
        <div>
          <h1 class="text-2xl font-bold text-gray-900 bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent">
            Perfil de <?= e($candidato['nombre']) ?>
          </h1>
          <p class="text-sm text-gray-600">Ver y editar información completa</p>
        </div>
      </div>
      <div class="flex items-center gap-4 no-print">
        <button 
          onclick="generarPDFProfesional()" 
          class="bg-gradient-to-r from-blue-600 to-indigo-700 hover:from-blue-700 hover:to-indigo-800 text-white font-medium px-4 py-2 rounded-lg transition-all duration-200 flex items-center gap-2 shadow-md hover:shadow-lg"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
          </svg>
          Generar PDF Profesional
        </button>
        <button 
          onclick="imprimirCV()" 
          class="bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white font-medium px-4 py-2 rounded-lg transition-all duration-200 flex items-center gap-2 shadow-md hover:shadow-lg"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
          </svg>
          Imprimir Web
        </button>
        <a href="dashboard.php" class="text-gray-600 hover:text-gray-800 font-medium transition-colors duration-200 flex items-center gap-2">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
          </svg>
          Volver al Panel
        </a>
      </div>
    </div>
  </div>
</header>

<main class="max-w-6xl mx-auto px-4 py-8">
  <?php if ($success): ?>
    <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-6 py-4 rounded-xl mb-6 shadow-sm">
      <div class="flex items-center gap-3">
        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
        </svg>
        <span class="font-medium"><?= e($success) ?></span>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($err): ?>
    <div class="bg-red-50 border border-red-200 text-red-800 px-6 py-4 rounded-xl mb-6 shadow-sm">
      <div class="flex items-center gap-3">
        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
        </svg>
        <span class="font-medium"><?= e($err) ?></span>
      </div>
    </div>
  <?php endif; ?>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 print-layout">
    <!-- Panel de información básica (fijo al hacer scroll) -->
    <div class="lg:col-span-1">
      <div class="bg-white/70 backdrop-blur-sm rounded-2xl shadow-xl border border-gray-200/50 p-6 sticky-panel">
        <div class="text-center mb-6">
          <!-- Usar el script photo.php para mostrar la imagen -->
          <img src="photo.php?id=<?= $candidato['id'] ?>&nombre=<?= urlencode($candidato['nombre']) ?>" 
            alt="Foto de <?= e($candidato['nombre']) ?>" 
            class="w-32 h-32 rounded-full mx-auto object-cover border-4 border-blue-500 shadow-lg mb-4 candidate-photo">
          
          <h2 class="text-xl font-bold text-gray-900"><?= e($candidato['nombre']) ?></h2>
          <div class="flex items-center justify-center gap-2 mt-2">
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium text-white"
                  style="background-color: <?= e($candidato['estado_color']) ?>">
              <?= e($candidato['estado_nombre']) ?>
            </span>
          </div>
        </div>

        <div class="space-y-4">
          <div class="flex items-center gap-3">
            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"/>
            </svg>
            <span class="text-sm text-gray-600"><?= e($candidato['email']) ?></span>
          </div>
          
          <div class="flex items-center gap-3">
            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
            </svg>
            <span class="text-sm text-gray-600"><?= e($candidato['telefono']) ?></span>
          </div>

          <div class="flex items-center gap-3">
            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
            </svg>
            <span class="text-sm text-gray-600"><?= e($candidato['experiencia']) ?> años de experiencia</span>
          </div>

          <?php if (!empty($areas_candidato)): ?>
          <div class="flex items-start gap-3">
            <svg class="w-5 h-5 text-gray-400 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2-2v2m8 0V2a2 2 0 00-2-2H8a2 2 0 00-2 2v4m8 0V6a2 2 0 012 2v6a2 2 0 01-2 2H8a2 2 0 01-2-2V8a2 2 0 012-2"/>
            </svg>
            <div class="flex-1 space-y-2">
              <?php foreach ($areas_candidato as $area): ?>
                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800 border border-blue-200">
                  <?= e($area['nombre']) ?>
                </span>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>

          <!-- Especialidades -->
          <?php if (!empty($especialidades_por_area)): ?>
          <!-- DEBUG: Mostrar datos de especialidades -->
          <?php if (false): // Cambiar a true para ver el debug ?>
          <div class="p-4 bg-yellow-50 border border-yellow-200 rounded mb-4">
            <h4 class="font-bold mb-2">DEBUG - Datos de Especialidades:</h4>
            <pre class="text-xs overflow-auto"><?php print_r($especialidades_candidato); ?></pre>
          </div>
          <?php endif; ?>
          <!-- FIN DEBUG -->
          <div class="pt-4 border-t border-gray-200">
            <div class="flex items-center gap-2 mb-3">
              <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
              </svg>
              <h4 class="text-sm font-bold text-gray-900">Especialidades</h4>
            </div>
            <div class="space-y-3">
              <?php foreach ($especialidades_por_area as $area_id => $area_data): ?>
                <div class="space-y-1">
                  <p class="text-xs font-semibold text-indigo-700"><?= e($area_data['area_nombre']) ?></p>
                  <div class="ml-3 space-y-1.5">
                    <?php foreach ($area_data['especialidades'] as $esp): ?>
                      <div class="flex items-center gap-2">
                        <svg class="w-3 h-3 text-indigo-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                        <span class="text-xs text-gray-700"><?= e($esp['especialidad_nombre']) ?></span>
                        <?php if (!empty($esp['nivel_nombre'])): ?>
                          <span class="px-1.5 py-0.5 rounded text-xs font-medium bg-indigo-100 text-indigo-800" 
                                title="<?= !empty($esp['nivel_descripcion']) ? e($esp['nivel_descripcion']) : '' ?>">
                            <?= e($esp['nivel_nombre']) ?>
                          </span>
                        <?php endif; ?>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>

          <!-- Experiencia Laboral -->
          <?php if (!empty($experiencias_laborales)): ?>
          <div class="pt-4 border-t border-gray-200">
            <div class="flex items-center gap-2 mb-3">
              <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2-2v2m8 0V2a2 2 0 00-2-2H8a2 2 0 00-2 2v4m8 0V6a2 2 0 012 2v6a2 2 0 01-2 2H8a2 2 0 01-2-2V8a2 2 0 012-2"/>
              </svg>
              <h4 class="text-sm font-bold text-gray-900">Experiencia Laboral</h4>
              <span class="ml-auto bg-blue-100 text-blue-800 px-2 py-0.5 rounded-full text-xs font-medium">
                <?= count($experiencias_laborales) ?>
              </span>
            </div>
            <div class="space-y-3">
              <?php foreach ($experiencias_laborales as $exp): ?>
              <div class="bg-gray-50 p-3 rounded-lg border border-gray-200">
                <div class="mb-2">
                  <h5 class="text-sm font-bold text-gray-900"><?= e($exp['puesto']) ?></h5>
                  <p class="text-xs font-semibold text-blue-600"><?= e($exp['empresa']) ?></p>
                </div>
                <div class="text-xs text-gray-600 mb-1">
                  <?= date('m/Y', strtotime($exp['fecha_desde'])) ?> - 
                  <?= $exp['fecha_hasta'] ? date('m/Y', strtotime($exp['fecha_hasta'])) : 'Actual' ?>
                </div>
                <?php if (!empty($exp['empleador'])): ?>
                <div class="text-xs text-gray-500 mb-1">
                  <span class="font-medium">Empleador:</span> <?= e($exp['empleador']) ?>
                </div>
                <?php endif; ?>
                <?php if (!empty($exp['tareas'])): ?>
                <div class="mt-2 pt-2 border-t border-gray-200">
                  <p class="text-xs text-gray-700 leading-relaxed"><?= e(mb_strimwidth($exp['tareas'], 0, 150, '...')) ?></p>
                </div>
                <?php endif; ?>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>

          <!-- Habilidades y Disponibilidad -->
          <?php if ($habilidades_disponibilidad): ?>
          <div class="pt-4 border-t border-gray-200">
            <div class="flex items-center gap-2 mb-3">
              <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
              <h4 class="text-sm font-bold text-gray-900">Habilidades</h4>
            </div>
            
            <div class="space-y-3">
              <!-- Antecedentes -->
              <div class="flex items-start gap-2">
                <svg class="w-4 h-4 text-gray-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
                <div>
                  <p class="text-xs font-medium text-gray-700 mb-1">Antecedentes</p>
                  <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium 
                    <?= strtolower($habilidades_disponibilidad['antecedentes_penales']) === 'no' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' ?>">
                    <?= strtolower($habilidades_disponibilidad['antecedentes_penales']) === 'no' ? 'Sin antecedentes' : 'Con antecedentes' ?>
                  </span>
                </div>
              </div>

              <!-- Licencias -->
              <div class="flex items-start gap-2">
                <svg class="w-4 h-4 text-gray-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"/>
                </svg>
                <div>
                  <p class="text-xs font-medium text-gray-700 mb-1">Licencias</p>
                  <?php if (!empty($habilidades_disponibilidad['licencia_conducir'])): ?>
                  <div class="flex flex-wrap gap-1">
                    <?php 
                    $licencias = explode(',', $habilidades_disponibilidad['licencia_conducir']);
                    foreach ($licencias as $lic): 
                      $lic = trim($lic);
                    ?>
                      <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800">
                        <?= e($lic) ?>
                      </span>
                    <?php endforeach; ?>
                  </div>
                  <?php else: ?>
                  <span class="text-xs text-gray-500">Sin licencias</span>
                  <?php endif; ?>
                  
                  <?php if (!empty($habilidades_disponibilidad['otras_licencias'])): ?>
                  <p class="text-xs text-gray-600 mt-1"><?= e($habilidades_disponibilidad['otras_licencias']) ?></p>
                  <?php endif; ?>
                </div>
              </div>

              <!-- Disponibilidad -->
              <div class="flex items-start gap-2">
                <svg class="w-4 h-4 text-gray-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <div>
                  <p class="text-xs font-medium text-gray-700 mb-1">Disponibilidad</p>
                  <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium 
                    <?php 
                      switch($habilidades_disponibilidad['disponibilidad']) {
                        case 'inmediata': echo 'bg-blue-100 text-blue-800'; break;
                        case '15_dias': echo 'bg-orange-100 text-orange-800'; break;
                        case '30_dias': echo 'bg-red-100 text-red-800'; break;
                        default: echo 'bg-gray-100 text-gray-800';
                      }
                    ?>">
                    <?php 
                      switch($habilidades_disponibilidad['disponibilidad']) {
                        case 'inmediata': echo 'Inmediata'; break;
                        case '15_dias': echo '15 días'; break;
                        case '30_dias': echo '30 días'; break;
                        default: echo $habilidades_disponibilidad['disponibilidad'];
                      }
                    ?>
                  </span>
                </div>
              </div>
            </div>
          </div>
          <?php endif; ?>

          <!-- Fecha de registro -->
          <div class="pt-4 border-t border-gray-200">
            <?php 
            // Convertir fecha a zona horaria de Argentina
            $fecha_registro = new DateTime($candidato['fecha_carga'], new DateTimeZone('UTC'));
            $fecha_registro->setTimezone(new DateTimeZone('America/Argentina/Buenos_Aires'));
            ?>
            <p class="text-xs text-gray-500">Registrado: <?= $fecha_registro->format('d/m/Y H:i') ?></p>
          </div>
        </div>
      </div>
    </div>

    <!-- Formulario de edición -->
    <div class="lg:col-span-2">
      <div class="bg-white/70 backdrop-blur-sm rounded-2xl shadow-xl border border-gray-200/50">
        <div class="bg-gradient-to-r from-blue-500 to-purple-600 p-6 rounded-t-2xl">
          <h2 class="text-xl font-bold text-white mb-2">Editar Información del Candidato</h2>
          <p class="text-blue-100">Modifica los datos según sea necesario</p>
        </div>

        <form method="post" class="p-6">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="update_candidato">

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Información básica -->
            <div class="md:col-span-2">
              <h3 class="text-lg font-semibold text-gray-900 mb-4 border-b border-gray-200 pb-2">Información Básica</h3>
            </div>

            <div class="space-y-2">
              <label for="nombre" class="block text-sm font-semibold text-gray-700">Nombre completo</label>
              <input type="text" id="nombre" name="nombre" value="<?= e($candidato['nombre']) ?>" required maxlength="100"
                     class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>

            <div class="space-y-2">
              <label for="email" class="block text-sm font-semibold text-gray-700">Email</label>
              <input type="email" id="email" name="email" value="<?= e($candidato['email']) ?>" required maxlength="150"
                     class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>

            <div class="space-y-2">
              <label for="telefono" class="block text-sm font-semibold text-gray-700">Teléfono</label>
              <input type="text" id="telefono" name="telefono" value="<?= e($candidato['telefono']) ?>" required maxlength="30"
                     class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>

            <div class="space-y-2">
              <label for="experiencia" class="block text-sm font-semibold text-gray-700">Años de experiencia</label>
              <input type="number" id="experiencia" name="experiencia" value="<?= e($candidato['experiencia']) ?>" required min="0" max="50"
                     class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>

            <!-- Áreas profesionales -->
            <div class="md:col-span-2 space-y-2">
              <label class="block text-sm font-semibold text-gray-700">Áreas profesionales (máx. 2)</label>
              <div class="grid grid-cols-2 md:grid-cols-3 gap-3 max-h-32 overflow-y-auto border border-gray-300 rounded-lg p-4 bg-gray-50">
                <?php 
                $areas_ids = array_column($areas_candidato, 'id');
                foreach ($todas_areas as $area): ?>
                  <label class="flex items-center space-x-2 cursor-pointer hover:bg-white p-2 rounded transition-colors">
                    <input type="checkbox" name="areas[]" value="<?= $area['id'] ?>" 
                           <?= in_array($area['id'], $areas_ids) ? 'checked' : '' ?>
                           class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500">
                    <span class="text-sm text-gray-700"><?= e($area['nombre']) ?></span>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>

            <!-- Especialidades del candidato -->
            <?php if (!empty($especialidades_candidato)): ?>
            <div class="md:col-span-2 space-y-3">
              <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <div class="flex items-start gap-3">
                  <svg class="w-5 h-5 text-blue-600 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                  </svg>
                  <div class="flex-1">
                    <h4 class="text-sm font-semibold text-blue-900 mb-2">Especialidades Seleccionadas</h4>
                    <div class="space-y-3">
                      <?php foreach ($especialidades_por_area as $area_id => $area_data): ?>
                        <div class="space-y-1">
                          <p class="text-xs font-medium text-blue-800"><?= e($area_data['area_nombre']) ?></p>
                          <div class="ml-3 space-y-1">
                            <?php foreach ($area_data['especialidades'] as $esp): ?>
                              <div class="flex items-center gap-2 text-xs">
                                <svg class="w-3 h-3 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                                <span class="text-gray-700"><?= e($esp['especialidad_nombre']) ?></span>
                                <?php if (!empty($esp['nivel_nombre'])): ?>
                                  <span class="px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800"
                                        title="<?= !empty($esp['nivel_descripcion']) ? e($esp['nivel_descripcion']) : '' ?>">
                                    <?= e($esp['nivel_nombre']) ?>
                                  </span>
                                <?php endif; ?>
                              </div>
                            <?php endforeach; ?>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <?php endif; ?>

            <!-- Información demográfica -->
            <?php if ($has_demographic_fields): ?>
            <div class="md:col-span-2 mt-8">
              <h3 class="text-lg font-semibold text-gray-900 mb-4 border-b border-gray-200 pb-2">Información Demográfica</h3>
            </div>

            <div class="space-y-2">
              <label for="dni" class="block text-sm font-semibold text-gray-700">DNI</label>
              <input type="text" id="dni" name="dni" value="<?= e($candidato['dni'] ?? '') ?>" required maxlength="20"
                     class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>

            <div class="space-y-2">
              <label for="edad" class="block text-sm font-semibold text-gray-700">Edad</label>
    <input type="number" id="edad" name="edad" value="<?= e($candidato['edad'] ?? '') ?>" required min="19" max="79"
                     class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>

            <div class="space-y-2">
              <label for="estado_civil" class="block text-sm font-semibold text-gray-700">Estado Civil</label>
              <select id="estado_civil" name="estado_civil" required
                      class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <option value="">Seleccionar...</option>
                <option value="Soltero" <?= ($candidato['estado_civil'] ?? '') === 'Soltero' ? 'selected' : '' ?>>Soltero/a</option>
                <option value="Casado" <?= ($candidato['estado_civil'] ?? '') === 'Casado' ? 'selected' : '' ?>>Casado/a</option>
                <option value="Divorciado" <?= ($candidato['estado_civil'] ?? '') === 'Divorciado' ? 'selected' : '' ?>>Divorciado/a</option>
                <option value="Viudo" <?= ($candidato['estado_civil'] ?? '') === 'Viudo' ? 'selected' : '' ?>>Viudo/a</option>
                <option value="Unión libre" <?= ($candidato['estado_civil'] ?? '') === 'Unión libre' ? 'selected' : '' ?>>Unión libre</option>
              </select>
            </div>

            <div class="space-y-2">
              <label for="hijos" class="block text-sm font-semibold text-gray-700">Número de hijos</label>
              <input type="number" id="hijos" name="hijos" value="<?= e($candidato['hijos'] ?? '0') ?>" required min="0" max="20"
                     class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>

            <div class="space-y-2 md:col-span-2">
              <label for="edad_hijos" class="block text-sm font-semibold text-gray-700">Edades de los hijos</label>
              <input type="text" id="edad_hijos" name="edad_hijos" value="<?= e($candidato['edad_hijos'] ?? '') ?>" maxlength="255"
                     class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
              <p class="text-xs text-gray-500 mt-1">Separa las edades con comas</p>
            </div>

            <div class="space-y-2 md:col-span-2">
              <label for="nacionalidad" class="block text-sm font-semibold text-gray-700">Nacionalidad</label>
              <input type="text" id="nacionalidad" name="nacionalidad" value="<?= e($candidato['nacionalidad'] ?? '') ?>" required maxlength="255"
                     class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>

            <div class="space-y-2 md:col-span-2">
              <label for="lugar_residencia" class="block text-sm font-semibold text-gray-700">Lugar de Residencia</label>
              <input type="text" id="lugar_residencia" name="lugar_residencia" value="<?= e($candidato['lugar_residencia'] ?? '') ?>" required maxlength="255"
                     class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>

            <div class="space-y-2">
              <label for="ocupacion_actual" class="block text-sm font-semibold text-gray-700">Ocupación Actual</label>
              <input type="text" id="ocupacion_actual" name="ocupacion_actual" value="<?= e($candidato['ocupacion_actual'] ?? '') ?>" required maxlength="100"
                     class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>

            <div class="space-y-2">
              <label for="ocupacion_padre" class="block text-sm font-semibold text-gray-700">Ocupación del Padre</label>
              <input type="text" id="ocupacion_padre" name="ocupacion_padre" value="<?= e($candidato['ocupacion_padre'] ?? '') ?>" required maxlength="100"
                     class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>

            <div class="space-y-2">
              <label for="ocupacion_madre" class="block text-sm font-semibold text-gray-700">Ocupación de la Madre</label>
              <input type="text" id="ocupacion_madre" name="ocupacion_madre" value="<?= e($candidato['ocupacion_madre'] ?? '') ?>" required maxlength="100"
                     class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>

            <!-- Formación Profesional -->
            <?php if ($has_formation_fields): ?>
            <div class="md:col-span-2 mt-8">
              <h3 class="text-lg font-semibold text-gray-900 mb-4 border-b border-gray-200 pb-2">Formación Profesional</h3>
            </div>

            <div class="space-y-2 md:col-span-2">
              <label for="nivel_educativo" class="block text-sm font-semibold text-gray-700">Nivel Educativo</label>
              <select id="nivel_educativo" name="nivel_educativo" required
                      class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <option value="">Seleccionar...</option>
                <option value="Primaria" <?= ($formacion_profesional['nivel_educativo'] ?? '') === 'Primaria' ? 'selected' : '' ?>>Primaria</option>
                <option value="Secundaria" <?= ($formacion_profesional['nivel_educativo'] ?? '') === 'Secundaria' ? 'selected' : '' ?>>Secundaria</option>
                <option value="Universitaria" <?= ($formacion_profesional['nivel_educativo'] ?? '') === 'Universitaria' ? 'selected' : '' ?>>Universitaria</option>
              </select>
            </div>

            <div class="space-y-2 md:col-span-2">
              <label for="carreras_titulos" class="block text-sm font-semibold text-gray-700">Carreras y Títulos</label>
              <textarea id="carreras_titulos" name="carreras_titulos" rows="3" maxlength="1000"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        placeholder="Ej: Ingeniería en Sistemas, Licenciatura en Administración..."><?= e($formacion_profesional['carreras_titulos'] ?? '') ?></textarea>
              <p class="text-xs text-gray-500 mt-1">Separa las carreras y títulos con comas si es necesario.</p>
            </div>

            <div class="space-y-2 md:col-span-2">
              <label for="cursos_capacitaciones" class="block text-sm font-semibold text-gray-700">Cursos y Capacitaciones</label>
              <textarea id="cursos_capacitaciones" name="cursos_capacitaciones" rows="3" maxlength="1000"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        placeholder="Ej: Curso de Marketing Digital, Certificación en Python..."><?= e($formacion_profesional['cursos_capacitaciones'] ?? '') ?></textarea>
              <p class="text-xs text-gray-500 mt-1">Menciona cursos, talleres o certificaciones que hayas realizado.</p>
            </div>
            <?php endif; ?>
            <?php endif; ?>

            <!-- Estado y observaciones -->
            <div class="md:col-span-2 mt-8">
              <h3 class="text-lg font-semibold text-gray-900 mb-4 border-b border-gray-200 pb-2">Estado y Observaciones</h3>
            </div>

            <div class="space-y-2">
              <label for="estado_id" class="block text-sm font-semibold text-gray-700">Estado del Candidato</label>
              <select id="estado_id" name="estado_id" required
                      class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <?php foreach ($estados as $estado): ?>
                  <option value="<?= $estado['id'] ?>" <?= $candidato['estado_id'] == $estado['id'] ? 'selected' : '' ?>>
                    <?= e($estado['nombre']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="md:col-span-2 space-y-2">
              <label for="observaciones" class="block text-sm font-semibold text-gray-700">Observaciones</label>
              <textarea id="observaciones" name="observaciones" rows="4" maxlength="1000"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        placeholder="Observaciones adicionales sobre el candidato..."><?= e($candidato['observaciones']) ?></textarea>
            </div>

            <?php if (!$has_demographic_fields): ?>
            <div class="md:col-span-2 mt-8">
              <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <div class="flex items-center gap-2">
                  <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                  </svg>
                  <p class="text-sm font-medium text-yellow-800">
                    Los campos demográficos adicionales no están disponibles. 
                    <span class="block text-xs text-yellow-700 mt-1">
                      Ejecuta las migraciones de base de datos para acceder a la información completa del candidato.
                    </span>
                  </p>
                </div>
              </div>
            </div>
            <?php endif; ?>
          </div>

          <div class="mt-8 flex justify-end gap-4">
            <a href="dashboard.php" 
               class="px-6 py-3 bg-gray-300 hover:bg-gray-400 text-gray-700 font-semibold rounded-lg transition-colors duration-200">
              Cancelar
            </a>
            <button type="submit" 
                    class="px-6 py-3 bg-gradient-to-r from-blue-500 to-purple-600 hover:from-blue-600 hover:to-purple-700 text-white font-semibold rounded-lg shadow-lg hover:shadow-xl transition-all duration-200">
              Guardar Cambios
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</main>

<script>
// Limitar selección de áreas a máximo 2
const areaCheckboxes = document.querySelectorAll('input[name="areas[]"]');
areaCheckboxes.forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        const checkedBoxes = document.querySelectorAll('input[name="areas[]"]:checked');
    if (checkedBoxes.length > 2) {
      this.checked = false;
      Swal.fire({ text: 'Solo puedes seleccionar hasta 2 áreas profesionales.', icon: 'warning', toast: true, position: 'top', showConfirmButton: false, timer: 2500 });
    }
    });
});

// Mostrar/ocultar campo de edades de hijos
const hijosInput = document.getElementById('hijos');
const edadHijosInput = document.getElementById('edad_hijos');

if (hijosInput && edadHijosInput) {
    hijosInput.addEventListener('change', function() {
        const numHijos = parseInt(this.value) || 0;
        if (numHijos > 0) {
            edadHijosInput.required = true;
        } else {
            edadHijosInput.required = false;
            edadHijosInput.value = '';
        }
    });
}

// Función para generar PDF profesional
function generarPDFProfesional() {
  Swal.fire({
    title: 'Generando PDF...',
    text: 'Por favor espera mientras se genera el CV en formato PDF profesional.',
    allowOutsideClick: false,
    allowEscapeKey: false,
    showConfirmButton: false,
    willOpen: () => {
      Swal.showLoading();
    }
  });

  // Crear formulario para enviar datos de forma segura
  const form = document.createElement('form');
  form.method = 'POST';
  form.action = 'generar_pdf.php?id=<?= $candidato_id ?>';
  form.style.display = 'none';
  
  // Agregar token CSRF
  const csrfInput = document.createElement('input');
  csrfInput.type = 'hidden';
  csrfInput.name = 'csrf';
  csrfInput.value = '<?= e(csrf_token()) ?>';
  form.appendChild(csrfInput);
  
  document.body.appendChild(form);
  
  // Crear iframe oculto para la descarga
  const iframe = document.createElement('iframe');
  iframe.style.display = 'none';
  iframe.onload = function() {
    setTimeout(() => {
      Swal.close();
      document.body.removeChild(form);
      document.body.removeChild(iframe);
    }, 1000);
  };
  
  document.body.appendChild(iframe);
  
  // Establecer el target del formulario al iframe
  form.target = iframe.name = 'downloadFrame_' + Date.now();
  iframe.name = form.target;
  
  // Enviar formulario
  form.submit();
}

// Función para imprimir CV (impresión web tradicional)
function imprimirCV() {
  Swal.fire({
    title: 'Preparando impresión...',
    text: 'Se abrirá la vista previa de impresión del navegador.',
    icon: 'info',
    showConfirmButton: true,
    confirmButtonText: 'Continuar',
    showCancelButton: true,
    cancelButtonText: 'Cancelar'
  }).then((result) => {
    if (result.isConfirmed) {
      setTimeout(function() {
        window.print();
      }, 100);
    }
  });
}

// Atajo de teclado para imprimir (Ctrl+P)
document.addEventListener('keydown', function(e) {
  if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
    e.preventDefault();
    imprimirCV();
  }
});
</script>

<!-- Modal para ver imagen del candidato en tamaño completo -->
<div id="image-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/80" style="backdrop-filter: blur(4px);">
  <div class="relative max-w-[100vw] max-h-[100vh] flex items-center justify-center" style="padding:20px;">
    <button id="image-modal-close" class="absolute top-2 right-2 text-white bg-black/50 rounded-full p-2 hover:bg-black/60" style="z-index:60">✕</button>
    <div style="max-width:100%; max-height:100%; overflow:auto; display:flex; align-items:center; justify-content:center;">
      <img id="image-modal-img" src="" alt="Imagen candidato" style="max-width:calc(100vw - 80px); max-height:calc(100vh - 80px); width:auto; height:auto; object-fit:contain; border-radius:8px; box-shadow:0 10px 30px rgba(0,0,0,0.5); cursor:zoom-out;" />
    </div>
  </div>
</div>

<script>
  (function(){
    const modal = document.getElementById('image-modal');
    const modalImg = document.getElementById('image-modal-img');
    const closeBtn = document.getElementById('image-modal-close');

    function openModal(src, alt){
      modalImg.classList.remove('fullsize');
      modalImg.style.cursor = 'zoom-out';
      modalImg.src = src;
      modalImg.alt = alt || '';
      modal.classList.remove('hidden');
      modal.classList.add('flex');
      document.body.style.overflow = 'hidden';
    }

    function closeModal(){
      modal.classList.add('hidden');
      modal.classList.remove('flex');
      modalImg.src = '';
      document.body.style.overflow = '';
    }

    function toggleFullsize(){
      if (modalImg.classList.contains('fullsize')){
        modalImg.classList.remove('fullsize');
        modalImg.style.maxWidth = 'calc(100vw - 80px)';
        modalImg.style.maxHeight = 'calc(100vh - 80px)';
        modalImg.style.cursor = 'zoom-out';
      } else {
        modalImg.classList.add('fullsize');
        modalImg.style.maxWidth = 'none';
        modalImg.style.maxHeight = 'none';
        modalImg.style.cursor = 'zoom-in';
      }
    }

    // Delegación: todos los imgs con class candidate-photo
    document.querySelectorAll('img.candidate-photo').forEach(img => {
      img.style.cursor = 'zoom-in';
      img.addEventListener('click', function(e){
        const src = this.getAttribute('src');
        const alt = this.getAttribute('alt') || '';
        openModal(src, alt);
      });
    });

    // Doble clic en la imagen para alternar tamaño completo
    modalImg.addEventListener('dblclick', function(e){
      toggleFullsize();
    });

    modal.addEventListener('click', function(e){
      if (e.target === modal || e.target === closeBtn) closeModal();
    });

    document.addEventListener('keydown', function(e){
      if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
        closeModal();
      }
    });
  })();
</script>

</body>
</html>