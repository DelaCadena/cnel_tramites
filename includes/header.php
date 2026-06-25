<?php
if (!isset($pdo)) {
    require_once 'config.php';
}
require_once 'auth.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Verificar timeout de sesión (30 minutos)
if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > 1800)) {
    session_unset();
    session_destroy();
    header('Location: login.php?timeout=1');
    exit;
}

// Actualizar tiempo de sesión
$_SESSION['login_time'] = time();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : 'Sistema de Trámites CNEL'; ?></title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="dashboard.php">
                <img src="https://www.derconsult.net/assets/images/cnel_logo_gris.png" alt="CNEL" height="70" class="me-2">
                <span class="fw-bold">Sistema de Trámites</span>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="bi bi-house-door me-1"></i>Inicio
                        </a>
                    </li>
                    
                    <?php if ($_SESSION['user_rol'] == 'ventanilla'): ?>
                        <li class="nav-item">
                            <a class="nav-link btn btn-outline-light btn-sm mx-1" href="subir_tramites.php">
                                <i class="bi bi-cloud-upload me-1"></i>Subir Trámite
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="estado_tramites.php">
                                <i class="bi bi-list-check me-1"></i>Mis Trámites
                            </a>
                        </li>
                    <?php elseif ($_SESSION['user_rol'] == 'encargado' || $_SESSION['user_rol'] == 'admin' || $_SESSION['user_rol'] == 'personal'): ?>
                        <li class="nav-item">
                            <a class="nav-link btn btn-warning btn-sm mx-1" href="revisar_tramites.php">
                                <i class="bi bi-eye me-1"></i>Revisar Trámites
                            </a>
                        </li>
                        <?php if ($_SESSION['user_rol'] == 'encargado' || $_SESSION['user_rol'] == 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="generar_reporte.php">
                                <i class="bi bi-graph-up me-1"></i>Reportes
                            </a>
                        </li>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php if ($_SESSION['user_rol'] == 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="gestion_usuarios.php">
                                <i class="bi bi-people me-1"></i>Usuarios
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="gestion_servicios.php">
                                <i class="bi bi-people me-1"></i>Servicios
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
                
                <div class="navbar-nav">
                    <span class="navbar-text me-3">
                        <i class="bi bi-person-circle me-1"></i><?php echo $_SESSION['user_nombre']; ?>
                    </span>
                    <a class="nav-link" href="logout.php">
                        <i class="bi bi-box-arrow-right me-1"></i>Cerrar Sesión
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <main class="container-fluid py-4">