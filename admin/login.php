<?php
require_once __DIR__ . '/../init.php';

if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$err = '';
$blocked = false;

// Throttle simple
$_SESSION['login_attempts'] = $_SESSION['login_attempts'] ?? ['count' => 0, 'time' => time()];
$attempts = &$_SESSION['login_attempts'];
if ($attempts['count'] >= 5 && (time() - $attempts['time']) < 600) { // 10 min
    $blocked = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$blocked) {
    if (!csrf_verify($_POST['csrf'] ?? null)) {
        $err = 'Sesión inválida. Recarga la página.';
    } else {
        $usuario = safe_trim($_POST['usuario'] ?? '');
        $contrasena = $_POST['contrasena'] ?? '';
        if ($usuario === '' || $contrasena === '') {
            $err = 'Completa usuario y contraseña.';
        } else {
            $stmt = $pdo->prepare('SELECT id, usuario, contrasena_hash, nombre_completo, is_admin, activo FROM usuarios_admin WHERE usuario = ? AND activo = TRUE LIMIT 1');
            $stmt->execute([$usuario]);
            $user = $stmt->fetch();
            if ($user && password_verify($contrasena, $user['contrasena_hash'])) {
                if (!$user['activo']) {
                    $err = 'Tu cuenta está desactivada. Contacta al administrador.';
                } else {
                    $attempts = ['count' => 0, 'time' => time()];
                    login_user($user);
                    header('Location: dashboard.php');
                    exit;
                }
            } else {
                $err = 'Usuario o contraseña incorrectos.';
                $attempts['count']++;
                if ($attempts['count'] === 1) {
                    $attempts['time'] = time();
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
  <title>Acceso | Panel de CVs</title>
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
<body class="bg-gradient-to-br from-slate-900 via-blue-900 to-indigo-900 min-h-screen flex items-center justify-center">
  <div class="absolute inset-0 bg-[url('data:image/svg+xml,%3Csvg width="60" height="60" viewBox="0 0 60 60" xmlns="http://www.w3.org/2000/svg"%3E%3Cg fill="none" fill-rule="evenodd"%3E%3Cg fill="%239C92AC" fill-opacity="0.1"%3E%3Ccircle cx="30" cy="30" r="2"/%3E%3C/g%3E%3C/g%3E%3C/svg%3E')] opacity-20"></div>
  
  <main class="relative z-10 w-full max-w-md px-6">
    <div class="bg-white/95 backdrop-blur-sm rounded-2xl shadow-2xl border border-white/20 overflow-hidden">
      <!-- Header with logo -->
      <div class="bg-gradient-to-r from-blue-600 to-indigo-700 p-8 text-center">
        <img src="../assets/images/logo_fg.png" alt="Logo" class="h-16 w-auto mx-auto mb-4 filter brightness-0 invert">
        <h2 class="text-2xl font-bold text-white mb-2">Panel de CVs</h2>
        <p class="text-blue-100">Acceso para administradores</p>
      </div>
      
      <form method="post" class="p-8">
        <?php if (!empty($_GET['expired'])): ?>
          <div class="bg-blue-50 border border-blue-200 text-blue-800 px-4 py-3 rounded-lg mb-6 flex items-center gap-3">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
            </svg>
            <span>Sesión expirada. Vuelve a iniciar sesión.</span>
          </div>
        <?php endif; ?>
        
        <?php if ($blocked): ?>
          <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg mb-6 flex items-center gap-3">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
            <span>Demasiados intentos. Intenta en 10 minutos.</span>
          </div>
        <?php elseif ($err): ?>
          <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg mb-6 flex items-center gap-3">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
            <span><?= e($err) ?></span>
          </div>
        <?php endif; ?>
        
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        
        <div class="space-y-6">
          <div class="space-y-2">
            <label for="usuario" class="block text-sm font-semibold text-gray-700">
              Usuario
            </label>
            <div class="relative">
              <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
              </div>
              <input 
                type="text" 
                id="usuario" 
                name="usuario" 
                required 
                autofocus
                class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 bg-gray-50 focus:bg-white"
                placeholder="Ingresa tu usuario"
              >
            </div>
          </div>
          
          <div class="space-y-2">
            <label for="contrasena" class="block text-sm font-semibold text-gray-700">
              Contraseña
            </label>
            <div class="relative">
              <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
              </div>
              <input 
                type="password" 
                id="contrasena" 
                name="contrasena" 
                required
                class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 bg-gray-50 focus:bg-white"
                placeholder="Ingresa tu contraseña"
              >
            </div>
          </div>
          
          <button 
            type="submit" 
            <?= $blocked ? 'disabled' : '' ?>
            class="<?= $blocked ? 'bg-gray-400 cursor-not-allowed' : 'bg-gradient-to-r from-blue-600 to-indigo-700 hover:from-blue-700 hover:to-indigo-800' ?> w-full text-white font-semibold py-3 px-4 rounded-lg shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 transition-all duration-200 flex items-center justify-center gap-3"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
            </svg>
            Acceder al Panel
          </button>
        </div>
      </form>
    </div>
    
    <!-- Footer -->
    <div class="text-center mt-8">
      <p class="text-white/70 text-sm">
        <a href="../index.php" class="text-white/90 hover:text-white underline underline-offset-2 transition-colors">
          ← Volver al formulario principal
        </a>
      </p>
    </div>
  </main>
</body>
</html>
