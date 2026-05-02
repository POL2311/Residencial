<?php
declare(strict_types=1);

require_once __DIR__ . '/app_security.php';

if (!function_exists('app_mailer_schema_columns')) {
    function app_mailer_schema_columns(): array
    {
        return [
            "ADD COLUMN smtp_host VARCHAR(190) DEFAULT NULL AFTER color_secundario",
            "ADD COLUMN smtp_port INT(11) DEFAULT NULL AFTER smtp_host",
            "ADD COLUMN smtp_username VARCHAR(190) DEFAULT NULL AFTER smtp_port",
            "ADD COLUMN smtp_password VARCHAR(255) DEFAULT NULL AFTER smtp_username",
            "ADD COLUMN smtp_encryption VARCHAR(20) DEFAULT NULL AFTER smtp_password",
            "ADD COLUMN smtp_from_email VARCHAR(190) DEFAULT NULL AFTER smtp_encryption",
            "ADD COLUMN smtp_from_name VARCHAR(190) DEFAULT NULL AFTER smtp_from_email",
        ];
    }
}

if (!function_exists('app_mailer_ensure_schema')) {
    function app_mailer_ensure_schema(PDO $pdo): void
    {
        $stmt = $pdo->prepare("
            SELECT COLUMN_NAME
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'config_general'
        ");
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        $required = [
            'smtp_host',
            'smtp_port',
            'smtp_username',
            'smtp_password',
            'smtp_encryption',
            'smtp_from_email',
            'smtp_from_name',
        ];

        foreach (app_mailer_schema_columns() as $index => $sql) {
            if (isset($required[$index]) && !in_array($required[$index], $columns, true)) {
                $pdo->exec("ALTER TABLE config_general {$sql}");
            }
        }
    }
}

if (!function_exists('app_mailer_fetch_config')) {
    function app_mailer_fetch_config(PDO $pdo): array
    {
        app_mailer_ensure_schema($pdo);
        $stmt = $pdo->query("SELECT * FROM config_general ORDER BY id ASC LIMIT 1");
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        return $config ?: [];
    }
}

if (!function_exists('app_mailer_validate_config')) {
    function app_mailer_validate_config(array $config): array
    {
        $host = trim((string)($config['smtp_host'] ?? ''));
        $port = (int)($config['smtp_port'] ?? 0);
        $username = trim((string)($config['smtp_username'] ?? ''));
        $password = (string)($config['smtp_password'] ?? '');
        $encryption = strtolower(trim((string)($config['smtp_encryption'] ?? 'tls')));
        $fromEmail = trim((string)($config['smtp_from_email'] ?? ''));
        $fromName = trim((string)($config['smtp_from_name'] ?? ($config['nombre_sistema'] ?? 'Sistema Residencial')));

        if ($host === '') {
            throw new RuntimeException('Falta configurar el host SMTP.');
        }
        if ($port <= 0) {
            throw new RuntimeException('Falta configurar el puerto SMTP.');
        }
        if ($fromEmail === '' || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Falta configurar un correo remitente válido.');
        }
        if (!in_array($encryption, ['tls', 'ssl', 'none', ''], true)) {
            throw new RuntimeException('El tipo de cifrado SMTP no es válido.');
        }

        return [
            'host' => $host,
            'port' => $port,
            'username' => $username,
            'password' => $password,
            'encryption' => $encryption === '' ? 'none' : $encryption,
            'from_email' => $fromEmail,
            'from_name' => $fromName !== '' ? $fromName : 'Sistema Residencial',
        ];
    }
}

if (!function_exists('app_mailer_read_response')) {
    function app_mailer_read_response($socket): string
    {
        $response = '';
        while (!feof($socket)) {
            $line = fgets($socket, 515);
            if ($line === false) {
                break;
            }
            $response .= $line;
            if (strlen($line) >= 4 && $line[3] === ' ') {
                break;
            }
        }
        return trim($response);
    }
}

if (!function_exists('app_mailer_expect')) {
    function app_mailer_expect($socket, array $codes): string
    {
        $response = app_mailer_read_response($socket);
        $code = (int)substr($response, 0, 3);
        if (!in_array($code, $codes, true)) {
            throw new RuntimeException('Respuesta SMTP inesperada: ' . $response);
        }
        return $response;
    }
}

if (!function_exists('app_mailer_write')) {
    function app_mailer_write($socket, string $command): void
    {
        fwrite($socket, $command . "\r\n");
    }
}

if (!function_exists('app_mailer_send_smtp')) {
    function app_mailer_send_smtp(array $smtp, string $toEmail, string $subject, string $bodyText, ?string $toName = null): void
    {
        $transport = $smtp['encryption'] === 'ssl' ? 'ssl://' : 'tcp://';
        $host = $smtp['host'];
        $port = (int)$smtp['port'];
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
        ]);

        $socket = @stream_socket_client(
            $transport . $host . ':' . $port,
            $errno,
            $errstr,
            20,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$socket) {
            throw new RuntimeException('No se pudo conectar al servidor SMTP: ' . $errstr);
        }

        stream_set_timeout($socket, 20);

        try {
            app_mailer_expect($socket, [220]);
            $ehlo = gethostname() ?: 'localhost';
            app_mailer_write($socket, 'EHLO ' . $ehlo);
            app_mailer_expect($socket, [250]);

            if ($smtp['encryption'] === 'tls') {
                app_mailer_write($socket, 'STARTTLS');
                app_mailer_expect($socket, [220]);
                $cryptoEnabled = stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                if ($cryptoEnabled !== true) {
                    throw new RuntimeException('No se pudo activar TLS para el envío SMTP.');
                }
                app_mailer_write($socket, 'EHLO ' . $ehlo);
                app_mailer_expect($socket, [250]);
            }

            if ($smtp['username'] !== '') {
                app_mailer_write($socket, 'AUTH LOGIN');
                app_mailer_expect($socket, [334]);
                app_mailer_write($socket, base64_encode($smtp['username']));
                app_mailer_expect($socket, [334]);
                app_mailer_write($socket, base64_encode((string)$smtp['password']));
                app_mailer_expect($socket, [235]);
            }

            app_mailer_write($socket, 'MAIL FROM:<' . $smtp['from_email'] . '>');
            app_mailer_expect($socket, [250]);
            app_mailer_write($socket, 'RCPT TO:<' . $toEmail . '>');
            app_mailer_expect($socket, [250, 251]);
            app_mailer_write($socket, 'DATA');
            app_mailer_expect($socket, [354]);

            $displayTo = $toName ? sprintf('"%s" <%s>', str_replace('"', '', $toName), $toEmail) : $toEmail;
            $displayFrom = sprintf('"%s" <%s>', str_replace('"', '', $smtp['from_name']), $smtp['from_email']);
            $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
            $normalizedBody = preg_replace("/\r\n|\r|\n/", "\r\n", trim($bodyText));
            $normalizedBody = preg_replace('/^\./m', '..', $normalizedBody);

            $headers = [
                'Date: ' . date(DATE_RFC2822),
                'To: ' . $displayTo,
                'From: ' . $displayFrom,
                'Subject: ' . $encodedSubject,
                'MIME-Version: 1.0',
                'Content-Type: text/plain; charset=UTF-8',
                'Content-Transfer-Encoding: 8bit',
            ];

            $message = implode("\r\n", $headers) . "\r\n\r\n" . $normalizedBody . "\r\n.";
            fwrite($socket, $message . "\r\n");
            app_mailer_expect($socket, [250]);

            app_mailer_write($socket, 'QUIT');
            app_mailer_expect($socket, [221]);
        } finally {
            fclose($socket);
        }
    }
}

if (!function_exists('app_mailer_send_text')) {
    function app_mailer_send_text(PDO $pdo, string $toEmail, string $subject, string $bodyText, ?string $toName = null): void
    {
        $config = app_mailer_fetch_config($pdo);
        $smtp = app_mailer_validate_config($config);
        app_mailer_send_smtp($smtp, $toEmail, $subject, $bodyText, $toName);
    }
}
