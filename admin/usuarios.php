<?php
require_once __DIR__ . '/../init.php';
require_super_admin(); // Solo administradores pueden acceder

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
    
    if ($action === 'crear_usuario') {
        $usuario = safe_trim($_POST['usuario'] ?? '');
        $password = $_POST['password'] ?? '';
        $nombre_completo = safe_trim($_POST['nombre_completo'] ?? '');
        $is_admin = isset($_POST['is_admin']) && $_POST['is_admin'] === '1';
        
        $result = create_user($usuario, $password, $nombre_completo, $is_admin);
        echo json_encode(['success' => $result[0], 'message' => $result[1]]);
        exit;
    }
    
    if ($action === 'actualizar_usuario') {
        $user_id = safe_int($_POST['user_id'] ?? 0);
        $usuario = safe_trim($_POST['usuario'] ?? '');
        $nombre_completo = safe_trim($_POST['nombre_completo'] ?? '');
        $is_admin = isset($_POST['is_admin']) && $_POST['is_admin'] === '1';
        $activo = isset($_POST['activo']) && $_POST['activo'] === '1';
        $password = !empty($_POST['password']) ? $_POST['password'] : null;
        
        $result = update_user($user_id, $usuario, $nombre_completo, $is_admin, $activo, $password);
        echo json_encode(['success' => $result[0], 'message' => $result[1]]);
        exit;
    }
    
    if ($action === 'eliminar_usuario') {
        $user_id = safe_int($_POST['user_id'] ?? 0);
        
        $result = delete_user($user_id);
        echo json_encode(['success' => $result[0], 'message' => $result[1]]);
        exit;
    }
    
    echo json_encode(['success' => false, 'message' => 'Acción no válida']);
    exit;
}

