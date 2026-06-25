<?php
$page_title = "Detalle del Trámite - Sistema de Trámites";
require_once 'includes/header.php';
checkRole(['admin', 'encargado', 'ventanilla', 'personal']);

if (!isset($_GET['id'])) {
    header('Location: estado_tramites.php');
    exit;
}

$tramite_id = $_GET['id'];

// Obtener información del trámite
$stmt = $pdo->prepare("
    SELECT t.*, u.nombre as usuario_nombre 
    FROM tramites t 
    LEFT JOIN usuarios u ON t.usuario_id = u.id 
    WHERE t.id = ?
");
$stmt->execute([$tramite_id]);
$tramite = $stmt->fetch();

if (!$tramite) {
    header('Location: estado_tramites.php');
    exit;
}

// Verificar permisos del cliente
if ($_SESSION['user_rol'] == 'cliente' && $tramite['usuario_id'] != $_SESSION['user_id']) {
    header('Location: estado_tramites.php');
    exit;
}

// Historial de revisiones
$stmt = $pdo->prepare("
    SELECT r.*, u.nombre as revisor_nombre 
    FROM revisiones r 
    LEFT JOIN usuarios u ON r.usuario_id = u.id 
    WHERE r.tramite_id = ? 
    ORDER BY r.fecha_revision DESC
");
$stmt->execute([$tramite_id]);
$revisiones = $stmt->fetchAll();

$estados = [
    'pendiente' => 'Pendiente',
    'revision'  => 'En Revisión',
    'aprobado'  => 'Aprobado',
    'rechazado' => 'Rechazado'
];

/* Etiquetas de prioridad, mismo set usado en revisar_tramite.php / revisar_tramites.php */
$etiquetasPrioridad = [
    'baja'    => ['🟢', 'Baja',    '#198754'],
    'media'   => ['🟡', 'Media',   '#f39c12'],
    'alta'    => ['🟠', 'Alta',    '#fd7e14'],
    'urgente' => ['🔴', 'Urgente', '#dc3545'],
];

$estaConstruido = (int)($tramite['construido'] ?? 0) === 1;
?>

<div class="container">
    <div class="page-header mb-4">
        <h1>Detalle del Trámite</h1>
        <p>Seguimiento completo del trámite</p>
        <a href="estado_tramites.php" class="btn btn-secondary">← Volver</a>
    </div>

    <!-- ESTADO GENERAL -->
    <div class="card mb-4">
        <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h4 class="mb-1">Trámite: <strong><?php echo $tramite['numero_tramite']; ?></strong></h4>
                <small>Registrado el <?php echo date('d/m/Y H:i', strtotime($tramite['fecha_creacion'])); ?></small>
            </div>

            <div class="d-flex align-items-center gap-2 flex-wrap">
                <?php if (!empty($tramite['prioridad']) && isset($etiquetasPrioridad[$tramite['prioridad']])): ?>
                    <?php [$emoji, $label, $color] = $etiquetasPrioridad[$tramite['prioridad']]; ?>
                    <span style="background:<?= $color ?>22;color:<?= $color ?>;border:1px solid <?= $color ?>55;padding:.4rem .85rem;border-radius:14px;font-size:.82rem;font-weight:600;white-space:nowrap;">
                        <?= $emoji ?> Prioridad <?= $label ?>
                    </span>
                <?php endif; ?>

                <?php if ($estaConstruido): ?>
                    <span style="background:#198754;color:#fff;padding:.4rem .85rem;border-radius:14px;font-size:.82rem;font-weight:700;white-space:nowrap;">
                        🏗️ Construido
                    </span>
                <?php endif; ?>

                <span class="badge fs-6 px-3 py-2
                    <?php
                        echo match($tramite['estado']) {
                            'aprobado'  => 'bg-success',
                            'rechazado' => 'bg-danger',
                            'revision'  => 'bg-info',
                            default     => 'bg-warning text-dark'
                        };
                    ?>">
                    <?php echo $estados[$tramite['estado']]; ?>
                </span>
            </div>
        </div>

        <?php if ($estaConstruido): ?>
        <div class="card-body pt-0">
            <div class="alert-modern" style="background:linear-gradient(135deg,#e7f7ee,#d4edda);border-left:4px solid #198754;color:#155724;margin:0;">
                <i class="bi bi-check-circle-fill me-2"></i>
                <strong>Ciclo de vida finalizado.</strong> Este trámite fue confirmado como construido
                el <?php echo date('d/m/Y H:i', strtotime($tramite['fecha_construido'])); ?>.
                Ya no admite más revisiones, cambios de estado ni reasignaciones — queda disponible solo para consulta.
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- DATOS -->
    <div class="row g-4">

        <!-- Información del trámite -->
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header bg-light">
                    <strong>📄 Información del Trámite</strong>
                </div>
                <div class="card-body">
                    <p><strong>Tipo:</strong> <?php echo $tramite['tipo'] == 'extension_red' ? 'Extensión de Red' : 'FERUM'; ?></p>
                    <p><strong>Última actualización:</strong>
                        <?php echo date('d/m/Y H:i', strtotime($tramite['fecha_actualizacion'])); ?>
                    </p>
                    <?php if (!empty($tramite['prioridad']) && isset($etiquetasPrioridad[$tramite['prioridad']])): ?>
                    <p class="mb-0"><strong>Prioridad:</strong>
                        <?php [$emoji, $label, $color] = $etiquetasPrioridad[$tramite['prioridad']]; ?>
                        <span style="color:<?= $color ?>;font-weight:600;"><?= $emoji ?> <?= $label ?></span>
                    </p>
                    <?php endif; ?>
                    <?php if ($estaConstruido): ?>
                    <p class="mb-0 mt-2"><strong>Construido el:</strong>
                        <?php echo date('d/m/Y H:i', strtotime($tramite['fecha_construido'])); ?>
                    </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Información del solicitante -->
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header bg-light">
                    <strong>👤 Solicitante</strong>
                </div>
                <div class="card-body">
                    <p><strong>Nombre:</strong> <?php echo htmlspecialchars($tramite['solicitante']); ?></p>
                    <p><strong>Cédula/RUC:</strong> <?php echo $tramite['cedula_ruc']; ?></p>
                    <p><strong>Teléfono:</strong> <?php echo $tramite['telefono'] ?: 'No proporcionado'; ?></p>
                    <p><strong>Email:</strong> <?php echo $tramite['email'] ?: 'No proporcionado'; ?></p>
                </div>
            </div>
        </div>

        <!-- Dirección -->
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header bg-light">
                    <strong>📍 Dirección</strong>
                </div>
                <div class="card-body">
                    <?php echo nl2br(htmlspecialchars($tramite['direccion'])); ?>
                </div>
            </div>
        </div>

        <!-- Documentos -->
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header bg-light">
                    <strong>📎 Documentos</strong>
                </div>
                <div class="card-body">
                    <?php if ($tramite['archivo_path']): ?>
                        <a href="<?php echo $tramite['archivo_path']; ?>" target="_blank" class="btn btn-primary">
                            Descargar archivo
                        </a>
                    <?php else: ?>
                        <p>No se adjuntaron documentos.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Descripción -->
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-light">
                    <strong>📝 Descripción del Trámite</strong>
                </div>
                <div class="card-body">
                    <?php echo nl2br(htmlspecialchars($tramite['descripcion'])); ?>
                </div>
            </div>
        </div>

    </div>

    <!-- HISTORIAL -->
    <div class="card mt-4">
        <div class="card-header bg-light">
            <strong>📚 Historial de Revisiones</strong>
        </div>
        <div class="card-body">

            <?php if ($revisiones): ?>
                <?php foreach ($revisiones as $rev): ?>
                    <div class="border rounded p-3 mb-3">
                        <div class="d-flex justify-content-between">
                            <strong><?php echo $rev['revisor_nombre'] ?: 'Sistema'; ?></strong>
                            <small><?php echo date('d/m/Y H:i', strtotime($rev['fecha_revision'])); ?></small>
                        </div>

                        <p class="mt-2">
                            Estado:
                            <span class="badge bg-secondary"><?php echo $estados[$rev['estado_anterior']] ?? ucfirst($rev['estado_anterior']); ?></span>
                            →
                            <span class="badge
                                <?php
                                    echo match($rev['estado_nuevo']) {
                                        'aprobado'  => 'bg-success',
                                        'rechazado' => 'bg-danger',
                                        'revision'  => 'bg-info',
                                        default     => 'bg-warning text-dark'
                                    };
                                ?>">
                                <?php echo $estados[$rev['estado_nuevo']] ?? ucfirst($rev['estado_nuevo']); ?>
                            </span>
                        </p>

                        <?php if ($rev['observaciones']): ?>
                            <div class="alert alert-light mt-2">
                                <strong>Observaciones:</strong><br>
                                <?php echo nl2br(htmlspecialchars($rev['observaciones'])); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No hay revisiones registradas.</p>
            <?php endif; ?>

        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>