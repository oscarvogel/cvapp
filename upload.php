<?php
require_once __DIR__ . '/init.php';

// Iniciar sesión si no está iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// MODO DEBUG: Logging detallado
error_log("=== UPLOAD.PHP DEBUG INICIADO ===");
error_log("REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD']);

// Log detallado de $_POST (cuidado con contraseñas o datos sensibles)
$post_keys = array_keys($_POST);
error_log("Claves recibidas en POST: " . print_r($post_keys, true));
// Opcional: log del tamaño de $_POST para detectar límites de PHP
error_log("Tamaño estimado de POST: " . strlen(serialize($_POST)));

// Log de valores específicos (excluyendo campos sensibles como contraseñas)
$fields_to_log = ['nombre', 'email', 'telefono', 'dni', 'edad', 'estado_civil', 'hijos', 'edad_hijos', 'nacionalidades', 'lugar_residencia', 'ocupacion_actual', 'ocupacion_padre', 'ocupacion_madre', 'nivel_educativo', 'carreras_titulos', 'cursos_capacitaciones', 'anios_experiencia', 'antecedentes_penales', 'disponibilidad', 'areas', 'experiencia', 'especialidades', 'licencia_conducir', 'otras_licencias'];
foreach ($fields_to_log as $field) {
    if (isset($_POST[$field])) {
        $value = is_array($_POST[$field]) ? 'ARRAY[' . count($_POST[$field]) . ']' : $_POST[$field];
        error_log("POST[{$field}]: {$value}");
    }
}

// Log de experiencias laborales si existen
if (isset($_POST['experiencia']) && is_array($_POST['experiencia'])) {
    error_log("=== DETALLES DE EXPERIENCIAS LABORALES RECIBIDAS ===");
    foreach ($_POST['experiencia'] as $index => $exp) {
        error_log("Experiencia [{$index}]: " . print_r($exp, true));
    }
} else {
    error_log("No se recibieron datos de experiencia laboral o no es un array.");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("ERROR: Método HTTP no es POST. Método recibido: " . $_SERVER['REQUEST_METHOD']);
    header('Location: index.php');
    exit;
}

// Validación CSRF
if (!csrf_verify($_POST['csrf'] ?? null)) {
    error_log("ERROR: Token CSRF inválido o ausente.");
    $_SESSION['form_errors']['general'] = 'Sesión inválida. Recarga la página.';
    $_SESSION['form_data'] = $_POST; // Guardar datos ingresados
    header('Location: index.php');
    exit;
}

// Honeypot check
if (!empty($_POST['website'])) { // honeypot
    error_log("Honeypot activado, redirigiendo como si fuera éxito.");
    header('Location: index.php?ok=1');
    exit;
}

// Recoger y sanitizar todos los datos
// Normalizar a mayúsculas entradas de texto (mantener email tal cual)
$nombre = safe_trim($_POST['nombre'] ?? '');
$nombre = mb_strtoupper($nombre, 'UTF-8');
$email = safe_trim($_POST['email'] ?? '');
$telefono = safe_trim($_POST['telefono'] ?? '');
$telefono = mb_strtoupper($telefono, 'UTF-8');
$areas = $_POST['areas'] ?? []; // Array de áreas seleccionadas
$anios_experiencia = null;
if (isset($_POST['anios_experiencia']) && $_POST['anios_experiencia'] !== '') {
    $anios_experiencia = safe_int($_POST['anios_experiencia'], -1);
} else {
    $anios_experiencia = 0; // Tratar como 0 si no se proporciona
}

// Nuevos campos demográficos
$dni = safe_trim($_POST['dni'] ?? '');
$dni = mb_strtoupper($dni, 'UTF-8');
$edad = safe_int($_POST['edad'] ?? null, -1);
$estado_civil = safe_trim($_POST['estado_civil'] ?? '');
$estado_civil = mb_strtoupper($estado_civil, 'UTF-8');
$hijos = safe_int($_POST['hijos'] ?? null, -1);
$edad_hijos = safe_trim($_POST['edad_hijos'] ?? '');
$edad_hijos = mb_strtoupper($edad_hijos, 'UTF-8');

// Procesar nacionalidades (puede ser un array de hasta 2)
$nacionalidades_array = [];
if (isset($_POST['nacionalidades']) && is_array($_POST['nacionalidades'])) {
    foreach ($_POST['nacionalidades'] as $nac) {
        $nac_limpia = safe_trim($nac);
        if ($nac_limpia !== '') {
            $nacionalidades_array[] = mb_strtoupper($nac_limpia, 'UTF-8');
        }
    }
} elseif (isset($_POST['nacionalidad'])) {
    // Retrocompatibilidad con campo antiguo
    $nac_limpia = safe_trim($_POST['nacionalidad']);
    if ($nac_limpia !== '') {
        $nacionalidades_array[] = mb_strtoupper($nac_limpia, 'UTF-8');
    }
}
// Unir las nacionalidades con " / " para almacenar en la BD
$nacionalidad = implode(' / ', $nacionalidades_array);

$lugar_residencia = safe_trim($_POST['lugar_residencia'] ?? '');
$lugar_residencia = mb_strtoupper($lugar_residencia, 'UTF-8');
$ocupacion_actual = safe_trim($_POST['ocupacion_actual'] ?? '');
$ocupacion_actual = mb_strtoupper($ocupacion_actual, 'UTF-8');
$ocupacion_padre = safe_trim($_POST['ocupacion_padre'] ?? '');
$ocupacion_padre = mb_strtoupper($ocupacion_padre, 'UTF-8');
$ocupacion_madre = safe_trim($_POST['ocupacion_madre'] ?? '');
$ocupacion_madre = mb_strtoupper($ocupacion_madre, 'UTF-8');

// Nuevos campos de formación profesional
$nivel_educativo = safe_trim($_POST['nivel_educativo'] ?? '');
$nivel_educativo = mb_strtoupper($nivel_educativo, 'UTF-8');
$carreras_titulos = safe_trim($_POST['carreras_titulos'] ?? '');
$carreras_titulos = mb_strtoupper($carreras_titulos, 'UTF-8');
$cursos_capacitaciones = safe_trim($_POST['cursos_capacitaciones'] ?? '');
$cursos_capacitaciones = mb_strtoupper($cursos_capacitaciones, 'UTF-8');

// Nuevos campos de experiencia laboral
$experiencias_raw = $_POST['experiencia'] ?? []; // Nombre temporal para el array original
error_log("=== PROCESANDO EXPERIENCIAS LABORALES (Después de sanitizar) ===");
error_log("Tipo de 'experiencias_raw': " . gettype($experiencias_raw));
if (is_array($experiencias_raw)) {
    error_log("Número de experiencias recibidas: " . count($experiencias_raw));
    foreach ($experiencias_raw as $k => $exp_item) {
        error_log("  - Experiencia [{$k}]: " . print_r($exp_item, true));
    }
}

// Normalizar campos de cada experiencia (empresa, puesto, empleador, tareas)
$experiencias = []; // Array final sanitizado
if (is_array($experiencias_raw)) {
    foreach ($experiencias_raw as $k => $exp_item) {
        $empresa = mb_strtoupper(safe_trim($exp_item['empresa'] ?? ''), 'UTF-8');
        $puesto = mb_strtoupper(safe_trim($exp_item['puesto'] ?? ''), 'UTF-8');
        $empleador = mb_strtoupper(safe_trim($exp_item['empleador'] ?? ''), 'UTF-8');
        $tareas = mb_strtoupper(safe_trim($exp_item['tareas'] ?? ''), 'UTF-8');
        $fecha_desde = safe_trim($exp_item['fecha_desde'] ?? '');
        $fecha_hasta = !empty($exp_item['fecha_hasta']) ? safe_trim($exp_item['fecha_hasta']) : null;

        // Asignar al array final
        $experiencias[$k] = [
            'empresa' => $empresa,
            'puesto' => $puesto,
            'empleador' => $empleador,
            'tareas' => $tareas,
            'fecha_desde' => $fecha_desde,
            'fecha_hasta' => $fecha_hasta
        ];
    }
}

// Log de experiencias después de sanitizar
error_log("=== EXPERIENCIAS LABORALES SANITIZADAS ===");
foreach ($experiencias as $k => $exp_sanitized) {
    error_log("  - Experiencia [{$k}] sanitizada: " . print_r($exp_sanitized, true));
}

// Nuevos campos de habilidades y disponibilidad
$antecedentes_penales = safe_trim($_POST['antecedentes_penales'] ?? '');
$licencia_conducir_array = $_POST['licencia_conducir'] ?? [];
$otras_licencias = safe_trim($_POST['otras_licencias'] ?? '');
$disponibilidad = safe_trim($_POST['disponibilidad'] ?? '');

// Nuevos campos de especialidades
$especialidades = $_POST['especialidades'] ?? []; // Array de especialidades

// Validaciones
$errores = [];

// Validar y sanitizar áreas
$areas_validas = [];
if (is_array($areas)) {
    foreach ($areas as $area) {
        $area_clean = safe_trim($area);
        if ($area_clean !== '' && mb_strlen($area_clean) <= 100) {
            $areas_validas[] = $area_clean;
        }
    }
}

// Validaciones individuales
if ($nombre === '' || mb_strlen($nombre) > 100) {
    $errores['nombre'] = 'Nombre inválido.';
    error_log("ERROR VALIDACION: Nombre inválido o vacío.");
}
// Email es opcional, solo validar si se proporciona
if ($email !== '' && (!valid_email($email) || mb_strlen($email) > 150)) {
    $errores['email'] = 'Email inválido.';
    error_log("ERROR VALIDACION: Email inválido: {$email}");
}
// Validación de teléfono más flexible
if ($telefono === '' || mb_strlen($telefono) > 30 || !preg_match('/^[0-9+\-\s\(\)]+$/', $telefono)) {
    $errores['telefono'] = 'Teléfono inválido. Solo se permiten números, +, -, espacios y paréntesis.';
    error_log("ERROR VALIDACION: Teléfono inválido o vacío: {$telefono}");
}
if (empty($areas_validas)) {
    $errores['areas'] = 'Debes seleccionar al menos un área profesional.';
    error_log("ERROR VALIDACION: No se seleccionaron áreas.");
} elseif (count($areas_validas) > 2) {
    $errores['areas'] = 'No puedes seleccionar más de 2 áreas profesionales.';
    error_log("ERROR VALIDACION: Se seleccionaron más de 2 áreas.");
}
if (!is_int($anios_experiencia) || $anios_experiencia < 0 || $anios_experiencia > 50) {
    $errores['anios_experiencia'] = 'Años de experiencia inválidos.';
    error_log("ERROR VALIDACION: Años de experiencia inválidos: {$anios_experiencia}");
}
if ($dni === '' || mb_strlen($dni) > 20 || !preg_match('/^[0-9A-Za-z\-\.]+$/', $dni)) {
    $errores['dni'] = 'DNI inválido. Solo se permiten números, letras, guiones y puntos.';
    error_log("ERROR VALIDACION: DNI inválido o vacío: {$dni}");
}
// Edad: mayores de 18 y menores de 80
if (!is_int($edad) || $edad <= 18 || $edad >= 80) {
    $errores['edad'] = 'Edad inválida. Debe ser mayor de 18 y menor de 80 años.';
    error_log("ERROR VALIDACION: Edad inválida: {$edad}");
}
// Validar estado civil comparando en mayúsculas para ser case-insensitive
$valid_estados = array_map(function($v){ return mb_strtoupper($v, 'UTF-8'); }, ['Soltero', 'Casado', 'Divorciado', 'Viudo', 'Unión libre']);
if (!in_array(mb_strtoupper($estado_civil, 'UTF-8'), $valid_estados)) {
    $errores['estado_civil'] = 'Estado civil inválido.';
    error_log("ERROR VALIDACION: Estado civil inválido: {$estado_civil}");
}
if ($hijos < 0 || $hijos > 20) {
    $errores['hijos'] = 'Número de hijos inválido.';
    error_log("ERROR VALIDACION: Número de hijos inválido: {$hijos}");
}
if ($hijos > 0 && $edad_hijos === '') {
    $errores['edad_hijos'] = 'Debes especificar las edades de los hijos.';
    error_log("ERROR VALIDACION: Hijos > 0 pero edades no especificadas.");
}
// Validar nacionalidades (al menos 1, máximo 2)
if (empty($nacionalidades_array)) {
    $errores['nacionalidades'] = 'Debe especificar al menos una nacionalidad.';
    error_log("ERROR VALIDACION: No se especificó ninguna nacionalidad");
} elseif (count($nacionalidades_array) > 2) {
    $errores['nacionalidades'] = 'Solo se permiten hasta 2 nacionalidades.';
    error_log("ERROR VALIDACION: Se especificaron más de 2 nacionalidades");
} elseif (mb_strlen($nacionalidad) > 255) {
    $errores['nacionalidades'] = 'Las nacionalidades son demasiado largas.';
    error_log("ERROR VALIDACION: Nacionalidades muy largas: {$nacionalidad}");
}
if ($lugar_residencia === '' || mb_strlen($lugar_residencia) > 255) {
    $errores['lugar_residencia'] = 'Lugar de residencia inválido.';
    error_log("ERROR VALIDACION: Lugar de residencia inválido o vacío: {$lugar_residencia}");
}
if ($ocupacion_actual === '' || mb_strlen($ocupacion_actual) > 100) {
    $errores['ocupacion_actual'] = 'Ocupación actual inválida.';
    error_log("ERROR VALIDACION: Ocupación actual inválida o vacía: {$ocupacion_actual}");
}
if ($ocupacion_padre === '' || mb_strlen($ocupacion_padre) > 100) {
    $errores['ocupacion_padre'] = 'Ocupación del padre inválida.';
    error_log("ERROR VALIDACION: Ocupación del padre inválida o vacía: {$ocupacion_padre}");
}
if ($ocupacion_madre === '' || mb_strlen($ocupacion_madre) > 100) {
    $errores['ocupacion_madre'] = 'Ocupación de la madre inválida.';
    error_log("ERROR VALIDACION: Ocupación de la madre inválida o vacía: {$ocupacion_madre}");
}
// Validar nivel educativo (case-insensitive)
$valid_niveles = array_map(function($v){ return mb_strtoupper($v, 'UTF-8'); }, ['Primaria', 'Secundaria', 'Universitaria', 'Terciario']);
if ($nivel_educativo === '' || !in_array(mb_strtoupper($nivel_educativo, 'UTF-8'), $valid_niveles)) {
    $errores['nivel_educativo'] = 'Nivel educativo inválido.';
    error_log("ERROR VALIDACION: Nivel educativo inválido o vacío: {$nivel_educativo}");
}

// Validaciones para experiencia laboral
$experiencias_validas = [];
error_log("=== VALIDANDO EXPERIENCIAS LABORALES ===");

if (is_array($experiencias)) {
    $exp_index = 0;
    foreach ($experiencias as $exp_data) {
        $empresa = $exp_data['empresa'] ?? '';
        $puesto = $exp_data['puesto'] ?? '';
        $empleador = $exp_data['empleador'] ?? '';
        $fecha_desde = $exp_data['fecha_desde'] ?? '';
        $tareas = $exp_data['tareas'] ?? '';

        error_log("Validando Experiencia {$exp_index}: empresa='{$empresa}', puesto='{$puesto}', empleador='{$empleador}', fecha_desde='{$fecha_desde}', tareas='{$tareas}'");

        // Solo validar si al menos un campo está lleno (para evitar validar campos vacíos si el usuario no los llenó)
        $algun_campo_lleno = !empty($empresa) || !empty($puesto) || !empty($empleador) || !empty($fecha_desde) || !empty($tareas);
        $todos_campos_llenos = !empty($empresa) && !empty($puesto) && !empty($empleador) && !empty($fecha_desde) && !empty($tareas);

        if ($algun_campo_lleno) {
            error_log("  - Experiencia {$exp_index} tiene campos llenos, validando completamente...");
            // Si algún campo requerido está vacío, marcar error
            if (empty($empresa)) {
                $errores["experiencia_{$exp_index}_empresa"] = 'Nombre de la empresa es obligatorio.';
                error_log("  - ERROR: Experiencia {$exp_index} - empresa vacía");
            }
            if (empty($puesto)) {
                $errores["experiencia_{$exp_index}_puesto"] = 'Puesto es obligatorio.';
                error_log("  - ERROR: Experiencia {$exp_index} - puesto vacío");
            }
            if (empty($empleador)) {
                $errores["experiencia_{$exp_index}_empleador"] = 'Empleador/Contacto es obligatorio.';
                error_log("  - ERROR: Experiencia {$exp_index} - empleador vacío");
            }
            if (empty($fecha_desde)) {
                $errores["experiencia_{$exp_index}_fecha_desde"] = 'Fecha de inicio es obligatoria.';
                error_log("  - ERROR: Experiencia {$exp_index} - fecha_desde vacía");
            } else {
                // Validar formato de fecha
                $fecha_desde_obj = DateTime::createFromFormat('Y-m-d', $fecha_desde);
                if (!$fecha_desde_obj || $fecha_desde_obj->format('Y-m-d') !== $fecha_desde) {
                    $errores["experiencia_{$exp_index}_fecha_desde"] = 'Formato de fecha de inicio inválido.';
                    error_log("  - ERROR: Experiencia {$exp_index} - formato fecha_desde inválido: {$fecha_desde}");
                }
            }
            if (empty($tareas)) {
                $errores["experiencia_{$exp_index}_tareas"] = 'Tareas principales son obligatorias.';
                error_log("  - ERROR: Experiencia {$exp_index} - tareas vacías");
            }

            // Si no hay errores para esta experiencia, agregarla al array de válidas
            if (!isset($errores["experiencia_{$exp_index}_empresa"]) &&
                !isset($errores["experiencia_{$exp_index}_puesto"]) &&
                !isset($errores["experiencia_{$exp_index}_empleador"]) &&
                !isset($errores["experiencia_{$exp_index}_fecha_desde"]) &&
                !isset($errores["experiencia_{$exp_index}_tareas"])) {

                $experiencias_validas[] = [
                    'empresa' => $empresa,
                    'puesto' => $puesto,
                    'empleador' => $empleador,
                    'fecha_desde' => $fecha_desde,
                    'fecha_hasta' => $exp_data['fecha_hasta'], // Puede ser null
                    'tareas' => $tareas
                ];
                error_log("  - Experiencia {$exp_index} es válida y se agregó a experiencias_validas.");
            } else {
                error_log("  - Experiencia {$exp_index} tiene errores, NO se agregará a experiencias_validas.");
            }
        } else {
            error_log("  - Experiencia {$exp_index} está completamente vacía, omitiendo validación.");
        }
        $exp_index++;
    }
} else {
    error_log("ERROR: 'experiencias' no es un array o no existe en POST.");
}

// Validaciones para habilidades y disponibilidad
if (!in_array($antecedentes_penales, ['Si', 'No'])) {
    $errores['antecedentes_penales'] = 'Debe indicar si tiene antecedentes penales.';
    error_log("ERROR VALIDACION: Antecedentes penales inválido: {$antecedentes_penales}");
}
if (!in_array($disponibilidad, ['Inmediata', '15 días', '30 días'])) {
    $errores['disponibilidad'] = 'Disponibilidad inválida.';
    error_log("ERROR VALIDACION: Disponibilidad inválida: {$disponibilidad}");
}

error_log("=== RESUMEN DE VALIDACIONES INICIALES ===");
error_log("Total de errores encontrados: " . count($errores));
if (!empty($errores)) {
    error_log("Errores específicos encontrados: " . print_r($errores, true));
}

// Si hay errores iniciales, guardarlos y redirigir
if (!empty($errores)) {
    error_log("HAY ERRORES INICIALES - Guardando en sesión y redirigiendo a index.php");
    $_SESSION['form_errors'] = $errores;
    $_SESSION['form_data'] = $_POST; // Guardar datos ingresados
    header('Location: index.php');
    exit;
}

error_log("No hay errores iniciales, continuando con validaciones de Base de Datos");

// Si no hay errores iniciales, continuar con validaciones que requieren BD
if (empty($errores)) {
    // Verificar que las áreas seleccionadas existen y están activas
    global $pdo;
    if (!empty($areas_validas)) {
        $placeholders = str_repeat('?,', count($areas_validas) - 1) . '?';
        $stmt = $pdo->prepare("SELECT nombre FROM areas_profesionales WHERE nombre IN ($placeholders) AND activa = 1");
        $stmt->execute($areas_validas);
        $areas_existentes = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (count($areas_existentes) !== count($areas_validas)) {
            $errores['areas'] = 'Una o más áreas seleccionadas no son válidas.';
            error_log("ERROR BD: Áreas seleccionadas no coinciden con las válidas en BD.");
        } else {
            error_log("Áreas seleccionadas verificadas correctamente contra la BD.");
        }
    }

    if (empty($errores)) {
        // Verificar que el DNI no esté ya registrado (validación única por DNI)
        $stmt = $pdo->prepare('SELECT id FROM candidatos WHERE dni = ? LIMIT 1');
        $stmt->execute([$dni]);
        if ($stmt->fetch()) {
            $errores['dni'] = 'Ya existe un candidato registrado con este DNI.';
            error_log("ERROR BD: DNI duplicado detectado: {$dni}");
        } else {
            error_log("DNI no encontrado en BD, es único.");
        }
    }

    if (empty($errores)) {
        [$ok, $msg] = validate_photo_upload($_FILES['foto'] ?? []);
        if (!$ok) {
            $errores['foto'] = $msg;
            error_log("ERROR FOTO: " . $msg);
        } else {
            error_log("Validación de foto subida exitosa.");
        }
    }
}

// Si hay errores después de las validaciones de BD, guardarlos y redirigir
if (!empty($errores)) {
    error_log("HAY ERRORES DESPUÉS DE VALIDACIONES BD - Guardando en sesión y redirigiendo a index.php");
    error_log("Errores BD: " . print_r($errores, true));
    $_SESSION['form_errors'] = $errores;
    $_SESSION['form_data'] = $_POST; // Guardar datos ingresados
    header('Location: index.php');
    exit;
}

// Si llegamos aquí, todas las validaciones pasaron
$foto_file = $_FILES['foto'];

$foto_ext = strtolower(pathinfo($foto_file['name'], PATHINFO_EXTENSION));
$foto_destName = random_filename($foto_ext);
$foto_destPath = $APP_CONFIG['uploads_dir'] . DIRECTORY_SEPARATOR . $foto_destName;

error_log("Procesando archivo de foto: {$foto_file['name']} -> {$foto_destName}");

// Mover foto
if (!move_uploaded_file($foto_file['tmp_name'], $foto_destPath)) {
    error_log("ERROR: No se pudo mover el archivo de foto a {$foto_destPath}");
    $_SESSION['form_errors']['general'] = 'No se pudo guardar la foto. Intenta de nuevo.';
    $_SESSION['form_data'] = $_POST;
    header('Location: index.php');
    exit;
}

// Permisos seguros
@chmod($foto_destPath, 0640);

// Guardar en BD
try {
    global $pdo;
    $pdo->beginTransaction();

    // Insertar candidato con todos los datos
    $stmt = $pdo->prepare('INSERT INTO candidatos (nombre, email, telefono, experiencia, foto_nombre_original, foto_ruta,
                          dni, edad, estado_civil, hijos, edad_hijos, nacionalidad, lugar_residencia,
                          ocupacion_actual, ocupacion_padre, ocupacion_madre, fecha_carga)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
    $stmt->execute([
        $nombre, $email, $telefono, $anios_experiencia,
        $foto_file['name'], $foto_destName,
        $dni, $edad, $estado_civil, $hijos, $edad_hijos, $nacionalidad, $lugar_residencia,
        $ocupacion_actual, $ocupacion_padre, $ocupacion_madre
    ]);

    $candidato_id = $pdo->lastInsertId();
    error_log("Candidato insertado con ID: {$candidato_id}");

    // Insertar las áreas del candidato
    if ($candidato_id && !empty($areas_validas)) {
        $stmt_area = $pdo->prepare('INSERT INTO candidato_areas (candidato_id, area_profesional_id)
                                    SELECT ?, id FROM areas_profesionales WHERE nombre = ? AND activa = 1');

        foreach ($areas_validas as $area_nombre) {
            $stmt_area->execute([$candidato_id, $area_nombre]);
        }
        error_log("Áreas del candidato insertadas.");
    }

    // --- Agregar Formación Profesional ---
    if ($candidato_id && !empty($nivel_educativo)) {
        try {
            $formacion_sql = "INSERT INTO formacion_profesional (candidato_id, nivel_educativo, carreras_titulos, cursos_capacitaciones) VALUES (?, ?, ?, ?)";
            $stmt_formacion = $pdo->prepare($formacion_sql);
            $stmt_formacion->execute([$candidato_id, $nivel_educativo, $carreras_titulos, $cursos_capacitaciones]);
            error_log("Formación profesional insertada.");
        } catch (PDOException $e) {
            error_log("Error al insertar formación profesional: " . $e->getMessage());
            // No detenemos el proceso por un error en formación profesional
        }
    }
    // --- Fin Agregar Formación Profesional ---

    // --- Agregar Experiencia Laboral ---
    if ($candidato_id && !empty($experiencias_validas)) {
        try {
            $stmt_exp = $pdo->prepare("INSERT INTO experiencia_laboral
                (candidato_id, empresa, puesto, fecha_desde, fecha_hasta, tareas, empleador)
                VALUES (?, ?, ?, ?, ?, ?, ?)");

            foreach ($experiencias_validas as $exp_data) {
                $stmt_exp->execute([
                    $candidato_id,
                    $exp_data['empresa'],
                    $exp_data['puesto'],
                    $exp_data['fecha_desde'],
                    $exp_data['fecha_hasta'], // Puede ser null
                    $exp_data['tareas'],
                    $exp_data['empleador']
                ]);
            }
            error_log("Experiencias laborales insertadas.");
        } catch (PDOException $e) {
            error_log("Error al insertar experiencia laboral: " . $e->getMessage());
            // Dependiendo de tus requisitos, podrías considerar esto un error crítico o no.
            // Por ahora, continuamos.
        }
    }
    // --- Fin Agregar Experiencia Laboral ---

    // --- Agregar Habilidades y Disponibilidad ---
    if ($candidato_id) {
        try {
            // Procesar licencias de conducir
            $licencia_conducir = '';
            if (is_array($licencia_conducir_array) && !empty($licencia_conducir_array)) {
                // Filtrar y sanitizar cada licencia
                $licencias_filtradas = [];
                foreach ($licencia_conducir_array as $lic) {
                    $lic = strtoupper(safe_trim($lic));
                    if (in_array($lic, ['A', 'B', 'C', 'D', 'E', 'F', 'G'])) {
                        $licencias_filtradas[] = $lic;
                    }
                }
                $licencia_conducir = implode(',', array_unique($licencias_filtradas));
            }

            // Procesar certificado de antecedentes (si aplica)
            $certificado_ruta = null;
            $certificado_nombre_original = null;

            if ($antecedentes_penales === 'Si' && isset($_FILES['certificado_antecedentes']) && $_FILES['certificado_antecedentes']['error'] === UPLOAD_ERR_OK) {
                $cert_file = $_FILES['certificado_antecedentes'];
                $allowed_types = [
                    'application/pdf' => 'pdf',
                    'application/msword' => 'doc',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx'
                ];

                $file_type = mime_content_type($cert_file['tmp_name']);
                $file_ext = $allowed_types[$file_type] ?? null;

                if ($file_ext && $cert_file['size'] <= 2 * 1024 * 1024) { // 2MB max
                    $cert_destName = random_filename($file_ext);
                    $cert_destPath = $APP_CONFIG['uploads_dir'] . DIRECTORY_SEPARATOR . $cert_destName;

                    if (move_uploaded_file($cert_file['tmp_name'], $cert_destPath)) {
                        @chmod($cert_destPath, 0640);
                        $certificado_ruta = $cert_destName;
                        $certificado_nombre_original = $cert_file['name'];
                        error_log("Certificado de antecedentes subido: {$cert_file['name']} -> {$cert_destName}");
                    } else {
                        $errores['certificado_antecedentes'] = 'No se pudo guardar el certificado. Intente nuevamente.';
                        error_log("ERROR: No se pudo mover el archivo de certificado a {$cert_destPath}");
                    }
                } else {
                    $errores['certificado_antecedentes'] = 'Formato o tamaño de archivo inválido para el certificado (solo PDF, DOC, DOCX, max 2MB).';
                    error_log("ERROR: Tipo o tamaño de archivo inválido para certificado: {$file_type}, tamaño: {$cert_file['size']}");
                }

                // Si hay errores con el certificado, redirigir
                if (!empty($errores)) {
                    error_log("HAY ERRORES CON EL CERTIFICADO - Guardando en sesión y redirigiendo a index.php");
                    $_SESSION['form_errors'] = $errores;
                    $_SESSION['form_data'] = $_POST;
                    header('Location: index.php');
                    exit;
                }
            } else {
                error_log("Certificado de antecedentes no requerido o no subido.");
            }

            // Insertar en la base de datos
            $stmt_hab = $pdo->prepare("INSERT INTO habilidades_disponibilidad
                (candidato_id, antecedentes_penales, certificado_ruta, certificado_nombre_original,
                licencia_conducir, otras_licencias, disponibilidad)
                VALUES (?, ?, ?, ?, ?, ?, ?)");

            $stmt_hab->execute([
                $candidato_id,
                $antecedentes_penales,
                $certificado_ruta,
                $certificado_nombre_original,
                $licencia_conducir,
                $otras_licencias,
                $disponibilidad
            ]);
            error_log("Habilidades y disponibilidad insertadas.");

        } catch (PDOException $e) {
            error_log("Error al insertar habilidades y disponibilidad: " . $e->getMessage());
            // Opcional: podrías considerar esto un error crítico
        }
    }
    // --- Fin Agregar Habilidades y Disponibilidad ---

    // --- Agregar Especialidades ---
    // Ahora cada especialidad puede tener múltiples niveles seleccionados
    if ($candidato_id && !empty($especialidades)) {
        try {
            $stmt_esp = $pdo->prepare('INSERT INTO candidato_especialidades (candidato_id, especialidad_id, nivel_id) VALUES (?, ?, ?)');

            foreach ($especialidades as $especialidad_id => $esp_data) {
                $especialidad_id = safe_int($especialidad_id);
                
                // Verificar si hay niveles seleccionados (array de checkboxes)
                if (isset($esp_data['niveles']) && is_array($esp_data['niveles'])) {
                    foreach ($esp_data['niveles'] as $nivel_id) {
                        $nivel_id = safe_int($nivel_id);
                        
                        // Solo insertar si el nivel es válido
                        if ($nivel_id > 0 && $especialidad_id > 0) {
                            // Verificar que el nivel existe, está activo y pertenece a la especialidad
                            $stmt_check = $pdo->prepare('SELECT id FROM niveles_especialidades WHERE id = ? AND especialidad_id = ? AND activo = 1');
                            $stmt_check->execute([$nivel_id, $especialidad_id]);
                            
                            if ($stmt_check->fetch()) {
                                $stmt_esp->execute([$candidato_id, $especialidad_id, $nivel_id]);
                            } else {
                                error_log("Nivel ID {$nivel_id} no válido para especialidad ID {$especialidad_id}");
                            }
                        }
                    }
                }
            }
            
            // Contar cuántas especialidades/niveles se insertaron
            $stmt_count = $pdo->prepare('SELECT COUNT(*) FROM candidato_especialidades WHERE candidato_id = ?');
            $stmt_count->execute([$candidato_id]);
            $total = $stmt_count->fetchColumn();
            error_log("Se insertaron {$total} especialidades/niveles para el candidato.");
            
        } catch (PDOException $e) {
            error_log("Error al insertar especialidades del candidato: " . $e->getMessage());
            // No detenemos el proceso por un error en especialidades
        }
    }
    // --- Fin Agregar Especialidades ---

    $pdo->commit();
    error_log("Transacción de base de datos completada exitosamente.");

    // Limpiar datos de sesión después del éxito
    unset($_SESSION['form_data']);
    unset($_SESSION['form_errors']);

} catch (Throwable $e) {
    error_log("ERROR CRÍTICO: Excepción durante la transacción de base de datos: " . $e->getMessage());
    $pdo->rollBack();
    // Si falla BD, borra la foto subida
    if (file_exists($foto_destPath)) {
        @unlink($foto_destPath);
        error_log("Foto subida eliminada debido al rollback: {$foto_destPath}");
    }
    $_SESSION['form_errors']['general'] = 'Error al guardar tus datos.';
    $_SESSION['form_data'] = $_POST;
    header('Location: index.php');
    exit;
}

error_log("=== UPLOAD.PHP FINALIZADO CON ÉXITO ===");
header('Location: gracias.php?ok=1');
exit;
?>