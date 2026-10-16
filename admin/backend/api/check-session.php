<?php
/**
 * Verifica la sesion actual para login.html - cvapp.
 *
 * Estaba roto por lo mismo que login.php: usaba AdminAuth del de /precios,
 * que consulta tablas que en cvapp no existen.
 */

require_once __DIR__ . '/../../../init.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    exit(0);
}

function sendResponse($data, $httpCode = 200) {
    if (ob_get_level() > 0) {
        ob_clean();
    }
    http_response_code($httpCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    if (!is_logged_in() || !isset($pdo)) {
        sendResponse(['success' => true, 'logged_in' => false]);
    }

    $stmt = $pdo->prepare(
        'SELECT id, usuario, nombre_completo, is_admin, activo
         FROM usuarios_admin WHERE id = ? AND activo = TRUE LIMIT 1'
    );
    $stmt->execute([$_SESSION['admin_id']]);
    $user = $stmt->fetch();

    // La sesion existe pero el usuario fue dado de baja o borrado.
    if (!$user) {
        sendResponse(['success' => true, 'logged_in' => false]);
    }

    sendResponse([
        'success' => true,
        'logged_in' => true,
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
    error_log('cvapp api/check-session: ' . get_class($e) . ' ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
    sendResponse(['success' => false, 'logged_in' => false, 'message' => 'Error interno del servidor.'], 500);
}