<?php
$page_title = "Generar Reportes - Sistema de Trámites";
require_once 'includes/header.php';
checkRole(['admin', 'encargado']);

// Procesar filtros
$filtros = [
    'fecha_desde'     => $_GET['fecha_desde'] ?? '',
    'fecha_hasta'     => $_GET['fecha_hasta'] ?? '',
    'tipo'            => $_GET['tipo'] ?? '',
    'estado'          => $_GET['estado'] ?? '',
    'usuario_id'      => $_GET['usuario_id'] ?? '',
    'prioridad'       => $_GET['prioridad'] ?? '',
    'construido'      => $_GET['construido'] ?? '', // '', '1' (solo construidos), '0' (solo no construidos)
];

// Construir consulta base
$sql = "SELECT t.*, u.nombre as usuario_nombre FROM tramites t LEFT JOIN usuarios u ON t.usuario_id = u.id WHERE 1=1";
$params = [];

// Aplicar filtros
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

if (!empty($filtros['prioridad'])) {
    $sql .= " AND t.prioridad = ?";
    $params[] = $filtros['prioridad'];
}

if ($filtros['construido'] === '1') {
    $sql .= " AND t.construido = 1";
} elseif ($filtros['construido'] === '0') {
    $sql .= " AND t.construido = 0";
}
// Si está vacío: no se filtra por construido — el reporte incluye TODO por defecto
// (construidos no desaparecen de reportes, solo dejan de admitir revisión)

$sql .= " ORDER BY t.fecha_creacion DESC";

// Ejecutar consulta
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tramites = $stmt->fetchAll();

// Obtener usuarios para filtro (solo admin)
$usuarios = [];
if ($_SESSION['user_rol'] == 'admin') {
    $stmt = $pdo->prepare("SELECT id, nombre FROM usuarios WHERE rol = 'cliente' ORDER BY nombre");
    $stmt->execute();
    $usuarios = $stmt->fetchAll();
}

// Estadísticas para el resumen
$total_tramites = count($tramites);
$tramites_extension = array_filter($tramites, fn($t) => $t['tipo'] == 'extension_red');
$tramites_ferum = array_filter($tramites, fn($t) => $t['tipo'] == 'ferum');
$estados_count = [
    'pendiente' => count(array_filter($tramites, fn($t) => $t['estado'] == 'pendiente')),
    'revision' => count(array_filter($tramites, fn($t) => $t['estado'] == 'revision')),
    'aprobado' => count(array_filter($tramites, fn($t) => $t['estado'] == 'aprobado')),
    'rechazado' => count(array_filter($tramites, fn($t) => $t['estado'] == 'rechazado'))
];

// Estadísticas nuevas: construidos y por nivel de prioridad
$tramites_construidos = count(array_filter($tramites, fn($t) => (int)($t['construido'] ?? 0) === 1));
$prioridad_count = [
    'baja'    => count(array_filter($tramites, fn($t) => ($t['prioridad'] ?? '') === 'baja')),
    'media'   => count(array_filter($tramites, fn($t) => ($t['prioridad'] ?? '') === 'media')),
    'alta'    => count(array_filter($tramites, fn($t) => ($t['prioridad'] ?? '') === 'alta')),
    'urgente' => count(array_filter($tramites, fn($t) => ($t['prioridad'] ?? '') === 'urgente')),
];

