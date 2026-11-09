<?php
declare(strict_types=1);

/**
 * NEVERMIND - Procesador de formulario de contacto
 * Recibe POST desde el modal y envía el mensaje a ambos destinatarios.
 * Requiere PHP con la función mail() habilitada y configurada en el hosting.
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate');

function respond(int $status, array $body): void {
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    respond(405, ['success' => false, 'message' => 'Método no permitido.']);
}

// Honeypot anti-spam: los bots suelen completar este campo oculto.
if (!empty($_POST['website'] ?? '')) {
    respond(200, ['success' => true, 'message' => 'Mensaje recibido.']);
}

function field(string $key, int $maxLength = 2000): string {
    $value = $_POST[$key] ?? '';
    if (!is_string($value)) return '';
    $value = trim(str_replace(["\r", "\0"], '', $value));
    return mb_substr($value, 0, $maxLength, 'UTF-8');
}

$nombre   = field('nombre', 120);
$empresa  = field('empresa', 160);
$whatsapp = field('whatsapp', 60);
$interes  = field('interes', 180);
$mensaje  = field('mensaje', 4000);

$serviciosRaw = $_POST['servicios'] ?? [];
if (!is_array($serviciosRaw)) $serviciosRaw = [];
$servicios = [];
foreach ($serviciosRaw as $item) {
    if (is_string($item)) {
        $item = trim(str_replace(["\r", "\n", "\0"], '', $item));
        if ($item !== '') $servicios[] = mb_substr($item, 0, 120, 'UTF-8');
    }
}
$servicios = array_values(array_unique(array_slice($servicios, 0, 12)));

if ($nombre === '' || $empresa === '' || $interes === '') {
    respond(422, ['success' => false, 'message' => 'Completa los campos obligatorios: nombre, empresa e interés.']);
}

if (mb_strlen($nombre, 'UTF-8') < 2 || mb_strlen($empresa, 'UTF-8') < 2) {
    respond(422, ['success' => false, 'message' => 'Verifica el nombre y la empresa.']);
}

$recipients = [
    'olver.x@gmail.com',
    'lpdiseno@gmail.com'
];

$subjectText = 'Nuevo contacto web NEVERMIND - ' . $nombre;
$subject = '=?UTF-8?B?' . base64_encode($subjectText) . '?=';

$bodyLines = [
    'NUEVO CONTACTO DESDE EL SITIO WEB NEVERMIND',
    '',
    'Nombre: ' . $nombre,
    'Empresa: ' . $empresa,
    'WhatsApp: ' . ($whatsapp !== '' ? $whatsapp : 'No indicado'),
    'Motivo de contacto: ' . $interes,
    'Servicios de interés: ' . ($servicios ? implode(', ', $servicios) : 'No seleccionados'),
    '',
    'Mensaje:',
    $mensaje !== '' ? $mensaje : 'Sin mensaje adicional',
    '',
    'Enviado desde el formulario de contacto de NEVERMIND.'
];
$body = implode("\r\n", $bodyLines);

// From debe pertenecer al dominio del sitio para mejorar la entregabilidad.
// Ajustar este remitente al crear/publicar el dominio real.
$host = preg_replace('/[^a-z0-9.-]/i', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
$host = preg_replace('/^www\./i', '', $host);
$fromEmail = 'webform@' . ($host ?: 'localhost');
$fromName = 'NEVERMIND Web';

$headers = [
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'From: ' . $fromName . ' <' . $fromEmail . '>',
    'Reply-To: ' . $fromEmail,
    'X-Mailer: PHP/' . PHP_VERSION
];

$sent = true;
foreach ($recipients as $recipient) {
    // Envío individual: cada destinatario recibe su propio correo.
    $ok = mail($recipient, $subject, $body, implode("\r\n", $headers));
    if (!$ok) $sent = false;
}

if (!$sent) {
    error_log('NEVERMIND contacto.php: mail() falló para uno o más destinatarios.');
    respond(500, [
        'success' => false,
        'message' => 'No pudimos enviar el mensaje en este momento. Inténtalo más tarde o contáctanos por correo.'
    ]);
}

respond(200, ['success' => true, 'message' => 'Mensaje enviado correctamente.']);
