<?php
// ============================================
// SISTEMA DE AUTENTICACIÓN ADMIN - SIMPLIFICADO
// ============================================

// Configuración
if (!defined('SESSION_NAME')) define('SESSION_NAME', 'ADMIN_SESSION');
if (!defined('SESSION_TIMEOUT')) define('SESSION_TIMEOUT', 7200);
if (!defined('MAX_LOGIN_ATTEMPTS')) define('MAX_LOGIN_ATTEMPTS', 5);

// Incluir conexión de base de datos
if (!isset($pdo)) {
    require_once __DIR__ . '/conexion.php';
}

// Configurar sesiones
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

class AdminAuth {
    private $db;
    
    public function __construct() {
        global $pdo;
        $this->db = $pdo;
    }
    
    public function login($username, $password) {
        try {
            // Buscar usuario
            $stmt = $this->db->prepare("
                SELECT id, username, email, password_hash, nombre, apellido, rol, activo, 
                       intentos_fallidos, bloqueado_hasta 
                FROM usuarios 
                WHERE (username = ? OR email = ?) AND activo = 1
            ");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                return ['success' => false, 'message' => 'Usuario o contraseña incorrectos'];
            }
            
            // Verificar si está bloqueado
            if ($user['bloqueado_hasta'] && strtotime($user['bloqueado_hasta']) > time()) {
                return ['success' => false, 'message' => 'Usuario temporalmente bloqueado'];
            }
            
            // Verificar contraseña
            if (!password_verify($password, $user['password_hash'])) {
                $this->incrementFailedAttempts($user['id']);
                return ['success' => false, 'message' => 'Usuario o contraseña incorrectos'];
            }
            
            // Login exitoso
            $this->resetFailedAttempts($user['id']);
            $this->updateLastLogin($user['id']);
            $this->createSession($user);
            
            return ['success' => true, 'user' => $this->getUserInfo($user)];
            
        } catch (Exception $e) {
            error_log("Error en login: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error interno del servidor'];
        }
    }
    
    public function logout() {
        try {
            if (isset($_SESSION['user_id'])) {
                $this->destroySession($_SESSION['session_id'] ?? '');
            }
            
            // Limpiar todas las variables de sesión
            $_SESSION = array();
            
            // Destruir la cookie de sesión si existe
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $params["path"], $params["domain"],
                    $params["secure"], $params["httponly"]
                );
            }
            
            // Destruir la sesión
            session_destroy();
            
            return ['success' => true, 'message' => 'Sesión cerrada correctamente'];
        } catch (Exception $e) {
            error_log("Error en logout: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error al cerrar sesión'];
        }
    }
    
    public function isLoggedIn() {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['session_id'])) {
            return false;
        }
        
        // Verificar sesión en BD
        $stmt = $this->db->prepare("
            SELECT usuario_id FROM sesiones_admin 
            WHERE id = ? AND usuario_id = ? AND activa = 1 AND fecha_expiracion > NOW()
        ");
        $stmt->execute([$_SESSION['session_id'], $_SESSION['user_id']]);
        
        return $stmt->rowCount() > 0;
    }
    
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        $stmt = $this->db->prepare("
            SELECT id, username, email, nombre, apellido, rol, ultimo_login
            FROM usuarios 
            WHERE id = ? AND activo = 1
        ");
        $stmt->execute([$_SESSION['user_id']]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function hasPermission($required_role = 'admin') {
        $user = $this->getCurrentUser();
        if (!$user) return false;
        
        $roles = ['usuario' => 1, 'moderador' => 2, 'admin' => 3];
        $user_level = $roles[$user['rol']] ?? 0;
        $required_level = $roles[$required_role] ?? 3;
        
        return $user_level >= $required_level;
    }
    
    // Métodos privados simplificados
    private function createSession($user) {
        $session_id = bin2hex(random_bytes(32));
        $expiration = date('Y-m-d H:i:s', time() + SESSION_TIMEOUT);
        
        $stmt = $this->db->prepare("
            INSERT INTO sesiones_admin (id, usuario_id, ip_address, user_agent, fecha_expiracion)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $session_id,
            $user['id'],
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $expiration
        ]);
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['session_id'] = $session_id;
        $_SESSION['username'] = $user['username'];
        $_SESSION['rol'] = $user['rol'];
    }
    
    private function destroySession($session_id) {
        if ($session_id) {
            $stmt = $this->db->prepare("UPDATE sesiones_admin SET activa = 0 WHERE id = ?");
            $stmt->execute([$session_id]);
        }
    }
    
    private function updateLastLogin($user_id) {
        $stmt = $this->db->prepare("UPDATE usuarios SET ultimo_login = NOW() WHERE id = ?");
        $stmt->execute([$user_id]);
    }
    
    private function incrementFailedAttempts($user_id) {
        $stmt = $this->db->prepare("
            UPDATE usuarios 
            SET intentos_fallidos = intentos_fallidos + 1,
                bloqueado_hasta = CASE 
                    WHEN intentos_fallidos + 1 >= ? THEN DATE_ADD(NOW(), INTERVAL 15 MINUTE)
                    ELSE bloqueado_hasta
                END
            WHERE id = ?
        ");
        $stmt->execute([MAX_LOGIN_ATTEMPTS, $user_id]);
    }
    
    private function resetFailedAttempts($user_id) {
        $stmt = $this->db->prepare("
            UPDATE usuarios 
            SET intentos_fallidos = 0, bloqueado_hasta = NULL 
            WHERE id = ?
        ");
        $stmt->execute([$user_id]);
    }
    
    private function getUserInfo($user) {
        return [
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'nombre' => $user['nombre'],
            'apellido' => $user['apellido'],
            'rol' => $user['rol']
        ];
    }
}

// Crear instancia global
$adminAuth = new AdminAuth();

// Funciones de compatibilidad (API procedural para código existente)
if (!function_exists('verificarSesion')) {
    function verificarSesion() {
        global $adminAuth;
        return $adminAuth->isLoggedIn();
    }
}

if (!function_exists('obtenerUsuarioActual')) {
    function obtenerUsuarioActual() {
        global $adminAuth;
        return $adminAuth->getCurrentUser();
    }
}

if (!function_exists('verificarPermiso')) {
    function verificarPermiso($role = 'admin') {
        global $adminAuth;
        return $adminAuth->hasPermission($role);
    }
}
?>