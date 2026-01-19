<?php
require_once __DIR__ . '/../init.php';
require_admin();

$current_user = get_current_user_info();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Información del Usuario</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center">
    <div class="bg-white rounded-xl shadow-lg p-8 max-w-md w-full">
        <h1 class="text-2xl font-bold text-center mb-6 text-gray-800">Información del Usuario</h1>
        
        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1">ID de Usuario</label>
                <p class="text-lg text-gray-900"><?= $current_user['id'] ?></p>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1">Nombre de Usuario</label>
                <p class="text-lg text-gray-900"><?= e($current_user['usuario']) ?></p>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1">Nombre Completo</label>
                <p class="text-lg text-gray-900"><?= e($current_user['nombre']) ?></p>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1">Rol</label>
                <p class="text-lg">
                    <?php if ($current_user['is_admin']): ?>
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-purple-100 text-purple-800 border border-purple-200">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                            </svg>
                            Super Administrador
                        </span>
                    <?php else: ?>
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800 border border-blue-200">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                            Usuario Regular
                        </span>
                    <?php endif; ?>
                </p>
            </div>
        </div>
        
        <div class="mt-8 space-y-3">
            <a href="dashboard.php" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition-colors flex items-center justify-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Volver al Dashboard
            </a>
            
            <?php if ($current_user['is_admin']): ?>
                <a href="usuarios.php" class="w-full bg-purple-600 hover:bg-purple-700 text-white font-medium py-2 px-4 rounded-lg transition-colors flex items-center justify-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/>
                    </svg>
                    Gestionar Usuarios
                </a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>