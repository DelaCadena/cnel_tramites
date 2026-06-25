<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

class EmailNotifier {

    private PHPMailer $mail;

    public function __construct() {
        $this->mail = new PHPMailer(true);
        $this->configurarSMTP();
    }

    private function configurarSMTP(): void {
        $this->mail->isSMTP();
        $this->mail->Host       = 'smtp.gmail.com';
        $this->mail->SMTPAuth   = true;
        $this->mail->Username   = 'sistemagestioncnel@gmail.com';
        $this->mail->Password   = 'ndxlmslyxdoorfyn';
        $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $this->mail->Port       = 587;
        $this->mail->CharSet    = 'UTF-8';
        $this->mail->isHTML(true);
        $this->mail->setFrom('sistemagestioncnel@gmail.com', 'Sistema CNEL');
    }

    public function enviar(string $email, string $nombre, string $asunto, string $mensaje): bool {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($email, $nombre);
            $this->mail->Subject = $asunto;
            $this->mail->Body    = $mensaje;
            $this->mail->AltBody = strip_tags($mensaje);
            return $this->mail->send();
        } catch (Exception $e) {
            error_log($this->mail->ErrorInfo);
            return false;
        }
    }
}

/* =================================================
   NOTIFICAR SOLICITANTE — NUEVO TRÁMITE
================================================= */
function notificarSolicitanteNuevo(PDO $pdo, int $tramite_id): void {
    $stmt = $pdo->prepare("SELECT * FROM tramites WHERE id = ?");
    $stmt->execute([$tramite_id]);
    $t = $stmt->fetch();
    if (!$t || empty($t['email'])) return;

    $email   = new EmailNotifier();
    $mensaje = "
        <h3>Trámite registrado exitosamente</h3>
        <p>Su trámite <strong>{$t['numero_tramite']}</strong> ha sido ingresado correctamente.</p>
        <p>Se le notificará el estado de su trámite en el lapso de 15 días.</p>
    ";
    $email->enviar($t['email'], $t['solicitante'], '📄 Trámite registrado - CNEL', $mensaje);
}

/* =================================================
   NOTIFICAR SOLICITANTE — CAMBIO DE ESTADO
   $obs_cliente = observaciones visibles al solicitante (opcional)
================================================= */
function notificarSolicitanteEstado(PDO $pdo, int $tramite_id, string $estado, string $obs_cliente = ''): void {
    $stmt = $pdo->prepare("SELECT * FROM tramites WHERE id = ?");
    $stmt->execute([$tramite_id]);
    $t = $stmt->fetch();
    if (!$t || empty($t['email'])) return;

    $map = [
        'aprobado'  => '✅ Su trámite ha sido APROBADO',
        'rechazado' => '❌ Su trámite ha sido RECHAZADO',
        'revision'  => '🔍 Su trámite está EN REVISIÓN',
        'pendiente' => '⏳ Su trámite está PENDIENTE',
    ];

    $bloqueObs = $obs_cliente
        ? "<p><strong>Observaciones:</strong><br>" . nl2br(htmlspecialchars($obs_cliente)) . "</p>"
        : '';

    $mensaje = "
        <div style='font-family:Arial,sans-serif;padding:20px;'>
            <h2 style='color:#0d6efd;'>{$map[$estado]}</h2>
            <p><strong>N° Trámite:</strong> {$t['numero_tramite']}</p>
            $bloqueObs
            <small style='color:#6c757d;'>Sistema de Trámites CNEL EP</small>
        </div>
    ";
    $email = new EmailNotifier();
    $email->enviar($t['email'], $t['solicitante'], 'Estado de su trámite - CNEL', $mensaje);
}

