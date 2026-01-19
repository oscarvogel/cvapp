<?php
declare(strict_types=1);

function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
function csrf_verify(?string $token): bool {
    return isset($_SESSION['csrf_token']) && $token && hash_equals($_SESSION['csrf_token'], $token);
}

function is_logged_in(): bool {
    return !empty($_SESSION['admin_id']);
}
function login_user(array $user): void {
    $_SESSION['admin_id'] = $user['id'];
    $_SESSION['admin_user'] = $user['usuario'];
    $_SESSION['admin_name'] = $user['nombre_completo'] ?? $user['usuario'];
    $_SESSION['is_admin'] = (bool)($user['is_admin'] ?? false);
    $_SESSION['last_activity'] = time();
    
    // Actualizar último acceso
    global $pdo;
    $stmt = $pdo->prepare("UPDATE usuarios_admin SET ultimo_acceso = NOW() WHERE id = ?");
    $stmt->execute([$user['id']]);
    
    session_regenerate_id(true);
}
function logout_user(): void {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
    }
    session_destroy();
}
function require_admin(): void {
    global $APP_CONFIG;
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
    if (!empty($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $APP_CONFIG['admin']['session_timeout'])) {
        logout_user();
        header('Location: login.php?expired=1');
        exit;
    }
    $_SESSION['last_activity'] = time();
}

function require_login(): void {
    global $APP_CONFIG;
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
    if (!empty($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $APP_CONFIG['admin']['session_timeout'])) {
        logout_user();
        header('Location: login.php?expired=1');
        exit;
    }
    $_SESSION['last_activity'] = time();
}

function safe_trim(?string $s): string {
    return trim((string)$s);
}
function safe_int($v, int $default = 0): int {
    return filter_var($v, FILTER_VALIDATE_INT) !== false ? (int)$v : $default;
}
function valid_email(string $email): bool {
    return (bool)filter_var($email, FILTER_VALIDATE_EMAIL);
}
function valid_phone(string $phone): bool {
    return preg_match('/^[0-9+\s().-]{7,25}$/', $phone) === 1;
}
function valid_date(string $d): bool {
    return (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);
}

function validate_cv_upload(array $file, array $config): array {
    if (!isset($file['error']) || is_array($file['error'])) {
        return [false, 'Archivo inválido.'];
    }
    switch ($file['error']) {
        case UPLOAD_ERR_OK: break;
        case UPLOAD_ERR_NO_FILE: return [false, 'No se subió ningún archivo.'];
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE: return [false, 'El archivo excede el tamaño permitido.'];
        default: return [false, 'Error al subir el archivo.'];
    }
    if ($file['size'] > $config['max_upload_bytes']) {
        return [false, 'El archivo es demasiado grande. Máximo ' . round($config['max_upload_bytes']/1024/1024, 2) . ' MB.'];
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $config['allowed_ext'], true)) {
        return [false, 'Tipo de archivo no permitido. Solo PDF, DOC, DOCX.'];
    }
    $mime = null;
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
        }
    } elseif (function_exists('mime_content_type')) {
        $mime = mime_content_type($file['tmp_name']);
    }
    if ($mime && !in_array($mime, $config['allowed_mime'], true)) {
        return [false, 'El archivo no parece ser un documento válido.'];
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        return [false, 'Subida inválida.'];
    }
    return [true, null];
}

function validate_photo_upload(array $file): array {
    if (!isset($file['error']) || is_array($file['error'])) {
        return [false, 'Foto inválida.'];
    }
    switch ($file['error']) {
        case UPLOAD_ERR_OK: break;
        case UPLOAD_ERR_NO_FILE: return [false, 'No se subió ninguna foto.'];
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE: return [false, 'La foto excede el tamaño permitido.'];
        default: return [false, 'Error al subir la foto.'];
    }
    
    // Máximo 2MB para fotos
    $max_size = 2 * 1024 * 1024; 
    if ($file['size'] > $max_size) {
        return [false, 'La foto es demasiado grande. Máximo 2MB.'];
    }
    
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_ext = ['jpg', 'jpeg', 'png'];
    if (!in_array($ext, $allowed_ext, true)) {
        return [false, 'Tipo de foto no permitido. Solo JPG, JPEG, PNG.'];
    }
    
    $mime = null;
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
        }
    } elseif (function_exists('mime_content_type')) {
        $mime = mime_content_type($file['tmp_name']);
    }
    
    $allowed_mime = ['image/jpeg', 'image/png'];
    if ($mime && !in_array($mime, $allowed_mime, true)) {
        return [false, 'El archivo no parece ser una imagen válida.'];
    }
    
    if (!is_uploaded_file($file['tmp_name'])) {
        return [false, 'Subida inválida.'];
    }
    
    return [true, null];
}

