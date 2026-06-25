<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/vendor/autoload.php';

try {
    $mail = new PHPMailer(true);

    // ================================
    // CONFIGURACIÓN SMTP
    // ================================
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'sistemagestioncnel@gmail.com';        // 👈 TU CORREO REAL
    $mail->Password   = 'ndxlmslyxdoorfyn';        // 👈 CLAVE DE APLICACIÓN
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->CharSet    = 'UTF-8';

    // ================================
    // DATOS DEL CORREO
    // ================================
    $mail->setFrom('TU_CORREO@gmail.com', 'Sistema de Trámites');
    $mail->addAddress('adriandelacadena843@gmail.com', 'Usuario Prueba'); // 👈 correo destino

    $mail->Subject = '✅ Prueba de correo - Sistema de Trámites';
    $mail->Body = "
Hola 👋

Si estás leyendo este mensaje, el sistema de correos funciona correctamente.

✔ PHPMailer
✔ SMTP
✔ Configuración correcta

Fecha: " . date('d/m/Y H:i:s') . "

Sistema de Trámites
";

    // ================================
    // ENVIAR
    // ================================
    $mail->send();

    echo "<h2 style='color:green;'>✅ CORREO ENVIADO CORRECTAMENTE</h2>";

} catch (Exception $e) {
    echo "<h2 style='color:red;'>❌ ERROR AL ENVIAR CORREO</h2>";
    echo "<pre>" . $mail->ErrorInfo . "</pre>";
}