/* =================================================
   NOTIFICAR ENCARGADO ASIGNADO AL CREAR TRÁMITE
================================================= */
function notificarEncargadoAsignado(PDO $pdo, int $tramite_id): void {
    $stmt = $pdo->prepare("
        SELECT t.numero_tramite, u.nombre, u.email
        FROM tramites t
        LEFT JOIN usuarios u ON t.encargado_id = u.id
        WHERE t.id = ?
    ");
    $stmt->execute([$tramite_id]);
    $data = $stmt->fetch();
    if (!$data || empty($data['email'])) return;

    $email   = new EmailNotifier();
    $mensaje = "
        <h3>📥 Nuevo trámite asignado</h3>
        <p>Se le ha asignado el siguiente trámite para revisión:</p>
        <p><strong>Número de trámite:</strong> {$data['numero_tramite']}</p>
        <p>Por favor ingrese al sistema para revisar y gestionar el trámite.</p>
    ";
    $email->enviar($data['email'], $data['nombre'], '📄 Nuevo trámite asignado - CNEL', $mensaje);
}

/* =================================================
   NOTIFICAR SOLICITANTE — REASIGNACIÓN
   $obs_cliente = observaciones opcionales para el solicitante
================================================= */
function notificarSolicitanteReasignacion(PDO $pdo, int $tramite_id, string $obs_cliente = ''): void {
    $stmt = $pdo->prepare("SELECT * FROM tramites WHERE id = ?");
    $stmt->execute([$tramite_id]);
    $t = $stmt->fetch();
    if (!$t || empty($t['email'])) return;

    $bloqueObs = $obs_cliente
        ? "<p><strong>Observaciones:</strong><br>" . nl2br(htmlspecialchars($obs_cliente)) . "</p>"
        : '';

    $email   = new EmailNotifier();
    $mensaje = "
        <div style='font-family:Arial,sans-serif;padding:20px;'>
            <h2 style='color:#fd7e14;'>🔄 Su trámite ha sido reasignado</h2>
            <p>El trámite <strong>{$t['numero_tramite']}</strong> ha sido reasignado a otro responsable para revisión.</p>
            $bloqueObs
            <p>Por favor espere próximas actualizaciones.</p>
            <small style='color:#6c757d;'>Sistema de Trámites CNEL EP</small>
        </div>
    ";
    $email->enviar($t['email'], $t['solicitante'], '🔄 Trámite reasignado - CNEL', $mensaje);
}

/* =================================================
   NOTIFICAR NUEVO ENCARGADO — REASIGNACIÓN
   $obs = observaciones internas del revisor para el encargado
================================================= */
function notificarNuevoEncargado(PDO $pdo, int $tramite_id, int $encargado_id, string $obs = ''): void {
    $stmt = $pdo->prepare("SELECT numero_tramite, tipo FROM tramites WHERE id = ?");
    $stmt->execute([$tramite_id]);
    $tramite = $stmt->fetch();
    if (!$tramite) return;

    $stmt = $pdo->prepare("SELECT nombre, email FROM usuarios WHERE id = ?");
    $stmt->execute([$encargado_id]);
    $encargado = $stmt->fetch();
    if (!$encargado || empty($encargado['email'])) return;

    $tipo      = $tramite['tipo'] === 'ferum' ? 'FERUM' : 'Extensión de Red';
    $bloqueObs = $obs
        ? "<div style='background:#fff3cd;padding:12px 15px;border-radius:8px;border-left:4px solid #ffc107;margin:15px 0;'>
               <strong>📝 Observaciones del revisor:</strong><br>
               " . nl2br(htmlspecialchars($obs)) . "
           </div>"
        : '';

    $email   = new EmailNotifier();
    $mensaje = "
        <div style='font-family:Arial,sans-serif;padding:20px;'>
            <h2 style='color:#0d6efd;'>📥 Trámite reasignado a usted</h2>
            <p>Hola <strong>{$encargado['nombre']}</strong>,</p>
            <p>Se le ha reasignado un trámite para revisión:</p>
            <div style='background:#f8f9fa;padding:15px;border-radius:10px;margin:15px 0;'>
                <p style='margin:0 0 8px;'><strong>Número de trámite:</strong> {$tramite['numero_tramite']}</p>
                <p style='margin:0;'><strong>Tipo:</strong> {$tipo}</p>
            </div>
            $bloqueObs
            <p>Por favor ingrese al sistema y revise el trámite asignado.</p>
            <small style='color:#6c757d;'>Sistema de Trámites CNEL EP</small>
        </div>
    ";
    $email->enviar($encargado['email'], $encargado['nombre'], '📄 Trámite reasignado - CNEL', $mensaje);
}

/* =================================================
   NOTIFICAR PERSONAL — ASIGNACIÓN / REASIGNACIÓN
   $obs = observaciones internas del revisor para el personal
================================================= */
function notificarNuevoPersonal(PDO $pdo, int $tramite_id, int $personal_id, string $obs = ''): void {
    $stmt = $pdo->prepare("SELECT numero_tramite, tipo FROM tramites WHERE id = ?");
    $stmt->execute([$tramite_id]);
    $tramite = $stmt->fetch();
    if (!$tramite) return;

    $stmt = $pdo->prepare("SELECT nombre, email FROM usuarios WHERE id = ?");
    $stmt->execute([$personal_id]);
    $personal = $stmt->fetch();
    if (!$personal || empty($personal['email'])) return;

    $tipo      = $tramite['tipo'] === 'ferum' ? 'FERUM' : 'Extensión de Red';
    $bloqueObs = $obs
        ? "<div style='background:#fff3cd;padding:12px 15px;border-radius:8px;border-left:4px solid #ffc107;margin:15px 0;'>
               <strong>📝 Observaciones del revisor:</strong><br>
               " . nl2br(htmlspecialchars($obs)) . "
           </div>"
        : '';

    $email   = new EmailNotifier();
    $mensaje = "
        <div style='font-family:Arial,sans-serif;padding:20px;'>
            <h2 style='color:#198754;'>📋 Trámite asignado para seguimiento</h2>
            <p>Hola <strong>{$personal['nombre']}</strong>,</p>
            <p>Se le ha asignado el siguiente trámite para seguimiento y revisión:</p>
            <div style='background:#f8f9fa;padding:15px;border-radius:10px;margin:15px 0;'>
                <p style='margin:0 0 8px;'><strong>Número de trámite:</strong> {$tramite['numero_tramite']}</p>
                <p style='margin:0;'><strong>Tipo:</strong> {$tipo}</p>
            </div>
            $bloqueObs
            <p>Por favor ingrese al sistema para revisar la información del trámite.</p>
            <small style='color:#6c757d;'>Sistema de Trámites CNEL EP</small>
        </div>
    ";
    $email->enviar($personal['email'], $personal['nombre'], '📋 Trámite asignado - CNEL', $mensaje);
}

/* =================================================
   NUEVO — NOTIFICAR ENCARGADO: CAMBIO DE PRIORIDAD
   O TRÁMITE MARCADO COMO CONSTRUIDO (hecho por personal)
   $tipoCambio = 'prioridad' | 'construido'
   $valor      = nivel de prioridad si aplica ('baja'|'media'|'alta'|'urgente')
================================================= */
function notificarEncargadoCambioCiclo(PDO $pdo, int $tramite_id, int $encargado_id, string $tipoCambio, ?string $valor, int $usuario_id): void {
    $stmt = $pdo->prepare("SELECT numero_tramite, tipo, solicitante FROM tramites WHERE id = ?");
    $stmt->execute([$tramite_id]);
    $tramite = $stmt->fetch();
    if (!$tramite) return;

    $stmt = $pdo->prepare("SELECT nombre, email FROM usuarios WHERE id = ?");
    $stmt->execute([$encargado_id]);
    $encargado = $stmt->fetch();
    if (!$encargado || empty($encargado['email'])) return;

    $stmt = $pdo->prepare("SELECT nombre FROM usuarios WHERE id = ?");
    $stmt->execute([$usuario_id]);
    $autor = $stmt->fetch();
    $nombreAutor = $autor['nombre'] ?? 'Personal asignado';

    $tipo = $tramite['tipo'] === 'ferum' ? 'FERUM' : 'Extensión de Red';

    if ($tipoCambio === 'prioridad') {
        $etiquetas = [
            'baja'    => ['🟢 Baja',    '#198754'],
            'media'   => ['🟡 Media',   '#f39c12'],
            'alta'    => ['🟠 Alta',    '#fd7e14'],
            'urgente' => ['🔴 Urgente', '#dc3545'],
        ];
        [$labelPrioridad, $color] = $etiquetas[$valor] ?? [$valor, '#6c757d'];

        $asunto  = '🚩 Prioridad actualizada - CNEL';
        $mensaje = "
            <div style='font-family:Arial,sans-serif;padding:20px;'>
                <h2 style='color:{$color};'>🚩 Prioridad actualizada</h2>
                <p>Hola <strong>{$encargado['nombre']}</strong>,</p>
                <p><strong>{$nombreAutor}</strong> estableció una nueva prioridad en el trámite que tiene a su cargo:</p>
                <div style='background:#f8f9fa;padding:15px;border-radius:10px;margin:15px 0;'>
                    <p style='margin:0 0 8px;'><strong>Número de trámite:</strong> {$tramite['numero_tramite']}</p>
                    <p style='margin:0 0 8px;'><strong>Tipo:</strong> {$tipo}</p>
                    <p style='margin:0 0 8px;'><strong>Solicitante:</strong> {$tramite['solicitante']}</p>
                    <p style='margin:0;'><strong>Nueva prioridad:</strong> <span style='color:{$color};font-weight:700;'>{$labelPrioridad}</span></p>
                </div>
                <p>Esto es solo informativo, el trámite sigue bajo su responsabilidad.</p>
                <small style='color:#6c757d;'>Sistema de Trámites CNEL EP</small>
            </div>
        ";
    } else { // construido
        $asunto  = '🏗️ Trámite marcado como CONSTRUIDO - CNEL';
        $mensaje = "
            <div style='font-family:Arial,sans-serif;padding:20px;'>
                <h2 style='color:#198754;'>🏗️ Trámite construido</h2>
                <p>Hola <strong>{$encargado['nombre']}</strong>,</p>
                <p><strong>{$nombreAutor}</strong> confirmó, tras inspección, que el siguiente trámite ya fue construido:</p>
                <div style='background:#f8f9fa;padding:15px;border-radius:10px;margin:15px 0;'>
                    <p style='margin:0 0 8px;'><strong>Número de trámite:</strong> {$tramite['numero_tramite']}</p>
                    <p style='margin:0 0 8px;'><strong>Tipo:</strong> {$tipo}</p>
                    <p style='margin:0;'><strong>Solicitante:</strong> {$tramite['solicitante']}</p>
                </div>
                <p><strong>El ciclo de vida de este trámite ha finalizado.</strong> Ya no admite más cambios de estado ni reasignaciones.</p>
                <small style='color:#6c757d;'>Sistema de Trámites CNEL EP</small>
            </div>
        ";
    }

    $email = new EmailNotifier();
    $email->enviar($encargado['email'], $encargado['nombre'], $asunto, $mensaje);
}

/* =================================================
   NUEVO — NOTIFICAR SOLICITANTE: TRÁMITE CONSTRUIDO
   $obs_cliente = observación de inspección, opcional
================================================= */
function notificarSolicitanteConstruido(PDO $pdo, int $tramite_id, string $obs_cliente = ''): void {
    $stmt = $pdo->prepare("SELECT * FROM tramites WHERE id = ?");
    $stmt->execute([$tramite_id]);
    $t = $stmt->fetch();
    if (!$t || empty($t['email'])) return;

    $bloqueObs = $obs_cliente
        ? "<p><strong>Detalle de la inspección:</strong><br>" . nl2br(htmlspecialchars($obs_cliente)) . "</p>"
        : '';

    $mensaje = "
        <div style='font-family:Arial,sans-serif;padding:20px;'>
            <h2 style='color:#198754;'>🏗️ Su trámite ha sido completado</h2>
            <p><strong>N° Trámite:</strong> {$t['numero_tramite']}</p>
            <p>Le informamos que su trámite ha sido verificado mediante inspección y confirmado como <strong>construido</strong>.</p>
            $bloqueObs
            <p>Gracias por su confianza en CNEL EP.</p>
            <small style='color:#6c757d;'>Sistema de Trámites CNEL EP</small>
        </div>
    ";
    $email = new EmailNotifier();
    $email->enviar($t['email'], $t['solicitante'], '🏗️ Trámite completado - CNEL', $mensaje);
}