<?php
require_once __DIR__ . '/../init.php';
require_admin();

// DEBUG: mostrar errores durante la investigación (retirar en producción)
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Generar token CSRF
$csrf_token = csrf_token();

// Manejo de acciones AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
        exit;
    }
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'cambiar_estado') {
        $candidato_id = safe_int($_POST['candidato_id'] ?? 0);
        $estado_id = safe_int($_POST['estado_id'] ?? 0);
        
        if ($candidato_id <= 0 || $estado_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
            exit;
        }
        
        try {
            // Verificar que el estado existe
            $stmt = $pdo->prepare("SELECT id FROM estados_cv WHERE id = ?");
            $stmt->execute([$estado_id]);
            if (!$stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Estado no válido']);
                exit;
            }
            
            // Actualizar el estado del candidato
            $stmt = $pdo->prepare("UPDATE candidatos SET estado_id = ? WHERE id = ?");
            $stmt->execute([$estado_id, $candidato_id]);
            
            echo json_encode(['success' => true, 'message' => 'Estado actualizado correctamente']);
        } catch (Exception $e) {
            error_log("Error al cambiar estado: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
        }
        exit;
    }
    
    if ($action === 'eliminar_candidato') {
        $candidato_id = safe_int($_POST['candidato_id'] ?? 0);
        
        if ($candidato_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID de candidato inválido']);
            exit;
        }
        
        try {
            // Obtener información del candidato para eliminar archivos
            $stmt = $pdo->prepare("SELECT foto_ruta FROM candidatos WHERE id = ?");
            $stmt->execute([$candidato_id]);
            $candidato = $stmt->fetch();
            
            if (!$candidato) {
                echo json_encode(['success' => false, 'message' => 'Candidato no encontrado']);
                exit;
            }
            
            // Eliminar archivos físicos
            if ($candidato['foto_ruta'] && file_exists(__DIR__ . '/../uploads/' . $candidato['foto_ruta'])) {
                unlink(__DIR__ . '/../uploads/' . $candidato['foto_ruta']);
            }
            
            // Eliminar registro de la base de datos
            $stmt = $pdo->prepare("DELETE FROM candidatos WHERE id = ?");
            $stmt->execute([$candidato_id]);
            
            echo json_encode(['success' => true, 'message' => 'Candidato eliminado correctamente']);
        } catch (Exception $e) {
            error_log("Error al eliminar candidato: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
        }
        exit;
    }
    
    echo json_encode(['success' => false, 'message' => 'Acción no válida']);
    exit;
}

// Filtros
$q = safe_trim($_GET['q'] ?? '');
$area = safe_trim($_GET['area'] ?? '');
$lugar_residencia = safe_trim($_GET['lugar_residencia'] ?? '');
$estado_id = ($_GET['estado_id'] ?? '') !== '' ? safe_int($_GET['estado_id']) : null;
$exp_min = ($_GET['exp_min'] ?? '') !== '' ? safe_int($_GET['exp_min']) : null;
$exp_max = ($_GET['exp_max'] ?? '') !== '' ? safe_int($_GET['exp_max']) : null;
$desde = safe_trim($_GET['desde'] ?? '');
$hasta = safe_trim($_GET['hasta'] ?? '');

$order = $_GET['orden'] ?? 'fecha_carga';
$dir = strtolower($_GET['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
$allowed_order = ['fecha_carga','experiencia','nombre'];
if (!in_array($order, $allowed_order, true)) $order = 'fecha_carga';

$page = max(1, safe_int($_GET['page'] ?? 1));
$per_page = safe_int($_GET['per_page'] ?? 20);
// Validar que per_page sea uno de los valores permitidos
if (!in_array($per_page, [10, 20, 50, 100], true)) {
    $per_page = 20;
}
$offset = ($page - 1) * $per_page;

$where = [];
$params = [];

if ($q !== '') {
  $where[] = '(c.nombre LIKE ? OR c.email LIKE ? OR c.telefono LIKE ?)';
  $like = '%' . $q . '%';
  $params[] = $like; $params[] = $like; $params[] = $like;
}
if ($lugar_residencia !== '') {
    $where[] = 'c.lugar_residencia LIKE ?';
    $params[] = '%' . $lugar_residencia . '%';
}
if ($area !== '') {
    $where[] = 'ap.nombre = ?';
    $params[] = $area;
}
if ($estado_id !== null) {
    $where[] = 'c.estado_id = ?';
    $params[] = $estado_id;
}
if ($exp_min !== null) {
    $where[] = 'experiencia >= ?';
    $params[] = $exp_min;
}
if ($exp_max !== null) {
    $where[] = 'experiencia <= ?';
    $params[] = $exp_max;
}
if ($desde !== '' && valid_date($desde)) {
    $where[] = 'fecha_carga >= ?';
    $params[] = $desde . ' 00:00:00';
}
if ($hasta !== '' && valid_date($hasta)) {
    $where[] = 'fecha_carga <= ?';
    $params[] = $hasta . ' 23:59:59';
}

$where_clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Obtener áreas para el filtro
$areas_stmt = $pdo->query("SELECT nombre FROM areas_profesionales ORDER BY orden, nombre");
$areas = $areas_stmt->fetchAll(PDO::FETCH_COLUMN);

// Obtener estados para el filtro
$estados_stmt = $pdo->query("SELECT id, nombre, color FROM estados_cv WHERE activo = 1 ORDER BY orden, nombre");
$estados = $estados_stmt->fetchAll();

// Query principal - ahora obtenemos las áreas de la tabla de relación y datos de habilidades/disponibilidad
$sql = "SELECT c.id, c.nombre, c.email, c.telefono, c.experiencia, c.dni, c.edad, c.estado_civil, c.nacionalidad, c.lugar_residencia,
               c.foto_nombre_original, c.foto_ruta, c.fecha_carga, c.fecha_estado, c.observaciones,
               COALESCE(c.estado_id, 1) as estado_id, 
               COALESCE(e.nombre, 'Pendiente') as estado_nombre, 
               COALESCE(e.color, '#f59e0b') as estado_color,
               GROUP_CONCAT(ap.nombre ORDER BY ap.nombre SEPARATOR ', ') as areas_profesionales,
               hd.antecedentes_penales,
               hd.licencia_conducir,
               hd.disponibilidad,
               COUNT(DISTINCT el.id) as experiencias_count
        FROM candidatos c 
        LEFT JOIN estados_cv e ON c.estado_id = e.id
        LEFT JOIN candidato_areas ca ON c.id = ca.candidato_id
        LEFT JOIN areas_profesionales ap ON ca.area_profesional_id = ap.id
        LEFT JOIN habilidades_disponibilidad hd ON c.id = hd.candidato_id
        LEFT JOIN experiencia_laboral el ON c.id = el.candidato_id
        $where_clause 
        GROUP BY c.id
        ORDER BY c.$order $dir 
        LIMIT $per_page OFFSET $offset";

try {
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $rows = $stmt->fetchAll();
} catch (Exception $e) {
  error_log("Error en consulta principal dashboard: " . $e->getMessage());
  echo "<h2>Error en consulta SQL</h2>";
  echo "<p><strong>Mensaje:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
  echo "<h3>SQL ejecutada</h3>";
  echo "<pre style='background:#f8f8f8;padding:10px;overflow:auto;'>" . htmlspecialchars($sql) . "</pre>";
  echo "<h3>Parámetros</h3>";
  echo "<pre style='background:#f8f8f8;padding:10px;overflow:auto;'>" . htmlspecialchars(var_export($params, true)) . "</pre>";
  exit;
}

// Contar total para paginación
$count_sql = "SELECT COUNT(DISTINCT c.id) FROM candidatos c 
              LEFT JOIN estados_cv e ON c.estado_id = e.id 
              LEFT JOIN candidato_areas ca ON c.id = ca.candidato_id
              LEFT JOIN areas_profesionales ap ON ca.area_profesional_id = ap.id
              $where_clause";
$count_stmt = $pdo->prepare($count_sql);
try {
  $count_stmt->execute($params);
  $total = $count_stmt->fetchColumn();
} catch (Exception $e) {
  error_log("Error en consulta de conteo dashboard: " . $e->getMessage());
  echo "<h2>Error en consulta SQL (conteo)</h2>";
  echo "<p><strong>Mensaje:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
  echo "<h3>SQL ejecutada</h3>";
  echo "<pre style='background:#f8f8f8;padding:10px;overflow:auto;'>" . htmlspecialchars($count_sql) . "</pre>";
  echo "<h3>Parámetros</h3>";
  echo "<pre style='background:#f8f8f8;padding:10px;overflow:auto;'>" . htmlspecialchars(var_export($params, true)) . "</pre>";
  exit;
}
$total_pages = ceil($total / $per_page);

function build_url($new_params = []) {
    $current = $_GET;
    foreach ($new_params as $k => $v) {
        if ($v === null || $v === '') {
            unset($current[$k]);
        } else {
            $current[$k] = $v;
        }
    }
    return 'dashboard.php' . ($current ? '?' . http_build_query($current) : '');
}

function sort_link($field, $label) {
    global $order, $dir;
    $new_dir = ($order === $field && $dir === 'ASC') ? 'desc' : 'asc';
    $url = build_url(['orden' => $field, 'dir' => $new_dir, 'page' => null]);
    $arrow = '';
    if ($order === $field) {
        $arrow = $dir === 'ASC' ? ' ↑' : ' ↓';
    }
    return "<a href='$url' class='hover:text-blue-600 transition-colors'>$label$arrow</a>";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard - Panel de Administración</title>
  <link rel="icon" type="image/x-icon" href="../assets/images/logo.ico">
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
    body { font-family: 'Inter', sans-serif; }
    
    /* Animaciones personalizadas */
    @keyframes slideIn {
      from { transform: translateY(-10px); opacity: 0; }
      to { transform: translateY(0); opacity: 1; }
    }
    
    .slide-in {
      animation: slideIn 0.3s ease-out;
    }
    
    /* Efecto hover para tarjetas */
    .card-hover {
      transition: all 0.3s ease;
    }
    
    .card-hover:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 25px rgba(0,0,0,0.1);
    }
  </style>
</head>
<body class="bg-gradient-to-br from-slate-50 to-blue-50 min-h-screen">
  
<header class="bg-white/90 backdrop-blur-md border-b border-gray-200 shadow-sm sticky top-0 z-50">
  <div class="max-w-7xl mx-auto px-4 py-4">
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-4">
        <img src="../assets/images/logo_fg.png" alt="Logo" class="h-10 w-auto">
        <div>
          <h1 class="text-2xl font-bold text-gray-900 bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent">Panel de Administración</h1>
          <p class="text-sm text-gray-600">Gestión de candidatos registrados</p>
        </div>
      </div>
      
      <nav class="hidden md:flex items-center space-x-1">
        <a 
          href="dashboard.php"
          class="bg-gradient-to-r from-blue-500 to-purple-600 text-white px-4 py-2 rounded-lg font-medium shadow-md hover:shadow-lg transform hover:-translate-y-0.5 transition-all duration-200"
        >
          <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
          </svg>
          Dashboard
        </a>
        <a 
          href="areas.php"
          class="text-gray-600 hover:text-gray-800 px-4 py-2 rounded-lg font-medium transition-colors duration-200 hover:bg-gray-100"
        >
          <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
          </svg>
          Gestionar Áreas
        </a>
        <a 
          href="change-password.php"
          class="text-gray-600 hover:text-gray-800 px-4 py-2 rounded-lg font-medium transition-colors duration-200 hover:bg-gray-100"
        >
          <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m0 0a2 2 0 012 2m-2-2a2 2 0 00-2 2m2-2a2 2 0 01-2-2M9 7v6a2 2 0 002 2h6M9 7V5a2 2 0 012-2h4a2 2 0 012 2v2M9 7H7a2 2 0 00-2 2v8a2 2 0 002 2h8a2 2 0 002-2V9a2 2 0 00-2-2"/>
          </svg>
          Cambiar Contraseña
        </a>
        <a 
          href="logout.php"
          class="text-red-600 hover:text-red-800 px-4 py-2 rounded-lg font-medium transition-colors duration-200 hover:bg-red-50"
        >
          <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
          </svg>
          Cerrar Sesión
        </a>
      </nav>

      <!-- Menú móvil -->
      <div class="md:hidden">
        <button onclick="toggleMobileMenu()" class="text-gray-600 hover:text-gray-800 p-2 rounded-lg hover:bg-gray-100 transition-colors">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
          </svg>
        </button>
      </div>
    </div>

    <!-- Menú móvil desplegable -->
    <div id="mobileMenu" class="hidden md:hidden mt-4 space-y-2 slide-in">
      <a href="dashboard.php" class="block bg-gradient-to-r from-blue-500 to-purple-600 text-white px-4 py-2 rounded-lg font-medium">
        <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
        </svg>
        Dashboard
      </a>
      <a href="areas.php" class="block text-gray-600 hover:text-gray-800 px-4 py-2 rounded-lg font-medium hover:bg-gray-100">Gestionar Áreas</a>
      <?php if (is_admin()): ?>
        <a href="usuarios.php" class="block text-gray-600 hover:text-gray-800 px-4 py-2 rounded-lg font-medium hover:bg-gray-100">Gestionar Usuarios</a>
      <?php endif; ?>
      <a href="change-password.php" class="block text-gray-600 hover:text-gray-800 px-4 py-2 rounded-lg font-medium hover:bg-gray-100">Cambiar Contraseña</a>
      <a href="logout.php" class="block text-red-600 hover:text-red-800 px-4 py-2 rounded-lg font-medium hover:bg-red-50">Cerrar Sesión</a>
    </div>
  </div>
</header>

<main class="max-w-7xl mx-auto px-4 py-8">
  
  <!-- Estadísticas rápidas -->
  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-200 card-hover">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-sm font-medium text-gray-600">Total Candidatos</p>
          <p class="text-3xl font-bold text-gray-900"><?= number_format($total) ?></p>
          <p class="text-xs text-gray-500 mt-1">Candidatos registrados</p>
        </div>
        <div class="bg-blue-100 rounded-full p-3">
          <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
          </svg>
        </div>
      </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-200 card-hover">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-sm font-medium text-gray-600">Candidatos Hoy</p>
          <?php 
          $today_stmt = $pdo->prepare("SELECT COUNT(*) FROM candidatos WHERE DATE(fecha_carga) = CURDATE()");
          $today_stmt->execute();
          $today_count = $today_stmt->fetchColumn();
          ?>
          <p class="text-3xl font-bold text-gray-900"><?= $today_count ?></p>
          <p class="text-xs text-gray-500 mt-1">Enviados hoy</p>
        </div>
        <div class="bg-green-100 rounded-full p-3">
          <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
          </svg>
        </div>
      </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-200 card-hover">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-sm font-medium text-gray-600">Esta Semana</p>
          <?php 
          $week_stmt = $pdo->prepare("SELECT COUNT(*) FROM candidatos WHERE fecha_carga >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
          $week_stmt->execute();
          $week_count = $week_stmt->fetchColumn();
          ?>
          <p class="text-3xl font-bold text-gray-900"><?= $week_count ?></p>
          <p class="text-xs text-gray-500 mt-1">Últimos 7 días</p>
        </div>
        <div class="bg-purple-100 rounded-full p-3">
          <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
          </svg>
        </div>
      </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-200 card-hover">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-sm font-medium text-gray-600">Áreas Activas</p>
          <p class="text-3xl font-bold text-gray-900"><?= count($areas) ?></p>
          <p class="text-xs text-gray-500 mt-1">Áreas profesionales</p>
        </div>
        <div class="bg-orange-100 rounded-full p-3">
          <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
          </svg>
        </div>
      </div>
    </div>
  </div>

  <!-- Botón para crear usuario (solo administradores) -->
  <?php if (is_admin()): ?>
    <div class="mb-8">
      <div class="bg-gradient-to-r from-purple-500 to-pink-600 rounded-xl shadow-lg p-6 border border-purple-200">
        <div class="flex items-center justify-between">
          <div>
            <h3 class="text-lg font-bold text-white mb-2">Gestión de Usuarios</h3>
            <p class="text-purple-100 text-sm">Como administrador, puedes crear y gestionar usuarios del sistema</p>
          </div>
          <div class="flex gap-3">
            <a 
              href="usuarios.php"
              class="bg-white/20 hover:bg-white/30 text-white font-medium py-2 px-4 rounded-lg transition-all duration-200 flex items-center gap-2 backdrop-blur-sm"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/>
              </svg>
              Ver Usuarios
            </a>
            <button 
              onclick="mostrarModalCrearUsuario()"
              class="bg-white hover:bg-gray-100 text-purple-600 font-medium py-2 px-4 rounded-lg shadow-md hover:shadow-lg transform hover:-translate-y-0.5 transition-all duration-200 flex items-center gap-2"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
              </svg>
              Crear Usuario
            </button>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <!-- Filtros -->
  <div class="bg-white rounded-xl shadow-lg p-6 mb-8 border border-gray-200">
    <div class="flex items-center gap-3 mb-4">
      <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.414A1 1 0 013 6.707V4z"/>
      </svg>
      <h2 class="text-xl font-bold text-gray-900">Filtros de Búsqueda</h2>
    </div>
    
    <form method="GET" class="space-y-4">
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
        
        <div class="md:col-span-2">
          <label for="q" class="block text-sm font-medium text-gray-700 mb-2">
            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            Búsqueda general
          </label>
          <input 
            type="text" 
            id="q" 
            name="q" 
            value="<?= e($q) ?>" 
            placeholder="Nombre, email o teléfono..."
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200"
          >
        </div>

        <div>
          <label for="lugar_residencia" class="block text-sm font-medium text-gray-700 mb-2">
            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857"/>
            </svg>
            Lugar de Residencia
          </label>
          <input
            type="text"
            id="lugar_residencia"
            name="lugar_residencia"
            value="<?= e($lugar_residencia) ?>"
            placeholder="Ej: Posadas, Misiones"
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200"
          >
        </div>

        <div>
          <label for="area" class="block text-sm font-medium text-gray-700 mb-2">
            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
            </svg>
            Área profesional
          </label>
          <select 
            id="area" 
            name="area"
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200"
          >
            <option value="">Todas las áreas</option>
            <?php foreach ($areas as $a): ?>
              <option value="<?= e($a) ?>" <?= $area === $a ? 'selected' : '' ?>><?= e($a) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label for="estado_id" class="block text-sm font-medium text-gray-700 mb-2">
            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            Estado del Candidato
          </label>
          <select 
            id="estado_id" 
            name="estado_id"
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200"
          >
            <option value="">Todos los estados</option>
            <?php foreach ($estados as $estado): ?>
              <option value="<?= (int)$estado['id'] ?>" <?= $estado_id === (int)$estado['id'] ? 'selected' : '' ?>>
                <?= e($estado['nombre']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">
            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
            </svg>
            Experiencia
          </label>
          <div class="grid grid-cols-2 gap-2">
            <input 
              type="number" 
              id="exp_min" 
              name="exp_min" 
              value="<?= $exp_min ?>" 
              min="0" max="50" 
              placeholder="Mín."
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200"
            >
            <input 
              type="number" 
              id="exp_max" 
              name="exp_max" 
              value="<?= $exp_max ?>" 
              min="0" max="50" 
              placeholder="Máx."
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200"
            >
          </div>
        </div>

        <div>
          <label for="desde" class="block text-sm font-medium text-gray-700 mb-2">
            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            Desde
          </label>
          <input 
            type="date" 
            id="desde" 
            name="desde" 
            value="<?= e($desde) ?>"
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200"
          >
        </div>

        <div>
          <label for="hasta" class="block text-sm font-medium text-gray-700 mb-2">
            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            Hasta
          </label>
          <input 
            type="date" 
            id="hasta" 
            name="hasta" 
            value="<?= e($hasta) ?>"
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200"
          >
        </div>

      </div>

      <div class="flex flex-wrap gap-3 pt-4 border-t border-gray-200">
        <button 
          type="submit"
          class="bg-gradient-to-r from-blue-500 to-purple-600 hover:from-blue-600 hover:to-purple-700 text-white font-medium py-2 px-6 rounded-lg shadow-md hover:shadow-lg transform hover:-translate-y-0.5 transition-all duration-200 flex items-center gap-2"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
          </svg>
          Buscar
        </button>
        
        <a 
          href="dashboard.php"
          class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-6 rounded-lg transition-all duration-200 flex items-center gap-2"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
          </svg>
          Limpiar Filtros
        </a>
        
        <button 
          type="button"
          onclick="exportarExcel()"
          class="bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white font-medium py-2 px-6 rounded-lg shadow-md hover:shadow-lg transform hover:-translate-y-0.5 transition-all duration-200 flex items-center gap-2"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
          </svg>
          Exportar a Excel
        </button>
        
        <div class="flex items-center gap-2 ml-auto">
          <label for="per_page" class="text-sm font-medium text-gray-700 flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
            Mostrar:
          </label>
          <select 
            id="per_page" 
            name="per_page"
            class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 text-sm"
            onchange="this.form.submit()"
          >
            <option value="10" <?= $per_page === 10 ? 'selected' : '' ?>>10 por página</option>
            <option value="20" <?= $per_page === 20 ? 'selected' : '' ?>>20 por página</option>
            <option value="50" <?= $per_page === 50 ? 'selected' : '' ?>>50 por página</option>
            <option value="100" <?= $per_page === 100 ? 'selected' : '' ?>>100 por página</option>
          </select>
        </div>
        
        <?php if ($total > 0): ?>
          <div class="flex items-center text-sm text-gray-600">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
            </svg>
            <?= number_format($total) ?> resultado<?= $total !== 1 ? 's' : '' ?> encontrado<?= $total !== 1 ? 's' : '' ?>
          </div>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <!-- Resultados -->
  <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
        <h2 class="text-xl font-bold text-gray-900 flex items-center gap-2">
          <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
          </svg>
          Candidatos 
          <span class="text-sm font-normal text-gray-500 bg-white px-2 py-1 rounded-full">
            <?= number_format($total) ?> total<?= $total !== 1 ? 'es' : '' ?>
          </span>
        </h2>
        
        <div class="flex flex-wrap items-center gap-3">
          <?php if ($total > 0): ?>
            <div class="flex items-center gap-2">
              <label for="per_page_top" class="text-sm font-medium text-gray-700 flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                Ver:
              </label>
              <form method="GET" class="inline">
                <?php
                // Mantener todos los parámetros actuales excepto per_page y page
                foreach ($_GET as $key => $value) {
                  if ($key !== 'per_page' && $key !== 'page') {
                    echo '<input type="hidden" name="'.htmlspecialchars($key).'" value="'.htmlspecialchars($value).'">';
                  }
                }
                ?>
                <select 
                  id="per_page_top" 
                  name="per_page"
                  class="px-3 py-1.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 text-sm bg-white"
                  onchange="this.form.submit()"
                >
                  <option value="10" <?= $per_page === 10 ? 'selected' : '' ?>>10</option>
                  <option value="20" <?= $per_page === 20 ? 'selected' : '' ?>>20</option>
                  <option value="50" <?= $per_page === 50 ? 'selected' : '' ?>>50</option>
                  <option value="100" <?= $per_page === 100 ? 'selected' : '' ?>>100</option>
                </select>
              </form>
            </div>
            
            <div class="text-sm text-gray-500 flex items-center gap-2">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 4V2a1 1 0 011-1h8a1 1 0 011 1v2m-9 0h10M5 4h14l-1 16H6L5 4z"/>
              </svg>
              Página <?= $page ?> de <?= $total_pages ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <?php if (empty($rows)): ?>
      <div class="p-12 text-center">
        <div class="bg-gray-100 rounded-full w-16 h-16 mx-auto mb-4 flex items-center justify-center">
          <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
          </svg>
        </div>
        <h3 class="text-lg font-medium text-gray-900 mb-2">No se encontraron candidatos</h3>
        <p class="text-gray-500 mb-4">No hay candidatos que coincidan con los filtros seleccionados.</p>
        <a 
          href="dashboard.php" 
          class="inline-flex items-center gap-2 bg-blue-100 hover:bg-blue-200 text-blue-800 px-4 py-2 rounded-lg font-medium transition-all duration-200"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
          </svg>
          Ver todos los candidatos
        </a>
      </div>
    <?php else: ?>
      
      <div class="overflow-x-auto">
        <table class="min-w-full">
          <thead class="bg-gray-50">
            <tr class="border-b border-gray-200">
              <th class="text-left py-3 px-4 font-semibold text-gray-700">
                <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                Foto
              </th>
              <th class="text-left py-3 px-4 font-semibold text-gray-700"><?= sort_link('nombre', 'Candidato') ?></th>
              <th class="text-left py-3 px-4 font-semibold text-gray-700">Contacto</th>
              <th class="text-left py-3 px-4 font-semibold text-gray-700">DNI</th>
              <th class="text-left py-3 px-4 font-semibold text-gray-700">Edad</th>
              <th class="text-left py-3 px-4 font-semibold text-gray-700">Áreas</th>
              <th class="text-left py-3 px-4 font-semibold text-gray-700"><?= sort_link('experiencia', 'Experiencia') ?></th>
              <th class="text-left py-3 px-4 font-semibold text-gray-700">
                <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2-2v2m8 0V2a2 2 0 00-2-2H8a2 2 0 00-2 2v4m8 0V6a2 2 0 012 2v6a2 2 0 01-2 2H8a2 2 0 01-2-2V8a2 2 0 012-2"/>
                </svg>
                Exp. Laboral
              </th>
              <th class="text-left py-3 px-4 font-semibold text-gray-700">
                <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
                Antecedentes
              </th>
              <th class="text-left py-3 px-4 font-semibold text-gray-700">
                <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Licencias
              </th>
              <th class="text-left py-3 px-4 font-semibold text-gray-700">
                <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Disponibilidad
              </th>
              <th class="text-left py-3 px-4 font-semibold text-gray-700">
                <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Estado
              </th>
              <th class="text-left py-3 px-4 font-semibold text-gray-700"><?= sort_link('fecha_carga', 'Fecha') ?></th>
              <th class="text-left py-3 px-4 font-semibold text-gray-700">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r): ?>
              <tr class="border-b border-gray-100 hover:bg-blue-50/50 transition-colors duration-150">
                <td class="py-4 px-4">
                  <div class="relative">
                    <img 
                      src="photo.php?id=<?= (int)$r['id'] ?>&nombre=<?= urlencode($r['nombre']) ?>" 
                      alt="Foto de <?= e($r['nombre']) ?>"
                      class="w-12 h-12 rounded-full object-cover border-2 border-gray-200 hover:border-blue-400 transition-colors duration-200 shadow-sm candidate-photo"
                      loading="lazy"
                      onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                    >
                    <div class="absolute inset-0 rounded-full bg-gradient-to-br from-blue-400 to-purple-500 flex items-center justify-center text-white text-sm font-semibold border-2 border-gray-200 shadow-sm hidden">
                      <?= strtoupper(substr(e($r['nombre']), 0, 2)) ?>
                    </div>
                  </div>
                </td>
                <td class="py-4 px-4">
                  <div>
                    <a href="candidato-detalle.php?id=<?= (int)$r['id'] ?>" class="font-medium text-blue-600 hover:text-blue-800 hover:underline transition-colors">
                      <?= e($r['nombre']) ?>
                    </a>
                    <p class="text-sm text-gray-500 flex items-center gap-1 mt-1">
                      <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                      </svg>
                      <?= e(date('d/m/Y H:i', strtotime($r['fecha_carga']))) ?>
                    </p>
                  </div>
                </td>
                <td class="py-4 px-4">
                  <div class="space-y-1">
                    <p class="text-sm text-gray-900 flex items-center gap-1">
                      <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"/>
                      </svg>
                      <span class="truncate max-w-48"><?= e($r['email']) ?></span>
                    </p>
                    <p class="text-sm text-gray-600 flex items-center gap-1">
                      <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                      </svg>
                      <?= e($r['telefono']) ?>
                    </p>
                  </div>
                </td>
                <td class="py-4 px-4">
                  <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-700 border border-gray-300">
                    <?= e($r['dni']) ?>
                  </span>
                </td>
                <td class="py-4 px-4">
                  <div class="flex items-center gap-2">
                    <div class="bg-blue-100 rounded-full p-1">
                      <svg class="w-3 h-3 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                      </svg>
                    </div>
                    <span class="text-sm font-medium text-gray-900"><?= e($r['edad']) ?> años</span>
                  </div>
                </td>
                <td class="py-4 px-4">
                  <div class="flex flex-wrap gap-1">
                    <?php if (!empty($r['areas_profesionales'])): ?>
                      <?php foreach (explode(', ', $r['areas_profesionales']) as $area): ?>
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800 border border-blue-200">
                          <?= e($area) ?>
                        </span>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600 border border-gray-200">
                        Sin área
                      </span>
                    <?php endif; ?>
                  </div>
                </td>
                <td class="py-4 px-4">
                  <div class="flex items-center gap-2">
                    <div class="bg-gray-100 rounded-full p-1">
                      <svg class="w-3 h-3 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                      </svg>
                    </div>
                    <span class="text-sm font-medium text-gray-900">
                      <?= (int)$r['experiencia'] ?> año<?= (int)$r['experiencia'] !== 1 ? 's' : '' ?>
                    </span>
                  </div>
                </td>
                <!-- Experiencia Laboral -->
                <td class="py-4 px-4">
                  <?php if ($r['experiencias_count'] > 0): ?>
                    <div class="flex items-center gap-2">
                      <div class="bg-green-100 rounded-full p-1">
                        <svg class="w-3 h-3 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                      </div>
                      <span class="text-sm font-medium text-green-800">
                        <?= (int)$r['experiencias_count'] ?> trabajo<?= (int)$r['experiencias_count'] !== 1 ? 's' : '' ?>
                      </span>
                    </div>
                  <?php else: ?>
                    <div class="flex items-center gap-2">
                      <div class="bg-gray-100 rounded-full p-1">
                        <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                      </div>
                      <span class="text-xs text-gray-500">Sin registros</span>
                    </div>
                  <?php endif; ?>
                </td>
                <!-- Antecedentes Penales -->
                <td class="py-4 px-4">
                  <?php if (!empty($r['antecedentes_penales'])): ?>
                    <?php if ($r['antecedentes_penales'] === 'No'): ?>
                      <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 border border-green-200">
                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Sin antecedentes
                      </span>
                    <?php else: ?>
                      <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 border border-yellow-200">
                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        Con antecedentes
                      </span>
                    <?php endif; ?>
                  <?php else: ?>
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
                      No especificado
                    </span>
                  <?php endif; ?>
                </td>
                <!-- Licencias de Conducir -->
                <td class="py-4 px-4">
                  <?php if (!empty($r['licencia_conducir'])): ?>
                    <div class="flex flex-wrap gap-1">
                      <?php 
                      $licencias = explode(',', $r['licencia_conducir']);
                      foreach ($licencias as $lic): 
                        $lic = trim($lic);
                      ?>
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800 border border-purple-200">
                          <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                          </svg>
                          <?= e($lic) ?>
                        </span>
                      <?php endforeach; ?>
                    </div>
                  <?php else: ?>
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
                      Sin licencia
                    </span>
                  <?php endif; ?>
                </td>
                <!-- Disponibilidad -->
                <td class="py-4 px-4">
                  <?php if (!empty($r['disponibilidad'])): ?>
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium 
                      <?php 
                        switch($r['disponibilidad']) {
                          case 'Inmediata': 
                            echo 'bg-blue-100 text-blue-800 border border-blue-200'; 
                            break;
                          case '15 días': 
                            echo 'bg-orange-100 text-orange-800 border border-orange-200'; 
                            break;
                          case '30 días': 
                            echo 'bg-red-100 text-red-800 border border-red-200'; 
                            break;
                          default: 
                            echo 'bg-gray-100 text-gray-600 border border-gray-200';
                        }
                      ?>">
                      <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                      </svg>
                      <?= e($r['disponibilidad']) ?>
                    </span>
                  <?php else: ?>
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
                      No especificado
                    </span>
                  <?php endif; ?>
                </td>
                <td class="py-4 px-4">
                  <div class="space-y-2">
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium"
                          style="background-color: <?= e($r['estado_color']) ?>20; color: <?= e($r['estado_color']) ?>; border: 1px solid <?= e($r['estado_color']) ?>40;">
                      <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                      </svg>
                      <?= e($r['estado_nombre']) ?>
                    </span>
                    <div>
                      <select class="text-xs bg-white border border-gray-300 rounded px-2 py-1 min-w-0 w-full max-w-32"
                              onchange="cambiarEstado(<?= (int)$r['id'] ?>, this.value, '<?= e($r['nombre']) ?>')">
                        <option value="">Cambiar...</option>
                        <?php
                        $stmt_estados = $pdo->query("SELECT * FROM estados_cv ORDER BY id");
                        while ($estado = $stmt_estados->fetch()) {
                            if ($estado['id'] != $r['estado_id']) {
                                echo '<option value="'.(int)$estado['id'].'">'.e($estado['nombre']).'</option>';
                            }
                        }
                        ?>
                      </select>
                    </div>
                  </div>
                </td>
                <td class="py-4 px-4">
                  <div class="text-sm">
                    <p class="text-gray-900 font-medium"><?= e(date('d/m/Y', strtotime($r['fecha_carga']))) ?></p>
                    <p class="text-gray-500"><?= e(date('H:i', strtotime($r['fecha_carga']))) ?></p>
                  </div>
                </td>
                <td class="py-4 px-4">
                  <div class="flex items-center gap-2">
                    <a 
                      href="candidato-detalle.php?id=<?= (int)$r['id'] ?>" 
                      class="inline-flex items-center gap-2 bg-blue-100 hover:bg-blue-200 text-blue-800 px-3 py-2 rounded-lg text-sm font-medium transition-all duration-200 hover:shadow-md group"
                      title="Ver CV completo de <?= e($r['nombre']) ?>"
                    >
                      <svg class="w-4 h-4 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                      </svg>
                      Ver CV
                    </a>
                    <button 
                      onclick="eliminarCandidato(<?= (int)$r['id'] ?>, '<?= e($r['nombre']) ?>')" 
                      class="inline-flex items-center gap-2 bg-red-100 hover:bg-red-200 text-red-800 px-3 py-2 rounded-lg text-sm font-medium transition-all duration-200 hover:shadow-md group"
                      title="Eliminar candidato <?= e($r['nombre']) ?>"
                    >
                      <svg class="w-4 h-4 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                      </svg>
                      Eliminar
                    </button>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- Paginación -->
      <?php if ($total_pages > 1): ?>
        <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
          <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="text-sm text-gray-500 flex items-center gap-1">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
              </svg>
              Mostrando <?= number_format(min($offset + 1, $total)) ?>-<?= number_format(min($offset + $per_page, $total)) ?> de <?= number_format($total) ?> resultados
            </div>
            
            <div class="flex items-center space-x-1">
              <?php if ($page > 1): ?>
                <a 
                  href="<?= build_url(['page' => 1]) ?>" 
                  class="px-3 py-2 rounded-lg bg-white hover:bg-gray-100 text-gray-700 border border-gray-300 transition-all duration-200 text-sm"
                  title="Primera página"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/>
                  </svg>
                </a>
                <a 
                  href="<?= build_url(['page' => $page - 1]) ?>" 
                  class="px-3 py-2 rounded-lg bg-white hover:bg-gray-100 text-gray-700 border border-gray-300 transition-all duration-200 flex items-center gap-1"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                  </svg>
                  Anterior
                </a>
              <?php endif; ?>
              
              <?php
              $start = max(1, $page - 2);
              $end = min($total_pages, $page + 2);
              
              for ($i = $start; $i <= $end; $i++):
              ?>
                <a 
                  href="<?= build_url(['page' => $i]) ?>" 
                  class="px-3 py-2 rounded-lg <?= $i === $page ? 'bg-blue-500 text-white border-blue-500' : 'bg-white hover:bg-gray-100 text-gray-700 border-gray-300' ?> border transition-all duration-200 font-medium"
                >
                  <?= $i ?>
                </a>
              <?php endfor; ?>
              
              <?php if ($page < $total_pages): ?>
                <a 
                  href="<?= build_url(['page' => $page + 1]) ?>" 
                  class="px-3 py-2 rounded-lg bg-white hover:bg-gray-100 text-gray-700 border border-gray-300 transition-all duration-200 flex items-center gap-1"
                >
                  Siguiente
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                  </svg>
                </a>
                <a 
                  href="<?= build_url(['page' => $total_pages]) ?>" 
                  class="px-3 py-2 rounded-lg bg-white hover:bg-gray-100 text-gray-700 border border-gray-300 transition-all duration-200 text-sm"
                  title="Última página"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7"/>
                  </svg>
                </a>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endif; ?>
      
    <?php endif; ?>
  </div>

</main>

<script>
function toggleMobileMenu() {
  const menu = document.getElementById('mobileMenu');
  menu.classList.toggle('hidden');
  
  // Agregar animación
  if (!menu.classList.contains('hidden')) {
    menu.classList.add('slide-in');
  }
}

// Cerrar menú móvil al hacer click fuera
document.addEventListener('click', function(event) {
  const menu = document.getElementById('mobileMenu');
  const button = event.target.closest('button');
  
  if (!menu.contains(event.target) && !button) {
    menu.classList.add('hidden');
  }
});

// Función para cambiar estado de candidato
async function cambiarEstado(candidatoId, nuevoEstadoId, nombreCandidato) {
  if (!nuevoEstadoId) return;
  
  // Confirmar el cambio con SweetAlert2
  const result = await Swal.fire({
    title: '¿Cambiar estado?',
    text: `¿Estás seguro de que quieres cambiar el estado del candidato ${nombreCandidato}?`,
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#3b82f6',
    cancelButtonColor: '#6b7280',
    confirmButtonText: 'Sí, cambiar',
    cancelButtonText: 'Cancelar'
  });
  
  if (!result.isConfirmed) {
    // Resetear el select
    event.target.value = '';
    return;
  }

  try {
    const formData = new FormData();
    formData.append('action', 'cambiar_estado');
    formData.append('candidato_id', candidatoId);
    formData.append('estado_id', nuevoEstadoId);
    formData.append('csrf_token', '<?= $csrf_token ?>');

    const response = await fetch('dashboard.php', {
      method: 'POST',
      body: formData
    });

    const result = await response.json();

    if (result.success) {
      // Mostrar mensaje de éxito
      await Swal.fire({
        title: '¡Estado actualizado!',
        text: 'El estado del candidato se ha cambiado correctamente.',
        icon: 'success',
        timer: 2000,
        showConfirmButton: false
      });
      // Recargar la página para mostrar el cambio
      location.reload();
    } else {
      await Swal.fire({
        title: 'Error',
        text: 'Error al cambiar el estado: ' + (result.message || 'Error desconocido'),
        icon: 'error',
        confirmButtonColor: '#ef4444'
      });
      // Resetear el select
      event.target.value = '';
    }
  } catch (error) {
    console.error('Error:', error);
    await Swal.fire({
      title: 'Error de conexión',
      text: 'No se pudo conectar con el servidor. Por favor, intenta de nuevo.',
      icon: 'error',
      confirmButtonColor: '#ef4444'
    });
    // Resetear el select
    event.target.value = '';
  }
}

// Función para eliminar candidato
async function eliminarCandidato(candidatoId, nombreCandidato) {
  // Confirmación con SweetAlert2 - diseño más dramático para acciones destructivas
  const result = await Swal.fire({
    title: '¡Atención!',
    html: `¿Estás seguro de que quieres <strong>ELIMINAR PERMANENTEMENTE</strong> al candidato:<br><br><strong>${nombreCandidato}</strong>?<br><br><small class="text-gray-500">Esta acción no se puede deshacer.</small>`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#ef4444',
    cancelButtonColor: '#6b7280',
    confirmButtonText: 'Sí, eliminar',
    cancelButtonText: 'Cancelar',
    focusCancel: true
  });
  
  if (!result.isConfirmed) return;
  
  // Segunda confirmación para acciones críticas
  const finalConfirm = await Swal.fire({
    title: '¿Estás completamente seguro?',
    text: `Esta acción eliminará permanentemente toda la información del candidato ${nombreCandidato}.`,
    icon: 'error',
    showCancelButton: true,
    confirmButtonColor: '#dc2626',
    cancelButtonColor: '#6b7280',
    confirmButtonText: 'Sí, eliminar definitivamente',
    cancelButtonText: 'No, cancelar'
  });
  
  if (!finalConfirm.isConfirmed) return;

  try {
    const formData = new FormData();
    formData.append('action', 'eliminar_candidato');
    formData.append('candidato_id', candidatoId);
    formData.append('csrf_token', '<?= $csrf_token ?>');

    const response = await fetch('dashboard.php', {
      method: 'POST',
      body: formData
    });

    const result = await response.json();

    if (result.success) {
      // Mostrar mensaje de éxito
      await Swal.fire({
        title: '¡Candidato eliminado!',
        text: 'El candidato y todos sus archivos han sido eliminados correctamente.',
        icon: 'success',
        timer: 2000,
        showConfirmButton: false
      });
      // Recargar la página para actualizar la lista
      location.reload();
    } else {
      await Swal.fire({
        title: 'Error al eliminar',
        text: 'Error al eliminar el candidato: ' + (result.message || 'Error desconocido'),
        icon: 'error',
        confirmButtonColor: '#ef4444'
      });
    }
  } catch (error) {
    console.error('Error:', error);
    await Swal.fire({
      title: 'Error de conexión',
      text: 'No se pudo conectar con el servidor. Por favor, intenta de nuevo.',
      icon: 'error',
      confirmButtonColor: '#ef4444'
    });
  }
}

// Tooltip para botones de descarga
document.addEventListener('DOMContentLoaded', function() {
  const downloadButtons = document.querySelectorAll('a[href*="download.php"]');
  
  downloadButtons.forEach(button => {
    button.addEventListener('mouseenter', function() {
      this.style.transform = 'scale(1.05)';
    });
    
    button.addEventListener('mouseleave', function() {
      this.style.transform = 'scale(1)';
    });
  });
});

// Auto-submit del formulario de búsqueda al cambiar el select de área
document.getElementById('area').addEventListener('change', function() {
  if (this.value !== '') {
    this.form.submit();
  }
});

// Efecto visual para las filas de la tabla
document.addEventListener('DOMContentLoaded', function() {
  const rows = document.querySelectorAll('tbody tr');
  
  rows.forEach((row, index) => {
    // Agregar delay escalonado para animación de entrada
    row.style.animationDelay = `${index * 50}ms`;
    row.classList.add('slide-in');
  });
});

// Modal para crear usuario desde dashboard
async function mostrarModalCrearUsuario() {
  const { value: formValues } = await Swal.fire({
    title: 'Crear Nuevo Usuario',
    html: `
      <div class="space-y-4 text-left">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Nombre de Usuario</label>
          <input id="swal-usuario" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500" placeholder="usuario123">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Nombre Completo</label>
          <input id="swal-nombre" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500" placeholder="Juan Pérez">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Contraseña</label>
          <input id="swal-password" type="password" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500" placeholder="Mínimo 6 caracteres">
        </div>
        <div class="flex items-center">
          <input id="swal-admin" type="checkbox" class="h-4 w-4 text-purple-600 focus:ring-purple-500 border-gray-300 rounded">
          <label for="swal-admin" class="ml-2 block text-sm text-gray-900">Es Administrador</label>
        </div>
      </div>
    `,
    showCancelButton: true,
    confirmButtonText: 'Crear Usuario',
    cancelButtonText: 'Cancelar',
    confirmButtonColor: '#8b5cf6',
    cancelButtonColor: '#6b7280',
    focusConfirm: false,
    preConfirm: () => {
      const usuario = document.getElementById('swal-usuario').value.trim();
      const nombre = document.getElementById('swal-nombre').value.trim();
      const password = document.getElementById('swal-password').value;
      const isAdmin = document.getElementById('swal-admin').checked;
      
      if (!usuario || !nombre || !password) {
        Swal.showValidationMessage('Todos los campos son obligatorios');
        return false;
      }
      
      if (usuario.length < 3) {
        Swal.showValidationMessage('El nombre de usuario debe tener al menos 3 caracteres');
        return false;
      }
      
      if (password.length < 6) {
        Swal.showValidationMessage('La contraseña debe tener al menos 6 caracteres');
        return false;
      }
      
      return { usuario, nombre, password, isAdmin };
    }
  });

  if (formValues) {
    await crearUsuarioDesdePanel(formValues.usuario, formValues.nombre, formValues.password, formValues.isAdmin);
  }
}

// Función para crear usuario desde dashboard
async function crearUsuarioDesdePanel(usuario, nombre, password, isAdmin) {
  try {
    const formData = new FormData();
    formData.append('action', 'crear_usuario');
    formData.append('usuario', usuario);
    formData.append('nombre_completo', nombre);
    formData.append('password', password);
    formData.append('is_admin', isAdmin ? '1' : '0');
    formData.append('csrf_token', '<?= $csrf_token ?>');

    const response = await fetch('usuarios.php', {
      method: 'POST',
      body: formData
    });

    const result = await response.json();

    if (result.success) {
      await Swal.fire({
        title: '¡Usuario creado!',
        html: `
          <div class="text-center">
            <p class="text-lg mb-4">${result.message}</p>
            <div class="bg-gray-50 rounded-lg p-4 mb-4">
              <p class="text-sm text-gray-600"><strong>Usuario:</strong> ${usuario}</p>
              <p class="text-sm text-gray-600"><strong>Nombre:</strong> ${nombre}</p>
              <p class="text-sm text-gray-600"><strong>Rol:</strong> ${isAdmin ? 'Administrador' : 'Usuario Regular'}</p>
            </div>
            <p class="text-sm text-gray-500">El usuario puede iniciar sesión inmediatamente</p>
          </div>
        `,
        icon: 'success',
        confirmButtonText: 'Gestionar Usuarios',
        showCancelButton: true,
        cancelButtonText: 'Continuar en Dashboard',
        confirmButtonColor: '#8b5cf6',
        cancelButtonColor: '#6b7280'
      }).then((result) => {
        if (result.isConfirmed) {
          window.location.href = 'usuarios.php';
        }
      });
    } else {
      await Swal.fire({
        title: 'Error',
        text: result.message,
        icon: 'error',
        confirmButtonColor: '#ef4444'
      });
    }
  } catch (error) {
    console.error('Error:', error);
    await Swal.fire({
      title: 'Error de conexión',
      text: 'No se pudo conectar con el servidor.',
      icon: 'error',
      confirmButtonColor: '#ef4444'
    });
  }
}

// Función para exportar a Excel con los filtros actuales
function exportarExcel() {
  // Obtener los parámetros de filtro actuales
  const params = new URLSearchParams(window.location.search);
  
  // Eliminar parámetros de paginación ya que queremos todos los resultados
  params.delete('page');
  params.delete('per_page');
  
  // Construir la URL de exportación
  const exportUrl = 'exportar_excel.php' + (params.toString() ? '?' + params.toString() : '');
  
  // Mostrar mensaje de confirmación
  Swal.fire({
    title: '¿Exportar a Excel?',
    html: `
      <div class="text-left">
        <p class="mb-3">Se exportarán <strong>todos</strong> los candidatos que coincidan con los filtros actuales.</p>
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 text-sm">
          <p class="font-medium text-blue-900 mb-2">Información incluida:</p>
          <ul class="list-disc list-inside text-blue-800 space-y-1 text-sm">
            <li>Datos personales completos</li>
            <li>Áreas profesionales</li>
            <li>Estado del candidato</li>
            <li>Experiencia laboral detallada</li>
            <li>Formación académica</li>
            <li>Especialidades e idiomas</li>
            <li>Habilidades y disponibilidad</li>
          </ul>
        </div>
      </div>
    `,
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: '<i class="fas fa-download"></i> Descargar Excel',
    cancelButtonText: 'Cancelar',
    confirmButtonColor: '#10b981',
    cancelButtonColor: '#6b7280',
  }).then((result) => {
    if (result.isConfirmed) {
      // Abrir la URL de exportación en una nueva ventana
      window.open(exportUrl, '_blank');
      
      // Mostrar mensaje de éxito
      Swal.fire({
        title: '¡Descarga iniciada!',
        text: 'El archivo Excel se está descargando...',
        icon: 'success',
        timer: 2000,
        showConfirmButton: false,
        toast: true,
        position: 'top-end'
      });
    }
  });
}
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

    // Cerrar al click fuera de la imagen (en el fondo)
    modal.addEventListener('click', function(e){
      if (e.target === modal || e.target === closeBtn) closeModal();
    });

    // Cerrar con ESC
    document.addEventListener('keydown', function(e){
      if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
        closeModal();
      }
    });
  })();
</script>

</script>

<script>
  (function(){
    const input = document.getElementById('lugar_residencia');
    if (!input) return;
    const datalistId = 'localidades-list-dashboard';
    let dl = document.getElementById(datalistId);
    if (!dl) {
      dl = document.createElement('datalist');
      dl.id = datalistId;
      input.insertAdjacentElement('afterend', dl);
    }
    input.setAttribute('list', datalistId);
    input.setAttribute('autocomplete', 'off');

    let timer = null;
    input.addEventListener('input', function() {
      const q = this.value.trim();
      if (timer) clearTimeout(timer);
      if (q.length < 2) {
        dl.innerHTML = '';
        return;
      }
      timer = setTimeout(async () => {
        try {
          const res = await fetch('../obtener_localidades.php?q=' + encodeURIComponent(q));
          if (!res.ok) return;
          const items = await res.json();
          dl.innerHTML = '';
          items.forEach(it => {
            const opt = document.createElement('option');
            opt.value = it.label;
            dl.appendChild(opt);
          });
        } catch (err) {
          console.error('Error cargando localidades (dashboard):', err);
        }
      }, 250);
    });
  })();
</script>

</body>
</html>
