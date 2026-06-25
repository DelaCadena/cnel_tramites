<?php
$page_title = "Dashboard - Sistema de Trámites";
require_once 'includes/header.php';
checkRole(['admin', 'encargado', 'ventanilla', 'personal']);

$rol = $_SESSION['user_rol'];

/* ── Estadísticas según rol ── */
if ($rol === 'ventanilla') {

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tramites WHERE usuario_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $total_tramites = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tramites WHERE usuario_id = ? AND estado IN ('pendiente','revision')");
    $stmt->execute([$_SESSION['user_id']]);
    $tramites_pendientes = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tramites WHERE usuario_id = ? AND estado = 'aprobado'");
    $stmt->execute([$_SESSION['user_id']]);
    $tramites_aprobados = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tramites WHERE usuario_id = ? AND estado = 'rechazado'");
    $stmt->execute([$_SESSION['user_id']]);
    $tramites_rechazados = $stmt->fetchColumn();

} elseif ($rol === 'personal') {

    /* Personal: solo cuenta sus trámites asignados o en los que participó */
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM tramites t
        WHERE t.encargado_id = ?
           OR EXISTS (SELECT 1 FROM revisiones r WHERE r.tramite_id = t.id AND r.usuario_id = ?)
    ");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
    $total_tramites = $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM tramites t
        WHERE t.estado = 'pendiente'
          AND (t.encargado_id = ? OR EXISTS (SELECT 1 FROM revisiones r WHERE r.tramite_id = t.id AND r.usuario_id = ?))
    ");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
    $tramites_pendientes = $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM tramites t
        WHERE t.estado = 'revision'
          AND (t.encargado_id = ? OR EXISTS (SELECT 1 FROM revisiones r WHERE r.tramite_id = t.id AND r.usuario_id = ?))
    ");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
    $tramites_revision = $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM tramites t
        WHERE t.estado = 'aprobado'
          AND (t.encargado_id = ? OR EXISTS (SELECT 1 FROM revisiones r WHERE r.tramite_id = t.id AND r.usuario_id = ?))
    ");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
    $tramites_aprobados = $stmt->fetchColumn();

    $tramites_rechazados = 0;

} else {

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tramites");
    $stmt->execute();
    $total_tramites = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tramites WHERE estado = 'pendiente'");
    $stmt->execute();
    $tramites_pendientes = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tramites WHERE estado = 'aprobado'");
    $stmt->execute();
    $tramites_aprobados = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tramites WHERE estado = 'revision'");
    $stmt->execute();
    $tramites_revision = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tramites WHERE estado = 'rechazado'");
    $stmt->execute();
    $tramites_rechazados = $stmt->fetchColumn();
}

