<?php
$page_title = "Mis Trámites - Sistema de Trámites";
require_once 'includes/header.php';
checkRole(['admin', 'encargado', 'ventanilla']);
// Configuración de paginación
$pagina_actual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$tramites_por_pagina = 10;
$offset = ($pagina_actual - 1) * $tramites_por_pagina;

// Filtros
$filtro_estado = $_GET['estado'] ?? 'todos';
$filtro_tipo = $_GET['tipo'] ?? 'todos';
$filtro_busqueda = $_GET['busqueda'] ?? '';
$vista = $_GET['vista'] ?? 'tabla'; // tabla o cards

// Construir consulta base
if ($_SESSION['user_rol'] == 'ventanilla') {

    $sql = "SELECT * FROM tramites WHERE usuario_id = ?";
    $params = [$_SESSION['user_id']];

} elseif ($_SESSION['user_rol'] == 'encargado') {

    $sql = "SELECT t.*, u.nombre as usuario_nombre 
            FROM tramites t 
            LEFT JOIN usuarios u ON t.usuario_id = u.id 
            WHERE t.encargado_id = ?";
            
    $params = [$_SESSION['user_id']];

} else {

    // ADMIN
    $sql = "SELECT t.*, u.nombre as usuario_nombre 
            FROM tramites t 
            LEFT JOIN usuarios u ON t.usuario_id = u.id 
            WHERE 1=1";

    $params = [];
}

// Aplicar filtros
if ($filtro_estado != 'todos') {
    $sql .= " AND estado = ?";
    $params[] = $filtro_estado;
}

if ($filtro_tipo != 'todos') {
    $sql .= " AND tipo = ?";
    $params[] = $filtro_tipo;
}

if (!empty($filtro_busqueda)) {
    $sql .= " AND (numero_tramite LIKE ? OR solicitante LIKE ? OR cedula_ruc LIKE ?)";
    $busqueda_param = "%$filtro_busqueda%";
    $params[] = $busqueda_param;
    $params[] = $busqueda_param;
    $params[] = $busqueda_param;
}

// Obtener total para paginación
$sql_count = "SELECT COUNT(*) FROM ($sql) as total";
$stmt_count = $pdo->prepare($sql_count);
$stmt_count->execute($params);
$total_tramites = $stmt_count->fetchColumn();
$total_paginas = ceil($total_tramites / $tramites_por_pagina);

// Consulta final con ordenamiento y paginación
$sql .= " ORDER BY fecha_creacion DESC LIMIT $tramites_por_pagina OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tramites = $stmt->fetchAll();

// Estadísticas
$estadisticas = [
    'total' => $total_tramites,
    'pendiente' => 0,
    'revision' => 0,
    'aprobado' => 0,
    'rechazado' => 0
];

foreach ($tramites as $tramite) {
    if (isset($estadisticas[$tramite['estado']])) {
        $estadisticas[$tramite['estado']]++;
    }
}
?>