// Obtener lista de usuarios
$stmt = $pdo->query("
    SELECT u.*, c.usuario as creador_usuario 
    FROM usuarios_admin u 
    LEFT JOIN usuarios_admin c ON u.creado_por = c.id 
    ORDER BY u.is_admin DESC, u.usuario ASC
");
$usuarios = $stmt->fetchAll();

$current_user = get_current_user_info();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gestión de Usuarios - Panel de Administración</title>
  <link rel="icon" type="image/x-icon" href="../assets/images/logo.ico">
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
    .card-hover { transition: all 0.3s ease; }
    .card-hover:hover { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
    .slide-in { animation: slideIn 0.3s ease-out; }
    @keyframes slideIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
  </style>
</head>
<body class="bg-gray-50 min-h-screen">

<header class="bg-white shadow-lg border-b border-gray-200">
  <div class="max-w-7xl mx-auto px-4">
    <div class="flex justify-between items-center py-4">
      <div class="flex items-center space-x-4">
        <img src="../assets/images/logo_fg.png" alt="Logo" class="h-10 w-auto">
        <div>
          <h1 class="text-2xl font-bold bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent">
            Gestión de Usuarios
          </h1>
          <p class="text-sm text-gray-600">Administración de cuentas de usuario</p>
        </div>
      </div>

      <div class="flex items-center space-x-4">
        <div class="text-right hidden md:block">
          <p class="text-sm font-medium text-gray-900"><?= e($current_user['nombre']) ?></p>
          <p class="text-xs text-gray-500">Super Administrador</p>
        </div>
        
        <!-- Menú de navegación -->
        <div class="hidden md:flex items-center space-x-2">
          <a href="dashboard.php" class="text-gray-600 hover:text-gray-800 px-4 py-2 rounded-lg font-medium hover:bg-gray-100 transition-all duration-200">Dashboard</a>
          <a href="areas.php" class="text-gray-600 hover:text-gray-800 px-4 py-2 rounded-lg font-medium hover:bg-gray-100 transition-all duration-200">Gestionar Áreas</a>
          <a href="usuarios.php" class="bg-gradient-to-r from-blue-500 to-purple-600 text-white px-4 py-2 rounded-lg font-medium">Gestionar Usuarios</a>
          <a href="change-password.php" class="text-gray-600 hover:text-gray-800 px-4 py-2 rounded-lg font-medium hover:bg-gray-100 transition-all duration-200">Cambiar Contraseña</a>
          <a href="logout.php" class="text-red-600 hover:text-red-800 px-4 py-2 rounded-lg font-medium hover:bg-red-50 transition-all duration-200">Cerrar Sesión</a>
        </div>

        <!-- Botón menú móvil -->
        <button onclick="toggleMobileMenu()" class="md:hidden bg-gray-100 hover:bg-gray-200 p-2 rounded-lg transition-colors">
          <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
          </svg>
        </button>
      </div>
    </div>

    <!-- Menú móvil desplegable -->
    <div id="mobileMenu" class="hidden md:hidden mt-4 space-y-2 slide-in">
      <a href="dashboard.php" class="block text-gray-600 hover:text-gray-800 px-4 py-2 rounded-lg font-medium hover:bg-gray-100">Dashboard</a>
      <a href="areas.php" class="block text-gray-600 hover:text-gray-800 px-4 py-2 rounded-lg font-medium hover:bg-gray-100">Gestionar Áreas</a>
      <a href="usuarios.php" class="block bg-gradient-to-r from-blue-500 to-purple-600 text-white px-4 py-2 rounded-lg font-medium">Gestionar Usuarios</a>
      <a href="change-password.php" class="block text-gray-600 hover:text-gray-800 px-4 py-2 rounded-lg font-medium hover:bg-gray-100">Cambiar Contraseña</a>
      <a href="logout.php" class="block text-red-600 hover:text-red-800 px-4 py-2 rounded-lg font-medium hover:bg-red-50">Cerrar Sesión</a>
    </div>
  </div>
</header>

<main class="max-w-7xl mx-auto px-4 py-8">
  
  <!-- Estadísticas rápidas -->
  <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-200 card-hover">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-sm font-medium text-gray-600">Total Usuarios</p>
          <p class="text-3xl font-bold text-gray-900"><?= count($usuarios) ?></p>
          <p class="text-xs text-gray-500 mt-1">Cuentas registradas</p>
        </div>
        <div class="bg-blue-100 rounded-full p-3">
          <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/>
          </svg>
        </div>
      </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-200 card-hover">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-sm font-medium text-gray-600">Administradores</p>
          <?php $admins = array_filter($usuarios, fn($u) => $u['is_admin']); ?>
          <p class="text-3xl font-bold text-gray-900"><?= count($admins) ?></p>
          <p class="text-xs text-gray-500 mt-1">Con permisos completos</p>
        </div>
        <div class="bg-purple-100 rounded-full p-3">
          <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
          </svg>
        </div>
      </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-200 card-hover">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-sm font-medium text-gray-600">Usuarios Activos</p>
          <?php $activos = array_filter($usuarios, fn($u) => $u['activo']); ?>
          <p class="text-3xl font-bold text-gray-900"><?= count($activos) ?></p>
          <p class="text-xs text-gray-500 mt-1">Pueden iniciar sesión</p>
        </div>
        <div class="bg-green-100 rounded-full p-3">
          <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
        </div>
      </div>
    </div>
  </div>

  <!-- Botón para agregar usuario -->
  <div class="mb-6">
    <button 
      onclick="mostrarModalCrear()"
      class="bg-gradient-to-r from-blue-500 to-purple-600 hover:from-blue-600 hover:to-purple-700 text-white font-medium py-3 px-6 rounded-lg shadow-md hover:shadow-lg transform hover:-translate-y-0.5 transition-all duration-200 flex items-center gap-2"
    >
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
      </svg>
      Agregar Nuevo Usuario
    </button>
  </div>

  <!-- Lista de usuarios -->
  <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
      <h2 class="text-xl font-bold text-gray-900 flex items-center gap-2">
        <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/>
        </svg>
        Usuarios del Sistema
      </h2>
    </div>

    <div class="overflow-x-auto">
      <table class="min-w-full">
        <thead class="bg-gray-50">
          <tr class="border-b border-gray-200">
            <th class="text-left py-3 px-4 font-semibold text-gray-700">Usuario</th>
            <th class="text-left py-3 px-4 font-semibold text-gray-700">Nombre Completo</th>
            <th class="text-left py-3 px-4 font-semibold text-gray-700">Rol</th>
            <th class="text-left py-3 px-4 font-semibold text-gray-700">Estado</th>
            <th class="text-left py-3 px-4 font-semibold text-gray-700">Último Acceso</th>
            <th class="text-left py-3 px-4 font-semibold text-gray-700">Creado Por</th>
            <th class="text-left py-3 px-4 font-semibold text-gray-700">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($usuarios as $user): ?>
            <tr class="border-b border-gray-50 hover:bg-gray-50 transition-colors">
              <td class="py-4 px-4">
                <div class="flex items-center gap-3">
                  <div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-400 to-purple-500 flex items-center justify-center text-white text-sm font-semibold">
                    <?= strtoupper(substr(e($user['usuario']), 0, 2)) ?>
                  </div>
                  <div>
                    <p class="font-medium text-gray-900"><?= e($user['usuario']) ?></p>
                    <p class="text-xs text-gray-500">ID: <?= $user['id'] ?></p>
                  </div>
                </div>
              </td>
              <td class="py-4 px-4">
                <p class="text-gray-900"><?= e($user['nombre_completo'] ?: 'No especificado') ?></p>
              </td>
              <td class="py-4 px-4">
                <?php if ($user['is_admin']): ?>
                  <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800 border border-purple-200">
                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                    Administrador
                  </span>
                <?php else: ?>
                  <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800 border border-blue-200">
                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                    Usuario
                  </span>
                <?php endif; ?>
              </td>
              <td class="py-4 px-4">
                <?php if ($user['activo']): ?>
                  <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 border border-green-200">
                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Activo
                  </span>
                <?php else: ?>
                  <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800 border border-red-200">
                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Inactivo
                  </span>
                <?php endif; ?>
              </td>
              <td class="py-4 px-4 text-sm text-gray-600">
                <?= $user['ultimo_acceso'] ? e(date('d/m/Y H:i', strtotime($user['ultimo_acceso']))) : 'Nunca' ?>
              </td>
              <td class="py-4 px-4 text-sm text-gray-600">
                <?= e($user['creador_usuario'] ?: 'Sistema') ?>
              </td>
              <td class="py-4 px-4">
                <div class="flex items-center gap-2">
                  <button 
                    onclick="mostrarModalEditar(<?= e(json_encode($user)) ?>)"
                    class="inline-flex items-center gap-1 bg-blue-100 hover:bg-blue-200 text-blue-800 px-2 py-1 rounded text-xs font-medium transition-all duration-200"
                  >
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    Editar
                  </button>
                  
                  <?php if ($user['id'] !== $current_user['id']): ?>
                    <button 
                      onclick="eliminarUsuario(<?= $user['id'] ?>, '<?= e($user['usuario']) ?>')"
                      class="inline-flex items-center gap-1 bg-red-100 hover:bg-red-200 text-red-800 px-2 py-1 rounded text-xs font-medium transition-all duration-200"
                    >
                      <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                      </svg>
                      Eliminar
                    </button>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

</main>

<script>
function toggleMobileMenu() {
  const menu = document.getElementById('mobileMenu');
  menu.classList.toggle('hidden');
  
  if (!menu.classList.contains('hidden')) {
    menu.classList.add('slide-in');
  }
}

// Modal para crear usuario
async function mostrarModalCrear() {
  const { value: formValues } = await Swal.fire({
    title: 'Crear Nuevo Usuario',
    html: `
      <div class="space-y-4 text-left">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Nombre de Usuario</label>
          <input id="swal-usuario" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="usuario123">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Nombre Completo</label>
          <input id="swal-nombre" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Juan Pérez">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Contraseña</label>
          <input id="swal-password" type="password" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Mínimo 6 caracteres">
        </div>
        <div class="flex items-center">
          <input id="swal-admin" type="checkbox" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
          <label for="swal-admin" class="ml-2 block text-sm text-gray-900">Es Administrador</label>
        </div>
      </div>
    `,
    showCancelButton: true,
    confirmButtonText: 'Crear Usuario',
    cancelButtonText: 'Cancelar',
    confirmButtonColor: '#3b82f6',
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
    await crearUsuario(formValues.usuario, formValues.nombre, formValues.password, formValues.isAdmin);
  }
}

// Modal para editar usuario
async function mostrarModalEditar(userData) {
  const { value: formValues } = await Swal.fire({
    title: 'Editar Usuario',
    html: `
      <div class="space-y-4 text-left">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Nombre de Usuario</label>
          <input id="swal-usuario" type="text" value="${userData.usuario}" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Nombre Completo</label>
          <input id="swal-nombre" type="text" value="${userData.nombre_completo || ''}" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Nueva Contraseña (opcional)</label>
          <input id="swal-password" type="password" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Dejar vacío para no cambiar">
        </div>
        <div class="flex items-center space-x-4">
          <div class="flex items-center">
            <input id="swal-admin" type="checkbox" ${userData.is_admin ? 'checked' : ''} class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
            <label for="swal-admin" class="ml-2 block text-sm text-gray-900">Es Administrador</label>
          </div>
          <div class="flex items-center">
            <input id="swal-activo" type="checkbox" ${userData.activo ? 'checked' : ''} class="h-4 w-4 text-green-600 focus:ring-green-500 border-gray-300 rounded">
            <label for="swal-activo" class="ml-2 block text-sm text-gray-900">Activo</label>
          </div>
        </div>
      </div>
    `,
    showCancelButton: true,
    confirmButtonText: 'Actualizar Usuario',
    cancelButtonText: 'Cancelar',
    confirmButtonColor: '#3b82f6',
    cancelButtonColor: '#6b7280',
    focusConfirm: false,
    preConfirm: () => {
      const usuario = document.getElementById('swal-usuario').value.trim();
      const nombre = document.getElementById('swal-nombre').value.trim();
      const password = document.getElementById('swal-password').value;
      const isAdmin = document.getElementById('swal-admin').checked;
      const activo = document.getElementById('swal-activo').checked;
      
      if (!usuario || !nombre) {
        Swal.showValidationMessage('El nombre de usuario y nombre completo son obligatorios');
        return false;
      }
      
      if (usuario.length < 3) {
        Swal.showValidationMessage('El nombre de usuario debe tener al menos 3 caracteres');
        return false;
      }
      
      if (password && password.length < 6) {
        Swal.showValidationMessage('La contraseña debe tener al menos 6 caracteres');
        return false;
      }
      
      return { usuario, nombre, password, isAdmin, activo };
    }
  });

  if (formValues) {
    await actualizarUsuario(userData.id, formValues.usuario, formValues.nombre, formValues.password, formValues.isAdmin, formValues.activo);
  }
}

// Función para crear usuario
async function crearUsuario(usuario, nombre, password, isAdmin) {
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
        text: result.message,
        icon: 'success',
        timer: 2000,
        showConfirmButton: false
      });
      location.reload();
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

// Función para actualizar usuario
async function actualizarUsuario(userId, usuario, nombre, password, isAdmin, activo) {
  try {
    const formData = new FormData();
    formData.append('action', 'actualizar_usuario');
    formData.append('user_id', userId);
    formData.append('usuario', usuario);
    formData.append('nombre_completo', nombre);
    if (password) formData.append('password', password);
    formData.append('is_admin', isAdmin ? '1' : '0');
    formData.append('activo', activo ? '1' : '0');
    formData.append('csrf_token', '<?= $csrf_token ?>');

    const response = await fetch('usuarios.php', {
      method: 'POST',
      body: formData
    });

    const result = await response.json();

    if (result.success) {
      await Swal.fire({
        title: '¡Usuario actualizado!',
        text: result.message,
        icon: 'success',
        timer: 2000,
        showConfirmButton: false
      });
      location.reload();
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

// Función para eliminar usuario
async function eliminarUsuario(userId, nombreUsuario) {
  const result = await Swal.fire({
    title: '¡Atención!',
    html: `¿Estás seguro de que quieres <strong>ELIMINAR</strong> el usuario:<br><br><strong>${nombreUsuario}</strong>?<br><br><small class="text-gray-500">Esta acción no se puede deshacer.</small>`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#ef4444',
    cancelButtonColor: '#6b7280',
    confirmButtonText: 'Sí, eliminar',
    cancelButtonText: 'Cancelar',
    focusCancel: true
  });
  
  if (!result.isConfirmed) return;

  try {
    const formData = new FormData();
    formData.append('action', 'eliminar_usuario');
    formData.append('user_id', userId);
    formData.append('csrf_token', '<?= $csrf_token ?>');

    const response = await fetch('usuarios.php', {
      method: 'POST',
      body: formData
    });

    const result = await response.json();

    if (result.success) {
      await Swal.fire({
        title: '¡Usuario eliminado!',
        text: result.message,
        icon: 'success',
        timer: 2000,
        showConfirmButton: false
      });
      location.reload();
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

// Cerrar menú móvil al hacer click fuera
document.addEventListener('click', function(event) {
  const menu = document.getElementById('mobileMenu');
  const button = event.target.closest('button');
  
  if (!menu.contains(event.target) && !button) {
    menu.classList.add('hidden');
  }
});
</script>

</body>
</html>