$etiquetasPrioridad = [
    'baja'    => ['🟢', 'Baja',    '#198754'],
    'media'   => ['🟡', 'Media',   '#f39c12'],
    'alta'    => ['🟠', 'Alta',    '#fd7e14'],
    'urgente' => ['🔴', 'Urgente', '#dc3545'],
];
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 text-cnel-primary mb-1">Generar Reportes</h1>
            <p class="text-muted mb-0">Reportes detallados de trámites del sistema</p>
        </div>
        <div class="btn-group">
            <button class="btn btn-success" onclick="exportToExcel()">
                <i class="bi bi-file-earmark-excel me-1"></i>Exportar Excel
            </button>
            <button class="btn btn-danger" onclick="exportToPDF()">
                <i class="bi bi-file-earmark-pdf me-1"></i>Exportar PDF
            </button>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card card-cnel mb-4">
        <div class="card-header card-header-cnel">
            <h5 class="card-title mb-0">
                <i class="bi bi-funnel me-2"></i>Filtros de Búsqueda
            </h5>
        </div>
        <div class="card-body">
            <form method="GET" id="filtrosForm">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label for="fecha_desde" class="form-label">Fecha Desde</label>
                        <input type="date" class="form-control" id="fecha_desde" name="fecha_desde" 
                               value="<?php echo htmlspecialchars($filtros['fecha_desde']); ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="fecha_hasta" class="form-label">Fecha Hasta</label>
                        <input type="date" class="form-control" id="fecha_hasta" name="fecha_hasta"
                               value="<?php echo htmlspecialchars($filtros['fecha_hasta']); ?>">
                    </div>
                    <div class="col-md-2">
                        <label for="tipo" class="form-label">Tipo de Trámite</label>
                        <select class="form-select" id="tipo" name="tipo">
                            <option value="">Todos</option>
                            <option value="extension_red" <?php echo $filtros['tipo'] == 'extension_red' ? 'selected' : ''; ?>>Extensión de Red</option>
                            <option value="ferum" <?php echo $filtros['tipo'] == 'ferum' ? 'selected' : ''; ?>>FERUM</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="estado" class="form-label">Estado</label>
                        <select class="form-select" id="estado" name="estado">
                            <option value="">Todos</option>
                            <option value="pendiente" <?php echo $filtros['estado'] == 'pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                            <option value="revision" <?php echo $filtros['estado'] == 'revision' ? 'selected' : ''; ?>>En Revisión</option>
                            <option value="aprobado" <?php echo $filtros['estado'] == 'aprobado' ? 'selected' : ''; ?>>Aprobado</option>
                            <option value="rechazado" <?php echo $filtros['estado'] == 'rechazado' ? 'selected' : ''; ?>>Rechazado</option>
                        </select>
                    </div>
                    <?php if ($_SESSION['user_rol'] == 'admin'): ?>
                    <div class="col-md-2">
                        <label for="usuario_id" class="form-label">Cliente</label>
                        <select class="form-select" id="usuario_id" name="usuario_id">
                            <option value="">Todos</option>
                            <?php foreach ($usuarios as $usuario): ?>
                                <option value="<?php echo $usuario['id']; ?>" 
                                    <?php echo $filtros['usuario_id'] == $usuario['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($usuario['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="row g-3 mt-1">
                    <div class="col-md-3">
                        <label for="prioridad" class="form-label">Prioridad</label>
                        <select class="form-select" id="prioridad" name="prioridad">
                            <option value="">Todas</option>
                            <?php foreach ($etiquetasPrioridad as $val => [$emoji, $label, $color]): ?>
                            <option value="<?= $val ?>" <?php echo $filtros['prioridad'] == $val ? 'selected' : ''; ?>>
                                <?= $emoji ?> <?= $label ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="construido" class="form-label">Construcción</label>
                        <select class="form-select" id="construido" name="construido">
                            <option value="">Todos (construidos y no construidos)</option>
                            <option value="0" <?php echo $filtros['construido'] === '0' ? 'selected' : ''; ?>>Solo no construidos</option>
                            <option value="1" <?php echo $filtros['construido'] === '1' ? 'selected' : ''; ?>>Solo construidos</option>
                        </select>
                        <small class="text-muted">Los trámites construidos nunca se ocultan del reporte por defecto.</small>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search me-1"></i>Buscar
                        </button>
                        <a href="generar_reporte.php" class="btn btn-outline-secondary">Limpiar Filtros</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Resumen Estadístico -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card card-cnel">
                <div class="card-header card-header-cnel">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-graph-up me-2"></i>Resumen Estadístico
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 col-6 mb-3">
                            <div class="text-center">
                                <div class="stat-number text-primary"><?php echo $total_tramites; ?></div>
                                <small class="text-muted">Total Trámites</small>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="text-center">
                                <div class="stat-number text-info"><?php echo count($tramites_extension); ?></div>
                                <small class="text-muted">Extensiones de Red</small>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="text-center">
                                <div class="stat-number text-warning"><?php echo count($tramites_ferum); ?></div>
                                <small class="text-muted">Trámites FERUM</small>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="text-center">
                                <div class="stat-number" style="color:#198754;"><?php echo $tramites_construidos; ?></div>
                                <small class="text-muted">🏗️ Construidos</small>
                            </div>
                        </div>
                    </div>

                    <!-- Gráfico de estados -->
                    <div class="row mt-4">
                        <div class="col-md-8">
                            <h6 class="text-cnel-primary mb-3">Distribución por Estado</h6>
                            <div class="progress mb-2" style="height: 25px;">
                                <div class="progress-bar bg-warning" style="width: <?php echo $total_tramites > 0 ? ($estados_count['pendiente'] / $total_tramites) * 100 : 0; ?>%">
                                    Pendiente: <?php echo $estados_count['pendiente']; ?>
                                </div>
                                <div class="progress-bar bg-info" style="width: <?php echo $total_tramites > 0 ? ($estados_count['revision'] / $total_tramites) * 100 : 0; ?>%">
                                    Revisión: <?php echo $estados_count['revision']; ?>
                                </div>
                                <div class="progress-bar bg-success" style="width: <?php echo $total_tramites > 0 ? ($estados_count['aprobado'] / $total_tramites) * 100 : 0; ?>%">
                                    Aprobado: <?php echo $estados_count['aprobado']; ?>
                                </div>
                                <div class="progress-bar bg-danger" style="width: <?php echo $total_tramites > 0 ? ($estados_count['rechazado'] / $total_tramites) * 100 : 0; ?>%">
                                    Rechazado: <?php echo $estados_count['rechazado']; ?>
                                </div>
                            </div>

                            <h6 class="text-cnel-primary mb-3 mt-4">Distribución por Prioridad</h6>
                            <div class="d-flex gap-2 flex-wrap">
                                <?php foreach ($etiquetasPrioridad as $val => [$emoji, $label, $color]): ?>
                                <span style="background:<?= $color ?>22;color:<?= $color ?>;border:1px solid <?= $color ?>55;padding:.4rem .9rem;border-radius:14px;font-size:.85rem;font-weight:600;">
                                    <?= $emoji ?> <?= $label ?>: <?= $prioridad_count[$val] ?>
                                </span>
                                <?php endforeach; ?>
                                <span style="background:#e9ecef;color:#6c757d;padding:.4rem .9rem;border-radius:14px;font-size:.85rem;font-weight:600;">
                                    Sin prioridad: <?php echo $total_tramites - array_sum($prioridad_count); ?>
                                </span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <h6 class="text-cnel-primary mb-3">Resumen por Tipo</h6>
                            <div class="d-flex justify-content-between mb-1">
                                <span>Extensiones de Red:</span>
                                <strong><?php echo count($tramites_extension); ?></strong>
                            </div>
                            <div class="d-flex justify-content-between mb-3">
                                <span>FERUM:</span>
                                <strong><?php echo count($tramites_ferum); ?></strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>🏗️ Construidos:</span>
                                <strong style="color:#198754;"><?php echo $tramites_construidos; ?></strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabla de Resultados -->
    <div class="card card-cnel">
        <div class="card-header card-header-cnel d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">
                <i class="bi bi-list-ul me-2"></i>Resultados del Reporte
                <span class="badge bg-primary ms-2"><?php echo $total_tramites; ?> trámites</span>
            </h5>
            <div class="text-muted small">
                <?php if (!empty($filtros['fecha_desde']) || !empty($filtros['fecha_hasta'])): ?>
                    Período: 
                    <?php echo $filtros['fecha_desde'] ? date('d/m/Y', strtotime($filtros['fecha_desde'])) : 'Inicio'; ?> 
                    - 
                    <?php echo $filtros['fecha_hasta'] ? date('d/m/Y', strtotime($filtros['fecha_hasta'])) : 'Hoy'; ?>
                <?php else: ?>
                    Todos los trámites
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body">
            <?php if ($tramites): ?>
            <div class="table-responsive">
                <table class="table table-hover table-cnel" id="tablaReporte">
                    <thead class="table-light">
                        <tr>
                            <th>N° Trámite</th>
                            <th>Tipo</th>
                            <th>Solicitante</th>
                            <th>Cédula/RUC</th>
                            <?php if ($_SESSION['user_rol'] == 'admin'): ?>
                            <th>Registrado por</th>
                            <?php endif; ?>
                            <th>Fecha</th>
                            <th>Estado</th>
                            <th>Prioridad</th>
                            <th>Construido</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tramites as $tramite): ?>
                        <tr style="<?= (int)($tramite['construido'] ?? 0) === 1 ? 'opacity:.7;' : '' ?>">
                            <td>
                                <strong><?php echo $tramite['numero_tramite']; ?></strong>
                            </td>
                            <td>
                                <span class="badge <?php echo $tramite['tipo'] == 'extension_red' ? 'bg-info' : 'bg-warning'; ?>">
                                    <?php echo $tramite['tipo'] == 'extension_red' ? 'Extensión Red' : 'FERUM'; ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($tramite['solicitante']); ?></td>
                            <td><?php echo htmlspecialchars($tramite['cedula_ruc']); ?></td>
                            <?php if ($_SESSION['user_rol'] == 'admin'): ?>
                            <td><?php echo htmlspecialchars($tramite['usuario_nombre']); ?></td>
                            <?php endif; ?>
                            <td><?php echo date('d/m/Y', strtotime($tramite['fecha_creacion'])); ?></td>
                            <td>
                                <span class="badge badge-status-<?php echo $tramite['estado']; ?>">
                                    <?php 
                                    $estados = [
                                        'pendiente' => 'Pendiente',
                                        'revision' => 'En Revisión', 
                                        'aprobado' => 'Aprobado',
                                        'rechazado' => 'Rechazado'
                                    ];
                                    echo $estados[$tramite['estado']];
                                    ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($tramite['prioridad']) && isset($etiquetasPrioridad[$tramite['prioridad']])): ?>
                                    <?php [$emoji, $label, $color] = $etiquetasPrioridad[$tramite['prioridad']]; ?>
                                    <span style="background:<?= $color ?>22;color:<?= $color ?>;border:1px solid <?= $color ?>55;padding:.3rem .7rem;border-radius:12px;font-size:.78rem;font-weight:600;white-space:nowrap;">
                                        <?= $emoji ?> <?= $label ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted" style="font-size:.8rem;">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ((int)($tramite['construido'] ?? 0) === 1): ?>
                                    <span class="badge" style="background:#198754;color:#fff;">🏗️ Sí</span>
                                <?php else: ?>
                                    <span class="text-muted" style="font-size:.8rem;">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="detalle_tramite.php?id=<?php echo $tramite['id']; ?>" 
                                       class="btn btn-outline-primary" 
                                       title="Ver detalles">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <?php if ((int)($tramite['construido'] ?? 0) === 0 && in_array($tramite['estado'], ['pendiente', 'revision', 'aprobado'])): ?>
                                        <a href="revisar_tramite.php?id=<?php echo $tramite['id']; ?>" 
                                           class="btn btn-outline-warning" 
                                           title="Revisar trámite">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="text-center py-5 empty-state">
                <div class="empty-icon mb-3">
                    <i class="bi bi-search"></i>
                </div>
                <h5 class="text-muted">No se encontraron trámites</h5>
                <p class="text-muted">Intenta ajustar los filtros de búsqueda</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal de Exportación -->
