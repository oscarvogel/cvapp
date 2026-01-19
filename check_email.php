<?php
require_once 'init.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

// Verificar token CSRF (usar csrf_verify definida en functions.php)
if (!csrf_verify($_POST['csrf'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'Token CSRF inválido']);
    exit;
}

$email = trim($_POST['email'] ?? '');

if (empty($email)) {
    echo json_encode(['exists' => false, 'message' => '']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['exists' => false, 'message' => 'Email inválido']);
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT id FROM candidatos WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    
    if ($stmt->fetch()) {
        echo json_encode([
            'exists' => true, 
            'message' => 'Ya existe un CV registrado con este correo electrónico.'
        ]);
    } else {
        echo json_encode([
            'exists' => false, 
            'message' => 'Email disponible'
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor']);
}
?>