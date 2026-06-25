<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

if (!isLoggedIn() || !in_array($_SESSION['user_rol'], ['admin', 'encargado'])) {
    header('HTTP/1.0 403 Forbidden');
    exit;
}

$filtros = [
    'fecha_desde' => $_GET['fecha_desde'] ?? '',
    'fecha_hasta' => $_GET['fecha_hasta'] ?? '',
    'tipo'        => $_GET['tipo'] ?? '',
    'estado'      => $_GET['estado'] ?? '',
    'usuario_id'  => $_GET['usuario_id'] ?? ''
];

$sql = "SELECT t.*, u.nombre as usuario_nombre 
        FROM tramites t 
        LEFT JOIN usuarios u ON t.usuario_id = u.id 
        WHERE 1=1";
$params = [];

if ($filtros['fecha_desde']) {
    $sql .= " AND DATE(t.fecha_creacion) >= ?";
    $params[] = $filtros['fecha_desde'];
}
if ($filtros['fecha_hasta']) {
    $sql .= " AND DATE(t.fecha_creacion) <= ?";
    $params[] = $filtros['fecha_hasta'];
}
if ($filtros['tipo']) {
    $sql .= " AND t.tipo = ?";
    $params[] = $filtros['tipo'];
}
if ($filtros['estado']) {
    $sql .= " AND t.estado = ?";
    $params[] = $filtros['estado'];
}
if ($filtros['usuario_id'] && $_SESSION['user_rol'] === 'admin') {
    $sql .= " AND t.usuario_id = ?";
    $params[] = $filtros['usuario_id'];
}

$sql .= " ORDER BY t.fecha_creacion DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tramites = $stmt->fetchAll();

$filename = "reporte_tramites_" . date('Y-m-d') . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// BOM UTF-8 para Excel
fputs($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

$headers = [
    'N° Trámite',
    'Tipo de Trámite',
    'Solicitante',
    'Cédula/RUC',
    'Dirección',
    'Teléfono',
    'Email',
    'Descripción',
    'Estado',
    'Prioridad',       // NUEVO
    'Construido',      // NUEVO
    'Fecha Construido', // NUEVO
    'Fecha de Registro',
    'Fecha de Actualización'
];

if ($_SESSION['user_rol'] === 'admin') {
    $headers[] = 'Registrado por';
}

fputcsv($output, $headers, ';');

// Mapeo de prioridades legibles
$prioridades = [
    'baja'    => 'Baja',
    'media'   => 'Media',
    'alta'    => 'Alta',
    'urgente' => 'Urgente',
];

foreach ($tramites as $tramite) {
    $construido      = $tramite['construido'] ? 'Sí' : 'No';
    $prioridad       = $tramite['prioridad'] ? ($prioridades[$tramite['prioridad']] ?? ucfirst($tramite['prioridad'])) : 'Sin asignar';
    $fecha_construido = $tramite['fecha_construido']
        ? '="' . date('d/m/Y H:i', strtotime($tramite['fecha_construido'])) . '"'
        : '—';

    $row = [
        $tramite['numero_tramite'],
        $tramite['tipo'] === 'extension_red' ? 'Extensión de Red' : 'FERUM',
        $tramite['solicitante'],
        $tramite['cedula_ruc'],
        $tramite['direccion'],
        $tramite['telefono']     ?: 'No proporcionado',
        $tramite['email']        ?: 'No proporcionado',
        $tramite['descripcion'],
        ucfirst($tramite['estado']),
        $prioridad,         // NUEVO
        $construido,        // NUEVO
        $fecha_construido,  // NUEVO
        '="' . date('d/m/Y H:i', strtotime($tramite['fecha_creacion'])) . '"',
        $tramite['fecha_actualizacion']
            ? '="' . date('d/m/Y H:i', strtotime($tramite['fecha_actualizacion'])) . '"'
            : 'No actualizada'
    ];

    if ($_SESSION['user_rol'] === 'admin') {
        $row[] = $tramite['usuario_nombre'];
    }

    fputcsv($output, $row, ';');
}

fclose($output);
exit;
?>