function random_filename(string $ext): string {
    $bytes = bin2hex(random_bytes(8));
    return time() . '_' . $bytes . '.' . $ext;
}

// Funciones para manejo de roles y usuarios

function is_admin(): bool {
    return is_logged_in() && ($_SESSION['is_admin'] ?? false);
}

function require_super_admin(): void {
    require_admin();
    if (!is_admin()) {
        http_response_code(403);
        exit('Acceso denegado. Se requieren permisos de administrador.');
    }
}

function get_current_user_info(): array {
    if (!is_logged_in()) {
        return [];
    }
    
    return [
        'id' => $_SESSION['admin_id'] ?? 0,
        'usuario' => $_SESSION['admin_user'] ?? '',
        'nombre' => $_SESSION['admin_name'] ?? '',
        'is_admin' => $_SESSION['is_admin'] ?? false
    ];
}

function create_user(string $usuario, string $password, string $nombre_completo, bool $is_admin = false): array {
    global $pdo;
    
    // Validaciones
    if (strlen($usuario) < 3) {
        return [false, 'El nombre de usuario debe tener al menos 3 caracteres'];
    }
    
    if (strlen($password) < 6) {
        return [false, 'La contraseña debe tener al menos 6 caracteres'];
    }
    
    if (strlen($nombre_completo) < 2) {
        return [false, 'El nombre completo es requerido'];
    }
    
    // Verificar que el usuario no exista
    $stmt = $pdo->prepare("SELECT id FROM usuarios_admin WHERE usuario = ?");
    $stmt->execute([$usuario]);
    if ($stmt->fetch()) {
        return [false, 'El nombre de usuario ya existe'];
    }
    
    // Crear usuario
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $current_user = get_current_user_info();
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO usuarios_admin (usuario, contrasena_hash, nombre_completo, is_admin, creado_por) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$usuario, $hash, $nombre_completo, $is_admin, $current_user['id']]);
        
        return [true, 'Usuario creado exitosamente'];
    } catch (Exception $e) {
        error_log("Error creating user: " . $e->getMessage());
        return [false, 'Error interno del servidor'];
    }
}

function update_user(int $user_id, string $usuario, string $nombre_completo, bool $is_admin, bool $activo, ?string $password = null): array {
    global $pdo;
    
    // Validaciones
    if (strlen($usuario) < 3) {
        return [false, 'El nombre de usuario debe tener al menos 3 caracteres'];
    }
    
    if (strlen($nombre_completo) < 2) {
        return [false, 'El nombre completo es requerido'];
    }
    
    if ($password && strlen($password) < 6) {
        return [false, 'La contraseña debe tener al menos 6 caracteres'];
    }
    
    // Verificar que el usuario no exista en otro registro
    $stmt = $pdo->prepare("SELECT id FROM usuarios_admin WHERE usuario = ? AND id != ?");
    $stmt->execute([$usuario, $user_id]);
    if ($stmt->fetch()) {
        return [false, 'El nombre de usuario ya existe'];
    }
    
    // Actualizar usuario
    try {
        if ($password) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                UPDATE usuarios_admin 
                SET usuario = ?, nombre_completo = ?, is_admin = ?, activo = ?, contrasena_hash = ? 
                WHERE id = ?
            ");
            $stmt->execute([$usuario, $nombre_completo, $is_admin, $activo, $hash, $user_id]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE usuarios_admin 
                SET usuario = ?, nombre_completo = ?, is_admin = ?, activo = ? 
                WHERE id = ?
            ");
            $stmt->execute([$usuario, $nombre_completo, $is_admin, $activo, $user_id]);
        }
        
        return [true, 'Usuario actualizado exitosamente'];
    } catch (Exception $e) {
        error_log("Error updating user: " . $e->getMessage());
        return [false, 'Error interno del servidor'];
    }
}

function delete_user(int $user_id): array {
    global $pdo;
    
    $current_user = get_current_user_info();
    
    // No permitir que se elimine a sí mismo
    if ($user_id === $current_user['id']) {
        return [false, 'No puedes eliminar tu propia cuenta'];
    }
    
    // Verificar que el usuario existe
    $stmt = $pdo->prepare("SELECT usuario FROM usuarios_admin WHERE id = ?");
    $stmt->execute([$user_id]);
    if (!$stmt->fetch()) {
        return [false, 'Usuario no encontrado'];
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM usuarios_admin WHERE id = ?");
        $stmt->execute([$user_id]);
        
        return [true, 'Usuario eliminado exitosamente'];
    } catch (Exception $e) {
        error_log("Error deleting user: " . $e->getMessage());
        return [false, 'Error interno del servidor'];
    }
}
?>