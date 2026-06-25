<?php
require_once '../includes/config.php';

header('Content-Type: application/json');

$encargado_id = isset($_GET['encargado_id']) ? (int)$_GET['encargado_id'] : 0;

if (!$encargado_id) {
    echo json_encode([]);
    exit;
}

$stmt = $pdo->prepare("
    SELECT id, nombre 
    FROM servicios 
    WHERE encargado_id = ? AND estado = 'activo'
    ORDER BY nombre ASC
");
$stmt->execute([$encargado_id]);
$servicios = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($servicios);