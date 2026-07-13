<?php
// ============================================================
//  APEX GLASS - Helper de correo (PHPMailer + SMTP .env)
// ============================================================
require_once __DIR__ . '/../lib/phpmailer/PHPMailer.php';
require_once __DIR__ . '/../lib/phpmailer/SMTP.php';
require_once __DIR__ . '/../lib/phpmailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Envía correo de OC.
 * $oc: array con numero_oc, proveedor_nombre, total_con_iva, notas
 * $archivo_path: ruta absoluta al archivo adjunto (o null)
 * Retorna ['ok'=>true] o ['ok'=>false,'error'=>'...']
 */
function enviarCorreoOC($oc, $archivo_path = null) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = env('SMTP_HOST', 'mail.apex.glass');
        $mail->SMTPAuth   = true;
        $mail->Username   = env('SMTP_USER', '');
        $mail->Password   = env('SMTP_PASS', '');
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = (int)env('SMTP_PORT', 465);
        $mail->CharSet    = 'UTF-8';
        $mail->Timeout    = 15;

        $mail->setFrom(env('SMTP_FROM', 'compras@apex.glass'), env('SMTP_FROM_NAME', 'APEX GLASS'));
        $mail->addAddress(env('MAIL_PAGOS', ''));

        $cc = env('MAIL_PAGOS_CC', '');
        if ($cc) $mail->addCC($cc);

        foreach (explode(',', env('MAIL_REPLY_TO', '')) as $rt) {
            $rt = trim($rt);
            if ($rt) $mail->addReplyTo($rt);
        }

        if ($archivo_path && file_exists($archivo_path)) {
            $mail->addAttachment($archivo_path);
        }

        $total_fmt  = '$' . number_format((float)($oc['total_con_iva'] ?? 0), 2, '.', ',');
        $notas_html = !empty($oc['notas'])
            ? '<p style="margin:0;font-size:14px;color:#374151">' . htmlspecialchars($oc['notas']) . '</p>'
            : '<p style="margin:0;font-size:14px;color:#9ca3af"><em>Sin notas adicionales.</em></p>';
        $adj_html   = ($archivo_path && file_exists($archivo_path))
            ? '<p style="margin:20px 0 0;font-size:12px;color:#6b7280">Se adjunta cotizaci&oacute;n / factura de referencia.</p>'
            : '';

        $mail->isHTML(true);
        $mail->Subject = 'OC ' . $oc['numero_oc'] . ' | ' . $oc['proveedor_nombre'];
        $mail->Body    = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head>'
            . '<body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,sans-serif">'
            . '<table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:32px 0">'
            . '<tr><td>'
            . '<table width="560" align="center" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.08)">'
            . '<tr><td style="background:#1a1a1a;padding:20px 32px">'
            . '<span style="color:#ffffff;font-size:20px;font-weight:700;letter-spacing:1px">APEX GLASS</span>'
            . '<span style="color:#9ca3af;font-size:12px;display:block;margin-top:2px">Templadora Noreste, S.A. de C.V.</span>'
            . '</td></tr>'
            . '<tr><td style="padding:28px 32px">'
            . '<p style="margin:0 0 20px;font-size:14px;color:#6b7280">Se ha generado una nueva Orden de Compra:</p>'
            . '<table width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;border-radius:8px;padding:20px 24px">'
            . '<tr><td style="padding:6px 0;font-size:13px;color:#6b7280;width:40%">N&uacute;mero OC</td>'
            .     '<td style="padding:6px 0;font-size:13px;font-weight:700;color:#1a1a1a">' . htmlspecialchars($oc['numero_oc']) . '</td></tr>'
            . '<tr><td style="padding:6px 0;font-size:13px;color:#6b7280">Proveedor</td>'
            .     '<td style="padding:6px 0;font-size:13px;font-weight:600;color:#1a1a1a">' . htmlspecialchars($oc['proveedor_nombre']) . '</td></tr>'
            . '<tr><td style="padding:10px 0 0;font-size:13px;color:#6b7280">Total con IVA</td>'
            .     '<td style="padding:10px 0 0;font-size:22px;font-weight:700;color:#2563eb">' . $total_fmt . '</td></tr>'
            . '</table>'
            . '<div style="margin-top:20px">'
            . '<p style="margin:0 0 6px;font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.5px">Notas</p>'
            . $notas_html
            . '</div>'
            . $adj_html
            . '</td></tr>'
            . '<tr><td style="padding:16px 32px;background:#f8fafc;border-top:1px solid #e2e8f0">'
            . '<p style="margin:0;font-size:11px;color:#9ca3af">Correo generado autom&aacute;ticamente &bull; APEX GLASS &bull; compras@apex.glass</p>'
            . '</td></tr>'
            . '</table>'
            . '</td></tr></table>'
            . '</body></html>';
        $mail->AltBody = 'OC ' . $oc['numero_oc'] . ' | ' . $oc['proveedor_nombre'] . ' | Total: ' . $total_fmt . ($oc['notas'] ? ' | Notas: ' . $oc['notas'] : '');

        $mail->send();
        return ['ok' => true];
    } catch (Exception $e) {
        error_log('APEX Mailer OC: ' . $mail->ErrorInfo);
        return ['ok' => false, 'error' => $mail->ErrorInfo];
    }
}

