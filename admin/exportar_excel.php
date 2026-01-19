<?php
// Activar errores para debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../init.php';
require_admin();

// Obtener los mismos filtros del dashboard
$q = safe_trim($_GET['q'] ?? '');
$area = safe_trim($_GET['area'] ?? '');
$estado_id = ($_GET['estado_id'] ?? '') !== '' ? safe_int($_GET['estado_id']) : null;
$exp_min = ($_GET['exp_min'] ?? '') !== '' ? safe_int($_GET['exp_min']) : null;
$exp_max = ($_GET['exp_max'] ?? '') !== '' ? safe_int($_GET['exp_max']) : null;
$desde = safe_trim($_GET['desde'] ?? '');
$hasta = safe_trim($_GET['hasta'] ?? '');

$order = $_GET['orden'] ?? 'fecha_carga';
$dir = strtolower($_GET['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
$allowed_order = ['fecha_carga','experiencia','nombre'];
if (!in_array($order, $allowed_order, true)) $order = 'fecha_carga';

// Construir la consulta con los mismos filtros
$where = [];
$params = [];

if ($q !== '') {
    $where[] = '(c.nombre LIKE ? OR c.email LIKE ? OR c.telefono LIKE ?)';
    $like = '%' . $q . '%';
    $params[] = $like; $params[] = $like; $params[] = $like;
}
if ($area !== '') {
    $where[] = 'ap.nombre = ?';
    $params[] = $area;
}
if ($estado_id !== null) {
    $where[] = 'c.estado_id = ?';
    $params[] = $estado_id;
}
if ($exp_min !== null) {
    $where[] = 'c.experiencia >= ?';
    $params[] = $exp_min;
}
if ($exp_max !== null) {
    $where[] = 'c.experiencia <= ?';
    $params[] = $exp_max;
}
if ($desde !== '' && valid_date($desde)) {
    $where[] = 'c.fecha_carga >= ?';
    $params[] = $desde . ' 00:00:00';
}
if ($hasta !== '' && valid_date($hasta)) {
    $where[] = 'c.fecha_carga <= ?';
    $params[] = $hasta . ' 23:59:59';
}

$where_clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Query principal - obtener TODOS los datos filtrados (sin límite)
$sql = "SELECT c.id, c.nombre, c.email, c.telefono, c.dni, c.edad, c.estado_civil, 
               c.nacionalidad, c.lugar_residencia, c.experiencia, c.fecha_carga, c.observaciones,
               COALESCE(e.nombre, 'Pendiente') as estado_nombre,
               GROUP_CONCAT(DISTINCT ap.nombre ORDER BY ap.nombre SEPARATOR ', ') as areas_profesionales,
               hd.antecedentes_penales,
               hd.licencia_conducir,
               hd.disponibilidad,
               GROUP_CONCAT(DISTINCT CASE WHEN el.puesto IS NOT NULL THEN 
                   CONCAT(el.puesto, ' en ', el.empresa, ' (', DATE_FORMAT(el.fecha_desde, '%Y-%m-%d'), ' - ', 
                   COALESCE(DATE_FORMAT(el.fecha_hasta, '%Y-%m-%d'), 'Presente'), ')') END 
                   ORDER BY el.fecha_desde DESC SEPARATOR ' | ') as experiencia_laboral,
               GROUP_CONCAT(CASE WHEN ea.nombre IS NOT NULL THEN 
                   CONCAT(ea.nombre, ' (Nivel: ', COALESCE(CAST(ce.nivel_id AS CHAR), 'N/A'), ')') END 
                   SEPARATOR ' | ') as especialidades
        FROM candidatos c 
        LEFT JOIN estados_cv e ON c.estado_id = e.id
        LEFT JOIN candidato_areas ca ON c.id = ca.candidato_id
        LEFT JOIN areas_profesionales ap ON ca.area_profesional_id = ap.id
        LEFT JOIN habilidades_disponibilidad hd ON c.id = hd.candidato_id
        LEFT JOIN experiencia_laboral el ON c.id = el.candidato_id
        LEFT JOIN candidato_especialidades ce ON c.id = ce.candidato_id
        LEFT JOIN especialidades_areas ea ON ce.especialidad_id = ea.id
        $where_clause 
        GROUP BY c.id
        ORDER BY c.fecha_carga DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $candidatos = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error en exportar_excel.php: " . $e->getMessage());
    error_log("SQL: " . $sql);
    error_log("Params: " . print_r($params, true));
    die("Error al obtener datos: " . $e->getMessage());
}

// Configurar headers para descarga de Excel
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="candidatos_' . date('Y-m-d_His') . '.xls"');
header('Pragma: no-cache');
header('Expires: 0');

// Agregar BOM para UTF-8
echo "\xEF\xBB\xBF";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Candidatos</title>
    <style>
        table {
            border-collapse: collapse;
            width: 100%;
        }
        th, td {
            border: 1px solid #000;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #4472C4;
            color: white;
            font-weight: bold;
        }
        tr:nth-child(even) {
            background-color: #F2F2F2;
        }
    </style>
</head>
<body>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Email</th>
                <th>Teléfono</th>
                <th>DNI</th>
                <th>Edad</th>
                <th>Estado Civil</th>
                <th>Nacionalidad</th>
                <th>Lugar de Residencia</th>
                <th>Experiencia (años)</th>
                <th>Áreas Profesionales</th>
                <th>Estado</th>
                <th>Antecedentes Penales</th>
                <th>Licencia de Conducir</th>
                <th>Disponibilidad</th>
                <th>Especialidades</th>
                <th>Experiencia Laboral</th>
                <th>Fecha de Registro</th>
                <th>Observaciones</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($candidatos)): ?>
                <tr>
                    <td colspan="19" style="text-align: center;">No se encontraron candidatos con los filtros aplicados</td>
                </tr>
            <?php else: ?>
                <?php foreach ($candidatos as $candidato): ?>
                    <tr>
                        <td><?= htmlspecialchars($candidato['id']) ?></td>
                        <td><?= htmlspecialchars($candidato['nombre']) ?></td>
                        <td><?= htmlspecialchars($candidato['email'] ?? '') ?></td>
                        <td><?= htmlspecialchars($candidato['telefono']) ?></td>
                        <td><?= htmlspecialchars($candidato['dni'] ?? '') ?></td>
                        <td><?= htmlspecialchars($candidato['edad'] ?? '') ?></td>
                        <td><?= htmlspecialchars($candidato['estado_civil'] ?? '') ?></td>
                        <td><?= htmlspecialchars($candidato['nacionalidad'] ?? '') ?></td>
                        <td><?= htmlspecialchars($candidato['lugar_residencia'] ?? '') ?></td>
                        <td><?= htmlspecialchars($candidato['experiencia']) ?></td>
                        <td><?= htmlspecialchars($candidato['areas_profesionales'] ?? '') ?></td>
                        <td><?= htmlspecialchars($candidato['estado_nombre']) ?></td>
                        <td><?= htmlspecialchars($candidato['antecedentes_penales'] ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars($candidato['licencia_conducir'] ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars($candidato['disponibilidad'] ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars($candidato['especialidades'] ?? '') ?></td>
                        <td><?= htmlspecialchars($candidato['experiencia_laboral'] ?? '') ?></td>
                        <td><?= date('d/m/Y H:i', strtotime($candidato['fecha_carga'])) ?></td>
                        <td><?= htmlspecialchars($candidato['observaciones'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>
