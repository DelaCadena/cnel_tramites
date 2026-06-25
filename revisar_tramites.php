<?php
$page_title = "Revisar Trámites - Sistema de Trámites";
require_once 'includes/header.php';

checkRole(['admin', 'encargado', 'personal', 'ventanilla']);

$rol    = $_SESSION['user_rol'];
$userId = (int)$_SESSION['user_id'];

/* ── Filtros de búsqueda ── */
$busqueda      = trim($_GET['busqueda'] ?? '');
$filtroEstado  = $_GET['estado']    ?? '';
$filtroTipo    = $_GET['tipo']      ?? '';
$filtroPrior   = $_GET['prioridad'] ?? '';
$verConstruidos = isset($_GET['ver_construidos']); // checkbox

/*------------------------------------------
  CONSULTA SEGÚN ROL
------------------------------------------*/

$params = [];
$where  = [];

if ($rol === 'encargado') {
    $where[]  = "t.encargado_id = ?";
    $params[] = $userId;
    if (!$verConstruidos) {
        $where[] = "t.construido = 0";
    }

} elseif ($rol === 'personal') {
    $where[]  = "(t.encargado_id = ? OR EXISTS (
                    SELECT 1 FROM revisiones r WHERE r.tramite_id = t.id AND r.usuario_id = ?
                 ))";
    $params[] = $userId;
    $params[] = $userId;
    if (!$verConstruidos) {
        $where[] = "t.construido = 0";
    }

} elseif ($rol === 'ventanilla') {
    /* Ve todos, sin restricción de estado ni construido */

} else {
    /* admin — todos, acceso total siempre */
}

/* Filtro de búsqueda por número o nombre */
if ($busqueda !== '') {
    $where[]  = "(t.numero_tramite LIKE ? OR t.solicitante LIKE ? OR t.cedula_ruc LIKE ?)";
    $like     = "%$busqueda%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if ($filtroEstado !== '') {
    $where[]  = "t.estado = ?";
    $params[] = $filtroEstado;
}

if ($filtroTipo !== '') {
    $where[]  = "t.tipo = ?";
    $params[] = $filtroTipo;
}

if ($filtroPrior !== '') {
    $where[]  = "t.prioridad = ?";
    $params[] = $filtroPrior;
}

