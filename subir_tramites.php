<?php
$page_title = "Nuevo Trámite - Sistema de Trámites";
require_once 'includes/email.php';
require_once 'includes/header.php';
checkRole(['admin', 'ventanilla']);
$success = '';
$error = '';

/*------------------------------------------
OBTENER ENCARGADOS
------------------------------------------*/
$stmtEnc = $pdo->prepare("SELECT id, nombre, username FROM usuarios WHERE rol = 'encargado'");
$stmtEnc->execute();
$encargados = $stmtEnc->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $tipo         = $_POST['tipo'];
    $solicitante  = trim($_POST['solicitante']);
    $cedula_ruc   = trim($_POST['cedula_ruc']);
    $telefono     = trim($_POST['telefono']);
    $email        = trim($_POST['email']);
    $encargado_id = $_POST['encargado_id'];

    $numero_tramite = 'TR-' . date('Ymd') . '-' . rand(1000, 9999);

    try {
        $pdo->beginTransaction();

        if ($tipo === 'extension_red') {

            $provincia        = trim($_POST['provincia']);
            $canton           = trim($_POST['canton']);
            $parroquia        = trim($_POST['parroquia']);
            $utm_x            = trim($_POST['utm_x']);
            $utm_y            = trim($_POST['utm_y']);
            $sector           = trim($_POST['sector']);
            $referencia       = trim($_POST['referencia']);
            $calle_principal  = trim($_POST['calle_principal']);
            $calle_secundaria = trim($_POST['calle_secundaria']);

            $direccion = "Provincia: $provincia, Cantón: $canton, Parroquia: $parroquia" .
                         ($calle_principal  ? ", Calle Principal: $calle_principal"   : "") .
                         ($calle_secundaria ? ", Calle Secundaria: $calle_secundaria" : "") .
                         ($sector           ? ", Sector: $sector"                     : "") .
                         ($referencia       ? ", Referencia: $referencia"             : "");

            $descripcion = "Tipo de servicio: "      . ($_POST['tipo_servicio']  ?? '') . "\n" .
                           "Fines del servicio: "    . ($_POST['fines']          ?? '') . "\n" .
                           "Información adicional: " . ($_POST['info_adicional'] ?? '');

            $archivo_path = '';
            if (
                isset($_FILES['archivos']) &&
                isset($_FILES['archivos']['error'][0]) &&
                $_FILES['archivos']['error'][0] === UPLOAD_ERR_OK
            ) {
                $upload_dir = 'uploads/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

                $nombre_original = $_FILES['archivos']['name'][0];
                $ext             = strtolower(pathinfo($nombre_original, PATHINFO_EXTENSION));
                $archivo_path    = $upload_dir . $numero_tramite . '.' . $ext;

                if (!move_uploaded_file($_FILES['archivos']['tmp_name'][0], $archivo_path)) {
                    throw new Exception('Error al subir el archivo principal');
                }
            }

            $stmt = $pdo->prepare("
                INSERT INTO tramites
                (numero_tramite, tipo, solicitante, cedula_ruc, direccion, telefono, email,
                 descripcion, archivo_path, usuario_id, encargado_id,
                 provincia, canton, parroquia, utm_x, utm_y, sector, referencia,
                 calle_principal, calle_secundaria)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $numero_tramite, $tipo, $solicitante, $cedula_ruc,
                $direccion, $telefono, $email, $descripcion,
                $archivo_path,
                $_SESSION['user_id'], $encargado_id,
                $provincia, $canton, $parroquia,
                $utm_x, $utm_y, $sector, $referencia,
                $calle_principal, $calle_secundaria
            ]);

            $tramite_id = $pdo->lastInsertId();

        } else {

            $comunidad   = trim($_POST['ferum_comunidad']);
            $parroquia   = trim($_POST['ferum_parroquia']);
            $canton      = trim($_POST['ferum_canton']);
            $provincia   = trim($_POST['ferum_provincia']);
            $tipo_sector = $_POST['ferum_tipo_sector'];

            $direccion   = "Provincia: $provincia, Cantón: $canton, Parroquia: $parroquia, Comunidad/Sector: $comunidad";
            $descripcion = "Tipo de sector: $tipo_sector\n" .
                           "Beneficiarios estimados: " . ($_POST['ferum_num_beneficiarios']  ?? '') . "\n" .
                           "Potencia requerida: "      . ($_POST['ferum_potencia_requerida'] ?? '') . "\n" .
                           "Distancia a la red: "      . ($_POST['ferum_distancia_red']      ?? '') . "\n" .
                           "Horario de contacto: "     . ($_POST['ferum_horario_contacto']   ?? '') . "\n" .
                           "Observaciones: "           . ($_POST['ferum_observaciones']       ?? '');

            $archivo_path = '';
            if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'uploads/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                $ext          = strtolower(pathinfo($_FILES['archivo']['name'], PATHINFO_EXTENSION));
                $archivo_path = $upload_dir . $numero_tramite . '.' . $ext;
                if (!move_uploaded_file($_FILES['archivo']['tmp_name'], $archivo_path)) {
                    throw new Exception('Error al subir la planilla');
                }
            }

            $stmt = $pdo->prepare("
                INSERT INTO tramites
                (numero_tramite, tipo, solicitante, cedula_ruc, direccion, telefono, email,
                 descripcion, archivo_path, usuario_id, encargado_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $numero_tramite, $tipo, $solicitante, $cedula_ruc,
                $direccion, $telefono, $email, $descripcion,
                $archivo_path, $_SESSION['user_id'], $encargado_id
            ]);

            $tramite_id = $pdo->lastInsertId();

            $subir = function($field, $sufijo) use ($numero_tramite) {
                if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = 'uploads/';
                    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                    $ext  = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
                    $path = $upload_dir . $numero_tramite . '_' . $sufijo . '.' . $ext;
                    if (!move_uploaded_file($_FILES[$field]['tmp_name'], $path))
                        throw new Exception("Error al subir el archivo: $field");
                    return $path;
                }
                return '';
            };

            $path_croquis       = $subir('ferum_archivo_croquis',      'croquis');
            $path_gad           = $subir('ferum_archivo_gad',           'gad');
            $path_beneficiarios = $subir('ferum_archivo_beneficiarios', 'beneficiarios');

            $stmtF = $pdo->prepare("
                INSERT INTO tramites_ferum
                (tramite_id, comunidad, parroquia, canton, provincia, utm_x, utm_y, tipo_sector,
                 num_beneficiarios, potencia_requerida, distancia_red,
                 presidente_nombre, presidente_cedula, presidente_celular,
                 coordinador_nombre, coordinador_cedula, coordinador_celular,
                 horario_contacto, archivo_croquis, archivo_gad, archivo_beneficiarios, observaciones)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ");
            $stmtF->execute([
                $tramite_id,
                $comunidad,
                $parroquia,
                $canton,
                $provincia,
                trim($_POST['ferum_utm_x']            ?? ''),
                trim($_POST['ferum_utm_y']            ?? ''),
                $tipo_sector,
                (int)($_POST['ferum_num_beneficiarios']  ?? 0),
                trim($_POST['ferum_potencia_requerida']  ?? ''),
                trim($_POST['ferum_distancia_red']       ?? ''),
                trim($_POST['ferum_presidente_nombre']   ?? ''),
                trim($_POST['ferum_presidente_cedula']   ?? ''),
                trim($_POST['ferum_presidente_celular']  ?? ''),
                trim($_POST['ferum_coordinador_nombre']  ?? ''),
                trim($_POST['ferum_coordinador_cedula']  ?? ''),
                trim($_POST['ferum_coordinador_celular'] ?? ''),
                trim($_POST['ferum_horario_contacto']    ?? ''),
                $path_croquis,
                $path_gad,
                $path_beneficiarios,
                trim($_POST['ferum_observaciones']       ?? ''),
            ]);
        }

        notificarSolicitanteNuevo($pdo, $tramite_id);
        notificarEncargadoAsignado($pdo, $tramite_id);

        $pdo->commit();

        $success = "Trámite registrado exitosamente. Número de trámite: <strong>$numero_tramite</strong>";
        $_POST = [];

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error al registrar el trámite: " . $e->getMessage();
    }
}
?>

