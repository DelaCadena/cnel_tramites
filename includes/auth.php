<?php
// Verificar si el usuario está logueado
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Verificar rol del usuario
function checkRole($allowed_roles) {
    if (!isLoggedIn() || !in_array($_SESSION['user_rol'], $allowed_roles)) {
        header('Location: login.php');
        exit;
    }
}

// Redirigir si ya está logueado
function redirectIfLoggedIn() {
    if (isLoggedIn()) {
        header('Location: dashboard.php');
        exit;
    }
}
?>