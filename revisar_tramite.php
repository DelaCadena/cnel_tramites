<?php
$page_title = "Revisar Trámite - Sistema de Trámites";
require_once 'includes/header.php';
require_once 'includes/email.php';

checkRole(['admin', 'encargado', 'personal']);

if (!isset($_GET['id'])) {
    header('Location: revisar_tramites.php');
    exit;
}

$tramite_id = (int)$_GET['id'];
$success    = '';
$error      = '';
$rol        = $_SESSION['user_rol'];
$userId     = (int)$_SESSION['user_id'];
$esPersonal = ($rol === 'personal');
$esAdmin    = ($rol === 'admin');

/* Bandera para mostrar el modal de éxito de "construido" tras el POST */
$mostrarModalConstruido = false;

/* ── Trámite principal ── */
$stmt = $pdo->prepare("
    SELECT t.*, u.nombre AS usuario_nombre, u.email AS usuario_email,
           DATEDIFF(NOW(), t.fecha_creacion) AS dias_transcurridos
    FROM tramites t
    LEFT JOIN usuarios u ON t.usuario_id = u.id
    WHERE t.id = ?
");
$stmt->execute([$tramite_id]);
$tramite = $stmt->fetch();

if (!$tramite) {
    header('Location: revisar_tramites.php');
    exit;
}

/*
 * ── Verificar acceso para personal ──
 * Admin tiene acceso total siempre.
 * El personal puede ver/revisar un trámite si:
 *   a) es el encargado_id actual del trámite (reasignado a él)
 *   b) ya participó antes (aparece en revisiones)
 */
if ($esPersonal) {
    $stmtAcc = $pdo->prepare("
        SELECT COUNT(*) FROM revisiones
        WHERE tramite_id = ? AND usuario_id = ?
    ");
    $stmtAcc->execute([$tramite_id, $userId]);
    $haInteractuado = (int)$stmtAcc->fetchColumn() > 0;

    $esEncargadoActual = ((int)$tramite['encargado_id'] === $userId);

    if (!$esEncargadoActual && !$haInteractuado) {
        header('Location: revisar_tramites.php');
        exit;
    }
}

/* ── Datos FERUM si aplica ── */
$ferum = null;
if ($tramite['tipo'] === 'ferum') {
    $stmtF = $pdo->prepare("SELECT * FROM tramites_ferum WHERE tramite_id = ?");
    $stmtF->execute([$tramite_id]);
    $ferum = $stmtF->fetch();
}

/* ── Historial de revisiones ── */
$stmt = $pdo->prepare("
    SELECT r.*, u.nombre AS revisor_nombre
    FROM revisiones r
    LEFT JOIN usuarios u ON r.usuario_id = u.id
    WHERE r.tramite_id = ?
    ORDER BY r.fecha_revision DESC
");
$stmt->execute([$tramite_id]);
$revisiones = $stmt->fetchAll();

/* ── Archivos adicionales ──
 * Personal: solo ve los archivos que él mismo subió
 * Admin / Encargado: ve todos
 */
if ($esPersonal) {
    $stmt = $pdo->prepare("
        SELECT a.*, u.nombre AS subido_por_nombre
        FROM tramite_archivos a
        LEFT JOIN usuarios u ON a.usuario_id = u.id
        WHERE a.tramite_id = ? AND a.usuario_id = ?
        ORDER BY a.fecha_subida DESC
    ");
    $stmt->execute([$tramite_id, $userId]);
} else {
    $stmt = $pdo->prepare("
        SELECT a.*, u.nombre AS subido_por_nombre
        FROM tramite_archivos a
        LEFT JOIN usuarios u ON a.usuario_id = u.id
        WHERE a.tramite_id = ?
        ORDER BY a.fecha_subida DESC
    ");
    $stmt->execute([$tramite_id]);
}
$archivosAdicionales = $stmt->fetchAll();

/* ── Encargados y Personal disponibles para reasignación ── */
$stmt = $pdo->prepare("
    SELECT id, nombre, email, rol
    FROM usuarios
    WHERE rol IN ('encargado','personal') AND activo = 1
    ORDER BY rol, nombre ASC
");
$stmt->execute();
$usuariosReasignacion = $stmt->fetchAll();

$listaEncargados = array_filter($usuariosReasignacion, fn($u) => $u['rol'] === 'encargado');
$listaPersonal   = array_filter($usuariosReasignacion, fn($u) => $u['rol'] === 'personal');

/* ── Antigüedad / días en trámite ── */
$dias = $tramite['dias_transcurridos'];

/*
 * CAMBIO: Se elimina la restricción de estado='aprobado'.
 * El tab Prioridad/Construcción aparece para todos los roles
 * siempre que el trámite no esté ya construido.
 */
$puedeGestionarCicloClasico = ((int)$tramite['construido'] === 0);

/*
 * CAMBIO: Se elimina el botón construido fijo exclusivo de personal.
 * Personal ahora usa el mismo panel unificado de Prioridad/Construcción.
 */
$personalPuedeMarcarConstruido = false;

$etiquetasPrioridad = [
    'baja'    => ['🟢', 'Baja',    '#198754'],
    'media'   => ['🟡', 'Media',   '#f39c12'],
    'alta'    => ['🟠', 'Alta',    '#fd7e14'],
    'urgente' => ['🔴', 'Urgente', '#dc3545'],
];

/* ════════════════════════════════════════
   POST — Procesar acciones
════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $accion        = $_POST['accion'] ?? 'estado';
    $observaciones = trim($_POST['observaciones'] ?? '');
    $obs_cliente   = trim($_POST['obs_cliente']   ?? '');

    try {
        $pdo->beginTransaction();

        /* ─────────────────────────────────────
           ACCIÓN: Subir archivo adicional
        ───────────────────────────────────── */
        if ($accion === 'archivo') {

            if (!isset($_FILES['archivo_adicional']) || $_FILES['archivo_adicional']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Debe seleccionar un archivo válido (PDF o imagen).');
            }

            $ext     = strtolower(pathinfo($_FILES['archivo_adicional']['name'], PATHINFO_EXTENSION));
            $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];
            if (!in_array($ext, $allowed)) {
                throw new Exception('Formato no permitido. Use PDF o imagen (JPG, PNG, WEBP).');
            }

            $maxSize = 15 * 1024 * 1024;
            if ($_FILES['archivo_adicional']['size'] > $maxSize) {
                throw new Exception('El archivo supera el límite de 15 MB.');
            }

            $upload_dir = 'uploads/adicionales/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

            $nombreUnico = $tramite['numero_tramite'] . '_' . time() . '_' . uniqid() . '.' . $ext;
            $path        = $upload_dir . $nombreUnico;

            if (!move_uploaded_file($_FILES['archivo_adicional']['tmp_name'], $path)) {
                throw new Exception('Error al guardar el archivo en el servidor.');
            }

            $pdo->prepare("
                INSERT INTO tramite_archivos (tramite_id, usuario_id, nombre_original, archivo_path, descripcion)
                VALUES (?, ?, ?, ?, ?)
            ")->execute([
                $tramite_id,
                $userId,
                $_FILES['archivo_adicional']['name'],
                $path,
                $observaciones ?: null,
            ]);

            $pdo->commit();
            $success = 'Archivo subido correctamente.';

        /* ─────────────────────────────────────
           ACCIÓN: Marcar Prioridad
           CAMBIO: Se elimina la validación que exigía estado='aprobado'.
                   Ahora cualquier rol puede priorizar en cualquier estado activo.
        ───────────────────────────────────── */
        } elseif ($accion === 'prioridad') {

            if ((int)$tramite['construido'] === 1) {
                throw new Exception('Este trámite ya fue marcado como construido. Su ciclo de vida ha finalizado.');
            }

            $nuevaPrioridad = $_POST['prioridad'] ?? '';
            if (!in_array($nuevaPrioridad, ['baja', 'media', 'alta', 'urgente'])) {
                throw new Exception('Debe seleccionar un nivel de prioridad válido.');
            }

            $pdo->prepare("
                UPDATE tramites SET prioridad = ?, fecha_actualizacion = NOW() WHERE id = ?
            ")->execute([$nuevaPrioridad, $tramite_id]);

            $pdo->prepare("
                INSERT INTO revisiones (tramite_id, usuario_id, observaciones, estado_anterior, estado_nuevo)
                VALUES (?, ?, ?, ?, ?)
            ")->execute([
                $tramite_id,
                $userId,
                "Prioridad establecida en: $nuevaPrioridad" . ($observaciones ? " — $observaciones" : ''),
                $tramite['estado'],
                $tramite['estado'],
            ]);

            if ($esPersonal && $tramite['encargado_id'] && (int)$tramite['encargado_id'] !== $userId) {
                notificarEncargadoCambioCiclo($pdo, $tramite_id, (int)$tramite['encargado_id'], 'prioridad', $nuevaPrioridad, $userId);
            }

            $pdo->commit();
            $success = 'Prioridad actualizada correctamente.';

        /* ─────────────────────────────────────
           ACCIÓN: Marcar Construido
           CAMBIO: Se elimina la restricción que diferenciaba personal de encargado/admin.
                   Ahora todos los roles pueden marcar construido en cualquier estado activo.
                   El trámite pasa automáticamente a estado='aprobado' + construido=1.
        ───────────────────────────────────── */
        } elseif ($accion === 'construido') {

            if ((int)$tramite['construido'] === 1) {
                throw new Exception('Este trámite ya fue marcado como construido previamente.');
            }

            $estadoAnterior = $tramite['estado'];

            $pdo->prepare("
                UPDATE tramites
                SET estado = 'aprobado', construido = 1, fecha_construido = NOW(),
                    construido_por = ?, fecha_actualizacion = NOW()
                WHERE id = ?
            ")->execute([$userId, $tramite_id]);

            $notaRevision = $estadoAnterior !== 'aprobado'
                ? "Trámite marcado como CONSTRUIDO (estado anterior: $estadoAnterior, se autoaprueba). Ciclo de vida finalizado."
                : 'Trámite marcado como CONSTRUIDO — ciclo de vida finalizado.';

            if ($observaciones) $notaRevision .= " Nota: $observaciones";

            $pdo->prepare("
                INSERT INTO revisiones (tramite_id, usuario_id, observaciones, estado_anterior, estado_nuevo)
                VALUES (?, ?, ?, ?, 'aprobado')
            ")->execute([$tramite_id, $userId, $notaRevision, $estadoAnterior]);

            if ($esPersonal && $tramite['encargado_id'] && (int)$tramite['encargado_id'] !== $userId) {
                notificarEncargadoCambioCiclo($pdo, $tramite_id, (int)$tramite['encargado_id'], 'construido', null, $userId);
            }

            notificarSolicitanteConstruido($pdo, $tramite_id, $obs_cliente);

            $pdo->commit();
            $success = 'Trámite marcado como CONSTRUIDO. El ciclo de vida ha finalizado.';
            $mostrarModalConstruido = true;

        /* ─────────────────────────────────────
           ACCIÓN: Cambiar estado / Reasignar
        ───────────────────────────────────── */
        } else {

            if ((int)$tramite['construido'] === 1) {
                throw new Exception('Este trámite ya está construido y su ciclo de vida ha finalizado. No se puede modificar su estado.');
            }

            $nuevo_estado            = $_POST['estado'];
            $encargadosSeleccionados = $_POST['encargados']    ?? [];
            $personalSeleccionado    = $_POST['personal_ids']  ?? [];

            if ($esPersonal && in_array($nuevo_estado, ['aprobado', 'rechazado'])) {
                throw new Exception('No tiene permisos para aprobar o rechazar trámites.');
            }

            if ($nuevo_estado === 'reasignar') {

                $todosSeleccionados = array_merge($encargadosSeleccionados, $personalSeleccionado);

                if (empty($todosSeleccionados)) {
                    throw new Exception('Debe seleccionar al menos un encargado o personal para reasignar.');
                }

                $primerEncargado = !empty($encargadosSeleccionados)
                    ? $encargadosSeleccionados[0]
                    : $tramite['encargado_id'];

                $pdo->prepare("
                    UPDATE tramites
                    SET encargado_id = ?, estado = 'pendiente', fecha_actualizacion = NOW()
                    WHERE id = ?
                ")->execute([$primerEncargado, $tramite_id]);

                $stmtRev = $pdo->prepare("
                    INSERT INTO revisiones (tramite_id, usuario_id, observaciones, estado_anterior, estado_nuevo)
                    VALUES (?, ?, ?, ?, 'pendiente')
                ");
                $stmtRev->execute([$tramite_id, $userId, $observaciones, $tramite['estado']]);

                $stmtRevPersonal = $pdo->prepare("
                    INSERT IGNORE INTO revisiones (tramite_id, usuario_id, observaciones, estado_anterior, estado_nuevo)
                    VALUES (?, ?, ?, ?, 'pendiente')
                ");
                foreach ($personalSeleccionado as $pid) {
                    $stmtRevPersonal->execute([
                        $tramite_id, (int)$pid, 'Tramite asignado', $tramite['estado'],
                    ]);
                }
                foreach ($encargadosSeleccionados as $eid) {
                    if ((int)$eid !== $userId) {
                        $stmtRevPersonal->execute([
                            $tramite_id, (int)$eid, 'Tramite asignado', $tramite['estado'],
                        ]);
                    }
                }

                notificarSolicitanteReasignacion($pdo, $tramite_id, $obs_cliente);

                foreach ($encargadosSeleccionados as $eid) {
                    notificarNuevoEncargado($pdo, $tramite_id, (int)$eid, $observaciones);
                }
                foreach ($personalSeleccionado as $pid) {
                    notificarNuevoPersonal($pdo, $tramite_id, (int)$pid, $observaciones);
                }

            } else {

                $pdo->prepare("
                    UPDATE tramites SET estado = ?, fecha_actualizacion = NOW() WHERE id = ?
                ")->execute([$nuevo_estado, $tramite_id]);

                $pdo->prepare("
                    INSERT INTO revisiones (tramite_id, usuario_id, observaciones, estado_anterior, estado_nuevo)
                    VALUES (?, ?, ?, ?, ?)
                ")->execute([$tramite_id, $userId, $observaciones, $tramite['estado'], $nuevo_estado]);

                notificarSolicitanteEstado($pdo, $tramite_id, $nuevo_estado, $obs_cliente);
            }

            $pdo->commit();
            $success = 'Trámite actualizado exitosamente.';
        }

        /* Recargar datos */
        $stmt = $pdo->prepare("
            SELECT t.*, u.nombre AS usuario_nombre, u.email AS usuario_email,
                   DATEDIFF(NOW(), t.fecha_creacion) AS dias_transcurridos
            FROM tramites t LEFT JOIN usuarios u ON t.usuario_id = u.id WHERE t.id = ?
        ");
        $stmt->execute([$tramite_id]);
        $tramite = $stmt->fetch();

        if ($tramite['tipo'] === 'ferum') {
            $stmtF = $pdo->prepare("SELECT * FROM tramites_ferum WHERE tramite_id = ?");
            $stmtF->execute([$tramite_id]);
            $ferum = $stmtF->fetch();
        }

        /* CAMBIO: Recalcular banderas con la nueva lógica */
        $puedeGestionarCicloClasico    = ((int)$tramite['construido'] === 0);
        $personalPuedeMarcarConstruido = false;

        /* Recargar revisiones */
        $stmt = $pdo->prepare("
            SELECT r.*, u.nombre AS revisor_nombre
            FROM revisiones r LEFT JOIN usuarios u ON r.usuario_id = u.id
            WHERE r.tramite_id = ? ORDER BY r.fecha_revision DESC
        ");
        $stmt->execute([$tramite_id]);
        $revisiones = $stmt->fetchAll();

        /* Recargar archivos con filtro por rol */
        if ($esPersonal) {
            $stmt = $pdo->prepare("
                SELECT a.*, u.nombre AS subido_por_nombre
                FROM tramite_archivos a
                LEFT JOIN usuarios u ON a.usuario_id = u.id
                WHERE a.tramite_id = ? AND a.usuario_id = ?
                ORDER BY a.fecha_subida DESC
            ");
            $stmt->execute([$tramite_id, $userId]);
        } else {
            $stmt = $pdo->prepare("
                SELECT a.*, u.nombre AS subido_por_nombre
                FROM tramite_archivos a
                LEFT JOIN usuarios u ON a.usuario_id = u.id
                WHERE a.tramite_id = ?
                ORDER BY a.fecha_subida DESC
            ");
            $stmt->execute([$tramite_id]);
        }
        $archivosAdicionales = $stmt->fetchAll();

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}
?>

<div class="main-container">

    <section class="revision-tramite-header">
        <div class="container">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Inicio</a></li>
                    <li class="breadcrumb-item"><a href="revisar_tramites.php">Trámites</a></li>
                    <li class="breadcrumb-item active">Revisar Trámite</li>
                </ol>
            </nav>
            <h1><i class="bi bi-clipboard-check me-2"></i>Revisar Trámite</h1>
            <p class="lead mb-0">Evaluación y gestión del trámite <?= $tramite['numero_tramite'] ?></p>
        </div>
    </section>

    <div class="container">

        <?php if ($success && !$mostrarModalConstruido): ?>
        <div class="alert-modern alert-success-modern mb-4">
            <i class="bi bi-check-circle-fill me-2"></i><?= $success ?>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="alert-modern alert-danger-modern mb-4">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= $error ?>
        </div>
        <?php endif; ?>

        <div class="notificacion-alerta">
            <div class="notificacion-icon"><i class="bi bi-envelope-check"></i></div>
            <div class="notificacion-content">
                <h5>Notificación Automática</h5>
                <p>El cliente recibirá un correo electrónico con la actualización del trámite.</p>
            </div>
        </div>

        <?php if ($esPersonal): ?>
        <div class="alert-modern alert-info-modern mb-4">
            <i class="bi bi-info-circle-fill me-2"></i>
            Como <strong>Personal</strong> puedes agregar archivos y reasignar el trámite, pero no puedes aprobarlo ni rechazarlo.
            <?php if ($puedeGestionarCicloClasico): ?>
            Si confirmas mediante inspección que ya está construido, puedes marcarlo directamente sin esperar aprobación.
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ((int)$tramite['construido'] === 1 && !$mostrarModalConstruido): ?>
        <div class="alert-modern" style="background:linear-gradient(135deg,#e7f7ee,#d4edda);border-left:4px solid #198754;color:#155724;">
            <i class="bi bi-check-circle-fill me-2"></i>
            <strong>Ciclo de vida finalizado.</strong> Este trámite fue marcado como <strong>CONSTRUIDO</strong>
            el <?= date('d/m/Y H:i', strtotime($tramite['fecha_construido'])) ?>.
            Ya no admite cambios de estado, reasignación ni repriorización.
        </div>
        <?php endif; ?>

        <div class="revision-layout">

            <!-- ══════════════════════════════
                 PANEL IZQUIERDO — Info del trámite
            ══════════════════════════════ -->
            <div class="tramite-panel">

                <div class="tramite-panel-header">
                    <div>
                        <div class="tramite-numero-grande"><?= $tramite['numero_tramite'] ?></div>
                        <small class="text-white-50">
                            Registrado el <?= date('d/m/Y', strtotime($tramite['fecha_creacion'])) ?>
                        </small>
                        <?php
                            $claseD = $dias > 15 ? 'danger' : ($dias > 7 ? 'warning' : 'normal');
                            $iconoD = $dias > 15 ? '⚠️' : '🕐';
                            $textoD = $dias > 15
                                ? "$dias días — Plazo excedido"
                                : ($dias > 7 ? "$dias días — Próximo a vencer" : "$dias días en trámite");
                        ?>
                        <div class="mt-2 d-flex gap-2 flex-wrap">
                            <span class="badge-dias <?= $claseD ?>"><?= $iconoD ?> <?= $textoD ?></span>
                            <?php if ($tramite['prioridad']): ?>
                                <?php [$emoji, $label, $color] = $etiquetasPrioridad[$tramite['prioridad']]; ?>
                                <span class="badge-dias" style="background:<?= $color ?>22;color:<?= $color ?>;border:1px solid <?= $color ?>55;">
                                    <?= $emoji ?> Prioridad: <?= $label ?>
                                </span>
                            <?php endif; ?>
                            <?php if ((int)$tramite['construido'] === 1): ?>
                                <span class="badge-dias" style="background:#19875422;color:#198754;border:1px solid #19875455;">
                                    🏗️ Construido
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <span class="tramite-estado-badge badge-status-<?= $tramite['estado'] ?>">
                        <?= ucfirst($tramite['estado']) ?>
                    </span>
                </div>

                <div class="tramite-panel-body">

                    <!-- Tipo -->
                    <div class="info-item-revision mb-3">
                        <span class="info-label-revision">Tipo de Trámite</span>
                        <span class="info-value-revision">
                            <?php if ($tramite['tipo'] === 'extension_red'): ?>
                                📡 Extensión de Red
                            <?php else: ?>
                                ⚡ FERUM
                                <?php if ($ferum): ?>
                                    <span class="badge ms-1" style="background:#0d6efd;font-size:.75rem;color:#fff;padding:.3rem .7rem;border-radius:12px;">
                                        <?= $ferum['tipo_sector'] === 'rural' ? 'Rural' : 'Urbano Marginal' ?>
                                    </span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </span>
                    </div>

                    <!-- Datos comunes -->
                    <div class="info-grid-revision">
                        <div class="info-item-revision">
                            <span class="info-label-revision">Solicitante</span>
                            <span class="info-value-revision"><?= htmlspecialchars($tramite['solicitante']) ?></span>
                        </div>
                        <div class="info-item-revision">
                            <span class="info-label-revision">Cédula / RUC</span>
                            <span class="info-value-revision"><?= htmlspecialchars($tramite['cedula_ruc']) ?></span>
                        </div>
                        <div class="info-item-revision">
                            <span class="info-label-revision">Teléfono</span>
                            <span class="info-value-revision"><?= htmlspecialchars($tramite['telefono'] ?: '—') ?></span>
                        </div>
                        <div class="info-item-revision">
                            <span class="info-label-revision">Email</span>
                            <span class="info-value-revision"><?= htmlspecialchars($tramite['email'] ?: '—') ?></span>
                        </div>
                        <div class="info-item-revision">
                            <span class="info-label-revision">Registrado por</span>
                            <span class="info-value-revision"><?= htmlspecialchars($tramite['usuario_nombre']) ?></span>
                        </div>
                    </div>

                    <!-- ── BLOQUE FERUM ── -->
                    <?php if ($tramite['tipo'] === 'ferum' && $ferum): ?>
                    <hr>
                    <h5 class="mt-3 mb-3"><i class="bi bi-geo-alt-fill me-1"></i> Datos de la Comunidad</h5>
                    <div class="info-grid-revision">
                        <div class="info-item-revision">
                            <span class="info-label-revision">Comunidad / Recinto</span>
                            <span class="info-value-revision"><?= htmlspecialchars($ferum['comunidad']) ?></span>
                        </div>
                        <div class="info-item-revision">
                            <span class="info-label-revision">Parroquia</span>
                            <span class="info-value-revision"><?= htmlspecialchars($ferum['parroquia']) ?></span>
                        </div>
                        <div class="info-item-revision">
                            <span class="info-label-revision">Cantón</span>
                            <span class="info-value-revision"><?= htmlspecialchars($ferum['canton']) ?></span>
                        </div>
                        <div class="info-item-revision">
                            <span class="info-label-revision">Provincia</span>
                            <span class="info-value-revision"><?= htmlspecialchars($ferum['provincia']) ?></span>
                        </div>
                        <?php if ($ferum['utm_x'] || $ferum['utm_y']): ?>
                        <div class="info-item-revision">
                            <span class="info-label-revision">Coordenadas UTM</span>
                            <span class="info-value-revision">
                                X: <?= htmlspecialchars($ferum['utm_x'] ?: '—') ?> &nbsp;
                                Y: <?= htmlspecialchars($ferum['utm_y'] ?: '—') ?>
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <h5 class="mt-3 mb-3"><i class="bi bi-lightning-charge me-1"></i> Datos Técnicos</h5>
                    <div class="info-grid-revision">
                        <div class="info-item-revision">
                            <span class="info-label-revision">Beneficiarios Estimados</span>
                            <span class="info-value-revision"><?= $ferum['num_beneficiarios'] ? $ferum['num_beneficiarios'] . ' viviendas' : '—' ?></span>
                        </div>
                        <div class="info-item-revision">
                            <span class="info-label-revision">Potencia Requerida</span>
                            <span class="info-value-revision"><?= htmlspecialchars($ferum['potencia_requerida'] ?: '—') ?></span>
                        </div>
                        <div class="info-item-revision">
                            <span class="info-label-revision">Distancia a Red</span>
                            <span class="info-value-revision"><?= htmlspecialchars($ferum['distancia_red'] ?: '—') ?></span>
                        </div>
                    </div>

                    <?php if ($ferum['presidente_nombre'] || $ferum['coordinador_nombre']): ?>
                    <h5 class="mt-3 mb-3"><i class="bi bi-people me-1"></i> Representantes</h5>
                    <div class="info-grid-revision">
                        <?php if ($ferum['presidente_nombre']): ?>
                        <div class="info-item-revision">
                            <span class="info-label-revision">Presidente</span>
                            <span class="info-value-revision">
                                <?= htmlspecialchars($ferum['presidente_nombre']) ?><br>
                                <small>CI: <?= htmlspecialchars($ferum['presidente_cedula'] ?: '—') ?> | 📱 <?= htmlspecialchars($ferum['presidente_celular'] ?: '—') ?></small>
                            </span>
                        </div>
                        <?php endif; ?>
                        <?php if ($ferum['coordinador_nombre']): ?>
                        <div class="info-item-revision">
                            <span class="info-label-revision">Coordinador</span>
                            <span class="info-value-revision">
                                <?= htmlspecialchars($ferum['coordinador_nombre']) ?><br>
                                <small>CI: <?= htmlspecialchars($ferum['coordinador_cedula'] ?: '—') ?> | 📱 <?= htmlspecialchars($ferum['coordinador_celular'] ?: '—') ?></small>
                            </span>
                        </div>
                        <?php endif; ?>
                        <?php if ($ferum['horario_contacto']): ?>
                        <div class="info-item-revision">
                            <span class="info-label-revision">Horario de Contacto</span>
                            <span class="info-value-revision"><?= htmlspecialchars($ferum['horario_contacto']) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <h5 class="mt-3 mb-2"><i class="bi bi-folder2-open me-1"></i> Documentos FERUM</h5>
                    <div class="info-grid-revision">
                        <?php
                        $docs = [
                            'archivo_croquis'       => '🗺️ Croquis de Ubicación',
                            'archivo_gad'           => '🏛️ Certificación GAD Municipal',
                            'archivo_beneficiarios' => '👥 Listado de Beneficiarios',
                        ];
                        foreach ($docs as $campo => $label):
                            if (!empty($ferum[$campo])):
                        ?>
                        <div class="info-item-revision">
                            <span class="info-label-revision"><?= $label ?></span>
                            <a href="<?= htmlspecialchars($ferum[$campo]) ?>" target="_blank"
                               class="btn btn-sm btn-outline-primary mt-1">
                                <i class="bi bi-download me-1"></i> Descargar
                            </a>
                        </div>
                        <?php endif; endforeach; ?>
                    </div>

                    <?php if ($ferum['observaciones']): ?>
                    <div class="descripcion-section mt-3">
                        <h5><i class="bi bi-chat-left-text"></i> Observaciones FERUM</h5>
                        <div class="descripcion-text"><?= nl2br(htmlspecialchars($ferum['observaciones'])) ?></div>
                    </div>
                    <?php endif; ?>
                    <?php endif; /* fin bloque FERUM */ ?>

                    <!-- Descripción general -->
                    <div class="descripcion-section mt-3">
                        <h5><i class="bi bi-card-text"></i> Descripción del Trámite</h5>
                        <div class="descripcion-text"><?= nl2br(htmlspecialchars($tramite['descripcion'])) ?></div>
                    </div>

                    <?php if ($tramite['archivo_path']): ?>
                    <div class="info-item-revision mt-3">
                        <span class="info-label-revision">Documento Adjunto Original</span>
                        <a href="<?= htmlspecialchars($tramite['archivo_path']) ?>" target="_blank"
                           class="btn btn-outline-primary mt-1">
                            <i class="bi bi-download me-2"></i>Descargar Archivo
                        </a>
                    </div>
                    <?php endif; ?>

                    <!-- ── ARCHIVOS ADICIONALES ── -->
                    <?php if ($archivosAdicionales): ?>
                    <hr>
                    <h5 class="mt-3 mb-3">
                        <i class="bi bi-paperclip me-1"></i> Archivos Adicionales
                        <?php if ($esPersonal): ?>
                        <small class="text-muted fw-normal" style="font-size:.8rem;">— Solo se muestran los archivos que tú has subido</small>
                        <?php endif; ?>
                    </h5>
                    <div style="display:flex;flex-direction:column;gap:.75rem;">
                        <?php foreach ($archivosAdicionales as $arch): ?>
                        <div style="background:#f8f9fa;border-radius:10px;padding:1rem;display:flex;justify-content:space-between;align-items:center;gap:1rem;border-left:4px solid var(--cnel-secondary);">
                            <div>
                                <div style="font-weight:600;color:var(--cnel-primary);font-size:.95rem;">
                                    <i class="bi bi-file-earmark me-1"></i>
                                    <?= htmlspecialchars($arch['nombre_original']) ?>
                                </div>
                                <?php if ($arch['descripcion']): ?>
                                <div style="font-size:.85rem;color:#6c757d;margin-top:.25rem;">
                                    <?= htmlspecialchars($arch['descripcion']) ?>
                                </div>
                                <?php endif; ?>
                                <div style="font-size:.8rem;color:#adb5bd;margin-top:.25rem;">
                                    Subido por <?= htmlspecialchars($arch['subido_por_nombre']) ?>
                                    el <?= date('d/m/Y H:i', strtotime($arch['fecha_subida'])) ?>
                                </div>
                            </div>
                            <a href="<?= htmlspecialchars($arch['archivo_path']) ?>" target="_blank"
                               class="btn btn-sm btn-outline-primary" style="white-space:nowrap;">
                                <i class="bi bi-download me-1"></i> Ver
                            </a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <hr>
                    <p class="text-muted mt-3" style="font-size:.9rem;">
                        <i class="bi bi-paperclip me-1"></i> Aún no hay archivos adicionales en este trámite.
                    </p>
                    <?php endif; ?>

                    <!-- ── HISTORIAL DE REVISIONES ── -->
                    <?php if ($revisiones): ?>
                    <hr>
                    <h5 class="mt-3 mb-3"><i class="bi bi-clock-history me-1"></i> Historial de Actividad</h5>
                    <div style="display:flex;flex-direction:column;gap:.6rem;max-height:320px;overflow-y:auto;">
                        <?php foreach ($revisiones as $rev): ?>
                        <div style="background:#f8f9fa;border-radius:8px;padding:.75rem 1rem;border-left:3px solid var(--cnel-secondary);font-size:.88rem;">
                            <div style="display:flex;justify-content:space-between;gap:.5rem;">
                                <strong style="color:var(--cnel-primary);"><?= htmlspecialchars($rev['revisor_nombre'] ?: 'Sistema') ?></strong>
                                <span class="text-muted" style="font-size:.78rem;"><?= date('d/m/Y H:i', strtotime($rev['fecha_revision'])) ?></span>
                            </div>
                            <?php if ($rev['observaciones']): ?>
                            <div class="text-muted mt-1"><?= nl2br(htmlspecialchars($rev['observaciones'])) ?></div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                </div><!-- /tramite-panel-body -->
            </div><!-- /tramite-panel -->


            <!-- ══════════════════════════════
                 PANEL DERECHO — Acciones
            ══════════════════════════════ -->
            <div class="revision-panel">

                <!-- TABS -->
                <div style="display:flex;gap:.5rem;margin-bottom:1.5rem;flex-wrap:wrap;">
                    <button type="button" id="tabEstado"
                            onclick="switchTab('estado')"
                            style="flex:1;min-width:120px;padding:.75rem;border:2px solid var(--cnel-secondary);border-radius:10px;background:var(--cnel-secondary);color:#fff;font-weight:600;cursor:pointer;transition:all .2s;">
                        <i class="bi bi-arrow-repeat me-1"></i>
                        <?= $esPersonal ? 'Reasignar' : 'Estado' ?>
                    </button>
                    <button type="button" id="tabArchivo"
                            onclick="switchTab('archivo')"
                            style="flex:1;min-width:120px;padding:.75rem;border:2px solid var(--cnel-secondary);border-radius:10px;background:#fff;color:var(--cnel-secondary);font-weight:600;cursor:pointer;transition:all .2s;">
                        <i class="bi bi-paperclip me-1"></i> Archivo
                    </button>
                    <?php if ($puedeGestionarCicloClasico): ?>
                    <button type="button" id="tabCiclo"
                            onclick="switchTab('ciclo')"
                            style="flex:1;min-width:120px;padding:.75rem;border:2px solid var(--cnel-secondary);border-radius:10px;background:#fff;color:var(--cnel-secondary);font-weight:600;cursor:pointer;transition:all .2s;">
                        <i class="bi bi-flag-fill me-1"></i> Prioridad
                    </button>
                    <?php endif; ?>
                </div>

                <!-- FORM ESTADO / REASIGNAR -->
                <form method="POST" enctype="multipart/form-data" id="formEstado">
                    <input type="hidden" name="accion" value="estado">

                    <div class="revision-panel-header">
                        <h3>
                            <i class="bi bi-pencil-square"></i>
                            <?= $esPersonal ? 'Reasignar Trámite' : 'Evaluación del Trámite' ?>
                        </h3>
                        <p class="text-muted mb-0">
                            <?= $esPersonal
                                ? 'Reasigne el trámite a otro encargado o personal'
                                : 'Seleccione el nuevo estado' ?>
                        </p>
                    </div>

                    <?php if ((int)$tramite['construido'] === 1): ?>
                    <div class="alert-modern alert-info-modern mb-3" style="font-size:.88rem;">
                        Este trámite ya está construido. No se permiten más cambios de estado.
                    </div>
                    <?php else: ?>

                    <div class="form-group">
                        <label class="form-label"><i class="bi bi-arrow-repeat"></i> Acción</label>
                        <div class="estado-options">

                            <?php if (!$esPersonal): ?>
                            <div class="estado-option aprobado">
                                <input type="radio" id="estado_aprobado" name="estado" value="aprobado">
                                <label for="estado_aprobado">
                                    <div class="estado-icon">✅</div>
                                    <div class="estado-text">Aprobar</div>
                                    <div class="estado-descripcion">Trámite aprobado</div>
                                </label>
                            </div>
                            <div class="estado-option rechazado">
                                <input type="radio" id="estado_rechazado" name="estado" value="rechazado">
                                <label for="estado_rechazado">
                                    <div class="estado-icon">❌</div>
                                    <div class="estado-text">Rechazar</div>
                                    <div class="estado-descripcion">No cumple requisitos</div>
                                </label>
                            </div>
                            <?php endif; ?>

                            <div class="estado-option reasignar">
                                <input type="radio" id="estado_reasignar" name="estado" value="reasignar"
                                       <?= $esPersonal ? 'checked' : '' ?>>
                                <label for="estado_reasignar">
                                    <div class="estado-icon">🔄</div>
                                    <div class="estado-text">Reasignar</div>
                                    <div class="estado-descripcion">A encargado o personal</div>
                                </label>
                            </div>

                            <?php if (!$esPersonal): ?>
                            <div class="estado-option">
                                <input type="radio" id="estado_pendiente" name="estado" value="pendiente">
                                <label for="estado_pendiente">
                                    <div class="estado-icon">⏳</div>
                                    <div class="estado-text">Pendiente</div>
                                    <div class="estado-descripcion">Esperando revisión</div>
                                </label>
                            </div>
                            <?php endif; ?>

                        </div>
                    </div>

                    <!-- Panel reasignación -->
                    <div id="panelReasignacion" style="display:<?= $esPersonal ? 'block' : 'none' ?>;">
                        <p style="font-size:.85rem;color:#6c757d;margin-bottom:1rem;">
                            <i class="bi bi-info-circle me-1"></i>
                            Puede seleccionar encargados y/o personal. Si selecciona encargados, el primero elegido queda como responsable principal.
                        </p>

                        <?php if ($listaEncargados): ?>
                        <div class="form-group">
                            <label class="form-label" style="font-size:.9rem;">
                                <i class="bi bi-person-badge"></i> Encargados
                            </label>
                            <div class="reasignacion-container">
                                <?php foreach ($listaEncargados as $enc): ?>
                                <label class="encargado-card">
                                    <input type="checkbox" name="encargados[]" value="<?= $enc['id'] ?>">
                                    <div class="encargado-card-content">
                                        <div class="encargado-avatar"><?= strtoupper(substr($enc['nombre'], 0, 1)) ?></div>
                                        <div class="encargado-info">
                                            <div class="encargado-nombre"><?= htmlspecialchars($enc['nombre']) ?></div>
                                            <div class="encargado-email"><?= htmlspecialchars($enc['email']) ?></div>
                                        </div>
                                    </div>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($listaPersonal): ?>
                        <div class="form-group" style="margin-top:1rem;">
                            <label class="form-label" style="font-size:.9rem;">
                                <i class="bi bi-people"></i> Personal
                            </label>
                            <div class="reasignacion-container">
                                <?php foreach ($listaPersonal as $per): ?>
                                <label class="encargado-card">
                                    <input type="checkbox" name="personal_ids[]" value="<?= $per['id'] ?>">
                                    <div class="encargado-card-content">
                                        <div class="encargado-avatar" style="background:linear-gradient(135deg,#198754,#20c997);">
                                            <?= strtoupper(substr($per['nombre'], 0, 1)) ?>
                                        </div>
                                        <div class="encargado-info">
                                            <div class="encargado-nombre"><?= htmlspecialchars($per['nombre']) ?></div>
                                            <div class="encargado-email"><?= htmlspecialchars($per['email']) ?></div>
                                        </div>
                                    </div>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div><!-- /panelReasignacion -->

                    <!-- Observaciones internas -->
                    <div class="form-group">
                        <label for="observaciones" class="form-label">
                            <i class="bi bi-chat-left-text"></i> Observaciones internas
                            <small class="text-muted fw-normal">(opcional — visibles solo al equipo)</small>
                        </label>
                        <textarea id="observaciones" name="observaciones"
                                  class="observaciones-textarea"
                                  placeholder="Notas para el equipo interno..."
                                  oninput="actualizarContador(this)"></textarea>
                        <div class="contador-caracteres"><span id="contador">0</span> caracteres</div>
                    </div>

                    <!-- Mensaje para el cliente -->
                    <div class="form-group">
                        <label for="obs_cliente" class="form-label">
                            <i class="bi bi-person-lines-fill"></i> Mensaje para el solicitante
                            <small class="text-muted fw-normal">(opcional — se incluye en el correo al cliente)</small>
                        </label>
                        <textarea id="obs_cliente" name="obs_cliente"
                                  class="observaciones-textarea"
                                  placeholder="Ej: Su trámite está siendo procesado..."></textarea>
                    </div>

                    <div class="acciones-revision">
                        <a href="revisar_tramites.php" class="btn-revision btn-cancelar">
                            <i class="bi bi-arrow-left me-2"></i>Volver
                        </a>
                        <button type="submit" class="btn-revision btn-guardar">
                            <i class="bi bi-check-lg me-2"></i>Guardar Cambios
                        </button>
                    </div>

                    <?php endif; ?>

                </form>

                <!-- FORM ARCHIVO -->
                <form method="POST" enctype="multipart/form-data" id="formArchivo" style="display:none;">
                    <input type="hidden" name="accion" value="archivo">

                    <div class="revision-panel-header">
                        <h3><i class="bi bi-paperclip"></i> Agregar Archivo / Informe</h3>
                        <p class="text-muted mb-0">Suba documentación adicional al trámite</p>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Archivo <span style="color:var(--cnel-accent);">*</span></label>
                        <div class="file-input-modern">
                            <input type="file" name="archivo_adicional" id="archivoAdicionalInput"
                                   accept=".pdf,.jpg,.jpeg,.png,.webp" required>
                            <label class="file-input-label" for="archivoAdicionalInput">
                                <span class="file-icon"><i class="bi bi-cloud-arrow-up"></i></span>
                                <span class="file-text">Haz clic o arrastra un archivo aquí</span>
                                <span class="file-hint">PDF o imagen · máx. 15 MB</span>
                            </label>
                            <p class="file-name" id="archivoAdicionalName"></p>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="observaciones_arch" class="form-label">
                            <i class="bi bi-chat-left-text"></i> Descripción del archivo
                            <small class="text-muted fw-normal">(opcional)</small>
                        </label>
                        <textarea id="observaciones_arch" name="observaciones"
                                  class="observaciones-textarea"
                                  style="width:100%;"
                                  placeholder="Ej: Informe técnico de inspección..."></textarea>
                    </div>

                    <div class="acciones-revision">
                        <a href="revisar_tramites.php" class="btn-revision btn-cancelar">
                            <i class="bi bi-arrow-left me-2"></i>Volver
                        </a>
                        <button type="submit" class="btn-revision btn-guardar">
                            <i class="bi bi-upload me-2"></i>Subir Archivo
                        </button>
                    </div>
                </form>

            <!-- ════════════════════════════════════════
                 FORM CICLO DE VIDA — Prioridad y Construcción
                 CAMBIO: Visible para TODOS los roles (personal, encargado, admin)
                         sin importar el estado del trámite, mientras no esté construido.
            ════════════════════════════════════════ -->
            <?php if ($puedeGestionarCicloClasico): ?>
            <div id="formCiclo" style="display:none;">

                <div class="revision-panel-header">
                    <h3><i class="bi bi-flag-fill"></i> Prioridad y Construcción</h3>
                    <p class="text-muted mb-0">
                        Defina la prioridad de ejecución o confirme la construcción tras inspección.
                    </p>
                </div>

                <!-- Sub-form: Prioridad -->
                <form method="POST" class="mb-4">
                    <input type="hidden" name="accion" value="prioridad">

                    <div class="form-group">
                        <label class="form-label" style="font-size:.9rem;">
                            <i class="bi bi-flag"></i> Nivel de Prioridad
                            <?php if ($tramite['prioridad']): ?>
                            <small class="text-muted fw-normal">
                                (actual: <?= $etiquetasPrioridad[$tramite['prioridad']][1] ?>)
                            </small>
                            <?php endif; ?>
                        </label>
                        <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:.6rem;">
                            <?php foreach ($etiquetasPrioridad as $valor => [$emoji, $label, $color]): ?>
                            <label style="position:relative;">
                                <input type="radio" name="prioridad" value="<?= $valor ?>"
                                       style="position:absolute;opacity:0;"
                                       <?= $tramite['prioridad'] === $valor ? 'checked' : '' ?>
                                       onchange="this.closest('form').querySelectorAll('.opt-prioridad').forEach(o=>o.style.borderColor='#e9ecef');this.closest('label').querySelector('.opt-prioridad').style.borderColor='<?= $color ?>';">
                                <div class="opt-prioridad" style="border:2px solid <?= $tramite['prioridad'] === $valor ? $color : '#e9ecef' ?>;border-radius:10px;padding:.75rem;text-align:center;cursor:pointer;font-weight:600;font-size:.88rem;">
                                    <?= $emoji ?> <?= $label ?>
                                </div>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="form-group mt-2">
                        <label class="form-label" style="font-size:.85rem;">
                            Nota <small class="text-muted fw-normal">(opcional)</small>
                        </label>
                        <textarea name="observaciones" class="observaciones-textarea" style="min-height:80px;"
                                  placeholder="Ej: Urgente por riesgo eléctrico reportado..."></textarea>
                    </div>

                    <button type="submit" class="btn-revision btn-guardar w-100 mt-2">
                        <i class="bi bi-flag-fill me-2"></i>Guardar Prioridad
                    </button>
                </form>

                <hr>

                <!-- Sub-form: Construido — disponible para TODOS los roles -->
                <div>
                    <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.75rem;">
                        <i class="bi bi-hammer" style="font-size:1.3rem;color:#198754;"></i>
                        <strong style="color:var(--cnel-primary);font-size:.95rem;">Confirmar construcción</strong>
                    </div>
                    <p class="text-muted mb-3" style="font-size:.85rem;">
                        Use esto solo tras confirmar mediante inspección que la obra o servicio ya existe.
                        Esta acción finaliza el ciclo de vida del trámite.
                    </p>

                    <button type="button" class="btn-revision" style="background:linear-gradient(135deg,#198754,#20c997);color:#fff;width:100%;"
                            onclick="abrirModalConstruido()">
                        <i class="bi bi-check-circle-fill me-2"></i>Marcar como Construido
                    </button>
                </div>

                <!-- Form oculto que se envía al confirmar en el modal -->
                <form method="POST" id="formConstruido" style="display:none;">
                    <input type="hidden" name="accion" value="construido">
                    <textarea name="obs_cliente" id="obsClienteConstruido"></textarea>
                </form>

            </div>
            <?php endif; ?>

            </div><!-- /revision-panel -->

        </div><!-- /revision-layout -->
    </div>
</div>

<!-- ════════════════════════════════════════
     MODAL — Confirmar "Marcar como Construido"
════════════════════════════════════════ -->
<div id="modalConfirmarConstruido" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;padding:1rem;">
    <div style="background:#fff;border-radius:16px;max-width:480px;width:100%;padding:2rem;box-shadow:0 20px 50px rgba(0,0,0,.25);text-align:center;animation:popIn .25s ease;">
        <div style="width:72px;height:72px;border-radius:50%;background:linear-gradient(135deg,#d4edda,#c3e6cb);display:flex;align-items:center;justify-content:center;margin:0 auto 1.25rem;">
            <i class="bi bi-hammer" style="font-size:2rem;color:#198754;"></i>
        </div>
        <h4 style="color:var(--cnel-primary);font-weight:700;margin-bottom:.5rem;">¿Confirmar construcción?</h4>
        <p class="text-muted" style="font-size:.92rem;margin-bottom:1.25rem;">
            Esta acción marcará el trámite como <strong>construido</strong> y finalizará su ciclo de vida.
            No podrá revertirse ni se admitirán más cambios de estado o reasignaciones.
        </p>

        <div style="text-align:left;margin-bottom:1.25rem;">
            <label class="form-label" style="font-size:.85rem;font-weight:600;">
                Observación de inspección <small class="text-muted fw-normal">(opcional, se envía al solicitante)</small>
            </label>
            <textarea id="modalObsCliente" class="observaciones-textarea" style="min-height:70px;width:100%;"
                      placeholder="Ej: Se confirmó la instalación del servicio el día de hoy..."></textarea>
        </div>

        <div style="display:flex;gap:.75rem;">
            <button type="button" onclick="cerrarModalConstruido()"
                    style="flex:1;padding:.8rem;border:2px solid #e9ecef;border-radius:10px;background:#fff;color:#6c757d;font-weight:600;cursor:pointer;">
                Cancelar
            </button>
            <button type="button" onclick="confirmarConstruido()"
                    style="flex:1;padding:.8rem;border:none;border-radius:10px;background:linear-gradient(135deg,#198754,#20c997);color:#fff;font-weight:600;cursor:pointer;">
                <i class="bi bi-check-lg me-1"></i> Confirmar
            </button>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════════
     MODAL — Éxito tras marcar como construido
════════════════════════════════════════ -->
<?php if ($mostrarModalConstruido): ?>
<div id="modalExitoConstruido" style="display:flex;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9999;align-items:center;justify-content:center;padding:1rem;">
    <div style="background:#fff;border-radius:18px;max-width:460px;width:100%;padding:2.5rem 2rem;box-shadow:0 25px 60px rgba(0,0,0,.3);text-align:center;animation:popIn .3s ease;">
        <div style="width:88px;height:88px;border-radius:50%;background:linear-gradient(135deg,#198754,#20c997);display:flex;align-items:center;justify-content:center;margin:0 auto 1.5rem;box-shadow:0 8px 20px rgba(25,135,84,.35);">
            <i class="bi bi-check-lg" style="font-size:2.8rem;color:#fff;"></i>
        </div>
        <h3 style="color:#198754;font-weight:800;margin-bottom:.5rem;">¡Trámite Construido!</h3>
        <p class="text-muted" style="font-size:.95rem;margin-bottom:1.5rem;">
            El ciclo de vida del trámite <strong><?= $tramite['numero_tramite'] ?></strong> ha finalizado correctamente.
            Se notificó al solicitante<?php if ($tramite['encargado_id'] && (int)$tramite['encargado_id'] !== $userId): ?> y al encargado responsable<?php endif; ?>.
        </p>
        <button type="button" onclick="document.getElementById('modalExitoConstruido').style.display='none';"
                style="padding:.85rem 2.5rem;border:none;border-radius:10px;background:linear-gradient(135deg,#198754,#20c997);color:#fff;font-weight:700;cursor:pointer;font-size:.95rem;">
            Entendido
        </button>
    </div>
</div>
<?php endif; ?>

<style>
@keyframes popIn {
    from { opacity: 0; transform: scale(.9); }
    to   { opacity: 1; transform: scale(1); }
}
</style>

<script>
function actualizarContador(textarea) {
    document.getElementById('contador').textContent = textarea.value.length;
}

function switchTab(tab) {
    const fEstado  = document.getElementById('formEstado');
    const fArchivo = document.getElementById('formArchivo');
    const fCiclo   = document.getElementById('formCiclo');
    const tEstado  = document.getElementById('tabEstado');
    const tArchivo = document.getElementById('tabArchivo');
    const tCiclo   = document.getElementById('tabCiclo');

    const activeStyle   = 'background:var(--cnel-secondary);color:#fff;';
    const inactiveStyle = 'background:#fff;color:var(--cnel-secondary);';

    [fEstado, fCiclo].forEach(f => { if (f) f.style.display = 'none'; });
    if (fArchivo) fArchivo.style.display = 'none';

    [tEstado, tArchivo, tCiclo].forEach(t => {
        if (t) t.setAttribute('style', t.getAttribute('style').replace(/background:[^;]+;color:[^;]+;/g, '') + inactiveStyle);
    });

    if (tab === 'estado') {
        if (fEstado) fEstado.style.display = '';
        if (tEstado) tEstado.setAttribute('style', tEstado.getAttribute('style').replace(/background:[^;]+;color:[^;]+;/g, '') + activeStyle);
    } else if (tab === 'archivo') {
        if (fArchivo) fArchivo.style.display = '';
        if (tArchivo) tArchivo.setAttribute('style', tArchivo.getAttribute('style').replace(/background:[^;]+;color:[^;]+;/g, '') + activeStyle);
    } else if (tab === 'ciclo' && fCiclo) {
        fCiclo.style.display = 'block';
        if (tCiclo) tCiclo.setAttribute('style', tCiclo.getAttribute('style').replace(/background:[^;]+;color:[^;]+;/g, '') + activeStyle);
    }
}

/* ── Modal de confirmación "Marcar como Construido" ── */
function abrirModalConstruido() {
    document.getElementById('modalConfirmarConstruido').style.display = 'flex';
}
function cerrarModalConstruido() {
    document.getElementById('modalConfirmarConstruido').style.display = 'none';
}
function confirmarConstruido() {
    const obs = document.getElementById('modalObsCliente').value;
    document.getElementById('obsClienteConstruido').value = obs;
    document.getElementById('formConstruido').submit();
}

document.addEventListener('DOMContentLoaded', function () {
    const contadorEl = document.getElementById('observaciones');
    if (contadorEl) actualizarContador(contadorEl);

    <?php if (!$esPersonal && (int)$tramite['construido'] === 0): ?>
    const radioReasignar = document.getElementById('estado_reasignar');
    const panel          = document.getElementById('panelReasignacion');
    if (radioReasignar && panel) {
        function toggleReasignacion() {
            panel.style.display = radioReasignar.checked ? 'block' : 'none';
        }
        document.querySelectorAll('input[name="estado"]')
            .forEach(r => r.addEventListener('change', toggleReasignacion));
    }
    <?php endif; ?>
});

document.getElementById('archivoAdicionalInput')?.addEventListener('change', function () {
    document.getElementById('archivoAdicionalName').textContent =
        this.files[0] ? '📎 ' + this.files[0].name : '';
});
</script>

<?php require_once 'includes/footer.php'; ?>