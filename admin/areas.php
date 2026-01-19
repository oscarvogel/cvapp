<?php
require_once __DIR__ . '/../init.php';
require_admin();

$success = '';
$error = '';

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf'] ?? null)) {
        $error = 'Sesión inválida. Recarga la página.';
    } else {
        $action = $_POST['action'] ?? '';
        
    if ($action === 'add') {
            $nombre = safe_trim($_POST['nombre'] ?? '');
            $orden = safe_int($_POST['orden'] ?? 0);
            
            if (empty($nombre)) {
                $error = 'El nombre del área es requerido.';
            } else {
                // Verificar si ya existe
                $stmt = $pdo->prepare('SELECT id FROM areas_profesionales WHERE nombre = ? LIMIT 1');
                $stmt->execute([$nombre]);
                
                if ($stmt->fetch()) {
                    $error = 'Ya existe un área profesional con ese nombre.';
                } else {
                    // Insertar nueva área
                    $stmt = $pdo->prepare('INSERT INTO areas_profesionales (nombre, orden) VALUES (?, ?)');
                    if ($stmt->execute([$nombre, $orden])) {
                        $success = 'Área profesional agregada correctamente.';
                    } else {
                        $error = 'Error al agregar el área profesional.';
                    }
                }
            }
    }
    // --- Especialidades handlers ---
    elseif ($action === 'add_especialidad') {
      $area_id = safe_int($_POST['area_profesional_id'] ?? 0);
      $nombre = safe_trim($_POST['nombre'] ?? '');
      $orden = safe_int($_POST['orden'] ?? 0);
      $tipo_seleccion = in_array($_POST['tipo_seleccion'] ?? '', ['unica', 'multiple']) ? $_POST['tipo_seleccion'] : 'multiple';
      if ($area_id <= 0 || empty($nombre)) {
        $error = 'Área o nombre inválido para la especialidad.';
      } else {
        // Verificar unicidad
        $stmt = $pdo->prepare('SELECT id FROM especialidades_areas WHERE area_profesional_id = ? AND nombre = ? LIMIT 1');
        $stmt->execute([$area_id, $nombre]);
        if ($stmt->fetch()) {
          $error = 'Ya existe una especialidad con ese nombre en el área.';
        } else {
          $stmt = $pdo->prepare('INSERT INTO especialidades_areas (area_profesional_id, nombre, orden, tipo_seleccion) VALUES (?, ?, ?, ?)');
          if ($stmt->execute([$area_id, $nombre, $orden, $tipo_seleccion])) {
            $success = 'Especialidad agregada correctamente.';
          } else {
            $error = 'Error al agregar la especialidad.';
          }
        }
      }

    } elseif ($action === 'edit_especialidad') {
      $id = safe_int($_POST['id'] ?? 0);
      $area_id = safe_int($_POST['area_profesional_id'] ?? 0);
      $nombre = safe_trim($_POST['nombre'] ?? '');
      $orden = safe_int($_POST['orden'] ?? 0);
      $tipo_seleccion = in_array($_POST['tipo_seleccion'] ?? '', ['unica', 'multiple']) ? $_POST['tipo_seleccion'] : 'multiple';
      if ($id <= 0 || $area_id <= 0 || empty($nombre)) {
        $error = 'Datos inválidos para editar la especialidad.';
      } else {
        $stmt = $pdo->prepare('SELECT id FROM especialidades_areas WHERE area_profesional_id = ? AND nombre = ? AND id != ? LIMIT 1');
        $stmt->execute([$area_id, $nombre, $id]);
        if ($stmt->fetch()) {
          $error = 'Ya existe otra especialidad con ese nombre en el área.';
        } else {
          $stmt = $pdo->prepare('UPDATE especialidades_areas SET nombre = ?, orden = ?, tipo_seleccion = ? WHERE id = ?');
          if ($stmt->execute([$nombre, $orden, $tipo_seleccion, $id])) {
            $success = 'Especialidad actualizada correctamente.';
          } else {
            $error = 'Error al actualizar la especialidad.';
          }
        }
      }

    } elseif ($action === 'toggle_especialidad') {
      $id = safe_int($_POST['id'] ?? 0);
      if ($id > 0) {
        $stmt = $pdo->prepare('UPDATE especialidades_areas SET activa = NOT activa WHERE id = ?');
        if ($stmt->execute([$id])) {
          $success = 'Estado de la especialidad actualizado correctamente.';
        } else {
          $error = 'Error al actualizar el estado de la especialidad.';
        }
      }

    } elseif ($action === 'delete_especialidad') {
      $id = safe_int($_POST['id'] ?? 0);
      if ($id <= 0) {
        $error = 'ID de especialidad inválido.';
      } else {
        // Verificar si la especialidad está siendo usada por candidatos
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM candidato_especialidades WHERE especialidad_id = ?');
        $stmt->execute([$id]);
        $count = $stmt->fetchColumn();
        if ($count > 0) {
          $error = "No se puede eliminar la especialidad porque está siendo usada por $count candidato(s).";
        } else {
          $stmt = $pdo->prepare('DELETE FROM especialidades_areas WHERE id = ?');
          if ($stmt->execute([$id])) {
            $success = 'Especialidad eliminada correctamente.';
          } else {
            $error = 'Error al eliminar la especialidad.';
          }
        }
      }

    } elseif ($action === 'update_especialidad_order') {
      $id = safe_int($_POST['id'] ?? 0);
      $orden = safe_int($_POST['orden'] ?? 0);
      if ($id > 0) {
        $stmt = $pdo->prepare('UPDATE especialidades_areas SET orden = ? WHERE id = ?');
        if ($stmt->execute([$orden, $id])) {
          $success = 'Orden de la especialidad actualizado correctamente.';
        } else {
          $error = 'Error al actualizar el orden de la especialidad.';
        }
      }

    }
    // --- fin handlers de especialidades ---
    
    // --- Handlers de niveles de especialidades ---
    elseif ($action === 'add_nivel') {
      $especialidad_id = safe_int($_POST['especialidad_id'] ?? 0);
      $nombre = safe_trim($_POST['nombre'] ?? '');
      $descripcion = safe_trim($_POST['descripcion'] ?? '');
      $orden = safe_int($_POST['orden'] ?? 0);
      
      if ($especialidad_id <= 0 || empty($nombre)) {
        $error = 'Especialidad o nombre de nivel inválido.';
      } else {
        // Verificar unicidad
        $stmt = $pdo->prepare('SELECT id FROM niveles_especialidades WHERE especialidad_id = ? AND nombre = ? LIMIT 1');
        $stmt->execute([$especialidad_id, $nombre]);
        if ($stmt->fetch()) {
          $error = 'Ya existe un nivel con ese nombre en esta especialidad.';
        } else {
          $stmt = $pdo->prepare('INSERT INTO niveles_especialidades (especialidad_id, nombre, descripcion, orden) VALUES (?, ?, ?, ?)');
          if ($stmt->execute([$especialidad_id, $nombre, $descripcion, $orden])) {
            $success = 'Nivel agregado correctamente.';
          } else {
            $error = 'Error al agregar el nivel.';
          }
        }
      }
    }
    
    elseif ($action === 'edit_nivel') {
      $id = safe_int($_POST['id'] ?? 0);
      $especialidad_id = safe_int($_POST['especialidad_id'] ?? 0);
      $nombre = safe_trim($_POST['nombre'] ?? '');
      $descripcion = safe_trim($_POST['descripcion'] ?? '');
      $orden = safe_int($_POST['orden'] ?? 0);
      
      if ($id <= 0 || $especialidad_id <= 0 || empty($nombre)) {
        $error = 'Datos inválidos para editar el nivel.';
      } else {
        $stmt = $pdo->prepare('SELECT id FROM niveles_especialidades WHERE especialidad_id = ? AND nombre = ? AND id != ? LIMIT 1');
        $stmt->execute([$especialidad_id, $nombre, $id]);
        if ($stmt->fetch()) {
          $error = 'Ya existe otro nivel con ese nombre en esta especialidad.';
        } else {
          $stmt = $pdo->prepare('UPDATE niveles_especialidades SET nombre = ?, descripcion = ?, orden = ? WHERE id = ?');
          if ($stmt->execute([$nombre, $descripcion, $orden, $id])) {
            $success = 'Nivel actualizado correctamente.';
          } else {
            $error = 'Error al actualizar el nivel.';
          }
        }
      }
    }
    
    elseif ($action === 'toggle_nivel') {
      $id = safe_int($_POST['id'] ?? 0);
      if ($id > 0) {
        $stmt = $pdo->prepare('UPDATE niveles_especialidades SET activo = NOT activo WHERE id = ?');
        if ($stmt->execute([$id])) {
          $success = 'Estado del nivel actualizado correctamente.';
        } else {
          $error = 'Error al actualizar el estado del nivel.';
        }
      }
    }
    
    elseif ($action === 'delete_nivel') {
      $id = safe_int($_POST['id'] ?? 0);
      if ($id <= 0) {
        $error = 'ID de nivel inválido.';
      } else {
        // Verificar si el nivel está siendo usado por candidatos
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM candidato_especialidades WHERE nivel_id = ?');
        $stmt->execute([$id]);
        $count = $stmt->fetchColumn();
        if ($count > 0) {
          $error = "No se puede eliminar el nivel porque está siendo usado por $count candidato(s).";
        } else {
          $stmt = $pdo->prepare('DELETE FROM niveles_especialidades WHERE id = ?');
          if ($stmt->execute([$id])) {
            $success = 'Nivel eliminado correctamente.';
          } else {
            $error = 'Error al eliminar el nivel.';
          }
        }
      }
    }
    
    elseif ($action === 'update_nivel_order') {
      $id = safe_int($_POST['id'] ?? 0);
      $orden = safe_int($_POST['orden'] ?? 0);
      if ($id > 0) {
        $stmt = $pdo->prepare('UPDATE niveles_especialidades SET orden = ? WHERE id = ?');
        if ($stmt->execute([$orden, $id])) {
          $success = 'Orden del nivel actualizado correctamente.';
        } else {
          $error = 'Error al actualizar el orden del nivel.';
        }
      }
    }
    // --- fin handlers de niveles ---
    
    elseif ($action === 'toggle') {
            $id = safe_int($_POST['id'] ?? 0);
            if ($id > 0) {
                $stmt = $pdo->prepare('UPDATE areas_profesionales SET activa = NOT activa WHERE id = ?');
                if ($stmt->execute([$id])) {
                    $success = 'Estado del área actualizado correctamente.';
                } else {
                    $error = 'Error al actualizar el estado del área.';
                }
            }
        } elseif ($action === 'update_order') {
            $id = safe_int($_POST['id'] ?? 0);
            $orden = safe_int($_POST['orden'] ?? 0);
            if ($id > 0) {
                $stmt = $pdo->prepare('UPDATE areas_profesionales SET orden = ? WHERE id = ?');
                if ($stmt->execute([$orden, $id])) {
                    $success = 'Orden actualizado correctamente.';
                } else {
                    $error = 'Error al actualizar el orden.';
                }
            }
        } elseif ($action === 'edit') {
            $id = safe_int($_POST['id'] ?? 0);
            $nombre = safe_trim($_POST['nombre'] ?? '');
            $orden = safe_int($_POST['orden'] ?? 0);
            
            if ($id <= 0) {
                $error = 'ID de área inválido.';
            } elseif (empty($nombre)) {
                $error = 'El nombre del área es requerido.';
            } else {
                // Verificar si ya existe otro área con el mismo nombre
                $stmt = $pdo->prepare('SELECT id FROM areas_profesionales WHERE nombre = ? AND id != ? LIMIT 1');
                $stmt->execute([$nombre, $id]);
                
                if ($stmt->fetch()) {
                    $error = 'Ya existe otra área profesional con ese nombre.';
                } else {
                    // Actualizar el área
                    $stmt = $pdo->prepare('UPDATE areas_profesionales SET nombre = ?, orden = ? WHERE id = ?');
                    if ($stmt->execute([$nombre, $orden, $id])) {
                        $success = 'Área profesional actualizada correctamente.';
                    } else {
                        $error = 'Error al actualizar el área profesional.';
                    }
                }
            }
        } elseif ($action === 'delete') {
            $id = safe_int($_POST['id'] ?? 0);
            
            if ($id <= 0) {
                $error = 'ID de área inválido.';
            } else {
                // Verificar si el área está siendo usada por candidatos
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM candidato_areas ca 
                                      INNER JOIN areas_profesionales ap ON ca.area_profesional_id = ap.id 
                                      WHERE ap.id = ?');
                $stmt->execute([$id]);
                $count = $stmt->fetchColumn();
                
                if ($count > 0) {
                    $error = "No se puede eliminar el área porque está siendo usada por $count candidato(s).";
                } else {
                    // Eliminar el área
                    $stmt = $pdo->prepare('DELETE FROM areas_profesionales WHERE id = ?');
                    if ($stmt->execute([$id])) {
                        $success = 'Área profesional eliminada correctamente.';
                    } else {
                        $error = 'Error al eliminar el área profesional.';
                    }
                }
            }
        }
    }
}

