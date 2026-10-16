<?php
/**
 * API de login para login.html - cvapp
 *
 * ESTE ARCHIVO ESTABA ROTO. Era el de /precios y delegaba en
 * AdminAuth, que consulta la tabla `usuarios` (no existe en cvapp: la real
 * es `usuarios_admin`). La excepcion terminaba en el catch generico y la
 * pantalla mostraba "Error interno del servidor" con cualquier clave.
 *
 * Ahora autentica contra usuarios_admin y usa login_user() de cvapp, que es
 * la misma funcion que usa admin/login.php. Asi la sesion que queda es
 * exactamente la que espera admin/dashboard.php.
 */

require_once __DIR__ . '/../../../init.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    exit(0);
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido'], JSON_UNESCAPED_UNICODE);
    exit;
}

function sendResponse($data, $httpCode = 200) {
    if (ob_get_level() > 0) {
        ob_clean();
    }
    http_response_code($httpCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($pdo)) {
    sendResponse(['success' => false, 'message' => 'La base de datos no está disponible.'], 500);
}

// --- Freno simple por IP, en memoria de la sesion -------------------------
$_SESSION['login_intentos'] ??= ['count' => 0, 'desde' => time()];
if ($_SESSION['login_intentos']['count'] >= 8 && (time() - $_SESSION['login_intentos']['desde']) < 600) {
    sendResponse(['success' => false, 'message' => 'Demasiados intentos. Probá de nuevo en 10 minutos.'], 429);
}

try {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!is_array($data)) {
        sendResponse(['success' => false, 'message' => 'Datos inválidos'], 400);
    }

    $campo = trim((string)($data['username'] ?? ''));
    $clave = (string)($data['password'] ?? '');

    if ($campo === '' || $clave === '') {
        sendResponse(['success' => false, 'message' => 'Usuario y contraseña son requeridos'], 400);
    }

    // El campo dice "Usuario o Email". cvapp guardaba el email solo para la
    // recuperacion, asi que se acepta cualquiera de los dos.
    $stmt = $pdo->prepare(
        'SELECT id, usuario, contrasena_hash, nombre_completo, is_admin, activo
         FROM usuarios_admin
         WHERE (usuario = ? OR email = ?) AND activo = TRUE
         LIMIT 1'
    );
    $stmt->execute([$campo, $campo]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($clave, $user['contrasena_hash'])) {
        $_SESSION['login_intentos']['count']++;
        if ($_SESSION['login_intentos']['count'] === 1) {
            $_SESSION['login_intentos']['desde'] = time();
        }
        // Mismo texto exista o no la cuenta.
        sendResponse(['success' => false, 'message' => 'Usuario o contraseña incorrectos'], 401);
    }

    if (password_needs_rehash($user['contrasena_hash'], PASSWORD_DEFAULT)) {
        $nuevo = password_hash($clave, PASSWORD_DEFAULT);
        $pdo->prepare('UPDATE usuarios_admin SET contrasena_hash = ? WHERE id = ?')
            ->execute([$nuevo, $user['id']]);
    }

    login_user($user);

    $_SESSION['login_intentos'] = ['count' => 0, 'desde' => time()];

    sendResponse([
        'success' => true,
        'message' => 'Sesión iniciada',
        'user' => [
            'id' => (int)$user['id'],
            'username' => $user['usuario'],
            'nombre' => $user['nombre_completo'],
            'apellido' => '',
            'rol' => $user['is_admin'] ? 'admin' : 'usuario',
            'is_admin' => (bool)$user['is_admin'],
        ],
    ]);

} catch (Throwable $e) {
    error_log('cvapp api/login: ' . get_class($e) . ' ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
    sendResponse(['success' => false, 'message' => 'Error interno del servidor.'], 500);
}