/**
 * Envía el PDF/XML de una factura timbrada a uno o varios correos.
 * $fac: array con folio_interno, receptor_nombre, total
 * $destinatarios: array de correos (ya separados y validados por el caller)
 * $pdfBinario/$xmlBinario: contenido binario ya descargado de FacturAPI (o null si no se pudo obtener)
 * Retorna ['ok'=>true] o ['ok'=>false,'error'=>'...']
 */
function enviarCorreoFactura($fac, $destinatarios, $pdfBinario = null, $xmlBinario = null) {
    $destinatarios = array_values(array_filter($destinatarios));
    if (!$destinatarios) return ['ok' => false, 'error' => 'Sin destinatarios'];

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = env('SMTP_HOST', 'mail.apex.glass');
        $mail->SMTPAuth   = true;
        $mail->Username   = env('SMTP_USER', '');
        $mail->Password   = env('SMTP_PASS', '');
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = (int)env('SMTP_PORT', 465);
        $mail->CharSet    = 'UTF-8';
        $mail->Timeout    = 15;

        $mail->setFrom(env('SMTP_FROM', 'facturacion@apex.glass'), env('SMTP_FROM_NAME', 'APEX GLASS'));
        foreach ($destinatarios as $correo) {
            $mail->addAddress($correo);
        }

        if ($pdfBinario) $mail->addStringAttachment($pdfBinario, 'Factura_' . $fac['folio_interno'] . '.pdf', 'base64', 'application/pdf');
        if ($xmlBinario) $mail->addStringAttachment($xmlBinario, 'Factura_' . $fac['folio_interno'] . '.xml', 'base64', 'application/xml');

        $total_fmt = '$' . number_format((float)($fac['total'] ?? 0), 2, '.', ',');

        $mail->isHTML(true);
        $mail->Subject = 'Factura ' . $fac['folio_interno'] . ' | APEX GLASS';
        $mail->Body    = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head>'
            . '<body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,sans-serif">'
            . '<table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:32px 0">'
            . '<tr><td>'
            . '<table width="560" align="center" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.08)">'
            . '<tr><td style="background:#1a1a1a;padding:20px 32px">'
            . '<span style="color:#ffffff;font-size:20px;font-weight:700;letter-spacing:1px">APEX GLASS</span>'
            . '<span style="color:#9ca3af;font-size:12px;display:block;margin-top:2px">Templadora Noreste, S.A. de C.V.</span>'
            . '</td></tr>'
            . '<tr><td style="padding:28px 32px">'
            . '<p style="margin:0 0 20px;font-size:14px;color:#6b7280">Se ha timbrado tu factura:</p>'
            . '<table width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;border-radius:8px;padding:20px 24px">'
            . '<tr><td style="padding:6px 0;font-size:13px;color:#6b7280;width:40%">Folio</td>'
            .     '<td style="padding:6px 0;font-size:13px;font-weight:700;color:#1a1a1a">' . htmlspecialchars($fac['folio_interno']) . '</td></tr>'
            . '<tr><td style="padding:6px 0;font-size:13px;color:#6b7280">Receptor</td>'
            .     '<td style="padding:6px 0;font-size:13px;font-weight:600;color:#1a1a1a">' . htmlspecialchars($fac['receptor_nombre'] ?? '') . '</td></tr>'
            . '<tr><td style="padding:10px 0 0;font-size:13px;color:#6b7280">Total</td>'
            .     '<td style="padding:10px 0 0;font-size:22px;font-weight:700;color:#2563eb">' . $total_fmt . '</td></tr>'
            . '</table>'
            . '<p style="margin:20px 0 0;font-size:12px;color:#6b7280">Se adjunta el PDF y XML del CFDI.</p>'
            . '</td></tr>'
            . '<tr><td style="padding:16px 32px;background:#f8fafc;border-top:1px solid #e2e8f0">'
            . '<p style="margin:0;font-size:11px;color:#9ca3af">Correo generado autom&aacute;ticamente &bull; APEX GLASS &bull; facturacion@apex.glass</p>'
            . '</td></tr>'
            . '</table>'
            . '</td></tr></table>'
            . '</body></html>';
        $mail->AltBody = 'Factura ' . $fac['folio_interno'] . ' | Total: ' . $total_fmt;

        $mail->send();
        return ['ok' => true];
    } catch (Exception $e) {
        error_log('APEX Mailer Factura: ' . $mail->ErrorInfo);
        return ['ok' => false, 'error' => $mail->ErrorInfo];
    }
}
