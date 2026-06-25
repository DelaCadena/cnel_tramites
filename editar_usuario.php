<?php
$page_title = "Editar Usuario - Sistema de Trámites";
require_once 'includes/header.php';
checkRole(['admin']);

if (!isset($_GET['id'])) {
    header('Location: gestion_usuarios.php');
    exit;
}

$user_id = $_GET['id'];
$success = '';
$error = '';

// Obtener información del usuario
$stmt = $pdo->prepare("SELECT id, username, nombre, email, rol, activo FROM usuarios WHERE id = ?");
$stmt->execute([$user_id]);
$usuario = $stmt->fetch();

if (!$usuario) {
    header('Location: gestion_usuarios.php');
    exit;
}

// No permitir editar el propio usuario
if ($usuario['id'] == $_SESSION['user_id']) {
    header('Location: gestion_usuarios.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombre = trim($_POST['nombre']);
    $email = trim($_POST['email']);
    $rol = $_POST['rol'];
    $activo = isset($_POST['activo']) ? 1 : 0;
    $cambiar_password = isset($_POST['cambiar_password']);
    $password = $_POST['password'];
    
    try {
        if ($cambiar_password && !empty($password)) {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE usuarios SET nombre = ?, email = ?, rol = ?, activo = ?, password = ? WHERE id = ?");
            $stmt->execute([$nombre, $email, $rol, $activo, $password_hash, $user_id]);
        } else {
            $stmt = $pdo->prepare("UPDATE usuarios SET nombre = ?, email = ?, rol = ?, activo = ? WHERE id = ?");
            $stmt->execute([$nombre, $email, $rol, $activo, $user_id]);
        }
        $success = "Usuario actualizado exitosamente";
        
        // Actualizar información del usuario
        $stmt = $pdo->prepare("SELECT id, username, nombre, email, rol, activo FROM usuarios WHERE id = ?");
        $stmt->execute([$user_id]);
        $usuario = $stmt->fetch();
        
    } catch (Exception $e) {
        $error = "Error al actualizar el usuario: " . $e->getMessage();
    }
}
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Editar Usuario</h1>
            <p class="text-muted mb-0">Modificar información del usuario</p>
        </div>
        <a href="gestion_usuarios.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Volver
        </a>
    </div>

    <!-- Alertas -->
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i><?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i><?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-person-gear me-2"></i>
                        <?php echo htmlspecialchars($usuario['username']); ?>
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Usuario</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($usuario['username']); ?>" disabled>
                                <div class="form-text">El nombre de usuario no se puede modificar</div>
                            </div>
                            
                            <div class="col-12">
                                <label for="nombre" class="form-label">Nombre Completo *</label>
                                <input type="text" class="form-control" id="nombre" name="nombre" 
                                       value="<?php echo htmlspecialchars($usuario['nombre']); ?>" required>
                            </div>
                            
                            <div class="col-12">
                                <label for="email" class="form-label">Email *</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($usuario['email']); ?>" required>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="rol" class="form-label">Rol *</label>
                                <select class="form-select" id="rol" name="rol" required>
                                    <option value="admin" <?php echo $usuario['rol'] == 'admin' ? 'selected' : ''; ?>>Administrador</option>
                                    <option value="encargado" <?php echo $usuario['rol'] == 'encargado' ? 'selected' : ''; ?>>Encargado</option>
                                    <option value="ventanilla" <?php echo $usuario['rol'] == 'ventanilla' ? 'selected' : ''; ?>>Ventanilla</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Estado</label>
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" id="activo" name="activo" 
                                           <?php echo $usuario['activo'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="activo">
                                        Usuario activo
                                    </label>
                                </div>
                            </div>
                            
                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="cambiar_password" name="cambiar_password">
                                    <label class="form-check-label" for="cambiar_password">
                                        Cambiar contraseña
                                    </label>
                                </div>
                            </div>
                            
                            <div class="col-12" id="password-field" style="display: none;">
                                <label for="password" class="form-label">Nueva Contraseña</label>
                                <input type="password" class="form-control" id="password" name="password">
                                <div class="form-text">Dejar en blanco para mantener la contraseña actual</div>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle me-1"></i>Actualizar Usuario
                            </button>
                            <a href="gestion_usuarios.php" class="btn btn-secondary">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const checkbox = document.getElementById('cambiar_password');
    const passwordField = document.getElementById('password-field');
    
    checkbox.addEventListener('change', function() {
        passwordField.style.display = this.checked ? 'block' : 'none';
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>