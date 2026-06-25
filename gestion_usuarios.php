<?php
$page_title = "Gestión de Usuarios - Sistema de Trámites";
require_once 'includes/header.php';
checkRole(['admin']);

$success = '';
$error   = '';

/* ── Crear usuario ─────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear_usuario'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $nombre   = trim($_POST['nombre']);
    $email    = trim($_POST['email']);
    $rol      = $_POST['rol'];

    $roles_validos = ['admin', 'encargado', 'ventanilla', 'personal'];
    if (!in_array($rol, $roles_validos)) {
        $error = "Rol no válido.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $error = "El nombre de usuario ya existe.";
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $pdo->prepare("INSERT INTO usuarios (username, password, nombre, email, rol) VALUES (?, ?, ?, ?, ?)")
                    ->execute([$username, $hash, $nombre, $email, $rol]);
                $success = "Usuario creado exitosamente.";
            }
        } catch (Exception $e) {
            $error = "Error al crear el usuario: " . $e->getMessage();
        }
    }
}

/* ── Activar / Desactivar ──────────────────────────────── */
if (isset($_GET['action'], $_GET['id'])) {
    $uid    = (int) $_GET['id'];
    $action = $_GET['action'];
    try {
        $nuevo = $action === 'activate' ? 1 : 0;
        $pdo->prepare("UPDATE usuarios SET activo = ? WHERE id = ?")->execute([$nuevo, $uid]);
        $success = $action === 'activate' ? "Usuario activado." : "Usuario desactivado.";
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

/* ── Listar usuarios ───────────────────────────────────── */
$stmt = $pdo->prepare("
    SELECT id, username, nombre, email, rol, activo, fecha_creacion
    FROM usuarios ORDER BY fecha_creacion DESC
");
$stmt->execute();
$usuarios = $stmt->fetchAll();

/* Mapa de colores y etiquetas por rol */
$rol_config = [
    'admin'      => ['bg' => '#e74c3c', 'label' => 'Administrador'],
    'encargado'  => ['bg' => '#f39c12', 'label' => 'Encargado'],
    'ventanilla' => ['bg' => '#17a2b8', 'label' => 'Ventanilla'],
    'personal'   => ['bg' => '#27ae60', 'label' => 'Personal'],
];
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Gestión de Usuarios</h1>
            <p class="text-muted mb-0">Administrar usuarios del sistema</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalUsuario">
            <i class="bi bi-person-plus me-1"></i>Nuevo Usuario
        </button>
    </div>

    <?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="bi bi-check-circle me-2"></i><?= $success ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="bi bi-exclamation-triangle me-2"></i><?= $error ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Tabla de usuarios -->
    <div class="card shadow-sm">
        <div class="card-header bg-white">
            <h5 class="card-title mb-0">Lista de Usuarios</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Usuario</th>
                            <th>Nombre</th>
                            <th>Email</th>
                            <th>Rol</th>
                            <th>Estado</th>
                            <th>Registro</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($usuarios as $u):
                        $cfg = $rol_config[$u['rol']] ?? ['bg' => '#6c757d', 'label' => ucfirst($u['rol'])];
                    ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <!-- Avatar coloreado según rol -->
                                    <div style="
                                        width:32px;height:32px;border-radius:50%;
                                        background:<?= $cfg['bg'] ?>;color:white;
                                        display:flex;align-items:center;justify-content:center;
                                        font-weight:700;font-size:.85rem;margin-right:.6rem;
                                        flex-shrink:0;">
                                        <?= strtoupper(substr($u['username'], 0, 1)) ?>
                                    </div>
                                    <strong><?= htmlspecialchars($u['username']) ?></strong>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($u['nombre']) ?></td>
                            <td><?= htmlspecialchars($u['email']) ?></td>
                            <td>
                                <span class="badge" style="background:<?= $cfg['bg'] ?>;">
                                    <?= $cfg['label'] ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?= $u['activo'] ? 'bg-success' : 'bg-secondary' ?>">
                                    <?= $u['activo'] ? 'Activo' : 'Inactivo' ?>
                                </span>
                            </td>
                            <td><?= date('d/m/Y', strtotime($u['fecha_creacion'])) ?></td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <?php if ($u['activo']): ?>
                                        <a href="gestion_usuarios.php?action=deactivate&id=<?= $u['id'] ?>"
                                           class="btn btn-outline-warning" title="Desactivar">
                                            <i class="bi bi-pause-circle"></i>
                                        </a>
                                    <?php else: ?>
                                        <a href="gestion_usuarios.php?action=activate&id=<?= $u['id'] ?>"
                                           class="btn btn-outline-success" title="Activar">
                                            <i class="bi bi-play-circle"></i>
                                        </a>
                                    <?php endif; ?>

                                    <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                        <a href="editar_usuario.php?id=<?= $u['id'] ?>"
                                           class="btn btn-outline-primary" title="Editar">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    <?php else: ?>
                                        <button class="btn btn-outline-secondary" disabled
                                                title="No puedes editar tu propio usuario">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                    <?php endif; ?>
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

<!-- Modal crear usuario -->
<div class="modal fade" id="modalUsuario" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Crear Nuevo Usuario</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Usuario *</label>
                            <input type="text" name="username" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Contraseña *</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Nombre Completo *</label>
                            <input type="text" name="nombre" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Email *</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Rol *</label>
                            <select name="rol" class="form-select" required>
                                <option value="">Seleccionar rol</option>
                                <option value="admin">Administrador</option>
                                <option value="encargado">Encargado</option>
                                <option value="ventanilla">Ventanilla</option>
                                <option value="personal">Personal</option>
                            </select>
                        </div>
                    </div>

                    <!-- Descripción de permisos según rol seleccionado -->
                    <div id="rolDescripcion" class="alert alert-info mt-3 mb-0"
                         style="display:none;font-size:.85rem;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="crear_usuario" class="btn btn-primary">Crear Usuario</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
/* Mostrar descripción de permisos al elegir rol */
const descripciones = {
    admin:      '🔑 Acceso completo: usuarios, reportes, aprobación y rechazo de trámites.',
    encargado:  '📋 Puede revisar, aprobar, rechazar y reasignar trámites. Recibe notificaciones.',
    ventanilla: '📤 Solo puede registrar nuevos trámites y consultar sus propios trámites.',
    personal:   '👁️ Puede revisar trámites, adjuntar archivos e informes, y reasignar. <strong>No puede aprobar ni rechazar.</strong>',
};

document.querySelector('select[name="rol"]')?.addEventListener('change', function () {
    const div = document.getElementById('rolDescripcion');
    if (descripciones[this.value]) {
        div.innerHTML = descripciones[this.value];
        div.style.display = 'block';
    } else {
        div.style.display = 'none';
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>