<div class="main-container">
<div class="container py-4">
<section class="form-section form-animate">

    <div class="form-header">
        <h1><i class="bi bi-cloud-upload me-2"></i>Nuevo Trámite</h1>
        <p>Registro de trámites CNEL – <?= $_SESSION['user_nombre'] ?? 'Ventanilla' ?></p>
    </div>

    <div class="form-body">

        <?php if ($success): ?>
        <div class="alert-modern alert-success-modern mb-4">
            <i class="bi bi-check-circle-fill me-2"></i><?= $success ?>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="alert-modern alert-danger-modern mb-4">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= $error ?>
        </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" id="tramiteForm">

            <!-- ── INFORMACIÓN DEL TRÁMITE ── -->
            <div class="field-group">
                <div class="field-group-title">
                    <i class="bi bi-card-checklist"></i> Información del Trámite
                </div>
                <div class="form-grid">

                    <div class="form-group-modern">
                        <label class="form-label-modern required">Tipo de Trámite</label>
                        <select name="tipo" id="tipoTramite" class="form-control-modern select-modern" required>
                            <option value="">Seleccione</option>
                            <option value="extension_red" <?= ($_POST['tipo'] ?? '') === 'extension_red' ? 'selected' : '' ?>>Extensión de Red</option>
                            <option value="ferum"         <?= ($_POST['tipo'] ?? '') === 'ferum'         ? 'selected' : '' ?>>FERUM</option>
                        </select>
                    </div>

                    <div class="form-group-modern">
                        <label class="form-label-modern required">Asignar Encargado</label>
                        <select name="encargado_id" class="form-control-modern select-modern" required>
                            <option value="">Seleccione un encargado</option>
                            <?php foreach ($encargados as $enc): ?>
                            <option value="<?= $enc['id'] ?>">
                                <?= htmlspecialchars($enc['nombre'] . ' — ' . $enc['username']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                </div>
            </div>

            <!-- ── DATOS DEL SOLICITANTE (común) ── -->
            <div class="field-group">
                <div class="field-group-title">
                    <i class="bi bi-person-badge"></i> Datos del Solicitante
                </div>
                <div class="form-grid">

                    <div class="form-group-modern">
                        <label class="form-label-modern required">Nombre Completo</label>
                        <input type="text" name="solicitante" class="form-control-modern" required
                               value="<?= htmlspecialchars($_POST['solicitante'] ?? '') ?>">
                    </div>

                    <div class="form-group-modern">
                        <label class="form-label-modern required">Cédula / RUC</label>
                        <input type="text" id="cedula_ruc" name="cedula_ruc"
                               class="form-control-modern" maxlength="13" inputmode="numeric" required
                               value="<?= htmlspecialchars($_POST['cedula_ruc'] ?? '') ?>">
                        <div class="invalid-feedback" id="cedula-feedback">Cédula ecuatoriana inválida</div>
                        <div class="valid-feedback">Cédula válida ✓</div>
                    </div>

                    <div class="form-group-modern">
                        <label class="form-label-modern">Teléfono</label>
                        <input type="text" name="telefono" class="form-control-modern" maxlength="15"
                               inputmode="numeric" value="<?= htmlspecialchars($_POST['telefono'] ?? '') ?>">
                    </div>

                    <div class="form-group-modern">
                        <label class="form-label-modern">Email</label>
                        <input type="email" name="email" class="form-control-modern"
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    </div>

                </div>
            </div>

            <!-- ══════════════════════════════════════
                 SECCIÓN EXTENSIÓN DE RED
            ══════════════════════════════════════ -->
            <div id="seccionExtensionRed" style="display:none;">

                <!-- Ubicación -->
                <div class="field-group">
                    <div class="field-group-title">
                        <i class="bi bi-geo-alt"></i> Ubicación del Proyecto
                    </div>
                    <div class="form-grid">

                        <div class="form-group-modern">
                            <label class="form-label-modern required">Provincia</label>
                            <select name="provincia" id="provincia_ext" class="form-control-modern select-modern" required>
                                <option value="">Seleccione provincia</option>
                                <option value="Bolívar">Bolívar</option>
                                <option value="Los Ríos">Los Ríos</option>
                                <option value="Chimborazo">Chimborazo</option>
                            </select>
                        </div>

                        <div class="form-group-modern">
                            <label class="form-label-modern required">Cantón</label>
                            <select name="canton" id="canton_ext" class="form-control-modern select-modern" required>
                                <option value="">Seleccione primero una provincia</option>
                            </select>
                        </div>

                        <div class="form-group-modern">
                            <label class="form-label-modern required">Parroquia</label>
                            <select name="parroquia" id="parroquia_ext" class="form-control-modern select-modern" required>
                                <option value="">Seleccione primero un cantón</option>
                            </select>
                        </div>

                        <div class="form-group-modern">
                            <label class="form-label-modern required">Calle Principal</label>
                            <input type="text" name="calle_principal" class="form-control-modern"
                                   placeholder="Ej: Av. Simón Bolívar" required>
                        </div>

                        <div class="form-group-modern">
                            <label class="form-label-modern">Calle Secundaria <small class="text-muted">(opcional)</small></label>
                            <input type="text" name="calle_secundaria" class="form-control-modern"
                                   placeholder="Ej: Calle 10 de Agosto">
                        </div>

                        <div class="form-group-modern">
                            <label class="form-label-modern">Sector</label>
                            <input type="text" name="sector" class="form-control-modern"
                                   placeholder="Ej: El Tejar">
                        </div>

                        <div class="form-group-modern">
                            <label class="form-label-modern">Referencia</label>
                            <input type="text" name="referencia" class="form-control-modern"
                                   placeholder="Ej: Frente al parque central">
                        </div>

                        <div class="form-group-modern">
                            <label class="form-label-modern">UTM X <small class="text-muted">(opcional)</small></label>
                            <input type="text" name="utm_x" class="form-control-modern" placeholder="Ej: 720350">
                        </div>

                        <div class="form-group-modern">
                            <label class="form-label-modern">UTM Y <small class="text-muted">(opcional)</small></label>
                            <input type="text" name="utm_y" class="form-control-modern" placeholder="Ej: 9820100">
                        </div>

                    </div>
                </div>

                <!-- Descripción Técnica -->
                <div class="field-group">
                    <div class="field-group-title">
                        <i class="bi bi-file-text"></i> Descripción del Servicio
                    </div>
                    <div class="form-grid">

                        <div class="form-group-modern">
                            <label class="form-label-modern required">Tipo de Servicio</label>
                            <select name="tipo_servicio" id="tipo_servicio" class="form-control-modern select-modern" required>
                                <option value="">Seleccione un servicio</option>
                            </select>
                        </div>

                        <div class="form-group-modern">
                            <label class="form-label-modern required">Fines del Servicio</label>
                            <input type="text" name="fines" class="form-control-modern" required>
                        </div>

                        <div class="form-group-modern full-width">
                            <label class="form-label-modern">Información Adicional</label>
                            <textarea name="info_adicional" class="form-control-modern textarea-modern"
                                      placeholder="Información adicional relevante (opcional)"></textarea>
                        </div>

                    </div>
                </div>

                <!-- Documento Adjunto -->
                <div class="field-group">
                    <div class="field-group-title">
                        <i class="bi bi-paperclip"></i> Documento Adjunto
                    </div>
                    <div class="file-input-modern">
                        <input type="file" name="archivos[]" id="archivoInput"
                               accept=".pdf,.doc,.docx,.zip,.jpg,.jpeg,.png,.webp" multiple>
                        <label class="file-input-label" for="archivoInput">
                            <span class="file-icon"><i class="bi bi-cloud-arrow-up"></i></span>
                            <span class="file-text">Haz clic o arrastra un archivo aquí</span>
                            <span class="file-hint">PDF, Word, ZIP, imagen · máx. 50 MB por archivo</span>
                        </label>
                        <p class="file-name" id="fileName"></p>
                    </div>
                </div>

            </div><!-- /seccionExtensionRed -->


            <!-- ══════════════════════════════════════
                 SECCIÓN FERUM
            ══════════════════════════════════════ -->
            <div id="seccionFerum" style="display:none;">

                <!-- Datos de la Comunidad -->
                <div class="field-group">
                    <div class="field-group-title">
                        <i class="bi bi-geo-alt-fill"></i> Datos de la Comunidad / Sector
                    </div>
                    <div class="form-grid">

                        <div class="form-group-modern">
                            <label class="form-label-modern required">Nombre del Recinto / Comunidad / Barrio</label>
                            <input type="text" name="ferum_comunidad" class="form-control-modern"
                                   placeholder="Ej: Recinto San José">
                        </div>

                        <div class="form-group-modern">
                            <label class="form-label-modern required">Tipo de Sector</label>
                            <select name="ferum_tipo_sector" class="form-control-modern select-modern">
                                <option value="">Seleccione</option>
                                <option value="rural">Rural</option>
                                <option value="urbano_marginal">Urbano Marginal</option>
                            </select>
                        </div>

                        <!-- PROVINCIA FERUM — ahora igual que Extensión de Red -->
                        <div class="form-group-modern">
                            <label class="form-label-modern required">Provincia</label>
                            <select name="ferum_provincia" id="provincia_ferum" class="form-control-modern select-modern">
                                <option value="">Seleccione provincia</option>
                                <option value="Bolívar">Bolívar</option>
                                <option value="Los Ríos">Los Ríos</option>
                                <option value="Chimborazo">Chimborazo</option>
                            </select>
                        </div>

                        <!-- CANTÓN FERUM — cascada -->
                        <div class="form-group-modern">
                            <label class="form-label-modern required">Cantón</label>
                            <select name="ferum_canton" id="canton_ferum" class="form-control-modern select-modern">
                                <option value="">Seleccione primero una provincia</option>
                            </select>
                        </div>

                        <!-- PARROQUIA FERUM — cascada -->
                        <div class="form-group-modern">
                            <label class="form-label-modern required">Parroquia</label>
                            <select name="ferum_parroquia" id="parroquia_ferum" class="form-control-modern select-modern">
                                <option value="">Seleccione primero un cantón</option>
                            </select>
                        </div>

                        <div class="form-group-modern">
                            <label class="form-label-modern">Coordenada UTM X <small class="text-muted">(opcional)</small></label>
                            <input type="text" name="ferum_utm_x" class="form-control-modern"
                                   placeholder="Ej: 720350">
                        </div>

                        <div class="form-group-modern">
                            <label class="form-label-modern">Coordenada UTM Y <small class="text-muted">(opcional)</small></label>
                            <input type="text" name="ferum_utm_y" class="form-control-modern"
                                   placeholder="Ej: 9820100">
                        </div>

                    </div>
                </div>

                <!-- Datos Técnicos -->
                <div class="field-group">
                    <div class="field-group-title">
                        <i class="bi bi-lightning-charge"></i> Datos Técnicos del Proyecto
                    </div>
                    <div class="form-grid">

                        <div class="form-group-modern">
                            <label class="form-label-modern required">N.° Estimado de Viviendas / Beneficiarios</label>
                            <input type="number" name="ferum_num_beneficiarios" class="form-control-modern"
                                   min="1" placeholder="Ej: 25">
                        </div>

                        <div class="form-group-modern">
                            <label class="form-label-modern">Potencia Requerida Estimada</label>
                            <input type="text" name="ferum_potencia_requerida" class="form-control-modern"
                                   placeholder="Ej: 50 kVA">
                        </div>

                        <div class="form-group-modern">
                            <label class="form-label-modern">Distancia Aproximada a la Red Existente</label>
                            <input type="text" name="ferum_distancia_red" class="form-control-modern"
                                   placeholder="Ej: 2.5 km">
                        </div>

                    </div>
                </div>

                <!-- Representantes de la Comunidad -->
                <div class="field-group">
                    <div class="field-group-title">
                        <i class="bi bi-people"></i> Representantes de la Comunidad
                    </div>

                    <p class="text-muted mb-3" style="font-size:.9rem;">
                        <i class="bi bi-info-circle me-1"></i>
                        Conforme al Anexo 2 del procedimiento PR-TEC-CTR-102, se requieren los datos
                        del Presidente y/o Coordinador de la comunidad.
                    </p>

                    <!-- Presidente -->
                    <div class="field-group-subtitle mb-2"><strong>Presidente</strong></div>
                    <div class="form-grid">

                        <div class="form-group-modern">
                            <label class="form-label-modern">Nombre Completo</label>
                            <input type="text" name="ferum_presidente_nombre" class="form-control-modern"
                                   placeholder="Nombre del presidente">
                        </div>

                        <div class="form-group-modern">
                            <label class="form-label-modern">Cédula</label>
                            <input type="text" name="ferum_presidente_cedula" class="form-control-modern"
                                   maxlength="10" inputmode="numeric" placeholder="Cédula">
                            <div class="invalid-feedback" id="cedula-presidente-feedback">Cédula ecuatoriana inválida</div>
                            <div class="valid-feedback">Cédula válida ✓</div>
                        </div>

                        <div class="form-group-modern">
                            <label class="form-label-modern">Celular</label>
                            <input type="text" name="ferum_presidente_celular" class="form-control-modern"
                                   maxlength="10" inputmode="numeric" placeholder="0999999999">
                            <div class="invalid-feedback" id="celular-presidente-feedback">Debe empezar en 09 y tener 10 dígitos</div>
                            <div class="valid-feedback">Celular válido ✓</div>
                        </div>

                    </div>

                    <!-- Coordinador -->
                    <div class="field-group-subtitle mb-2 mt-3"><strong>Coordinador</strong></div>
                    <div class="form-grid">

                        <div class="form-group-modern">
                            <label class="form-label-modern">Nombre Completo</label>
                            <input type="text" name="ferum_coordinador_nombre" class="form-control-modern"
                                   placeholder="Nombre del coordinador">
                        </div>

                        <div class="form-group-modern">
                            <label class="form-label-modern">Cédula</label>
                            <input type="text" name="ferum_coordinador_cedula" class="form-control-modern"
                                   maxlength="10" inputmode="numeric" placeholder="Cédula">
                            <div class="invalid-feedback" id="cedula-coordinador-feedback">Cédula ecuatoriana inválida</div>
                            <div class="valid-feedback">Cédula válida ✓</div>
                        </div>

                        <div class="form-group-modern">
                            <label class="form-label-modern">Celular</label>
                            <input type="text" name="ferum_coordinador_celular" class="form-control-modern"
                                   maxlength="10" inputmode="numeric" placeholder="0999999999">
                            <div class="invalid-feedback" id="celular-coordinador-feedback">Debe empezar en 09 y tener 10 dígitos</div>
                            <div class="valid-feedback">Celular válido ✓</div>
                        </div>

                    </div>

                    <div class="form-grid mt-3">
                        <div class="form-group-modern">
                            <label class="form-label-modern">Horario Preferible para Contactar</label>
                            <input type="text" name="ferum_horario_contacto" class="form-control-modern"
                                   placeholder="Ej: Lunes a viernes 8h00 - 17h00">
                        </div>
                    </div>

                </div>

                <!-- Documentos FERUM -->
                <div class="field-group">
                    <div class="field-group-title">
                        <i class="bi bi-folder2-open"></i> Documentos Requeridos (FERUM)
                    </div>

                    <p class="text-muted mb-3" style="font-size:.9rem;">
                        <i class="bi bi-info-circle me-1"></i>
                        Según el punto 6.1 del procedimiento PR-TEC-CTR-102. Formatos aceptados: PDF, imagen.
                    </p>

                    <div class="form-grid">

                        <!-- Croquis -->
                        <div class="form-group-modern">
                            <label class="form-label-modern required">Croquis de Ubicación</label>
                            <div class="file-input-modern">
                                <input type="file" name="ferum_archivo_croquis" id="croquisInput"
                                       accept=".pdf,.jpg,.jpeg,.png">
                                <label class="file-input-label file-input-sm" for="croquisInput">
                                    <span class="file-icon"><i class="bi bi-map"></i></span>
                                    <span class="file-text">Subir croquis</span>
                                    <span class="file-hint">PDF o imagen</span>
                                </label>
                                <p class="file-name" id="croquisName"></p>
                            </div>
                        </div>

                        <!-- Cert. GAD -->
                        <div class="form-group-modern">
                            <label class="form-label-modern required">
                                Certificación de Regularización del Sector
                                <small class="text-muted d-block">Otorgada por el GAD Municipal</small>
                            </label>
                            <div class="file-input-modern">
                                <input type="file" name="ferum_archivo_gad" id="gadInput"
                                       accept=".pdf,.jpg,.jpeg,.png">
                                <label class="file-input-label file-input-sm" for="gadInput">
                                    <span class="file-icon"><i class="bi bi-building-check"></i></span>
                                    <span class="file-text">Subir certificación GAD</span>
                                    <span class="file-hint">PDF o imagen</span>
                                </label>
                                <p class="file-name" id="gadName"></p>
                            </div>
                        </div>

                        <!-- Listado beneficiarios -->
                        <div class="form-group-modern">
                            <label class="form-label-modern required">Listado de Beneficiarios del Proyecto</label>
                            <div class="file-input-modern">
                                <input type="file" name="ferum_archivo_beneficiarios" id="beneficiariosInput"
                                       accept=".pdf,.xls,.xlsx,.jpg,.jpeg,.png">
                                <label class="file-input-label file-input-sm" for="beneficiariosInput">
                                    <span class="file-icon"><i class="bi bi-people-fill"></i></span>
                                    <span class="file-text">Subir listado de beneficiarios</span>
                                    <span class="file-hint">PDF, Excel o imagen</span>
                                </label>
                                <p class="file-name" id="beneficiariosName"></p>
                            </div>
                        </div>

                        <!-- Planilla servicio básico (opcional) -->
                        <div class="form-group-modern">
                            <label class="form-label-modern">
                                Planilla de Servicio Básico
                                <small class="text-muted d-block">De la vivienda más próxima al recinto (opcional)</small>
                            </label>
                            <div class="file-input-modern">
                                <input type="file" name="archivo" id="archivoInputFerum"
                                       accept=".pdf,.jpg,.jpeg,.png">
                                <label class="file-input-label file-input-sm" for="archivoInputFerum">
                                    <span class="file-icon"><i class="bi bi-receipt"></i></span>
                                    <span class="file-text">Subir planilla (opcional)</span>
                                    <span class="file-hint">PDF o imagen</span>
                                </label>
                                <p class="file-name" id="fileNameFerum"></p>
                            </div>
                        </div>

                    </div>

                    <!-- Observaciones FERUM -->
                    <div class="form-grid mt-2">
                        <div class="form-group-modern full-width">
                            <label class="form-label-modern">Observaciones / Información Adicional</label>
                            <textarea name="ferum_observaciones" class="form-control-modern textarea-modern"
                                      placeholder="Cualquier información adicional relevante para el proyecto FERUM..."></textarea>
                        </div>
                    </div>

                </div>

            </div><!-- /seccionFerum -->


            <!-- ── PLACEHOLDER cuando no se ha elegido tipo ── -->
            <div id="seccionPlaceholder" class="field-group text-center py-4">
                <i class="bi bi-arrow-up-circle" style="font-size:2rem;color:#adb5bd;"></i>
                <p class="text-muted mt-2">Seleccione el tipo de trámite para continuar</p>
            </div>

            <!-- ── BOTONES ── -->
            <div class="form-actions">
                <a href="dashboard.php" class="btn-modern btn-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Cancelar
                </a>
                <button type="submit" class="btn-modern btn-primary-modern">
                    <i class="bi bi-send-check me-2"></i>Registrar Trámite
                </button>
            </div>

        </form>
    </div>
</section>
</div>
</div>

<script>
/* ── Datos geográficos para cascada ── */
const datosGeograficos = {
    "Bolívar": {
        "Guaranda": ["Guaranda", "Facundo Vela", "Julio E Moreno", "Salinas", "San Lorenzo", "San Luis de Pambil", "San Simon", "Santa Fe", "Simiatug", "Angel Polibio Chavez", "Gabriel Ignacio Veintimilla", "Guanujo"],
        "Chillanes": ["Chillanes", "San José del Tambo"],
        "Chimbo": ["Chimbo", "Asunción", "Magdalena", "San Sebastian", "Telimbela"],
        "Echeandía": ["Echeandía"],
        "San Miguel": ["San Miguel", "Balsapamba", "Bilován", "Régulo de Mora", "San Pablo", "San Vicente", "Santiago"],
        "Caluma": ["Caluma"],
        "Las Naves": ["Las Naves", "Las Mercedes"]
    },
    "Los Ríos": {
        "Babahoyo": ["Babahoyo", "Clemente Baquerizo", "Dr. Camilo Ponce Enríquez", "Barreiro", "Caracol", "Febres Cordero", "La Unión", "Pimocha"],
        "Quevedo": ["Quevedo", "San Carlos", "La Esperanza"],
        "Ventanas": ["Ventanas", "Zapotal"],
        "Vinces": ["Vinces", "Antonio Sotomayor"],
        "Montalvo": ["Montalvo"],
        "Puebloviejo": ["Puebloviejo", "San Juan"],
        "Urdaneta": ["Catarama", "Ricaurte"],
        "Buena Fe": ["San Jacinto de Buena Fe", "Patricia Pilar"],
        "Mocache": ["Mocache"],
        "Palenque": ["Palenque"],
        "Quinsaloma": ["Quinsaloma"],
        "Valencia": ["Valencia"],
        "Baba": ["Baba", "Isla de Bejucal", "Guare"]
    },
    "Chimborazo": {
        "Riobamba": ["Lican", "Calpi", "Cubijíes", "Flores", "Licto", "Pungalá", "Punín", "Quimiag", "San Juan", "San Luis"],
        "Alausí": ["Alausí", "Achupallas", "Guasuntos", "Huigra", "Multitud", "Pistishí", "Sevilla", "Tixán"],
        "Chambo": ["Chambo"],
        "Chunchi": ["Chunchi", "Capzol", "Gonzol", "Llagos"],
        "Colta": ["Cajabamba", "Sicalpa", "Cañi", "Columbe", "Juan de Velasco", "Santiago de Quito"],
        "Cumandá": ["Cumandá"],
        "Guamote": ["Guamote", "Cebadas", "Palmira"],
        "Guano": ["Guano", "Guanando", "Ilapo", "La Providencia", "San Andrés", "San Isidro", "San José de Chazo", "Santa Fe de Galán", "Valparaíso"],
        "Pallatanga": ["Pallatanga"],
        "Penipe": ["Penipe", "El Altar", "Matus", "Puela", "San Antonio de Bayushig", "La Candelaria"]
    }
};

function actualizarCantones(provinciaId, cantonSelectId, parroquiaSelectId) {
    const provincia       = document.getElementById(provinciaId).value;
    const cantonSelect    = document.getElementById(cantonSelectId);
    const parroquiaSelect = document.getElementById(parroquiaSelectId);

    cantonSelect.innerHTML    = '<option value="">Seleccione un cantón</option>';
    parroquiaSelect.innerHTML = '<option value="">Seleccione primero un cantón</option>';
    parroquiaSelect.disabled  = true;

    if (provincia && datosGeograficos[provincia]) {
        cantonSelect.disabled = false;
        Object.keys(datosGeograficos[provincia]).forEach(canton => {
            const opt = document.createElement('option');
            opt.value = canton; opt.textContent = canton;
            cantonSelect.appendChild(opt);
        });
    } else {
        cantonSelect.disabled = true;
    }
}

function actualizarParroquias(provinciaId, cantonId, parroquiaSelectId) {
    const provincia       = document.getElementById(provinciaId).value;
    const canton          = document.getElementById(cantonId).value;
    const parroquiaSelect = document.getElementById(parroquiaSelectId);

    parroquiaSelect.innerHTML = '<option value="">Seleccione una parroquia</option>';

    if (provincia && canton && datosGeograficos[provincia]?.[canton]) {
        parroquiaSelect.disabled = false;
        datosGeograficos[provincia][canton].forEach(p => {
            const opt = document.createElement('option');
            opt.value = p; opt.textContent = p;
            parroquiaSelect.appendChild(opt);
        });
    } else {
        parroquiaSelect.disabled = true;
    }
}

/* ── Mostrar sección según tipo elegido ── */
const tipoSel        = document.getElementById('tipoTramite');
const secExt         = document.getElementById('seccionExtensionRed');
const secFer         = document.getElementById('seccionFerum');
const secPlaceholder = document.getElementById('seccionPlaceholder');

function toggleSecciones() {
    const val = tipoSel.value;
    secExt.style.display         = val === 'extension_red' ? 'block' : 'none';
    secFer.style.display         = val === 'ferum'         ? 'block' : 'none';
    secPlaceholder.style.display = val === ''              ? 'block' : 'none';
}
tipoSel.addEventListener('change', toggleSecciones);
toggleSecciones();

/* ── Cascada geográfica ── */
document.addEventListener('DOMContentLoaded', function () {

    /* Extensión de Red */
    const provinciaExt = document.getElementById('provincia_ext');
    const cantonExt    = document.getElementById('canton_ext');
    if (provinciaExt) {
        provinciaExt.addEventListener('change', () =>
            actualizarCantones('provincia_ext', 'canton_ext', 'parroquia_ext'));
    }
    if (cantonExt) {
        cantonExt.addEventListener('change', () =>
            actualizarParroquias('provincia_ext', 'canton_ext', 'parroquia_ext'));
    }

    /* FERUM */
    const provinciaFerum = document.getElementById('provincia_ferum');
    const cantonFerum    = document.getElementById('canton_ferum');
    if (provinciaFerum) {
        provinciaFerum.addEventListener('change', () =>
            actualizarCantones('provincia_ferum', 'canton_ferum', 'parroquia_ferum'));
    }
    if (cantonFerum) {
        cantonFerum.addEventListener('change', () =>
            actualizarParroquias('provincia_ferum', 'canton_ferum', 'parroquia_ferum'));
    }
});

/* ── Mostrar nombre(s) de archivo seleccionado ── */
function bindFileInput(inputId, labelId) {
    const input = document.getElementById(inputId);
    const label = document.getElementById(labelId);
    if (!input || !label) return;
    input.addEventListener('change', function () {
        if (!this.files.length) { label.textContent = ''; return; }
        label.textContent = Array.from(this.files).map(f => '📎 ' + f.name).join('  ');
    });
}
bindFileInput('archivoInput',       'fileName');
bindFileInput('archivoInputFerum',  'fileNameFerum');
bindFileInput('croquisInput',       'croquisName');
bindFileInput('gadInput',           'gadName');
bindFileInput('beneficiariosInput', 'beneficiariosName');

/* ── Drag & drop ── */
document.querySelectorAll('.file-input-label').forEach(dropLabel => {
    dropLabel.addEventListener('dragover',  e => { e.preventDefault(); dropLabel.classList.add('dragover'); });
    dropLabel.addEventListener('dragleave', () => dropLabel.classList.remove('dragover'));
    dropLabel.addEventListener('drop', e => {
        e.preventDefault();
        dropLabel.classList.remove('dragover');
        const inp = document.getElementById(dropLabel.getAttribute('for'));
        if (!inp) return;
        inp.files = e.dataTransfer.files;
        const nameEl = document.getElementById(
            inp.id.replace('Input', 'Name').replace('archivo', 'file')
        );
        if (nameEl) nameEl.textContent = Array.from(e.dataTransfer.files).map(f => '📎 ' + f.name).join('  ');
    });
});

/* ── Cargar servicios según encargado elegido ── */
const selectEncargado = document.querySelector('select[name="encargado_id"]');
const selectServicio  = document.getElementById('tipo_servicio');

async function cargarServicios(encargadoId) {
    selectServicio.innerHTML = '<option value="">Cargando...</option>';
    if (!encargadoId) {
        selectServicio.innerHTML = '<option value="">Seleccione un servicio</option>';
        return;
    }
    try {
        const res  = await fetch('ajax/get_servicios.php?encargado_id=' + encargadoId);
        const data = await res.json();
        selectServicio.innerHTML = '<option value="">Seleccione un servicio</option>';
        if (data.length === 0) {
            selectServicio.innerHTML += '<option value="" disabled>— Sin servicios asignados —</option>';
        } else {
            data.forEach(s => {
                selectServicio.innerHTML += `<option value="${s.nombre}">${s.nombre}</option>`;
            });
        }
    } catch (e) {
        selectServicio.innerHTML = '<option value="">Error al cargar servicios</option>';
    }
}

selectEncargado.addEventListener('change', function () { cargarServicios(this.value); });
if (selectEncargado.value) cargarServicios(selectEncargado.value);
</script>

<script src="js/servicios.js"></script>
<?php require_once 'includes/footer.php'; ?>