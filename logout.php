<?php
session_start();
session_unset();
session_destroy();

// Redirigir al login
header('Location: login.php?logout=1');
exit;
?>