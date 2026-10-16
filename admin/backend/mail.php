<?php
/**
 * Envio de correo - cvapp
 *
 * El mail.php que habia aqui era copia del de /precios y estaba roto de dos
 * formas:
 *   1) hacia require_once vendor/autoload.php y luego new PHPMailer(...).
 *      El vendor/autoload.php de cvapp tiene el classmap desactualizado y NO
 *      registra PHPMailer, aunque la libreria SI esta en
 *      vendor/phpmailer/phpmailer/src/. El `new` tiraba
 *      "Class PHPMailer\\PHPMailer\\PHPMailer not found".
 *   2) ese new estaba dentro de un catch (Exception). Una clase inexistente
 *      lanza Error, no Exception, asi que se escapaba del catch y mataba el
 *      endpoint entero con un 500 opaco.
 *
 * Ahora carga las clases por ruta directa y comprueba class_exists() antes de
 * instanciar, asi que nunca mas puede haber un fatal por esto.
 */

function cvapp_env($path) {
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $vars = [];
    if (is_file($path)) {
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linea) {
            $linea = trim($linea);
            if ($linea === '' || $linea[0] === '#' || strpos($linea, '=') === false) {
                continue;
            }
            list($k, $v) = explode('=', $linea, 2);
            $v = trim(trim($v));
            if (strlen($v) >= 2 && (($v[0] === '"' && substr($v, -1) === '"') || ($v[0] === "'" && substr($v, -1) === "'"))) {
                $v = substr($v, 1, -1);
            }
            $vars[trim($k)] = $v;
        }
    }
    $cache = $vars;
    return $vars;
}

/**
 * Registra PHPMailer sin depender del autoloader de composer.
 * Devuelve un mensaje de error o null si quedo lista.
 */
function cvapp_cargar_phpmailer() {
    if (class_exists('PHPMailer\PHPMailer\PHPMailer', false)) {
        return null;
    }

    // Camino 1: carga directa de las clases (no depende del classmap).
    $src = __DIR__ . '/../../vendor/phpmailer/phpmailer/src/';
    if (is_file($src . 'PHPMailer.php')) {
        require_once $src . 'Exception.php';
        require_once $src . 'SMTP.php';
        require_once $src . 'PHPMailer.php';
    }

    // Camino 2: el autoloader de composer, por si en algun momento lo arreglan.
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer', false)) {
        $autoload = __DIR__ . '/../../vendor/autoload.php';
        if (is_file($autoload)) {
            require_once $autoload;
        }
    }

    if (class_exists('PHPMailer\PHPMailer\PHPMailer', false)) {
        return null;
    }

    return 'PHPMailer no disponible (ni por ruta directa ni por autoload)';
}

function cvapp_enviar_smtp($para, $asunto, $cuerpo, $alterno = '') {
    $env = cvapp_env(__DIR__ . '/../../.env');

    $error = cvapp_cargar_phpmailer();
    if ($error !== null) {
        return ['success' => false, 'message' => $error];
    }

    $host = $env['SMTP_HOST'] ?? '';
    if ($host === '') {
        return ['success' => false, 'message' => 'SMTP no configurado en .env'];
    }

    $de = $env['MAIL_FROM'] ?? $env['SMTP_USER'] ?? 'no-reply@localhost';
    $nombreDe = $env['MAIL_FROM_NAME'] ?? 'Panel de CVs';

    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);

        $mail->isSMTP();
        $mail->Host = $host;
        $mail->Port = (int)($env['SMTP_PORT'] ?? 465);
        $mail->CharSet = 'UTF-8';
        $mail->SMTPAuth = true;
        $mail->Username = $env['SMTP_USER'] ?? '';
        $mail->Password = $env['SMTP_PASS'] ?? '';
        $mail->Timeout = 30;

        $seguro = strtolower(trim($env['SMTP_SECURE'] ?? ''));

        // En PHPMailer 6.12 las constantes ENCRYPTION_* viven en la clase
        // PHPMailer. En las 6.x anteriores estaban en SMTP. Referenciar la
        // constante equivocada tira "Undefined constant" y el correo nunca
        // sale. Por eso se resuelven por nombre con fallback al literal.
        $claseMail = 'PHPMailer\\PHPMailer\\PHPMailer';
        $smtps     = defined($claseMail . '::ENCRYPTION_SMTPS')     ? constant($claseMail . '::ENCRYPTION_SMTPS')     : 'ssl';
        $starttls  = defined($claseMail . '::ENCRYPTION_STARTTLS')  ? constant($claseMail . '::ENCRYPTION_STARTTLS')  : 'tls';

        if ($seguro === 'ssl' || $seguro === 'smtps') {
            $mail->SMTPSecure = $smtps;
        } elseif ($seguro === 'tls' || $seguro === 'starttls') {
            $mail->SMTPSecure = $starttls;
        } else {
            $mail->SMTPSecure = '';
        }

        $mail->setFrom($de, $nombreDe);
        $mail->addAddress($para);
        $mail->isHTML(false);
        $mail->Subject = $asunto;
        $mail->Body = $cuerpo;
        if ($alterno !== '') {
            $mail->AltBody = $alterno;
        }

        $mail->send();
        return ['success' => true, 'message' => 'Enviado'];
    } catch (Throwable $e) {
        // Throwable, no Exception: SMTP() lanza Exception pero el resto
        // puede tirar Error. Antes esos se escapaban y rompian el endpoint.
        return ['success' => false, 'message' => get_class($e) . ': ' . $e->getMessage()];
    }
}

function cvapp_enviar_mail_fallback($para, $asunto, $cuerpo) {
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $headers = "From: no-reply@{$host}\r\nContent-Type: text/plain; charset=utf-8";
    $ok = @mail($para, $asunto, $cuerpo, $headers);
    return $ok
        ? ['success' => true, 'message' => 'Enviado vía mail()']
        : ['success' => false, 'message' => 'mail() falló'];
}

/** Firma que espera el endpoint: send_mail($para, $asunto, $cuerpo, $alterno) */
function send_mail($para, $asunto, $cuerpo, $alterno = '') {
    $smtp = cvapp_enviar_smtp($para, $asunto, $cuerpo, $alterno);
    if ($smtp['success']) {
        return $smtp;
    }
    $fallback = cvapp_enviar_mail_fallback($para, $asunto, $cuerpo);
    if ($fallback['success']) {
        return $fallback;
    }
    return ['success' => false, 'message' => $smtp['message'] . ' | ' . $fallback['message']];
}