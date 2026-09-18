<?php
// Mensajería interna 1-a-1: cada persona (comercial/piso/etc.) tiene UN solo
// hilo con "desarrollo" (agrupado por otro_usuario_id, sin importar cuál
// usuario dev/dir_admin haya escrito). Nunca entre dos usuarios que no sean dev.
require_once 'config.php';
require_once 'permisos.php';

header('Content-Type: application/json');

$user = requireSessionApi();
$rol  = $user['rol'];
$pdo  = getDB();

$esDev = in_array($rol, ['desarrollo', 'dir_admin']);

// ── Imágenes adjuntas (tabla aparte: mensajes_internos_adjuntos) ─────────────
// Los archivos viven fuera de produccion/, en la carpeta hermana de
// archivos_ordenes/, y solo se sirven por accion=imagen — la carpeta lleva
// .htaccess "Deny from all", nunca se llega por URL directa. El tope real del
// servidor es upload_max_filesize=2M (php.d/zzz-apex.glass.ini), por eso el
// navegador reescala la foto antes de subirla.
const MSG_DIR_ADJUNTOS = __DIR__ . '/../../mensajes_adjuntos/';
const MSG_MIMES_OK     = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
const MSG_MAX_BYTES    = 2 * 1024 * 1024;

$accion = $_GET['accion'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// ── GET conversaciones (solo desarrollo/dir_admin): 1 fila por persona ───────
if ($method === 'GET' && $accion === 'conversaciones') {
    if (!$esDev) { jsonResponse(['ok'=>false,'error'=>'Sin permiso'], 403); exit; }

    $rows = $pdo->query("
        SELECT u.id AS usuario_id, u.nombre, u.rol,
               (SELECT IF(m2.mensaje <> '', m2.mensaje,
                          IF(EXISTS (SELECT 1 FROM mensajes_internos_adjuntos a
                                      WHERE a.mensaje_id = m2.id), 'Imagen', ''))
                  FROM mensajes_internos m2
                 WHERE m2.otro_usuario_id = u.id ORDER BY m2.id DESC LIMIT 1) AS ultimo_mensaje,
               (SELECT created_at FROM mensajes_internos m3
                 WHERE m3.otro_usuario_id = u.id ORDER BY m3.id DESC LIMIT 1) AS ultimo_at,
               (SELECT COUNT(*) FROM mensajes_internos m4
                 WHERE m4.otro_usuario_id = u.id AND m4.de_otro = 1 AND m4.leido_at IS NULL) AS no_leidos
        FROM usuarios u
        WHERE EXISTS (SELECT 1 FROM mensajes_internos m WHERE m.otro_usuario_id = u.id)
        ORDER BY ultimo_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    jsonResponse(['ok'=>true, 'conversaciones'=>$rows]);
    exit;
}

// ── GET hilo (dev: ?con=usuario_id · no-dev: su propio hilo) ─────────────────
if ($method === 'GET' && $accion === 'hilo') {
    $otroId = $esDev ? (int)($_GET['con'] ?? 0) : (int)$user['id'];
    if (!$otroId) { jsonResponse(['ok'=>false,'error'=>'Falta usuario']); exit; }

    $rows = $pdo->prepare("
        SELECT m.id, m.de_otro, m.autor_nombre, m.mensaje, m.created_at, m.leido_at,
               a.nombre AS adjunto_nombre, a.mime AS adjunto_mime,
               (a.id IS NOT NULL) AS tiene_imagen
        FROM mensajes_internos m
        LEFT JOIN mensajes_internos_adjuntos a ON a.mensaje_id = m.id
        WHERE m.otro_usuario_id = ?
        ORDER BY m.id ASC LIMIT 300
    ");
    $rows->execute([$otroId]);

    // Marcar como leído lo que le tocaba leer al que está consultando
    $deOtroAMarcar = $esDev ? 1 : 0;
    $upd = $pdo->prepare("
        UPDATE mensajes_internos SET leido_at = NOW()
        WHERE otro_usuario_id = ? AND de_otro = ? AND leido_at IS NULL
    ");
    $upd->execute([$otroId, $deOtroAMarcar]);

    jsonResponse(['ok'=>true, 'mensajes'=>$rows->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

// ── GET sin_leer (contador para badge del ícono) ──────────────────────────────
if ($method === 'GET' && $accion === 'sin_leer') {
    if ($esDev) {
        $total = (int)$pdo->query("
            SELECT COUNT(*) FROM mensajes_internos WHERE de_otro = 1 AND leido_at IS NULL
        ")->fetchColumn();
    } else {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM mensajes_internos
            WHERE otro_usuario_id = ? AND de_otro = 0 AND leido_at IS NULL
        ");
        $stmt->execute([$user['id']]);
        $total = (int)$stmt->fetchColumn();
    }
    jsonResponse(['ok'=>true, 'total'=>$total]);
    exit;
}

// ── POST enviar (texto, imagen, o ambos) ───────────────────────────────────
if ($method === 'POST' && $accion === 'enviar') {
    // multipart cuando trae imagen; JSON cuando es solo texto (como siempre)
    $esMultipart = str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/form-data');

    // Si el POST rebasó post_max_size, PHP llega hasta aquí con todo vacío
    if ($esMultipart && !$_POST && !$_FILES) {
        jsonResponse(['ok'=>false,'error'=>'La imagen es demasiado grande para el servidor']); exit;
    }

    if ($esMultipart) {
        $mensaje = trim($_POST['mensaje'] ?? '');
        $paraId  = (int)($_POST['para'] ?? 0);
    } else {
        $d       = json_decode(file_get_contents('php://input'), true) ?? [];
        $mensaje = trim($d['mensaje'] ?? '');
        $paraId  = (int)($d['para'] ?? 0);
    }

    $traeImagen = isset($_FILES['imagen']) && is_array($_FILES['imagen'])
                  && ($_FILES['imagen']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

    if (!$mensaje && !$traeImagen) { jsonResponse(['ok'=>false,'error'=>'Escribe un mensaje o adjunta una imagen']); exit; }
    if (mb_strlen($mensaje) > 2000) { jsonResponse(['ok'=>false,'error'=>'Mensaje muy largo']); exit; }

    if ($esDev) {
        $otroId = $paraId;
        if (!$otroId) { jsonResponse(['ok'=>false,'error'=>'Falta destinatario']); exit; }
        // El destinatario nunca puede ser otro usuario dev/dir_admin (regla: solo persona<->yo)
        $chk = $pdo->prepare("SELECT rol FROM usuarios WHERE id = ? AND activo = 1");
        $chk->execute([$otroId]);
        $rolOtro = $chk->fetchColumn();
        if (!$rolOtro || in_array($rolOtro, ['desarrollo', 'dir_admin'])) {
            jsonResponse(['ok'=>false,'error'=>'Destinatario inválido']); exit;
        }
        $deOtro = 0;
    } else {
        $otroId = (int)$user['id'];
        $deOtro = 1;
    }

    // ── Validar y guardar la imagen en disco (antes de tocar la BD) ──────────
    $adjRuta = null; $adjArchivo = null; $adjNombre = null; $adjMime = null; $adjBytes = null;
    if ($traeImagen) {
        $img = $_FILES['imagen'];
        if ($img['error'] !== UPLOAD_ERR_OK) {
            $err = in_array($img['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE])
                ? 'La imagen supera el límite de 2 MB del servidor'
                : 'No se pudo recibir la imagen';
            jsonResponse(['ok'=>false,'error'=>$err]); exit;
        }
        if ($img['size'] > MSG_MAX_BYTES) {
            jsonResponse(['ok'=>false,'error'=>'La imagen supera el límite de 2 MB']); exit;
        }
        // Manda el MIME real del archivo, no la extensión ni lo que diga el navegador
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($img['tmp_name']);
        if (!isset(MSG_MIMES_OK[$mime]) || !@getimagesize($img['tmp_name'])) {
            jsonResponse(['ok'=>false,'error'=>'Solo se aceptan imágenes JPG, PNG o WEBP']); exit;
        }

        if (!is_dir(MSG_DIR_ADJUNTOS)) {
            if (!@mkdir(MSG_DIR_ADJUNTOS, 0750, true)) {
                jsonResponse(['ok'=>false,'error'=>'No se pudo preparar la carpeta de imágenes']); exit;
            }
            file_put_contents(MSG_DIR_ADJUNTOS . '.htaccess', "Order deny,allow\nDeny from all\nRequire all denied\n");
        }

        $adjArchivo = 'msg_' . $otroId . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4))
                      . '.' . MSG_MIMES_OK[$mime];
        $adjRuta    = MSG_DIR_ADJUNTOS . $adjArchivo;
        if (!move_uploaded_file($img['tmp_name'], $adjRuta)) {
            jsonResponse(['ok'=>false,'error'=>'No se pudo guardar la imagen']); exit;
        }
        // El nombre lo pone quien sube: se guarda ya sin caracteres que puedan
        // romper un atributo HTML al pintarlo de vuelta en el chat
        $adjNombre = mb_substr(preg_replace('/[^\w .()-]/u', '_', $img['name'] ?: 'imagen'), 0, 120);
        $adjMime   = $mime;
        $adjBytes  = (int)$img['size'];
    }

    // ── Mensaje + adjunto se escriben juntos, o no se escribe ninguno ───────
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("
            INSERT INTO mensajes_internos (otro_usuario_id, de_otro, autor_usuario_id, autor_nombre, mensaje)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$otroId, $deOtro, $user['id'], $user['nombre'], $mensaje]);

        if ($adjArchivo) {
            $msgId = (int)$pdo->lastInsertId();
            $pdo->prepare("
                INSERT INTO mensajes_internos_adjuntos (mensaje_id, archivo, nombre, mime, bytes)
                VALUES (?, ?, ?, ?, ?)
            ")->execute([$msgId, $adjArchivo, $adjNombre, $adjMime, $adjBytes]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        // Sin fila en la BD, la imagen quedaría huérfana en disco
        if ($adjRuta && is_file($adjRuta)) @unlink($adjRuta);
        error_log('[mensajes] no se pudo guardar el mensaje: ' . $e->getMessage());
        jsonResponse(['ok'=>false,'error'=>'No se pudo guardar el mensaje']); exit;
    }

    // Si el mensaje va sin texto, el aviso se anuncia como imagen
    $preview = $mensaje !== '' ? mb_substr($mensaje, 0, 100) : 'Envió una imagen';

    // Notificación cruzada vía el sistema de notificaciones ya existente
    if ($deOtro === 0) {
        $stmtN = $pdo->prepare("
            INSERT INTO notificaciones (tipo, titulo, mensaje, usuario_id_dest, usuario_id_orig, usuario_nombre)
            VALUES ('mensaje_interno', 'Nuevo mensaje de Desarrollo', ?, ?, ?, ?)
        ");
        $stmtN->execute([$preview, $otroId, $user['id'], $user['nombre']]);
    } else {
        $devs = $pdo->query("SELECT id FROM usuarios WHERE rol IN ('desarrollo','dir_admin') AND activo=1")->fetchAll(PDO::FETCH_ASSOC);
        $stmtN = $pdo->prepare("
            INSERT INTO notificaciones (tipo, titulo, mensaje, usuario_id_dest, usuario_id_orig, usuario_nombre)
            VALUES ('mensaje_interno', ?, ?, ?, ?, ?)
        ");
        foreach ($devs as $dev) {
            $stmtN->execute(['Nuevo mensaje de ' . $user['nombre'], $preview, $dev['id'], $user['id'], $user['nombre']]);
        }
    }

    jsonResponse(['ok'=>true]);
    exit;
}

// ── GET imagen (única vía de acceso al archivo; la carpeta está cerrada) ────
if ($method === 'GET' && $accion === 'imagen') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) { jsonResponse(['ok'=>false,'error'=>'Falta id'], 400); exit; }

    $stmt = $pdo->prepare("
        SELECT m.otro_usuario_id, a.archivo, a.nombre, a.mime
        FROM mensajes_internos_adjuntos a
        JOIN mensajes_internos m ON m.id = a.mensaje_id
        WHERE a.mensaje_id = ?
    ");
    $stmt->execute([$id]);
    $adj = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$adj) { jsonResponse(['ok'=>false,'error'=>'Imagen no encontrada'], 404); exit; }

    // Quien no es dev solo puede ver las imágenes de su propio hilo
    if (!$esDev && (int)$adj['otro_usuario_id'] !== (int)$user['id']) {
        jsonResponse(['ok'=>false,'error'=>'Sin permiso'], 403); exit;
    }

    $ruta = MSG_DIR_ADJUNTOS . basename($adj['archivo']);
    if (!is_file($ruta)) { jsonResponse(['ok'=>false,'error'=>'La imagen ya no está en el servidor'], 404); exit; }

    // El MIME guardado nunca se devuelve sin validarlo contra la lista blanca
    $mime   = isset(MSG_MIMES_OK[$adj['mime']]) ? $adj['mime'] : 'application/octet-stream';
    $nombre = preg_replace('/[^A-Za-z0-9._-]/', '_', (string)($adj['nombre'] ?: 'imagen'));

    header('Content-Type: ' . $mime);
    header('X-Content-Type-Options: nosniff');
    header('Content-Disposition: inline; filename="' . $nombre . '"');
    header('Content-Length: ' . filesize($ruta));
    header('Cache-Control: private, max-age=86400');
    readfile($ruta);
    exit;
}

// ── GET resolver_usuario (dev: busca el usuario_id de un reporte por nombre) ─
if ($method === 'GET' && $accion === 'resolver_usuario') {
    if (!$esDev) { jsonResponse(['ok'=>false,'error'=>'Sin permiso'], 403); exit; }
    $nombre = trim($_GET['nombre'] ?? '');
    if (!$nombre) { jsonResponse(['ok'=>false,'error'=>'Falta nombre']); exit; }
    $stmt = $pdo->prepare("SELECT id, nombre, rol FROM usuarios WHERE nombre = ? AND activo = 1 LIMIT 1");
    $stmt->execute([$nombre]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$u || in_array($u['rol'], ['desarrollo', 'dir_admin'])) {
        jsonResponse(['ok'=>false,'error'=>'Usuario no encontrado']); exit;
    }
    jsonResponse(['ok'=>true, 'usuario'=>$u]);
    exit;
}

jsonResponse(['ok'=>false,'error'=>'Acción no válida'], 400);
