<?php
require_once __DIR__ . '/../init.php';
require_admin();

$success = '';
$error = '';
$current_user_id = $_SESSION['admin_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf'] ?? null)) {
        $error = 'Sesión inválida. Recarga la página.';
    } else {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        // Validaciones
        if (empty($current_password)) {
            $error = 'Debes ingresar tu contraseña actual.';
        } elseif (empty($new_password)) {
            $error = 'Debes ingresar una nueva contraseña.';
        } elseif (strlen($new_password) < 6) {
            $error = 'La nueva contraseña debe tener al menos 6 caracteres.';
        } elseif ($new_password !== $confirm_password) {
            $error = 'Las contraseñas nuevas no coinciden.';
        } elseif ($current_password === $new_password) {
            $error = 'La nueva contraseña debe ser diferente a la actual.';
        } else {
            // Verificar contraseña actual
            $stmt = $pdo->prepare('SELECT contrasena_hash FROM usuarios_admin WHERE id = ? LIMIT 1');
            $stmt->execute([$current_user_id]);
            $user = $stmt->fetch();
            
            if (!$user || !password_verify($current_password, $user['contrasena_hash'])) {
                $error = 'La contraseña actual es incorrecta.';
            } else {
                // Actualizar contraseña
                $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('UPDATE usuarios_admin SET contrasena_hash = ? WHERE id = ?');
                
                if ($stmt->execute([$new_hash, $current_user_id])) {
                    $success = '¡Contraseña actualizada correctamente!';
                    // Limpiar campos después del éxito
                    $_POST = [];
                } else {
                    $error = 'Error al actualizar la contraseña. Intenta nuevamente.';
                }
            }
        }
    }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Cambiar Contraseña | Panel de CVs</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="icon" type="image/x-icon" href="../assets/images/logo.ico">
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            'brand': {
              50: '#eff6ff',
              500: '#3b82f6',
              600: '#2563eb',
              700: '#1d4ed8',
            }
          }
        }
      }
    }
  </script>
</head>
<body class="bg-gradient-to-br from-slate-50 to-blue-50 min-h-screen">
<header class="bg-white/80 backdrop-blur-md border-b border-gray-200 shadow-sm sticky top-0 z-10">
  <div class="max-w-4xl mx-auto px-4 py-4">
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-4">
        <img src="../assets/images/logo_fg.png" alt="Logo" class="h-10 w-auto">
        <div>
          <h1 class="text-2xl font-bold text-gray-900 bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent">Cambiar Contraseña</h1>
          <p class="text-sm text-gray-600">Configuración de seguridad</p>
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

<main class="max-w-2xl mx-auto px-4 py-8">
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

  <div class="bg-white/70 backdrop-blur-sm rounded-2xl shadow-xl border border-gray-200/50 overflow-hidden">
    <div class="bg-gradient-to-r from-purple-500 to-pink-600 p-6">
      <h2 class="text-2xl font-bold text-white mb-2">Actualizar Contraseña</h2>
      <p class="text-purple-100">Cambia tu contraseña por una nueva y segura</p>
    </div>

    <form method="post" class="p-8">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      
      <div class="space-y-6">
        <div class="space-y-2">
          <label for="current_password" class="block text-sm font-semibold text-gray-700">
            Contraseña Actual
            <span class="text-red-500">*</span>
          </label>
          <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
              <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
              </svg>
            </div>
            <input 
              type="password" 
              id="current_password" 
              name="current_password" 
              required
              class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-all duration-200 bg-gray-50 focus:bg-white"
              placeholder="Ingresa tu contraseña actual"
            >
          </div>
        </div>

        <div class="space-y-2">
          <label for="new_password" class="block text-sm font-semibold text-gray-700">
            Nueva Contraseña
            <span class="text-red-500">*</span>
          </label>
          <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
              <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m0 0a2 2 0 01-2 2m2-2h-3m-6 3a5 5 0 1110 0v-1M6 20v-2a6 6 0 1112 0v2a1 1 0 01-1 1H7a1 1 0 01-1-1z"/>
              </svg>
            </div>
            <input 
              type="password" 
              id="new_password" 
              name="new_password" 
              required
              minlength="6"
              class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-all duration-200 bg-gray-50 focus:bg-white"
              placeholder="Mínimo 6 caracteres"
            >
          </div>
          <p class="text-xs text-gray-500">Debe tener al menos 6 caracteres y ser diferente a la actual</p>
        </div>

        <div class="space-y-2">
          <label for="confirm_password" class="block text-sm font-semibold text-gray-700">
            Confirmar Nueva Contraseña
            <span class="text-red-500">*</span>
          </label>
          <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
              <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
            </div>
            <input 
              type="password" 
              id="confirm_password" 
              name="confirm_password" 
              required
              minlength="6"
              class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-all duration-200 bg-gray-50 focus:bg-white"
              placeholder="Repite la nueva contraseña"
            >
          </div>
        </div>
      </div>

      <div class="mt-8 flex justify-center">
        <button 
          type="submit"
          class="bg-gradient-to-r from-purple-500 to-pink-600 hover:from-purple-600 hover:to-pink-700 text-white font-semibold py-4 px-8 rounded-xl shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 transition-all duration-200 flex items-center gap-3"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
          </svg>
          Actualizar Contraseña
        </button>
      </div>
    </form>
  </div>

  <!-- Consejos de seguridad -->
  <div class="mt-8 bg-blue-50 border border-blue-200 rounded-xl p-6">
    <h3 class="text-lg font-semibold text-blue-900 mb-3 flex items-center gap-2">
      <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
      </svg>
      Consejos de Seguridad
    </h3>
    <ul class="text-blue-800 space-y-2">
      <li class="flex items-start gap-2">
        <svg class="w-4 h-4 mt-0.5 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
        </svg>
        <span>Usa una contraseña única que no utilices en otros sitios</span>
      </li>
      <li class="flex items-start gap-2">
        <svg class="w-4 h-4 mt-0.5 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
        </svg>
        <span>Combina letras mayúsculas, minúsculas, números y símbolos</span>
      </li>
      <li class="flex items-start gap-2">
        <svg class="w-4 h-4 mt-0.5 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
        </svg>
        <span>Evita información personal como nombres o fechas importantes</span>
      </li>
      <li class="flex items-start gap-2">
        <svg class="w-4 h-4 mt-0.5 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
        </svg>
        <span>Cambia tu contraseña regularmente por seguridad</span>
      </li>
    </ul>
  </div>
</main>

<script>
// Validación en tiempo real
document.addEventListener('DOMContentLoaded', function() {
    const newPassword = document.getElementById('new_password');
    const confirmPassword = document.getElementById('confirm_password');
    
    function validatePasswords() {
        if (confirmPassword.value && newPassword.value !== confirmPassword.value) {
            confirmPassword.setCustomValidity('Las contraseñas no coinciden');
        } else {
            confirmPassword.setCustomValidity('');
        }
    }
    
    newPassword.addEventListener('input', validatePasswords);
    confirmPassword.addEventListener('input', validatePasswords);
});
</script>
</body>
</html>