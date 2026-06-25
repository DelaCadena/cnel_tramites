<?php
// setup_database.php - Versión simplificada
$host = 'localhost';
$dbname = 'cnel_tramites';
$username = 'root';
$password = '';

// Conexión básica sin atributos
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    
    // Hashear contraseñas
    $pass_admin = password_hash('admin123', PASSWORD_DEFAULT);
    $pass_encargado = password_hash('encargado123', PASSWORD_DEFAULT);
    $pass_cliente = password_hash('cliente123', PASSWORD_DEFAULT);
    
    // Insertar usuarios
    $sql = "INSERT INTO usuarios (username, password, nombre, email, rol) VALUES 
            ('admin', '$pass_admin', 'Administrador', 'admin@cnel.gob.ec', 'admin'),
            ('encargado', '$pass_encargado', 'Encargado de Trámites', 'encargado@cnel.gob.ec', 'encargado'),
            ('ventanilla', '$pass_cliente', 'Juan Pérez Cliente', 'cliente@ejemplo.com', 'cliente')";
    
    $pdo->exec($sql);
    
    echo "✓ Usuarios creados exitosamente!\n\n";
    echo "Credenciales:\n";
    echo "Admin: admin / admin123\n";
    echo "Encargado: encargado / encargado123\n";
    echo "Cliente: cliente / cliente123\n";
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>