/* ── Trámites recientes según rol ──
   TODOS los roles hacen JOIN con usuarios para mostrar el nombre del registrador
*/
if ($rol === 'ventanilla') {

    $stmt = $pdo->prepare("
        SELECT t.*, u.nombre AS usuario_nombre
        FROM tramites t
        LEFT JOIN usuarios u ON t.usuario_id = u.id
        WHERE t.usuario_id = ?
        ORDER BY t.fecha_actualizacion DESC
        LIMIT 5
    ");
    $stmt->execute([$_SESSION['user_id']]);

} elseif ($rol === 'encargado') {

    $stmt = $pdo->prepare("
        SELECT t.*, u.nombre AS usuario_nombre
        FROM tramites t
        LEFT JOIN usuarios u ON t.usuario_id = u.id
        WHERE t.encargado_id = ?
        ORDER BY t.fecha_actualizacion DESC
        LIMIT 5
    ");
    $stmt->execute([$_SESSION['user_id']]);

} elseif ($rol === 'personal') {

    /* Personal: SOLO los asignados a él o en los que ya participó */
    $stmt = $pdo->prepare("
        SELECT t.*, u.nombre AS usuario_nombre
        FROM tramites t
        LEFT JOIN usuarios u ON t.usuario_id = u.id
        WHERE t.encargado_id = ?
           OR EXISTS (
               SELECT 1 FROM revisiones r WHERE r.tramite_id = t.id AND r.usuario_id = ?
           )
        ORDER BY t.fecha_actualizacion DESC
        LIMIT 5
    ");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);

} else { /* admin */

    $stmt = $pdo->prepare("
        SELECT t.*, u.nombre AS usuario_nombre
        FROM tramites t
        LEFT JOIN usuarios u ON t.usuario_id = u.id
        ORDER BY t.fecha_actualizacion DESC
        LIMIT 5
    ");
    $stmt->execute();
}

$tramites_recientes = $stmt->fetchAll();
?>

<div class="main-container">

    <section class="hero-section">
        <div class="container">
            <div class="hero-content text-center animate-fade-in-up">
                <h1 class="hero-title">Bienvenido, <?= $_SESSION['user_nombre'] ?></h1>
                <p class="hero-subtitle">CNEL EP — Unidad de Negocio Bolívar</p>
                <p class="lead mb-0">Sistema de Control de Trámites</p>
            </div>
        </div>
    </section>

    <div class="container py-5">

        <!-- ── Acciones Rápidas ── -->
        <section class="quick-actions">
            <div class="text-center mb-5">
                <h2 class="text-gradient h1 mb-3">Acciones Rápidas</h2>
                <p class="lead text-muted">Accede rápidamente a las funciones principales</p>
            </div>

            <div class="actions-grid">

                <?php if ($rol === 'ventanilla'): ?>

                    <a href="subir_tramites.php" class="action-card">
                        <div class="action-icon text-primary">📤</div>
                        <div class="action-content">
                            <h3>Nuevo Trámite</h3>
                            <p>Registre una nueva solicitud de extensión de red o trámite FERUM</p>
                            <span class="action-btn">Iniciar Trámite →</span>
                        </div>
                    </a>

                    <a href="estado_tramites.php" class="action-card">
                        <div class="action-icon text-info">📋</div>
                        <div class="action-content">
                            <h3>Mis Trámites</h3>
                            <p>Consulte el estado y seguimiento de sus trámites en proceso</p>
                            <span class="action-btn">Ver Estado →</span>
                        </div>
                    </a>

                <?php elseif ($rol === 'personal'): ?>

                    <a href="revisar_tramites.php" class="action-card">
                        <div class="action-icon text-warning">👁️</div>
                        <div class="action-content">
                            <h3>Mis Trámites Asignados</h3>
                            <p>Ver trámites asignados, agregar información y reasignar cuando sea necesario</p>
                            <span class="action-btn">Ver Trámites →</span>
                        </div>
                    </a>
                <?php else: /* admin y encargado */ ?>

                    <a href="revisar_tramites.php" class="action-card">
                        <div class="action-icon text-warning">👁️</div>
                        <div class="action-content">
                            <h3>Revisar Trámites</h3>
                            <p>Gestionar y revisar trámites pendientes de evaluación</p>
                            <span class="action-btn">Revisar Ahora →</span>
                        </div>
                    </a>

                    <a href="generar_reporte.php" class="action-card">
                        <div class="action-icon text-success">📊</div>
                        <div class="action-content">
                            <h3>Generar Reportes</h3>
                            <p>Reportes detallados y estadísticas de todos los trámites</p>
                            <span class="action-btn">Ver Reportes →</span>
                        </div>
                    </a>

                    <?php if ($rol === 'admin'): ?>

                    <a href="gestion_usuarios.php" class="action-card">
                        <div class="action-icon text-secondary">👥</div>
                        <div class="action-content">
                            <h3>Gestión de Usuarios</h3>
                            <p>Administrar usuarios y permisos del sistema</p>
                            <span class="action-btn">Gestionar →</span>
                        </div>
                    </a>

                    <a href="gestion_servicios.php" class="action-card">
                        <div class="action-icon text-dark">🛠️</div>
                        <div class="action-content">
                            <h3>Gestión de Servicios</h3>
                            <p>Administrar tipos de servicios por encargado</p>
                            <span class="action-btn">Gestionar →</span>
                        </div>
                    </a>

                    <?php endif; ?>
                <?php endif; ?>

            </div>
        </section>

        <!-- ── Estadísticas ── -->
        <section class="stats-section">
            <div class="text-center mb-5">
                <h2 class="text-gradient h1 mb-3">Estadísticas</h2>
                <p class="lead text-muted">Resumen general de la actividad del sistema</p>
            </div>

            <div class="stats-grid">
                <?php if ($rol === 'ventanilla'): ?>

                    <div class="stat-card info">
                        <div class="stat-icon">📁</div>
                        <div class="stat-number"><?= $total_tramites ?></div>
                        <div class="stat-label">Total de Trámites</div>
                    </div>
                    <div class="stat-card warning">
                        <div class="stat-icon">⏳</div>
                        <div class="stat-number"><?= $tramites_pendientes ?></div>
                        <div class="stat-label">En Proceso</div>
                    </div>
                    <div class="stat-card success">
                        <div class="stat-icon">✅</div>
                        <div class="stat-number"><?= $tramites_aprobados ?></div>
                        <div class="stat-label">Aprobados</div>
                    </div>
                    <div class="stat-card danger">
                        <div class="stat-icon">❌</div>
                        <div class="stat-number"><?= $tramites_rechazados ?></div>
                        <div class="stat-label">Rechazados</div>
                    </div>

                <?php else: /* admin, encargado, personal */ ?>

                    <div class="stat-card info">
                        <div class="stat-icon">📁</div>
                        <div class="stat-number"><?= $total_tramites ?></div>
                        <div class="stat-label">Total Trámites</div>
                    </div>
                    <div class="stat-card warning">
                        <div class="stat-icon">⏳</div>
                        <div class="stat-number"><?= $tramites_pendientes ?></div>
                        <div class="stat-label">Pendientes</div>
                    </div>
                    <div class="stat-card primary">
                        <div class="stat-icon">🔍</div>
                        <div class="stat-number"><?= $tramites_revision ?></div>
                        <div class="stat-label">En Revisión</div>
                    </div>
                    <div class="stat-card success">
                        <div class="stat-icon">✅</div>
                        <div class="stat-number"><?= $tramites_aprobados ?></div>
                        <div class="stat-label">Aprobados</div>
                    </div>

                <?php endif; ?>
            </div>
        </section>

        <!-- ── Actividad Reciente ── -->
        <section class="recent-section">
            <div class="section-header d-flex justify-content-between align-items-center">
                <h2 class="mb-0">
                    <i class="bi bi-clock-history me-2"></i>
                    <?= $rol === 'ventanilla' ? 'Mis Trámites Recientes' : 'Actividad Reciente' ?>
                </h2>
                <a href="<?= $rol === 'ventanilla' ? 'estado_tramites.php' : 'revisar_tramites.php' ?>" class="view-all">
                    Ver Todos <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>

            <div class="recent-list">
                <?php if ($tramites_recientes): ?>
                    <?php foreach ($tramites_recientes as $tramite): ?>
                    <div class="recent-item">
                        <div class="recent-info">
                            <span class="tramite-number"><?= $tramite['numero_tramite'] ?></span>
                            <span class="tramite-solicitante"><?= htmlspecialchars($tramite['solicitante']) ?></span>
                            <?php if ($rol !== 'ventanilla' && !empty($tramite['usuario_nombre'])): ?>
                            <span class="tramite-registrado" style="font-size:.8rem;color:#6c757d;">
                                Registrado por: <?= htmlspecialchars($tramite['usuario_nombre']) ?>
                            </span>
                            <?php endif; ?>
                            <span class="tramite-type">
                                <?= $tramite['tipo'] === 'extension_red' ? 'Extensión Red' : 'FERUM' ?>
                            </span>
                            <?php if ((int)($tramite['construido'] ?? 0) === 1): ?>
                            <span class="badge" style="background:#198754;color:#fff;font-size:.7rem;">🏗️ Construido</span>
                            <?php elseif (!empty($tramite['prioridad'])): ?>
                                <?php
                                $coloresP = ['baja' => '#198754', 'media' => '#f39c12', 'alta' => '#fd7e14', 'urgente' => '#dc3545'];
                                $emojisP  = ['baja' => '🟢', 'media' => '🟡', 'alta' => '🟠', 'urgente' => '🔴'];
                                $c = $coloresP[$tramite['prioridad']] ?? '#6c757d';
                                $e = $emojisP[$tramite['prioridad']] ?? '';
                                ?>
                                <span class="badge" style="background:<?= $c ?>22;color:<?= $c ?>;border:1px solid <?= $c ?>55;font-size:.7rem;">
                                    <?= $e ?> <?= ucfirst($tramite['prioridad']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="recent-status">
                            <?php
                            $estados = [
                                'pendiente' => 'Pendiente',
                                'revision'  => 'En Revisión',
                                'aprobado'  => 'Aprobado',
                                'rechazado' => 'Rechazado',
                            ];
                            ?>
                            <span class="badge-modern badge-status-<?= $tramite['estado'] ?>">
                                <?= $estados[$tramite['estado']] ?? ucfirst($tramite['estado']) ?>
                            </span>
                            <span class="recent-time">
                                <?= date('d/m/Y H:i', strtotime($tramite['fecha_actualizacion'])) ?>
                            </span>
                        </div>
                        <div class="recent-actions">
                            <a href="detalle_tramite.php?id=<?= $tramite['id'] ?>"
                               class="btn-modern btn-primary-modern btn-sm">
                                <i class="bi bi-eye me-1"></i>Ver
                            </a>
                            <?php
                            /* El botón "Revisar" se muestra mientras el trámite NO esté construido,
                               sin importar si ya está aprobado — porque ahí es donde se gestiona
                               prioridad y construcción. Solo se oculta si construido = 1 (ciclo terminado). */
                            $yaConstruido = (int)($tramite['construido'] ?? 0) === 1;
                            if (in_array($rol, ['admin','encargado','personal']) && !$yaConstruido):
                            ?>
                            <a href="revisar_tramite.php?id=<?= $tramite['id'] ?>"
                               class="btn-modern btn-warning-modern btn-sm">
                                <i class="bi bi-pencil me-1"></i>Revisar
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-5">
                        <div class="empty-icon mb-3" style="font-size:4rem;">📋</div>
                        <h5 class="text-muted">No hay trámites recientes</h5>
                        <?php if ($rol === 'ventanilla'): ?>
                        <a href="subir_tramites.php" class="btn-modern btn-primary-modern mt-3">
                            <i class="bi bi-plus-circle me-2"></i>Registrar Primer Trámite
                        </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- ── Info adicional ventanilla ── -->
        <?php if ($rol === 'ventanilla'): ?>
        <section class="info-section mt-5">
            <div class="row">
                <div class="col-md-6 mb-4">
                    <div class="card-modern h-100">
                        <div class="card-header-modern">
                            <h5 class="card-title mb-0"><i class="bi bi-telephone me-2"></i>Contacto y Soporte</h5>
                        </div>
                        <div class="card-body-modern">
                            <ul class="list-unstyled">
                                <li class="mb-2"><i class="bi bi-telephone-fill text-primary me-2"></i><strong>Teléfono:</strong> (04) 234-5678</li>
                                <li class="mb-2"><i class="bi bi-envelope-fill text-primary me-2"></i><strong>Email:</strong> soporte@cnel.gob.ec</li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 mb-4">
                    <div class="card-modern h-100">
                        <div class="card-header-modern">
                            <h5 class="card-title mb-0"><i class="bi bi-info-circle me-2"></i>Tiempos de Procesamiento</h5>
                        </div>
                        <div class="card-body-modern">
                            <ul>
                                <li>Revisión inicial: 3–5 días hábiles</li>
                                <li>Evaluación técnica: 5–10 días hábiles</li>
                                <li>Aprobación final: 2–3 días hábiles</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <?php endif; ?>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.stat-number').forEach(stat => {
        const target    = parseInt(stat.textContent);
        let current     = 0;
        const increment = target / 50;
        const timer = setInterval(() => {
            current += increment;
            if (current >= target) { stat.textContent = target; clearInterval(timer); }
            else                   { stat.textContent = Math.round(current); }
        }, 30);
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>