<?php
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    echo "<pre>";
    echo "POST: ";
    print_r($_POST);
    echo "\nFILES: ";
    print_r($_FILES);
    echo "</pre>";
    
    if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        $destino = $upload_dir . $_FILES['archivo']['name'];
        if (move_uploaded_file($_FILES['archivo']['tmp_name'], $destino)) {
            echo "Archivo subido correctamente a: $destino";
        } else {
            echo "Error al mover el archivo";
        }
    } else {
        echo "Error en archivo: " . ($_FILES['archivo']['error'] ?? 'No se recibió archivo');
    }
    exit;
}
?>
<form method="POST" enctype="multipart/form-data">
    <input type="file" name="archivo">
    <button type="submit">Subir</button>
</form>