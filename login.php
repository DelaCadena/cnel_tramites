<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
redirectIfLoggedIn();

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    // Validaciones básicas
    if (empty($username) || empty($password)) {
        $error = "Por favor, complete todos los campos";
    } else {
        // Validar credenciales
        $stmt = $pdo->prepare("SELECT id, username, password, nombre, rol, activo FROM usuarios WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            if ($user['activo'] == 0) {
                $error = "Usuario desactivado. Contacte al administrador.";
            } elseif (password_verify($password, $user['password'])) {
                // Iniciar sesión
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_nombre'] = $user['nombre'];
                $_SESSION['user_rol'] = $user['rol'];
                $_SESSION['login_time'] = time();
                
                // Redirigir según el rol
                header('Location: dashboard.php');
                exit;
            } else {
                $error = "Contraseña incorrecta";
            }
        } else {
            $error = "Usuario no encontrado";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistema de Trámites CNEL</title>
    <link rel="stylesheet" href="css/login.css">
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <img src="https://www.cnelep.gob.ec/wp-content/uploads/2019/03/header_Logo.png" alt="CNEL" class="logo">
            <h1>Sistema de Control de Trámites</h1>
            <p>CNEL EP - Unidad de Negocio Bolívar</p>
        </div>
        
        <form method="POST" class="login-form">
            <?php if ($error): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="form-group">
                <label for="username">Usuario:</label>
                <input type="text" id="username" name="username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required autofocus>
            </div>
            
            <div class="form-group">
                <label for="password">Contraseña:</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit" class="btn-login">Iniciar Sesión</button>

        </form>
        
        <div class="login-footer">
            <p>&copy; 2026 CNEL EP. Todos los derechos reservados.</p>
        </div>
    </div>
</body>
</html>