$whereSQL = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$stmt = $pdo->prepare("
    SELECT t.*, u.nombre AS usuario_nombre
    FROM tramites t
    LEFT JOIN usuarios u ON t.usuario_id = u.id
    $whereSQL
    ORDER BY
        t.construido ASC,
        CASE t.prioridad
            WHEN 'urgente' THEN 1
            WHEN 'alta'    THEN 2
            WHEN 'media'   THEN 3
            WHEN 'baja'    THEN 4
            ELSE 5
        END ASC,
        t.fecha_creacion DESC
");
$stmt->execute($params);
$tramites = $stmt->fetchAll();

/* Solo lectura para ventanilla. Personal SÍ puede actuar (revisar/reasignar/priorizar) */
$soloVisualizacion = ($rol === 'ventanilla');

$etiquetasPrioridad = [
    'baja'    => ['🟢', 'Baja',    '#198754'],
    'media'   => ['🟡', 'Media',   '#f39c12'],
    'alta'    => ['🟠', 'Alta',    '#fd7e14'],
    'urgente' => ['🔴', 'Urgente', '#dc3545'],
];
?>

<div class="container py-4">

    <div class="page-header mb-4">
        <h1>
            <?php if ($soloVisualizacion): ?>
                <i class="bi bi-eye me-2"></i>Consultar Trámites
            <?php else: ?>
                <i class="bi bi-clipboard-check me-2"></i>Revisar Trámites
            <?php endif; ?>
        </h1>
        <p class="text-muted mb-0">
            <?php if ($rol === 'ventanilla'): ?>
                Consulta y seguimiento de todos los trámites registrados en el sistema.
            <?php elseif ($rol === 'personal'): ?>
                Trámites asignados a ti o en los que has participado.
            <?php elseif ($rol === 'encargado'): ?>
                Trámites asignados a tu cargo.
            <?php else: ?>
                Gestión de todos los trámites del sistema.
            <?php endif; ?>
        </p>
    </div>

    <!-- ── BUSCADOR Y FILTROS ── -->
    <div class="card mb-4" style="border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.08);">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label fw-600 mb-1" style="font-size:.9rem;">
                        <i class="bi bi-search me-1"></i> Buscar
                    </label>
                    <input type="text" name="busqueda" class="form-control"
                           placeholder="N° trámite, solicitante o cédula..."
                           value="<?= htmlspecialchars($busqueda) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-600 mb-1" style="font-size:.9rem;">Estado</label>
                    <select name="estado" class="form-select">
                        <option value="">Todos</option>
                        <option value="pendiente"  <?= $filtroEstado === 'pendiente'  ? 'selected' : '' ?>>Pendiente</option>
                        <option value="revision"   <?= $filtroEstado === 'revision'   ? 'selected' : '' ?>>En Revisión</option>
                        <option value="aprobado"   <?= $filtroEstado === 'aprobado'   ? 'selected' : '' ?>>Aprobado</option>
                        <option value="rechazado"  <?= $filtroEstado === 'rechazado'  ? 'selected' : '' ?>>Rechazado</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-600 mb-1" style="font-size:.9rem;">Tipo</label>
                    <select name="tipo" class="form-select">
                        <option value="">Todos</option>
                        <option value="extension_red" <?= $filtroTipo === 'extension_red' ? 'selected' : '' ?>>Extensión de Red</option>
                        <option value="ferum"         <?= $filtroTipo === 'ferum'         ? 'selected' : '' ?>>FERUM</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-600 mb-1" style="font-size:.9rem;">Prioridad</label>
                    <select name="prioridad" class="form-select">
                        <option value="">Todas</option>
                        <?php foreach ($etiquetasPrioridad as $val => [$emoji, $label, $color]): ?>
                        <option value="<?= $val ?>" <?= $filtroPrior === $val ? 'selected' : '' ?>><?= $emoji ?> <?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search me-1"></i> Buscar
                    </button>
                </div>

                <?php if (in_array($rol, ['encargado', 'personal'])): ?>
                <div class="col-12">
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" id="ver_construidos" name="ver_construidos"
                               value="1" <?= $verConstruidos ? 'checked' : '' ?>
                               onchange="this.form.submit()">
                        <label class="form-check-label" for="ver_construidos" style="font-size:.88rem;">
                            Incluir trámites ya construidos (ciclo finalizado)
                        </label>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($busqueda || $filtroEstado || $filtroTipo || $filtroPrior || $verConstruidos): ?>
                <div class="col-12">
                    <a href="revisar_tramites.php" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-x-lg me-1"></i> Limpiar filtros
                    </a>
                </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Estadística rápida -->
    <div class="mb-3 d-flex align-items-center gap-2 flex-wrap">
        <span class="badge bg-secondary" style="font-size:.9rem;padding:.5rem .9rem;border-radius:8px;">
            <?= count($tramites) ?> trámite<?= count($tramites) !== 1 ? 's' : '' ?> encontrado<?= count($tramites) !== 1 ? 's' : '' ?>
        </span>
        <?php if ($soloVisualizacion): ?>
        <span class="badge bg-info text-white" style="font-size:.85rem;padding:.45rem .8rem;border-radius:8px;">
            <i class="bi bi-eye me-1"></i> Solo visualización
        </span>
        <?php endif; ?>
    </div>

    <!-- TABLA DE TRÁMITES -->
    <div class="card" style="border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.08);">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>N° Trámite</th>
                            <th>Tipo</th>
                            <th>Solicitante</th>
                            <th>Cédula / RUC</th>
                            <th>Fecha</th>
                            <th>Estado</th>
                            <th>Prioridad</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($tramites): ?>
                        <?php foreach ($tramites as $index => $tramite): ?>
                        <tr style="<?= (int)$tramite['construido'] === 1 ? 'opacity:.65;' : '' ?>">
                            <td><?= $index + 1 ?></td>
                            <td><strong><?= htmlspecialchars($tramite['numero_tramite']) ?></strong></td>
                            <td>
                                <?= $tramite['tipo'] === 'extension_red'
                                    ? '<span class="badge-tipo extension">Extensión de Red</span>'
                                    : '<span class="badge-tipo ferum">FERUM</span>'
                                ?>
                            </td>
                            <td><?= htmlspecialchars($tramite['solicitante']) ?></td>
                            <td><?= htmlspecialchars($tramite['cedula_ruc']) ?></td>
                            <td><?= date('d/m/Y H:i', strtotime($tramite['fecha_creacion'])) ?></td>
                            <td>
                                <?php
                                $badgeMap = [
                                    'pendiente' => ['bg-warning text-dark', 'Pendiente'],
                                    'revision'  => ['bg-info text-white',   'En Revisión'],
                                    'aprobado'  => ['bg-success text-white', 'Aprobado'],
                                    'rechazado' => ['bg-danger text-white',  'Rechazado'],
                                ];
                                [$cls, $lbl] = $badgeMap[$tramite['estado']] ?? ['bg-secondary text-white', ucfirst($tramite['estado'])];
                                ?>
                                <span class="badge <?= $cls ?>"><?= $lbl ?></span>
                                <?php if ((int)$tramite['construido'] === 1): ?>
                                <br><span class="badge mt-1" style="background:#198754;color:#fff;font-size:.7rem;">🏗️ Construido</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($tramite['prioridad']): ?>
                                    <?php [$emoji, $label, $color] = $etiquetasPrioridad[$tramite['prioridad']]; ?>
                                    <span style="background:<?= $color ?>22;color:<?= $color ?>;border:1px solid <?= $color ?>55;padding:.3rem .7rem;border-radius:14px;font-size:.78rem;font-weight:600;white-space:nowrap;">
                                        <?= $emoji ?> <?= $label ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted" style="font-size:.8rem;">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($soloVisualizacion): ?>
                                    <a href="detalle_tramite.php?id=<?= $tramite['id'] ?>"
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye me-1"></i> Ver
                                    </a>
                                <?php else: ?>
                                    <?php if ((int)$tramite['construido'] === 1): ?>
                                        <a href="detalle_tramite.php?id=<?= $tramite['id'] ?>"
                                           class="btn btn-sm btn-secondary">
                                            <i class="bi bi-eye me-1"></i> Ver
                                        </a>
                                    <?php else: ?>
                                        <a href="revisar_tramite.php?id=<?= $tramite['id'] ?>"
                                           class="btn btn-sm btn-primary">
                                            <i class="bi bi-pencil me-1"></i> Revisar
                                        </a>
                                        <a href="detalle_tramite.php?id=<?= $tramite['id'] ?>"
                                           class="btn btn-sm btn-secondary">
                                            <i class="bi bi-eye me-1"></i> Detalles
                                        </a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="text-center py-5">
                                <div style="font-size:3rem;opacity:.4;">📋</div>
                                <strong class="d-block mt-2">No se encontraron trámites</strong>
                                <?php if ($busqueda || $filtroEstado || $filtroTipo || $filtroPrior): ?>
                                <a href="revisar_tramites.php" class="btn btn-sm btn-outline-secondary mt-2">
                                    <i class="bi bi-x-lg me-1"></i> Limpiar filtros
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<?php require_once 'includes/footer.php'; ?>