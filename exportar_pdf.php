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

// =========================
// CONSTRUCCIÓN DE CONSULTA
// =========================
$sql = "SELECT t.*, u.nombre as usuario_nombre 
        FROM tramites t 
        LEFT JOIN usuarios u ON t.usuario_id = u.id 
        WHERE 1=1";

$params = [];

if (!empty($filtros['fecha_desde'])) {
    $sql .= " AND DATE(t.fecha_creacion) >= ?";
    $params[] = $filtros['fecha_desde'];
}

if (!empty($filtros['fecha_hasta'])) {
    $sql .= " AND DATE(t.fecha_creacion) <= ?";
    $params[] = $filtros['fecha_hasta'];
}

if (!empty($filtros['tipo'])) {
    $sql .= " AND t.tipo = ?";
    $params[] = $filtros['tipo'];
}

if (!empty($filtros['estado'])) {
    $sql .= " AND t.estado = ?";
    $params[] = $filtros['estado'];
}

if (!empty($filtros['usuario_id']) && $_SESSION['user_rol'] == 'admin') {
    $sql .= " AND t.usuario_id = ?";
    $params[] = $filtros['usuario_id'];
}

$sql .= " ORDER BY t.fecha_creacion DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tramites = $stmt->fetchAll();

// =========================
// 🔹 FECHA DEL PRIMER TRÁMITE
// =========================
$fecha_primer_tramite = null;
if (!empty($tramites)) {
    $ultimo = end($tramites);
    $fecha_primer_tramite = date('d/m/Y', strtotime($ultimo['fecha_creacion']));
    reset($tramites);
}

// =========================
// ESTADÍSTICAS
// =========================
$total_tramites    = count($tramites);
$tramites_extension = array_filter($tramites, fn($t) => $t['tipo'] == 'extension_red');
$tramites_ferum    = array_filter($tramites, fn($t) => $t['tipo'] == 'ferum');

$estados_count = [
    'pendiente' => count(array_filter($tramites, fn($t) => $t['estado'] == 'pendiente')),
    'revision'  => count(array_filter($tramites, fn($t) => $t['estado'] == 'revision')),
    'aprobado'  => count(array_filter($tramites, fn($t) => $t['estado'] == 'aprobado')),
    'rechazado' => count(array_filter($tramites, fn($t) => $t['estado'] == 'rechazado'))
];

