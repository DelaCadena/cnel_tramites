<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/config.php'; 

header('Content-Type: application/json');

if(isset($_GET['encargado_id'])){

    $encargado_id = $_GET['encargado_id'];

    $stmt = $pdo->prepare("
        SELECT id, nombre 
        FROM servicios 
        WHERE encargado_id = ? AND estado = 'activo'
    ");

    $stmt->execute([$encargado_id]);

    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($result);
}