<div class="modal fade" id="modalExportar" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Exportar Reporte</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    El reporte se generará con los filtros aplicados actualmente.
                </div>
                <div class="mb-3">
                    <label for="formatoExportacion" class="form-label">Formato de Exportación</label>
                    <select class="form-select" id="formatoExportacion">
                        <option value="excel">Excel (.xlsx)</option>
                        <option value="pdf">PDF (.pdf)</option>
                        <option value="csv">CSV (.csv)</option>
                    </select>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="incluirDetalles" checked>
                    <label class="form-check-label" for="incluirDetalles">
                        Incluir detalles completos de los trámites
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="procesarExportacion()">Generar Reporte</button>
            </div>
        </div>
    </div>
</div>

<script>
function exportToExcel() {
    // Mostrar loading
    showLoading();
    
    const filtros = new URLSearchParams(window.location.search);
    
    // Usar fetch para manejar mejor la descarga
    fetch('exportar_excel.php?' + filtros.toString())
        .then(response => {
            if (!response.ok) throw new Error('Error en la descarga');
            return response.blob();
        })
        .then(blob => {
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.style.display = 'none';
            a.href = url;
            a.download = 'reporte_tramites_' + new Date().toISOString().split('T')[0] + '.csv';
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al descargar el reporte. Intente nuevamente.');
        })
        .finally(() => {
            hideLoading();
        });
}