// Obtener todas las áreas
$areas = $pdo->query("SELECT * FROM areas_profesionales ORDER BY orden ASC, nombre ASC")->fetchAll();
// Obtener todas las especialidades agrupadas por área
try {
  $esp_stmt = $pdo->query("SELECT id, area_profesional_id, nombre, orden, activa, tipo_seleccion FROM especialidades_areas ORDER BY area_profesional_id, orden ASC, nombre ASC");
  $all_especialidades = $esp_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
  $all_especialidades = [];
}

$especialidades_by_area = [];
foreach ($all_especialidades as $esp) {
  $especialidades_by_area[$esp['area_profesional_id']][] = $esp;
}

// Obtener todos los niveles agrupados por especialidad
try {
  $niveles_stmt = $pdo->query("SELECT * FROM niveles_especialidades ORDER BY especialidad_id, orden ASC, nombre ASC");
  $all_niveles = $niveles_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
  $all_niveles = [];
}

$niveles_by_especialidad = [];
foreach ($all_niveles as $niv) {
  $niveles_by_especialidad[$niv['especialidad_id']][] = $niv;
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Áreas Profesionales | Panel de CVs</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="icon" type="image/x-icon" href="../assets/images/logo.ico">
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
    body { font-family: 'Inter', sans-serif; }
    
    /* Modal animations */
    .modal {
      transition: opacity 0.25s ease-out;
    }
    
    .modal-content {
      transition: transform 0.25s ease-out;
      transform: scale(0.95);
    }
    
    .modal.show .modal-content {
      transform: scale(1);
    }
    
    /* Custom animations */
    @keyframes slideIn {
      from { transform: translateY(-10px); opacity: 0; }
      to { transform: translateY(0); opacity: 1; }
    }
    
    .slide-in {
      animation: slideIn 0.3s ease-out;
    }
  </style>
</head>
<body class="bg-gradient-to-br from-slate-50 to-blue-50 min-h-screen">
<header class="bg-white/80 backdrop-blur-md border-b border-gray-200 shadow-sm sticky top-0 z-10">
  <div class="max-w-7xl mx-auto px-4 py-4">
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-4">
        <img src="../assets/images/logo_fg.png" alt="Logo" class="h-10 w-auto">
        <div>
          <h1 class="text-2xl font-bold text-gray-900 bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent">Áreas Profesionales</h1>
          <p class="text-sm text-gray-600">Gestión de categorías</p>
        </div>
      </div>
      <div class="flex items-center gap-4">
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

  <?php if ($error): ?>
    <div class="bg-red-50 border border-red-200 text-red-800 px-6 py-4 rounded-xl mb-6 shadow-sm">
      <div class="flex items-center gap-3">
        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
        </svg>
        <span class="font-medium"><?= e($error) ?></span>
      </div>
    </div>
  <?php endif; ?>

  <!-- Formulario para agregar nueva área -->
  <div class="bg-white/70 backdrop-blur-sm rounded-2xl shadow-xl border border-gray-200/50 mb-8">
    <div class="bg-gradient-to-r from-green-500 to-emerald-600 p-6 rounded-t-2xl">
      <h2 class="text-xl font-bold text-white mb-2">Agregar Nueva Área</h2>
      <p class="text-green-100">Crea una nueva categoría profesional</p>
    </div>
    
    <form method="post" class="p-6">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="add">
      
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="md:col-span-2 space-y-2">
          <label for="nombre" class="block text-sm font-semibold text-gray-700">
            Nombre del Área
            <span class="text-red-500">*</span>
          </label>
          <input 
            type="text" 
            id="nombre" 
            name="nombre" 
            required
            maxlength="100"
            placeholder="Ej: Desarrollo Frontend"
            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all duration-200 bg-gray-50 focus:bg-white"
          >
        </div>
        
        <div class="space-y-2">
          <label for="orden" class="block text-sm font-semibold text-gray-700">Orden</label>
          <input 
            type="number" 
            id="orden" 
            name="orden" 
            min="0"
            max="255"
            value="0"
            placeholder="0"
            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all duration-200 bg-gray-50 focus:bg-white"
          >
        </div>
      </div>
      
      <div class="mt-6">
        <button 
          type="submit"
          class="bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white font-semibold py-3 px-6 rounded-lg shadow-lg hover:shadow-xl transition-all duration-200 flex items-center gap-2"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
          </svg>
          Agregar Área
        </button>
      </div>
    </form>
  </div>

  <!-- Lista de áreas existentes -->
  <div class="bg-white/70 backdrop-blur-sm rounded-2xl shadow-xl border border-gray-200/50">
    <div class="bg-gradient-to-r from-blue-500 to-purple-600 p-6 rounded-t-2xl">
      <h2 class="text-xl font-bold text-white mb-2">Áreas Existentes</h2>
      <p class="text-blue-100"><?= count($areas) ?> área<?= count($areas) !== 1 ? 's' : '' ?> profesional<?= count($areas) !== 1 ? 'es' : '' ?> registrada<?= count($areas) !== 1 ? 's' : '' ?></p>
    </div>
    
    <div class="p-6">
      <?php if (!$areas): ?>
        <div class="text-center py-12">
          <svg class="mx-auto h-12 w-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
          </svg>
          <p class="text-gray-500 text-lg font-medium">No hay áreas registradas</p>
          <p class="text-gray-400">Agrega la primera área profesional</p>
        </div>
      <?php else: ?>
        <div class="space-y-4">
          <?php foreach ($areas as $area): ?>
            <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors duration-150">
              <div class="flex items-center gap-4">
                <div class="<?= $area['activa'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?> px-3 py-1 rounded-full text-sm font-medium">
                  <?= $area['activa'] ? 'Activa' : 'Inactiva' ?>
                </div>
                <div>
                  <h3 class="font-semibold text-gray-900"><?= e($area['nombre']) ?></h3>
                  <p class="text-sm text-gray-500">Orden: <?= (int)$area['orden'] ?> | ID: <?= (int)$area['id'] ?></p>
                </div>
              </div>
              
              <div class="flex items-center gap-2">
                <!-- Cambiar orden -->
                <form method="post" class="inline">
                  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="action" value="update_order">
                  <input type="hidden" name="id" value="<?= (int)$area['id'] ?>">
                  <input 
                    type="number" 
                    name="orden" 
                    value="<?= (int)$area['orden'] ?>"
                    min="0"
                    max="255"
                    class="w-16 px-2 py-1 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                    onchange="this.form.submit()"
                  >
                </form>
                
                <!-- Editar área -->
                <button 
                  onclick="openEditModal(<?= (int)$area['id'] ?>, '<?= e($area['nombre']) ?>', <?= (int)$area['orden'] ?>)"
                  class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1.5 rounded-lg text-sm font-medium transition-colors duration-200 flex items-center gap-1"
                  title="Editar área"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                  </svg>
                  Editar
                </button>
                
                <!-- Toggle activo/inactivo -->
                <form method="post" class="inline">
                  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="id" value="<?= (int)$area['id'] ?>">
                  <button 
                    type="submit"
                    class="<?= $area['activa'] ? 'bg-orange-500 hover:bg-orange-600' : 'bg-green-500 hover:bg-green-600' ?> text-white px-3 py-1.5 rounded-lg text-sm font-medium transition-colors duration-200 flex items-center gap-1"
                    title="<?= $area['activa'] ? 'Desactivar' : 'Activar' ?> área"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <?php if ($area['activa']): ?>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728L5.636 5.636m12.728 12.728L5.636 5.636"/>
                      <?php else: ?>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                      <?php endif; ?>
                    </svg>
                    <?= $area['activa'] ? 'Desactivar' : 'Activar' ?>
                  </button>
                </form>
                
                <!-- Eliminar área -->
                <button 
                  onclick="openDeleteModal(<?= (int)$area['id'] ?>, '<?= e($area['nombre']) ?>')"
                  class="bg-red-500 hover:bg-red-600 text-white px-3 py-1.5 rounded-lg text-sm font-medium transition-colors duration-200 flex items-center gap-1"
                  title="Eliminar área"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                  </svg>
                  Eliminar
                </button>
              </div>
            </div>
            <!-- Sección de especialidades (colapsable) -->
            <div class="mt-2 mb-4 p-4 bg-gray-50 rounded-lg border border-gray-100">
              <div class="flex items-center justify-between">
                <div class="text-sm text-gray-700 font-medium">Especialidades relacionadas</div>
                <div class="flex items-center gap-2">
                  <button type="button" onclick="toggleEspecialidades(<?= (int)$area['id'] ?>)" class="text-sm text-blue-600 hover:underline">Mostrar / Ocultar</button>
                </div>
              </div>

              <div id="especialidades-area-<?= (int)$area['id'] ?>" class="mt-4 hidden">
                <?php $esp_list = $especialidades_by_area[$area['id']] ?? []; ?>
                <?php if (empty($esp_list)): ?>
                  <p class="text-sm text-gray-500">No hay especialidades registradas para esta área.</p>
                <?php else: ?>
                  <div class="space-y-2">
                    <?php foreach ($esp_list as $esp): ?>
                      <div class="border border-gray-200 rounded-lg bg-white shadow-sm">
                        <!-- Cabecera de la especialidad -->
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-t-lg border-b border-gray-200">
                          <div class="flex-1">
                            <div class="text-sm font-medium text-gray-900">
                              <?php if (($esp['tipo_seleccion'] ?? 'multiple') === 'multiple'): ?>
                                <span class="text-blue-600" title="Selección múltiple">☑</span>
                              <?php else: ?>
                                <span class="text-purple-600" title="Selección única">◉</span>
                              <?php endif; ?>
                              <?= e($esp['nombre']) ?>
                            </div>
                            <div class="text-xs text-gray-500">Orden: <?= (int)$esp['orden'] ?> | ID: <?= (int)$esp['id'] ?> | <?= $esp['activa'] ? 'Activa' : 'Inactiva' ?> | Tipo: <?= ($esp['tipo_seleccion'] ?? 'multiple') === 'multiple' ? 'Múltiple' : 'Única' ?></div>
                          </div>
                          <div class="flex items-center gap-2">
                            <form method="post" class="inline">
                              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                              <input type="hidden" name="action" value="update_especialidad_order">
                              <input type="hidden" name="id" value="<?= (int)$esp['id'] ?>">
                              <input type="number" name="orden" value="<?= (int)$esp['orden'] ?>" min="0" max="255" class="w-16 px-2 py-1 text-sm border border-gray-300 rounded" onchange="this.form.submit()">
                            </form>

                            <button onclick="toggleNiveles(<?= (int)$esp['id'] ?>)" class="text-xs bg-indigo-100 hover:bg-indigo-200 text-indigo-700 px-2 py-1 rounded" title="Ver niveles">
                              <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                              </svg>
                              Niveles
                            </button>

                            <button onclick="openEditEspecialidadModal(<?= (int)$esp['id'] ?>, '<?= e($esp['nombre']) ?>', <?= (int)$esp['orden'] ?>, <?= (int)$esp['area_profesional_id'] ?>, '<?= e($esp['tipo_seleccion'] ?? 'multiple') ?>')" class="bg-blue-500 hover:bg-blue-600 text-white px-2 py-1 rounded text-sm">Editar</button>

                            <form method="post" class="inline">
                              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                              <input type="hidden" name="action" value="toggle_especialidad">
                              <input type="hidden" name="id" value="<?= (int)$esp['id'] ?>">
                              <button type="submit" class="<?= $esp['activa'] ? 'bg-orange-500 hover:bg-orange-600' : 'bg-green-500 hover:bg-green-600' ?> text-white px-2 py-1 rounded text-sm"><?= $esp['activa'] ? 'Desactivar' : 'Activar' ?></button>
                            </form>

                            <button onclick="openDeleteEspecialidadModal(<?= (int)$esp['id'] ?>, '<?= e($esp['nombre']) ?>')" class="bg-red-500 hover:bg-red-600 text-white px-2 py-1 rounded text-sm">Eliminar</button>
                          </div>
                        </div>
                        
                        <!-- Sección de niveles (colapsable) -->
                        <div id="niveles-especialidad-<?= (int)$esp['id'] ?>" class="hidden p-3 bg-indigo-50/30">
                          <div class="text-xs font-semibold text-indigo-700 mb-2">Niveles de esta especialidad:</div>
                          <?php $niveles_list = $niveles_by_especialidad[$esp['id']] ?? []; ?>
                          <?php if (empty($niveles_list)): ?>
                            <p class="text-xs text-gray-500 mb-2">No hay niveles definidos para esta especialidad.</p>
                          <?php else: ?>
                            <div class="space-y-1 mb-2">
                              <?php foreach ($niveles_list as $nivel): ?>
                                <div class="flex items-center justify-between p-2 bg-white border border-indigo-100 rounded text-xs">
                                  <div class="flex-1">
                                    <div class="font-medium text-gray-900"><?= e($nivel['nombre']) ?></div>
                                    <?php if (!empty($nivel['descripcion'])): ?>
                                      <div class="text-gray-600"><?= e($nivel['descripcion']) ?></div>
                                    <?php endif; ?>
                                    <div class="text-gray-500 mt-1">Orden: <?= (int)$nivel['orden'] ?> | <?= $nivel['activo'] ? 'Activo' : 'Inactivo' ?></div>
                                  </div>
                                  <div class="flex items-center gap-1 ml-2">
                                    <form method="post" class="inline">
                                      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                      <input type="hidden" name="action" value="update_nivel_order">
                                      <input type="hidden" name="id" value="<?= (int)$nivel['id'] ?>">
                                      <input type="number" name="orden" value="<?= (int)$nivel['orden'] ?>" min="0" max="255" class="w-12 px-1 py-0.5 text-xs border border-gray-300 rounded" onchange="this.form.submit()">
                                    </form>
                                    
                                    <button onclick="openEditNivelModal(<?= (int)$nivel['id'] ?>, '<?= e($nivel['nombre']) ?>', '<?= e($nivel['descripcion'] ?? '') ?>', <?= (int)$nivel['orden'] ?>, <?= (int)$nivel['especialidad_id'] ?>)" class="bg-blue-500 hover:bg-blue-600 text-white px-2 py-0.5 rounded text-xs">Editar</button>
                                    
                                    <form method="post" class="inline">
                                      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                      <input type="hidden" name="action" value="toggle_nivel">
                                      <input type="hidden" name="id" value="<?= (int)$nivel['id'] ?>">
                                      <button type="submit" class="<?= $nivel['activo'] ? 'bg-orange-500 hover:bg-orange-600' : 'bg-green-500 hover:bg-green-600' ?> text-white px-2 py-0.5 rounded text-xs"><?= $nivel['activo'] ? 'Off' : 'On' ?></button>
                                    </form>
                                    
                                    <button onclick="openDeleteNivelModal(<?= (int)$nivel['id'] ?>, '<?= e($nivel['nombre']) ?>')" class="bg-red-500 hover:bg-red-600 text-white px-2 py-0.5 rounded text-xs">×</button>
                                  </div>
                                </div>
                              <?php endforeach; ?>
                            </div>
                          <?php endif; ?>
                          
                          <!-- Formulario para agregar nuevo nivel -->
                          <form method="post" class="p-2 bg-white border border-indigo-200 rounded">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="add_nivel">
                            <input type="hidden" name="especialidad_id" value="<?= (int)$esp['id'] ?>">
                            <div class="grid grid-cols-12 gap-2 items-end">
                              <div class="col-span-4">
                                <label class="block text-xs text-gray-700 mb-1">Nombre del nivel</label>
                                <input type="text" name="nombre" required maxlength="50" placeholder="Ej: Junior" class="w-full px-2 py-1 text-xs border border-gray-300 rounded">
                              </div>
                              <div class="col-span-6">
                                <label class="block text-xs text-gray-700 mb-1">Descripción (opcional)</label>
                                <input type="text" name="descripcion" maxlength="255" placeholder="Descripción del nivel" class="w-full px-2 py-1 text-xs border border-gray-300 rounded">
                              </div>
                              <div class="col-span-2">
                                <label class="block text-xs text-gray-700 mb-1">Orden</label>
                                <input type="number" name="orden" value="0" min="0" max="255" class="w-full px-2 py-1 text-xs border border-gray-300 rounded">
                              </div>
                            </div>
                            <div class="mt-2">
                              <button type="submit" class="bg-gradient-to-r from-indigo-500 to-purple-600 text-white px-3 py-1 rounded text-xs font-medium">+ Agregar Nivel</button>
                            </div>
                          </form>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>

                <!-- Formulario para agregar nueva especialidad -->
                <form method="post" class="mt-4 p-3 bg-white border border-gray-100 rounded">
                  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="action" value="add_especialidad">
                  <input type="hidden" name="area_profesional_id" value="<?= (int)$area['id'] ?>">
                  <div class="grid grid-cols-1 md:grid-cols-4 gap-2 items-end">
                    <div class="md:col-span-2">
                      <label class="block text-sm text-gray-700">Nombre de la especialidad</label>
                      <input type="text" name="nombre" required maxlength="100" class="w-full px-3 py-2 border border-gray-300 rounded">
                    </div>
                    <div>
                      <label class="block text-sm text-gray-700">Tipo de Selección</label>
                      <select name="tipo_seleccion" class="w-full px-3 py-2 border border-gray-300 rounded text-sm" title="Determina si se puede seleccionar uno o varios niveles">
                        <option value="multiple">☑ Múltiple</option>
                        <option value="unica">◉ Única</option>
                      </select>
                    </div>
                    <div>
                      <label class="block text-sm text-gray-700">Orden</label>
                      <input type="number" name="orden" value="0" min="0" max="255" class="w-full px-3 py-2 border border-gray-300 rounded">
                    </div>
                  </div>
                  <div class="mt-2 text-xs text-gray-500">
                    <strong>Múltiple:</strong> Permite marcar varios niveles (ej: Excel, Word, PowerBI)
                    • <strong>Única:</strong> Solo un nivel a la vez (ej: nivel de experiencia)
                  </div>
                  <div class="mt-3">
                    <button type="submit" class="bg-gradient-to-r from-green-500 to-emerald-600 text-white px-4 py-2 rounded">Agregar Especialidad</button>
                  </div>
                </form>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</main>

<!-- Modal para editar área -->
<div id="editModal" class="modal fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
  <div class="modal-content bg-white rounded-xl shadow-2xl max-w-md w-full mx-4">
    <div class="bg-gradient-to-r from-blue-500 to-purple-600 p-6 rounded-t-xl">
      <h3 class="text-xl font-bold text-white">Editar Área Profesional</h3>
    </div>
    
    <form method="post" class="p-6">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" id="editAreaId">
      
      <div class="space-y-4">
        <div>
          <label for="editNombre" class="block text-sm font-semibold text-gray-700 mb-2">Nombre del Área</label>
          <input 
            type="text" 
            id="editNombre" 
            name="nombre" 
            required
            maxlength="100"
            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200"
          >
        </div>
        
        <div>
          <label for="editOrden" class="block text-sm font-semibold text-gray-700 mb-2">Orden</label>
          <input 
            type="number" 
            id="editOrden" 
            name="orden" 
            min="0"
            max="255"
            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200"
          >
        </div>
      </div>
      
      <div class="flex gap-3 mt-6">
        <button 
          type="submit"
          class="flex-1 bg-gradient-to-r from-blue-500 to-purple-600 hover:from-blue-600 hover:to-purple-700 text-white font-semibold py-3 px-6 rounded-lg transition-all duration-200"
        >
          Actualizar
        </button>
        <button 
          type="button"
          onclick="closeEditModal()"
          class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold py-3 px-6 rounded-lg transition-all duration-200"
        >
          Cancelar
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Modal para confirmar eliminación -->
<div id="deleteModal" class="modal fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
  <div class="modal-content bg-white rounded-xl shadow-2xl max-w-md w-full mx-4">
    <div class="bg-gradient-to-r from-red-500 to-red-600 p-6 rounded-t-xl">
      <h3 class="text-xl font-bold text-white">Confirmar Eliminación</h3>
    </div>
    
    <div class="p-6">
      <div class="flex items-center gap-4 mb-6">
        <div class="bg-red-100 rounded-full p-3">
          <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"/>
          </svg>
        </div>
        <div>
          <p class="text-gray-900 font-medium">¿Estás seguro de eliminar esta área?</p>
          <p class="text-sm text-gray-600 mt-1">
            <strong id="deleteAreaName"></strong><br>
            Esta acción no se puede deshacer.
          </p>
        </div>
      </div>
      
      <form method="post" class="flex gap-3">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="deleteAreaId">
        
        <button 
          type="submit"
          class="flex-1 bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 text-white font-semibold py-3 px-6 rounded-lg transition-all duration-200"
        >
          Sí, Eliminar
        </button>
        <button 
          type="button"
          onclick="closeDeleteModal()"
          class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold py-3 px-6 rounded-lg transition-all duration-200"
        >
          Cancelar
        </button>
      </form>
    </div>
  </div>
</div>

<script>
// Funciones para el modal de edición
function openEditModal(id, nombre, orden) {
  document.getElementById('editAreaId').value = id;
  document.getElementById('editNombre').value = nombre;
  document.getElementById('editOrden').value = orden;
  
  const modal = document.getElementById('editModal');
  modal.classList.remove('hidden');
  modal.classList.add('flex');
  
  // Trigger animation
  setTimeout(() => modal.classList.add('show'), 10);
  
  // Focus en el input de nombre
  document.getElementById('editNombre').focus();
}

function closeEditModal() {
  const modal = document.getElementById('editModal');
  modal.classList.remove('show');
  
  setTimeout(() => {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
  }, 250);
}

// Funciones para el modal de eliminación
function openDeleteModal(id, nombre) {
  document.getElementById('deleteAreaId').value = id;
  document.getElementById('deleteAreaName').textContent = nombre;
  
  const modal = document.getElementById('deleteModal');
  modal.classList.remove('hidden');
  modal.classList.add('flex');
  
  // Trigger animation
  setTimeout(() => modal.classList.add('show'), 10);
}

function closeDeleteModal() {
  const modal = document.getElementById('deleteModal');
  modal.classList.remove('show');
  
  setTimeout(() => {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
  }, 250);
}

// Cerrar modales al hacer clic fuera
document.addEventListener('click', function(event) {
  if (event.target.classList.contains('modal')) {
    if (event.target.id === 'editModal') {
      closeEditModal();
    } else if (event.target.id === 'deleteModal') {
      closeDeleteModal();
    }
  }
});

// Cerrar modales con Escape
document.addEventListener('keydown', function(event) {
  if (event.key === 'Escape') {
    closeEditModal();
    closeDeleteModal();
  }
});
</script>

<script>
// Mostrar/ocultar especialidades
function toggleEspecialidades(areaId) {
  const el = document.getElementById('especialidades-area-' + areaId);
  if (!el) return;
  if (el.classList.contains('hidden')) el.classList.remove('hidden'); else el.classList.add('hidden');
}

// Mostrar/ocultar niveles
function toggleNiveles(especialidadId) {
  const el = document.getElementById('niveles-especialidad-' + especialidadId);
  if (!el) return;
  if (el.classList.contains('hidden')) el.classList.remove('hidden'); else el.classList.add('hidden');
}

// Modal para editar especialidad
function openEditEspecialidadModal(id, nombre, orden, areaId, tipoSeleccion = 'multiple') {
  document.getElementById('editEspecialidadId').value = id;
  document.getElementById('editEspecialidadNombre').value = nombre;
  document.getElementById('editEspecialidadOrden').value = orden;
  document.getElementById('editEspecialidadArea').value = areaId;
  document.getElementById('editEspecialidadTipoSeleccion').value = tipoSeleccion;
  const modal = document.getElementById('editEspecialidadModal');
  modal.classList.remove('hidden'); modal.classList.add('flex');
  setTimeout(()=> modal.classList.add('show'), 10);
}

function closeEditEspecialidadModal() {
  const modal = document.getElementById('editEspecialidadModal');
  modal.classList.remove('show'); setTimeout(()=> { modal.classList.add('hidden'); modal.classList.remove('flex'); }, 250);
}

function openDeleteEspecialidadModal(id, nombre) {
  document.getElementById('deleteEspecialidadId').value = id;
  document.getElementById('deleteEspecialidadName').textContent = nombre;
  const modal = document.getElementById('deleteEspecialidadModal');
  modal.classList.remove('hidden'); modal.classList.add('flex'); setTimeout(()=> modal.classList.add('show'), 10);
}

function closeDeleteEspecialidadModal() {
  const modal = document.getElementById('deleteEspecialidadModal');
  modal.classList.remove('show'); setTimeout(()=> { modal.classList.add('hidden'); modal.classList.remove('flex'); }, 250);
}

// Modales para niveles
function openEditNivelModal(id, nombre, descripcion, orden, especialidadId) {
  document.getElementById('editNivelId').value = id;
  document.getElementById('editNivelNombre').value = nombre;
  document.getElementById('editNivelDescripcion').value = descripcion;
  document.getElementById('editNivelOrden').value = orden;
  document.getElementById('editNivelEspecialidad').value = especialidadId;
  const modal = document.getElementById('editNivelModal');
  modal.classList.remove('hidden'); modal.classList.add('flex');
  setTimeout(()=> modal.classList.add('show'), 10);
}

function closeEditNivelModal() {
  const modal = document.getElementById('editNivelModal');
  modal.classList.remove('show'); setTimeout(()=> { modal.classList.add('hidden'); modal.classList.remove('flex'); }, 250);
}

function openDeleteNivelModal(id, nombre) {
  document.getElementById('deleteNivelId').value = id;
  document.getElementById('deleteNivelName').textContent = nombre;
  const modal = document.getElementById('deleteNivelModal');
  modal.classList.remove('hidden'); modal.classList.add('flex'); setTimeout(()=> modal.classList.add('show'), 10);
}

function closeDeleteNivelModal() {
  const modal = document.getElementById('deleteNivelModal');
  modal.classList.remove('show'); setTimeout(()=> { modal.classList.add('hidden'); modal.classList.remove('flex'); }, 250);
}
</script>

<!-- Modal para editar especialidad -->
<div id="editEspecialidadModal" class="modal fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
  <div class="modal-content bg-white rounded-xl shadow-2xl max-w-2xl w-full mx-4">
    <div class="bg-gradient-to-r from-blue-500 to-purple-600 p-6 rounded-t-xl">
      <h3 class="text-xl font-bold text-white">Editar Especialidad</h3>
    </div>
    <form method="post" class="p-6">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="edit_especialidad">
      <input type="hidden" name="id" id="editEspecialidadId">
      <input type="hidden" name="area_profesional_id" id="editEspecialidadArea">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
          <label for="editEspecialidadNombre" class="block text-sm font-semibold text-gray-700 mb-2">Nombre</label>
          <input type="text" id="editEspecialidadNombre" name="nombre" required maxlength="100" class="w-full px-4 py-3 border border-gray-300 rounded-lg">
        </div>
        <div>
          <label for="editEspecialidadTipoSeleccion" class="block text-sm font-semibold text-gray-700 mb-2">Tipo de selección</label>
          <select id="editEspecialidadTipoSeleccion" name="tipo_seleccion" class="w-full px-4 py-3 border border-gray-300 rounded-lg">
            <option value="multiple">☑ Múltiple</option>
            <option value="unica">◉ Única</option>
          </select>
        </div>
        <div>
          <label for="editEspecialidadOrden" class="block text-sm font-semibold text-gray-700 mb-2">Orden</label>
          <input type="number" id="editEspecialidadOrden" name="orden" min="0" max="255" class="w-full px-4 py-3 border border-gray-300 rounded-lg">
        </div>
      </div>
      <div class="mt-2 text-xs text-gray-500">
        <strong>Múltiple:</strong> Permite seleccionar varios niveles (ej: Excel, Word, PowerBI).<br>
        <strong>Única:</strong> Permite seleccionar solo un nivel (ej: Básico, Intermedio, Avanzado).
      </div>
      <div class="flex gap-3 mt-6">
        <button type="submit" class="flex-1 bg-gradient-to-r from-blue-500 to-purple-600 text-white py-3 rounded-lg">Actualizar</button>
        <button type="button" onclick="closeEditEspecialidadModal()" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 py-3 rounded-lg">Cancelar</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal para confirmar eliminación de especialidad -->
<div id="deleteEspecialidadModal" class="modal fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
  <div class="modal-content bg-white rounded-xl shadow-2xl max-w-md w-full mx-4">
    <div class="bg-gradient-to-r from-red-500 to-red-600 p-6 rounded-t-xl">
      <h3 class="text-xl font-bold text-white">Confirmar Eliminación de Especialidad</h3>
    </div>
    <div class="p-6">
      <div class="flex items-center gap-4 mb-6">
        <div class="bg-red-100 rounded-full p-3">
          <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
        </div>
        <div>
          <p class="text-gray-900 font-medium">¿Estás seguro de eliminar esta especialidad?</p>
          <p class="text-sm text-gray-600 mt-1"><strong id="deleteEspecialidadName"></strong><br>Esta acción no se puede deshacer.</p>
        </div>
      </div>
      <form method="post" class="flex gap-3">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="delete_especialidad">
        <input type="hidden" name="id" id="deleteEspecialidadId">
        <button type="submit" class="flex-1 bg-gradient-to-r from-red-500 to-red-600 text-white py-3 rounded-lg">Sí, Eliminar</button>
        <button type="button" onclick="closeDeleteEspecialidadModal()" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 py-3 rounded-lg">Cancelar</button>
      </form>
    </div>
  </div>
</div>

<!-- Modal para editar nivel -->
<div id="editNivelModal" class="modal fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
  <div class="modal-content bg-white rounded-xl shadow-2xl max-w-md w-full mx-4">
    <div class="bg-gradient-to-r from-indigo-500 to-purple-600 p-6 rounded-t-xl">
      <h3 class="text-xl font-bold text-white">Editar Nivel</h3>
    </div>
    <form method="post" class="p-6">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="edit_nivel">
      <input type="hidden" name="id" id="editNivelId">
      <input type="hidden" name="especialidad_id" id="editNivelEspecialidad">
      <div class="space-y-4">
        <div>
          <label for="editNivelNombre" class="block text-sm font-semibold text-gray-700 mb-2">Nombre del Nivel</label>
          <input type="text" id="editNivelNombre" name="nombre" required maxlength="50" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
        </div>
        <div>
          <label for="editNivelDescripcion" class="block text-sm font-semibold text-gray-700 mb-2">Descripción (opcional)</label>
          <textarea id="editNivelDescripcion" name="descripcion" maxlength="255" rows="3" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"></textarea>
        </div>
        <div>
          <label for="editNivelOrden" class="block text-sm font-semibold text-gray-700 mb-2">Orden</label>
          <input type="number" id="editNivelOrden" name="orden" min="0" max="255" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
        </div>
      </div>
      <div class="flex gap-3 mt-6">
        <button type="submit" class="flex-1 bg-gradient-to-r from-indigo-500 to-purple-600 text-white py-3 rounded-lg font-semibold">Actualizar</button>
        <button type="button" onclick="closeEditNivelModal()" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 py-3 rounded-lg font-semibold">Cancelar</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal para confirmar eliminación de nivel -->
<div id="deleteNivelModal" class="modal fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
  <div class="modal-content bg-white rounded-xl shadow-2xl max-w-md w-full mx-4">
    <div class="bg-gradient-to-r from-red-500 to-red-600 p-6 rounded-t-xl">
      <h3 class="text-xl font-bold text-white">Confirmar Eliminación de Nivel</h3>
    </div>
    <div class="p-6">
      <div class="flex items-center gap-4 mb-6">
        <div class="bg-red-100 rounded-full p-3">
          <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"/>
          </svg>
        </div>
        <div>
          <p class="text-gray-900 font-medium">¿Estás seguro de eliminar este nivel?</p>
          <p class="text-sm text-gray-600 mt-1"><strong id="deleteNivelName"></strong><br>Esta acción no se puede deshacer.</p>
        </div>
      </div>
      <form method="post" class="flex gap-3">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="delete_nivel">
        <input type="hidden" name="id" id="deleteNivelId">
        <button type="submit" class="flex-1 bg-gradient-to-r from-red-500 to-red-600 text-white py-3 rounded-lg font-semibold">Sí, Eliminar</button>
        <button type="button" onclick="closeDeleteNivelModal()" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 py-3 rounded-lg font-semibold">Cancelar</button>
      </form>
    </div>
  </div>
</div>

</body>
</html>