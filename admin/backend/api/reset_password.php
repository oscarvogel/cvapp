<?php
/**
 * Restablecer contrasena - cvapp
 *
 * ESTE ARCHIVO TAMBIEN ESTABA ROTO: era el de /precios y actualizaba
 * `usuarios`. En cvapp la tabla es `usuarios_admin` y el hash vive en
 * `contrasena_hash`.
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
    sendResponse([
        'success' => false,
        'message' => 'La base de datos no está disponible.',
        'error_code' => 'db_missing',
    ], 500);
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input) || empty($input['token']) || empty($input['password'])) {
        sendResponse(['success' => false, 'message' => 'Token y nueva contraseña requeridos'], 400);
    }

    $token = trim($input['token']);
    $password = $input['password'];

    if (strlen($password) < 8) {
        sendResponse(['success' => false, 'message' => 'La contraseña debe tener al menos 8 caracteres'], 400);
    }

    // usuarios_admin con sus columnas reales.
    $stmt = $pdo->prepare(
        'SELECT id, usuario FROM usuarios_admin
         WHERE token_recuperacion = ? AND token_expira > NOW() AND activo = 1 LIMIT 1'
    );
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        sendResponse(['success' => false, 'message' => 'Token inválido o expirado. Pedí uno nuevo.'], 400);
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);

    // El token se consume y se limpian las columnas en la misma operacion.
    $upd = $pdo->prepare(
        'UPDATE usuarios_admin
         SET contrasena_hash = ?, token_recuperacion = NULL, token_expira = NULL
         WHERE id = ? AND token_recuperacion = ?'
    );
    $upd->execute([$hash, $user['id'], $token]);

    if ($upd->rowCount() !== 1) {
        sendResponse(['success' => false, 'message' => 'El token ya fue usado. Pedí uno nuevo.'], 400);
    }

    sendResponse([
        'success' => true,
        'message' => 'Contraseña restablecida. Ya podés iniciar sesión.',
    ]);

} catch (Throwable $e) {
    error_log('cvapp reset_password: ' . get_class($e) . ' ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
    sendResponse([
        'success' => false,
        'message' => 'Error interno del servidor.',
        'error_code' => 'internal_error',
    ], 500);
}