function exportToPDF() {
    // Para PDF, abrimos en nueva pestaña para que el usuario pueda imprimir/guardar
    const filtros = new URLSearchParams(window.location.search);
    window.open('exportar_pdf.php?' + filtros.toString(), '_blank');
}

function showLoading() {
    // Crear overlay de loading
    const loader = document.createElement('div');
    loader.className = 'position-fixed top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center bg-dark bg-opacity-50';
    loader.style.zIndex = '9999';
    loader.innerHTML = `
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Generando reporte...</span>
        </div>
        <div class="ms-2 text-white">Generando reporte...</div>
    `;
    loader.id = 'loadingOverlay';
    document.body.appendChild(loader);
}

function hideLoading() {
    const loader = document.getElementById('loadingOverlay');
    if (loader) {
        loader.remove();
    }
}

// Validación de fechas
document.getElementById('filtrosForm').addEventListener('submit', function(e) {
    const fechaDesde = document.getElementById('fecha_desde').value;
    const fechaHasta = document.getElementById('fecha_hasta').value;
    
    if (fechaDesde && fechaHasta && new Date(fechaDesde) > new Date(fechaHasta)) {
        e.preventDefault();
        alert('La fecha "Desde" no puede ser mayor que la fecha "Hasta"');
        return false;
    }
});

// Inicializar tooltips
document.addEventListener('DOMContentLoaded', function() {
    const tooltips = document.querySelectorAll('[title]');
    tooltips.forEach(el => {
        new bootstrap.Tooltip(el);
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>