<div class="main-container">
    <!-- Header Mejorado -->
    <section class="mis-tramites-header">
        <div class="container">
            <h1>
                <i class="bi bi-folder-check me-3"></i>
                <?php echo $_SESSION['user_rol'] == 'ventanilla' ? 'Mis Trámites' : 'Gestión de Trámites'; ?>
            </h1>
            <p class="lead">
                <?php echo $_SESSION['user_rol'] == 'ventanilla' 
                    ? 'Seguimiento y gestión de todos tus trámites registrados' 
                    : 'Administración completa de trámites del sistema'; ?>
            </p>
        </div>
    </section>

    <div class="container">
        <!-- Estadísticas Rápidas -->
        <div class="estadisticas-tramites">
            <div class="estadistica-tramite total">
                <div class="estadistica-numero"><?php echo $estadisticas['total']; ?></div>
                <div class="estadistica-label">Total</div>
            </div>
            <div class="estadistica-tramite pendiente">
                <div class="estadistica-numero"><?php echo $estadisticas['pendiente']; ?></div>
                <div class="estadistica-label">Pendientes</div>
            </div>
            <div class="estadistica-tramite revision">
                <div class="estadistica-numero"><?php echo $estadisticas['revision']; ?></div>
                <div class="estadistica-label">En Revisión</div>
            </div>
            <div class="estadistica-tramite aprobado">
                <div class="estadistica-numero"><?php echo $estadisticas['aprobado']; ?></div>
                <div class="estadistica-label">Aprobados</div>
            </div>
            <div class="estadistica-tramite rechazado">
                <div class="estadistica-numero"><?php echo $estadisticas['rechazado']; ?></div>
                <div class="estadistica-label">Rechazados</div>
            </div>
        </div>

        <!-- Filtros y Búsqueda -->
        <div class="filtros-tramites">
            <div class="filtros-header">
                <h5 class="mb-0">
                    <i class="bi bi-funnel me-2"></i>Filtros y Búsqueda
                </h5>
                <button class="filtros-toggle" onclick="toggleFiltrosAvanzados()">
                    <i class="bi bi-search"></i> Búsqueda Avanzada
                </button>
            </div>

            <form method="GET" id="filtrosForm">
                <input type="hidden" name="vista" value="<?php echo $vista; ?>">
                
                <div class="filtros-content">
                    <div class="filtro-group">
                        <label class="filtro-label">Estado</label>
                        <select name="estado" class="form-control form-control-sm" onchange="this.form.submit()">
                            <option value="todos" <?php echo $filtro_estado == 'todos' ? 'selected' : ''; ?>>Todos los estados</option>
                            <option value="pendiente" <?php echo $filtro_estado == 'pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                            <option value="revision" <?php echo $filtro_estado == 'revision' ? 'selected' : ''; ?>>En Revisión</option>
                            <option value="aprobado" <?php echo $filtro_estado == 'aprobado' ? 'selected' : ''; ?>>Aprobado</option>
                            <option value="rechazado" <?php echo $filtro_estado == 'rechazado' ? 'selected' : ''; ?>>Rechazado</option>
                        </select>
                    </div>

                    <div class="filtro-group">
                        <label class="filtro-label">Tipo</label>
                        <select name="tipo" class="form-control form-control-sm" onchange="this.form.submit()">
                            <option value="todos" <?php echo $filtro_tipo == 'todos' ? 'selected' : ''; ?>>Todos los tipos</option>
                            <option value="extension_red" <?php echo $filtro_tipo == 'extension_red' ? 'selected' : ''; ?>>Extensión de Red</option>
                            <option value="ferum" <?php echo $filtro_tipo == 'ferum' ? 'selected' : ''; ?>>FERUM</option>
                        </select>
                    </div>

                    <div class="filtro-group">
                        <label class="filtro-label">Buscar</label>
                        <input type="text" name="busqueda" class="form-control form-control-sm" 
                               placeholder="N° trámite, solicitante..." value="<?php echo htmlspecialchars($filtro_busqueda); ?>">
                    </div>

                    <div class="filtro-group">
                        <label class="filtro-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="bi bi-search me-1"></i>Buscar
                        </button>
                        <a href="estado_tramites.php" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-arrow-clockwise me-1"></i>Limpiar
                        </a>
                    </div>
                </div>

                <!-- Búsqueda Avanzada (inicialmente oculta) -->
                <div id="busquedaAvanzada" style="display: none;">
                    <div class="busqueda-avanzada mt-3">
                        <h6 class="mb-3">
                            <i class="bi bi-search-heart me-2"></i>Búsqueda Avanzada
                        </h6>
                        <div class="busqueda-grid">
                            <div>
                                <label class="filtro-label">Palabras clave</label>
                                <input type="text" class="form-control form-control-sm" placeholder="Buscar en descripción...">
                            </div>
                            <div>
                                <label class="filtro-label">Fecha desde</label>
                                <input type="date" class="form-control form-control-sm">
                            </div>
                            <div>
                                <label class="filtro-label">Fecha hasta</label>
                                <input type="date" class="form-control form-control-sm">
                            </div>
                            <div>
                                <label class="filtro-label">&nbsp;</label>
                                <button type="button" class="btn btn-outline-primary btn-sm w-100">
                                    <i class="bi bi-filter me-1"></i>Filtrar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Toggle de Vista -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="vista-toggle">
                <a href="?<?php echo http_build_query(array_merge($_GET, ['vista' => 'tabla'])); ?>" 
                   class="btn-vista <?php echo $vista == 'tabla' ? 'active' : ''; ?>">
                    <i class="bi bi-table"></i> Vista Tabla
                </a>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['vista' => 'cards'])); ?>" 
                   class="btn-vista <?php echo $vista == 'cards' ? 'active' : ''; ?>">
                    <i class="bi bi-grid-3x3-gap"></i> Vista Tarjetas
                </a>
            </div>
            
            <?php if ($_SESSION['user_rol'] == 'ventanilla'): ?>
            <a href="subir_tramites.php" class="btn btn-primary">
                <i class="bi bi-plus-circle me-2"></i>Nuevo Trámite
            </a>
            <?php endif; ?>
        </div>

        <!-- Lista de Trámites -->
        <div class="lista-tramites-mejorada">
            <div class="lista-header">
                <h2>
                    <i class="bi bi-list-ul me-2"></i>
                    <?php echo $_SESSION['user_rol'] == 'ventanilla' ? 'Mis Trámites' : 'Todos los Trámites'; ?>
                </h2>
                <span class="contador-tramites">
                    <?php echo $total_tramites; ?> trámite<?php echo $total_tramites != 1 ? 's' : ''; ?>
                </span>
            </div>

            <?php if ($tramites): ?>
                <?php if ($vista == 'tabla'): ?>
                <!-- Vista Tabla -->
                <div class="table-responsive">
                    <table class="tabla-tramites-mejorada">
                        <thead>
                            <tr>
                                <th>N° Trámite</th>
                                <th class="col-tipo">Tipo</th>
                                <th>Solicitante</th>
                                <?php if ($_SESSION['user_rol'] != 'cliente'): ?>
                                <th>Registrado por</th>
                                <?php endif; ?>
                                <th class="col-fecha">Fecha</th>
                                <th class="col-estado">Estado</th>
                                <th class="col-acciones">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tramites as $tramite): ?>
                            <tr>
                                <td class="col-numero">
                                    <strong><?php echo $tramite['numero_tramite']; ?></strong>
                                </td>
                                <td class="col-tipo">
                                    <span class="badge-tipo <?php echo $tramite['tipo'] == 'extension_red' ? 'extension' : 'ferum'; ?>">
                                        <?php echo $tramite['tipo'] == 'extension_red' ? 'Extensión' : 'FERUM'; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($tramite['solicitante']); ?>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($tramite['cedula_ruc']); ?></small>
                                </td>
                                <?php if ($_SESSION['user_rol'] != 'ventanilla'): ?>
                                <td><?php echo htmlspecialchars($tramite['usuario_nombre']); ?></td>
                                <?php endif; ?>
                                <td class="col-fecha">
                                    <?php echo date('d/m/Y', strtotime($tramite['fecha_creacion'])); ?>
                                    <br><small class="text-muted"><?php echo date('H:i', strtotime($tramite['fecha_creacion'])); ?></small>
                                </td>
                                <td class="col-estado">
                                    <span class="badge-estado-mejorado <?php echo $tramite['estado']; ?>">
                                        <i class="bi 
                                            <?php echo $tramite['estado'] == 'pendiente' ? 'bi-clock' : 
                                                  ($tramite['estado'] == 'revision' ? 'bi-search' : 
                                                  ($tramite['estado'] == 'aprobado' ? 'bi-check-circle' : 'bi-x-circle')); ?>">
                                        </i>
                                        <?php echo ucfirst($tramite['estado']); ?>
                                    </span>
                                </td>
                                <td class="col-acciones">
                                    <div class="acciones-tramites">
                                        <a href="detalle_tramite.php?id=<?php echo $tramite['id']; ?>" 
                                           class="btn-accion ver" title="Ver detalles">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        
                                        <?php if ($tramite['archivo_path']): ?>
                                        <a href="<?php echo $tramite['archivo_path']; ?>" 
                                           class="btn-accion descargar" title="Descargar archivo" target="_blank">
                                            <i class="bi bi-download"></i>
                                        </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($_SESSION['user_rol'] != 'ventanilla' && in_array($tramite['estado'], ['pendiente', 'revision'])): ?>
                                        <a href="revisar_tramite.php?id=<?php echo $tramite['id']; ?>" 
                                           class="btn-accion revisar" title="Revisar trámite">
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
                <!-- Vista Tarjetas -->
                <div class="tramites-cards-view">
                    <?php foreach ($tramites as $tramite): ?>
                    <div class="tramite-card-detalle">
                        <div class="tramite-card-header">
                            <div>
                                <div class="tramite-card-numero"><?php echo $tramite['numero_tramite']; ?></div>
                                <div class="tramite-card-tipo">
                                    <span class="badge-tipo <?php echo $tramite['tipo'] == 'extension_red' ? 'extension' : 'ferum'; ?>">
                                        <?php echo $tramite['tipo'] == 'extension_red' ? 'Extensión de Red' : 'FERUM'; ?>
                                    </span>
                                </div>
                            </div>
                            <span class="badge-estado-mejorado <?php echo $tramite['estado']; ?>">
                                <?php echo ucfirst($tramite['estado']); ?>
                            </span>
                        </div>
                        
                        <div class="tramite-card-body">
                            <div class="tramite-card-info">
                                <div class="tramite-card-item">
                                    <span class="tramite-card-label">Solicitante</span>
                                    <span class="tramite-card-value"><?php echo htmlspecialchars($tramite['solicitante']); ?></span>
                                </div>
                                <div class="tramite-card-item">
                                    <span class="tramite-card-label">Cédula/RUC</span>
                                    <span class="tramite-card-value"><?php echo htmlspecialchars($tramite['cedula_ruc']); ?></span>
                                </div>
                                <?php if ($_SESSION['user_rol'] != 'ventanilla'): ?>
                                <div class="tramite-card-item">
                                    <span class="tramite-card-label">Registrado por</span>
                                    <span class="tramite-card-value"><?php echo htmlspecialchars($tramite['usuario_nombre']); ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (!empty($tramite['descripcion'])): ?>
                            <div class="tramite-card-descripcion">
                                <p><?php echo htmlspecialchars($tramite['descripcion']); ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="tramite-card-footer">
                            <div class="tramite-card-fecha">
                                <?php echo date('d/m/Y H:i', strtotime($tramite['fecha_creacion'])); ?>
                            </div>
                            <div class="acciones-tramites">
                                <a href="detalle_tramite.php?id=<?php echo $tramite['id']; ?>" 
                                   class="btn-accion ver" title="Ver detalles">
                                    <i class="bi bi-eye"></i>
                                </a>
                                
                                <?php if ($tramite['archivo_path']): ?>
                                <a href="<?php echo $tramite['archivo_path']; ?>" 
                                   class="btn-accion descargar" title="Descargar archivo" target="_blank">
                                    <i class="bi bi-download"></i>
                                </a>
                                <?php endif; ?>
                                
                                <?php if ($_SESSION['user_rol'] != 'ventanilla' && in_array($tramite['estado'], ['pendiente', 'revision'])): ?>
                                <a href="revisar_tramite.php?id=<?php echo $tramite['id']; ?>" 
                                   class="btn-accion revisar" title="Revisar trámite">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Paginación -->
                <?php if ($total_paginas > 1): ?>
                <div class="paginacion-tramites">
                    <div class="info-paginacion">
                        Mostrando <?php echo count($tramites); ?> de <?php echo $total_tramites; ?> trámites
                    </div>
                    <div class="controles-paginacion">
                        <?php if ($pagina_actual > 1): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => 1])); ?>" class="btn-paginacion">
                                <i class="bi bi-chevron-double-left"></i>
                            </a>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $pagina_actual - 1])); ?>" class="btn-paginacion">
                                <i class="bi bi-chevron-left"></i> Anterior
                            </a>
                        <?php endif; ?>

                        <?php for ($i = max(1, $pagina_actual - 2); $i <= min($total_paginas, $pagina_actual + 2); $i++): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $i])); ?>" 
                               class="btn-paginacion <?php echo $i == $pagina_actual ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($pagina_actual < $total_paginas): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $pagina_actual + 1])); ?>" class="btn-paginacion">
                                Siguiente <i class="bi bi-chevron-right"></i>
                            </a>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $total_paginas])); ?>" class="btn-paginacion">
                                <i class="bi bi-chevron-double-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

            <?php else: ?>
                <!-- Empty State -->
                <div class="empty-state-tramites">
                    <div class="empty-icon">📋</div>
                    <h3>No se encontraron trámites</h3>
                    <p><?php echo $_SESSION['user_rol'] == 'ventanilla' 
                        ? 'No tienes trámites registrados con los filtros aplicados.' 
                        : 'No hay trámites que coincidan con los criterios de búsqueda.'; ?></p>
                    <?php if ($_SESSION['user_rol'] == 'ventanilla'): ?>
                        <a href="subir_tramites.php" class="btn btn-primary btn-lg">
                            <i class="bi bi-plus-circle me-2"></i>Registrar Primer Trámite
                        </a>
                    <?php else: ?>
                        <a href="estado_tramites.php" class="btn btn-outline-primary">
                            <i class="bi bi-arrow-clockwise me-2"></i>Ver Todos los Trámites
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function toggleFiltrosAvanzados() {
    const avanzados = document.getElementById('busquedaAvanzada');
    const toggle = document.querySelector('.filtros-toggle');
    
    if (avanzados.style.display === 'none') {
        avanzados.style.display = 'block';
        toggle.innerHTML = '<i class="bi bi-chevron-up"></i> Ocultar Búsqueda Avanzada';
    } else {
        avanzados.style.display = 'none';
        toggle.innerHTML = '<i class="bi bi-search"></i> Búsqueda Avanzada';
    }
}

// Auto-submit al cambiar filtros rápidos
document.addEventListener('DOMContentLoaded', function() {
    const selects = document.querySelectorAll('select[name="estado"], select[name="tipo"]');
    selects.forEach(select => {
        select.addEventListener('change', function() {
            this.form.submit();
        });
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>