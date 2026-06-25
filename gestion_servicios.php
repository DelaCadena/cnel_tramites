<?php
$page_title = "Gestión de Servicios - Sistema de Trámites";
require_once 'includes/header.php';
checkRole(['admin']);

$success = '';
$error = '';

// Obtener encargados
$encargados = $pdo->query("SELECT id, nombre FROM usuarios WHERE rol='encargado'")->fetchAll();

// CREAR SERVICIO
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['crear'])) {
    try {
        $stmt = $pdo->prepare("INSERT INTO servicios (nombre, encargado_id) VALUES (?, ?)");
        $stmt->execute([$_POST['nombre'], $_POST['encargado_id']]);
        $success = "Servicio creado correctamente";
    } catch (Exception $e) {
        $error = "Error al crear: " . $e->getMessage();
    }
}

// EDITAR SERVICIO (CARGAR DATOS)
$editando = false;
$servicioEditar = null;

if (isset($_GET['editar'])) {
    $stmt = $pdo->prepare("SELECT * FROM servicios WHERE id=?");
    $stmt->execute([$_GET['editar']]);
    $servicioEditar = $stmt->fetch();
    $editando = true;
}

// ACTUALIZAR SERVICIO
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['actualizar'])) {
    try {
        $stmt = $pdo->prepare("UPDATE servicios SET nombre=?, encargado_id=? WHERE id=?");
        $stmt->execute([
            $_POST['nombre'],
            $_POST['encargado_id'],
            $_GET['editar']
        ]);
        header("Location: gestion_servicios.php");
        exit;
    } catch (Exception $e) {
        $error = "Error al actualizar: " . $e->getMessage();
    }
}

// ACTIVAR / DESACTIVAR
if (isset($_GET['toggle'])) {
    $pdo->query("UPDATE servicios 
                 SET estado = IF(estado='activo','inactivo','activo') 
                 WHERE id=" . $_GET['toggle']);
    header("Location: gestion_servicios.php");
    exit;
}

// ELIMINAR
if (isset($_GET['eliminar'])) {
    $pdo->query("DELETE FROM servicios WHERE id=" . $_GET['eliminar']);
    header("Location: gestion_servicios.php");
    exit;
}

// LISTAR SERVICIOS
$servicios = $pdo->query("
    SELECT s.*, u.nombre as encargado 
    FROM servicios s
    JOIN usuarios u ON s.encargado_id = u.id
")->fetchAll();
?>

<div class="container mt-4">

    <!-- TITULO -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Gestión de Servicios</h1>
            <p class="text-muted mb-0">Administrar servicios del sistema</p>
        </div>
    </div>

    <!-- ALERTAS -->
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= $success ?>
            <button class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= $error ?>
            <button class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- FORMULARIO -->
    <div class="card mb-4">
        <div class="card-header bg-white">
            <strong><?= $editando ? 'Editar Servicio' : 'Nuevo Servicio' ?></strong>
        </div>
        <div class="card-body">
            <form method="POST" class="row g-3">

            <?php if ($editando): ?>
            <input type="hidden" name="id" value="<?= $servicioEditar['id'] ?>">
                <?php endif; ?>

                <div class="col-md-6">
                    <label class="form-label">Nombre del Servicio</label>
                    <input type="text" name="nombre" class="form-control"
                        value="<?= $editando ? $servicioEditar['nombre'] : '' ?>" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Encargado</label>
                    <select name="encargado_id" class="form-select" required>
                        <option value="">Seleccionar</option>
                        <?php foreach($encargados as $e): ?>
                        <option value="<?= $e['id'] ?>"
                        <?= ($editando && $servicioEditar['encargado_id'] == $e['id']) ? 'selected' : '' ?>>
                        <?= $e['nombre'] ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-12">
                    <button class="btn btn-primary" name="<?= $editando ? 'actualizar' : 'crear' ?>">
                        <?= $editando ? 'Actualizar' : 'Crear' ?>
                    </button>

                    <?php if ($editando): ?>
                        <a href="gestion_servicios.php" class="btn btn-secondary">Cancelar</a>
                    <?php endif; ?>
                </div>

            </form>
        </div>
    </div>

    <!-- TABLA -->
    <div class="card shadow-sm">
        <div class="card-header bg-white">
            <h5 class="card-title mb-0">Lista de Servicios</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Servicio</th>
                            <th>Encargado</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>

                    <?php foreach($servicios as $s): ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center">
                                <i class="bi bi-gear me-2 text-muted"></i>
                                <strong><?= htmlspecialchars($s['nombre']) ?></strong>
                            </div>
                        </td>

                        <td><?= htmlspecialchars($s['encargado']) ?></td>

                        <td>
                            <span class="badge <?= $s['estado'] == 'activo' ? 'bg-success' : 'bg-secondary' ?>">
                                <?= ucfirst($s['estado']) ?>
                            </span>
                        </td>

                        <td>
                            <div class="btn-group btn-group-sm">

                                <?php if($s['estado'] == 'activo'): ?>
                                    <a href="?toggle=<?= $s['id'] ?>" class="btn btn-outline-warning">
                                        <i class="bi bi-pause-circle"></i>
                                    </a>
                                <?php else: ?>
                                    <a href="?toggle=<?= $s['id'] ?>" class="btn btn-outline-success">
                                        <i class="bi bi-play-circle"></i>
                                    </a>
                                <?php endif; ?>

                                <a href="?editar=<?= $s['id'] ?>" class="btn btn-outline-primary">
                                    <i class="bi bi-pencil"></i>
                                </a>

                                <a href="?eliminar=<?= $s['id'] ?>" 
                                   class="btn btn-outline-danger"
                                   onclick="return confirm('¿Eliminar servicio?')">
                                    <i class="bi bi-trash"></i>
                                </a>

                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>

                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<?php require_once 'includes/footer.php'; ?>