// Mapeo de prioridades
$prioridades = [
    'baja'    => 'Baja',
    'media'   => 'Media',
    'alta'    => 'Alta',
    'urgente' => 'Urgente',
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Reporte de Trámites - CNEL</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:Arial,sans-serif;color:#333;padding:20px}
.header{text-align:center;margin-bottom:25px;border-bottom:3px solid #2c3e50;padding-bottom:10px}
.header h1{font-size:22px;color:#2c3e50}
.header h2{font-size:17px;color:#3498db}
.report-info{background:#f8f9fa;padding:15px;border-left:4px solid #3498db;margin-bottom:20px}
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:15px}
.stat-card{background:#fff;padding:15px;text-align:center;border-top:4px solid #3498db}
.stat-number{font-size:22px;font-weight:bold}
table{width:100%;border-collapse:collapse;font-size:12px;margin-top:20px}
th{background:#2c3e50;color:#fff;padding:8px}
td{padding:8px;border:1px solid #ddd}
tr:nth-child(even){background:#f8f9fa}
.badge{padding:4px 8px;border-radius:12px;font-size:11px;font-weight:bold}
.badge-pendiente{background:#fff3cd}
.badge-revision{background:#cce7ff}
.badge-aprobado{background:#d4edda}
.badge-rechazado{background:#f8d7da}
/* Badges de prioridad */
.badge-baja{background:#e2f0d9;color:#375623}
.badge-media{background:#fff2cc;color:#7d6608}
.badge-alta{background:#fce4d6;color:#843c0c}
.badge-urgente{background:#f4cccc;color:#660000;font-weight:bold}
/* Construido */
.badge-si{background:#d4edda;color:#155724}
.badge-no{background:#f8d7da;color:#721c24}
.footer{text-align:center;margin-top:25px;font-size:12px;color:#777}
</style>
</head>

<body>

<div class="header">
    <img src="https://www.cnelep.gob.ec/wp-content/uploads/2019/03/header_Logo.png" style="height:50px"><br>
    <h1>CNEL EP - UNIDAD DE NEGOCIO BOLÍVAR</h1>
    <h2>REPORTE DE TRÁMITES</h2>
    <p>Generado el <?php echo date('d/m/Y H:i'); ?></p>
</div>

<div class="report-info">
<strong>Filtros aplicados:</strong><br>
• Período:
<?php
if ($filtros['fecha_desde']) {
    echo date('d/m/Y', strtotime($filtros['fecha_desde']));
} else {
    echo $fecha_primer_tramite ?? 'Desde el inicio';
}
?> 
&nbsp;hasta
<?php echo $filtros['fecha_hasta'] ? date('d/m/Y', strtotime($filtros['fecha_hasta'])) : date('d/m/Y'); ?><br>

• Tipo: <?php echo $filtros['tipo'] ? ($filtros['tipo'] == 'extension_red' ? 'Extensión de Red' : 'FERUM') : 'Todos los tipos'; ?><br>
• Estado: <?php echo $filtros['estado'] ? ucfirst($filtros['estado']) : 'Todos los estados'; ?><br>
• Total de trámites: <strong><?php echo $total_tramites; ?></strong>
</div>

<div class="stats-grid">
<div class="stat-card"><div class="stat-number"><?php echo $total_tramites; ?></div>Total</div>
<div class="stat-card"><div class="stat-number"><?php echo count($tramites_extension); ?></div>Extensión</div>
<div class="stat-card"><div class="stat-number"><?php echo count($tramites_ferum); ?></div>FERUM</div>
<div class="stat-card"><div class="stat-number"><?php echo $estados_count['aprobado']; ?></div>Aprobados</div>
</div>

<table>
<thead>
<tr>
<th>N°</th>
<th>Tipo</th>
<th>Solicitante</th>
<th>Cédula</th>
<?php if ($_SESSION['user_rol']=='admin'): ?><th>Registrado por</th><?php endif; ?>
<th>Fecha</th>
<th>Estado</th>
<th>Prioridad</th>
<th>Construido</th>
</tr>
</thead>
<tbody>
<?php if ($tramites): foreach ($tramites as $t):
    $prioridad_label = $t['prioridad'] ? ($prioridades[$t['prioridad']] ?? ucfirst($t['prioridad'])) : 'Sin asignar';
    $prioridad_class = $t['prioridad'] ? 'badge-' . $t['prioridad'] : '';
    $construido_label = $t['construido'] ? 'Sí' : 'No';
    $construido_class = $t['construido'] ? 'badge-si' : 'badge-no';
?>
<tr>
<td><strong><?php echo $t['numero_tramite']; ?></strong></td>
<td><?php echo $t['tipo']=='extension_red'?'Extensión':'FERUM'; ?></td>
<td><?php echo htmlspecialchars($t['solicitante']); ?></td>
<td><?php echo htmlspecialchars($t['cedula_ruc']); ?></td>
<?php if ($_SESSION['user_rol']=='admin'): ?><td><?php echo htmlspecialchars($t['usuario_nombre']); ?></td><?php endif; ?>
<td><?php echo date('d/m/Y', strtotime($t['fecha_creacion'])); ?></td>
<td><span class="badge badge-<?php echo $t['estado']; ?>"><?php echo ucfirst($t['estado']); ?></span></td>
<td>
    <?php if ($t['prioridad']): ?>
        <span class="badge <?php echo $prioridad_class; ?>"><?php echo $prioridad_label; ?></span>
    <?php else: ?>
        <span style="color:#999;font-size:11px">—</span>
    <?php endif; ?>
</td>
<td><span class="badge <?php echo $construido_class; ?>"><?php echo $construido_label; ?></span></td>
</tr>
<?php endforeach; else: ?>
<tr><td colspan="9" style="text-align:center">No hay trámites</td></tr>
<?php endif; ?>
</tbody>
</table>

<div class="footer">
Sistema de Control de Trámites – CNEL EP Bolívar<br>
Generado automáticamente
</div>

</body>
</html>
<?php exit; ?>