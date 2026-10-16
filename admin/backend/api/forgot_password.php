<?php
/**
 * Recuperacion de contrasena - cvapp
 *
 * ESTE ARCHIVO ESTABA ROTO. Era copia literal del de /precios y consultaba
 * la tabla `usuarios` con columnas `email`/`username`, que en cvapp no
 * existen: la tabla real es `usuarios_admin` y la columna es `usuario`.
 * Con eso la consulta moria en "Error interno del servidor" siempre, con
 * cualquier email, y nunca se enviaba nada.
 *
 * Ahora usa el $pdo del propio cvapp (init.php), que es el mismo que
 * valida el login, para que no haya forma de apuntar a otra base.
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
    if (!is_array($input) || empty($input['email'])) {
        sendResponse(['success' => false, 'message' => 'Email requerido'], 400);
    }

    $email = trim($input['email']);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendResponse(['success' => false, 'message' => 'Email inválido'], 400);
    }

    // usuarios_admin, NO usuarios. Y se busca por `email`, no por `username`.
    $stmt = $pdo->prepare(
        'SELECT id, usuario, email FROM usuarios_admin WHERE email = ? AND activo = 1 LIMIT 1'
    );
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Mismo texto exista o no la cuenta: no se revela si el email esta dado de alta.
    $generico = ['success' => true, 'message' => 'Si la cuenta existe, recibirás un correo con instrucciones para recuperar la contraseña.'];

    if (!$user) {
        sendResponse($generico);
    }

    $token = bin2hex(random_bytes(32));
    $expira = date('Y-m-d H:i:s', time() + 3600); // 1 hora

    $upd = $pdo->prepare('UPDATE usuarios_admin SET token_recuperacion = ?, token_expira = ? WHERE id = ?');
    $upd->execute([$token, $expira, $user['id']]);

    // El link del correo tiene que ser ABSOLUTO. Antes se armaba solo
    // con SCRIPT_NAME y quedaba "/cvapp/admin/reset_password.php?token=...",
    // que en un cliente de correo no abre nada: falta esquema y host.
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $pathAdmin = preg_replace('#/backend/api/[^/]*$#', '', $_SERVER['SCRIPT_NAME'] ?? '');

    if ($pathAdmin) {
        $host = $_SERVER['HTTP_HOST'] ?: (parse_url($APP_CONFIG['base_url'], PHP_URL_HOST) ?: '');
        $resetUrl = $scheme . '://' . $host . $pathAdmin . '/reset_password.php?token=' . $token;
    } else {
        $resetUrl = rtrim($APP_CONFIG['base_url'], '/') . '/admin/reset_password.php?token=' . $token;
    }

    // En produccion nunca se devuelve el token por la respuesta.
    $esProduccion = (getenv('APP_ENV') ?: 'production') === 'production';
    if (!$esProduccion) {
        sendResponse([
            'success' => true,
            'message' => 'Token generado (entorno de desarrollo).',
            'reset_url' => $resetUrl,
        ]);
    }

    require_once __DIR__ . '/../mail.php';

    $asunto = 'Recuperación de contraseña - Panel de CVs';
    $cuerpo = "Hola {$user['usuario']},\n\n"
        . "Se solicitó restablecer la contraseña de tu cuenta del Panel de CVs.\n\n"
        . "Entrá a este enlace para definir una nueva contraseña (válido 1 hora):\n\n"
        . $resetUrl . "\n\n"
        . "Si no fuiste vos, ignorá este mensaje: la contraseña no cambia nada.\n";

    $envio = send_mail($user['email'], $asunto, $cuerpo, 'Restablecer contraseña: ' . $resetUrl);

    if ($envio['success']) {
        sendResponse($generico);
    }

    // No se dice si el correo salio o fallo, para no filtrar que la cuenta existe.
    error_log('cvapp forgot_password: fallo el envio a ' . $user['email'] . ' - ' . $envio['message']);
    sendResponse($generico);

} catch (Throwable $e) {
    error_log('cvapp forgot_password: ' . get_class($e) . ' ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
    sendResponse([
        'success' => false,
        'message' => 'Error interno del servidor.',
        'error_code' => 'internal_error',
    ], 500);
}