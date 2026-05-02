<?php
declare(strict_types=1);

if (!function_exists('app_mailer_table_has_column')) {
    function app_mailer_table_has_column(PDO $pdo, string $table, string $column): bool
    {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table_name
              AND COLUMN_NAME = :column_name
        ");
        $stmt->execute([
            'table_name' => $table,
            'column_name' => $column,
        ]);
        return (int)$stmt->fetchColumn() > 0;
    }
}

if (!function_exists('app_mailer_ensure_schema')) {
    function app_mailer_ensure_schema(PDO $pdo): void
    {
        $columns = [
            'smtp_host' => "ALTER TABLE config_general ADD COLUMN smtp_host VARCHAR(190) DEFAULT NULL AFTER color_secundario",
            'smtp_port' => "ALTER TABLE config_general ADD COLUMN smtp_port INT(11) DEFAULT NULL AFTER smtp_host",
            'smtp_username' => "ALTER TABLE config_general ADD COLUMN smtp_username VARCHAR(190) DEFAULT NULL AFTER smtp_port",
            'smtp_password' => "ALTER TABLE config_general ADD COLUMN smtp_password VARCHAR(255) DEFAULT NULL AFTER smtp_username",
            'smtp_encryption' => "ALTER TABLE config_general ADD COLUMN smtp_encryption ENUM('none','tls','ssl') NOT NULL DEFAULT 'tls' AFTER smtp_password",
            'smtp_from_email' => "ALTER TABLE config_general ADD COLUMN smtp_from_email VARCHAR(190) DEFAULT NULL AFTER smtp_encryption",
            'smtp_from_name' => "ALTER TABLE config_general ADD COLUMN smtp_from_name VARCHAR(190) DEFAULT NULL AFTER smtp_from_email",
        ];

        foreach ($columns as $column => $sql) {
            if (!app_mailer_table_has_column($pdo, 'config_general', $column)) {
                $pdo->exec($sql);
            }
        }
    }
}

if (!function_exists('app_mailer_encode_header')) {
    function app_mailer_encode_header(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (preg_match('/^[\x20-\x7E]+$/', $value) === 1) {
            return $value;
        }

        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }
}

if (!function_exists('app_mailer_normalize_settings')) {
    function app_mailer_normalize_settings(array $raw): array
    {
        $encryption = strtolower(trim((string)($raw['smtp_encryption'] ?? 'tls')));
        if (!in_array($encryption, ['none', 'tls', 'ssl'], true)) {
            $encryption = 'tls';
        }

        $port = (int)($raw['smtp_port'] ?? 0);
        if ($port <= 0) {
            $port = $encryption === 'ssl' ? 465 : ($encryption === 'tls' ? 587 : 25);
        }

        return [
            'nombre_sistema' => trim((string)($raw['nombre_sistema'] ?? 'Sistema Residencial')),
            'smtp_host' => trim((string)($raw['smtp_host'] ?? '')),
            'smtp_port' => $port,
            'smtp_username' => trim((string)($raw['smtp_username'] ?? '')),
            'smtp_password' => (string)($raw['smtp_password'] ?? ''),
            'smtp_encryption' => $encryption,
            'smtp_from_email' => trim((string)($raw['smtp_from_email'] ?? '')),
            'smtp_from_name' => trim((string)($raw['smtp_from_name'] ?? '')),
        ];
    }
}

if (!function_exists('app_mailer_fetch_settings')) {
    function app_mailer_fetch_settings(PDO $pdo): array
    {
        app_mailer_ensure_schema($pdo);

        $stmt = $pdo->query("SELECT * FROM config_general ORDER BY id ASC LIMIT 1");
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$config) {
            $pdo->exec("
                INSERT INTO config_general (nombre_sistema, empresa, email_soporte)
                VALUES ('Sistema Residencial', 'Tu Empresa', 'soporte@tuempresa.com')
            ");
            $stmt = $pdo->query("SELECT * FROM config_general ORDER BY id ASC LIMIT 1");
            $config = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        }

        return app_mailer_normalize_settings($config);
    }
}

if (!function_exists('app_mailer_socket_read')) {
    function app_mailer_socket_read($socket): string
    {
        $response = '';
        while (!feof($socket)) {
            $line = fgets($socket, 515);
            if ($line === false) {
                break;
            }
            $response .= $line;
            if (preg_match('/^\d{3}\s/', $line) === 1) {
                break;
            }
        }
        return $response;
    }
}

if (!function_exists('app_mailer_expect')) {
    function app_mailer_expect($socket, array $allowedCodes, string $context): string
    {
        $response = app_mailer_socket_read($socket);
        $code = (int)substr($response, 0, 3);
        if (!in_array($code, $allowedCodes, true)) {
            throw new RuntimeException($context . ': ' . trim($response));
        }
        return $response;
    }
}

if (!function_exists('app_mailer_write')) {
    function app_mailer_write($socket, string $command): void
    {
        $written = fwrite($socket, $command . "\r\n");
        if ($written === false) {
            throw new RuntimeException('No pudimos escribir en la conexión SMTP.');
        }
    }
}

if (!function_exists('app_mailer_send_with_settings')) {
    function app_mailer_send_with_settings(array $settings, string $toEmail, string $toName, string $subject, string $textBody): void
    {
        $settings = app_mailer_normalize_settings($settings);
        app_mailer_validate_settings($settings);
        $host = $settings['smtp_host'];
        $fromEmail = $settings['smtp_from_email'];

        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('El correo destino no es válido.');
        }

        $encryption = $settings['smtp_encryption'];
        $remoteHost = $encryption === 'ssl' ? 'ssl://' . $host : $host;
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
        ]);

        $socket = @stream_socket_client(
            $remoteHost . ':' . $settings['smtp_port'],
            $errno,
            $errstr,
            20,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$socket) {
            throw new RuntimeException('No pudimos conectar con el servidor SMTP: ' . $errstr);
        }

        try {
            stream_set_timeout($socket, 20);
            app_mailer_expect($socket, [220], 'Servidor SMTP no disponible');

            $clientHost = preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
            if ($clientHost === '') {
                $clientHost = 'localhost';
            }

            app_mailer_write($socket, 'EHLO ' . $clientHost);
            app_mailer_expect($socket, [250], 'No se pudo iniciar el saludo SMTP');

            if ($encryption === 'tls') {
                app_mailer_write($socket, 'STARTTLS');
                app_mailer_expect($socket, [220], 'El servidor SMTP rechazó STARTTLS');
                $cryptoOk = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                if ($cryptoOk !== true) {
                    throw new RuntimeException('No pudimos establecer el canal seguro TLS.');
                }
                app_mailer_write($socket, 'EHLO ' . $clientHost);
                app_mailer_expect($socket, [250], 'No se pudo reiniciar el saludo SMTP después de TLS');
            }

            if ($settings['smtp_username'] !== '') {
                app_mailer_write($socket, 'AUTH LOGIN');
                app_mailer_expect($socket, [334], 'El servidor SMTP rechazó la autenticación');
                app_mailer_write($socket, base64_encode($settings['smtp_username']));
                app_mailer_expect($socket, [334], 'El usuario SMTP no fue aceptado');
                app_mailer_write($socket, base64_encode($settings['smtp_password']));
                app_mailer_expect($socket, [235], 'La contraseña SMTP no fue aceptada');
            }

            app_mailer_write($socket, 'MAIL FROM:<' . $fromEmail . '>');
            app_mailer_expect($socket, [250], 'El remitente fue rechazado por el servidor SMTP');

            app_mailer_write($socket, 'RCPT TO:<' . $toEmail . '>');
            app_mailer_expect($socket, [250, 251], 'El destinatario fue rechazado por el servidor SMTP');

            app_mailer_write($socket, 'DATA');
            app_mailer_expect($socket, [354], 'El servidor SMTP no aceptó el contenido del mensaje');

            $fromName = $settings['smtp_from_name'] !== '' ? $settings['smtp_from_name'] : $settings['nombre_sistema'];
            $encodedSubject = app_mailer_encode_header($subject);
            $encodedFromName = app_mailer_encode_header($fromName);
            $encodedToName = app_mailer_encode_header($toName);

            $headers = [
                'Date: ' . date('r'),
                'From: ' . ($encodedFromName !== '' ? $encodedFromName . ' ' : '') . '<' . $fromEmail . '>',
                'To: ' . ($encodedToName !== '' ? $encodedToName . ' ' : '') . '<' . $toEmail . '>',
                'Subject: ' . $encodedSubject,
                'MIME-Version: 1.0',
                'Content-Type: text/plain; charset=UTF-8',
                'Content-Transfer-Encoding: 8bit',
            ];

            $body = preg_replace("/\r\n|\r|\n/", "\r\n", $textBody) ?? $textBody;
            $body = preg_replace('/^\./m', '..', $body) ?? $body;
            $payload = implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.";

            $written = fwrite($socket, $payload . "\r\n");
            if ($written === false) {
                throw new RuntimeException('No pudimos enviar el contenido del correo.');
            }

            app_mailer_expect($socket, [250], 'El servidor SMTP no aceptó el mensaje');
            app_mailer_write($socket, 'QUIT');
        } finally {
            fclose($socket);
        }
    }
}

if (!function_exists('app_mailer_send')) {
    function app_mailer_send(PDO $pdo, string $toEmail, string $toName, string $subject, string $textBody): void
    {
        $settings = app_mailer_fetch_settings($pdo);
        app_mailer_send_with_settings($settings, $toEmail, $toName, $subject, $textBody);
    }
}

if (!function_exists('app_mailer_validate_settings')) {
    function app_mailer_validate_settings(array $settings): void
    {
        $settings = app_mailer_normalize_settings($settings);
        if ($settings['smtp_host'] === '') {
            throw new RuntimeException('SMTP_MISSING_HOST');
        }
        if ($settings['smtp_from_email'] === '' || !filter_var($settings['smtp_from_email'], FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('SMTP_MISSING_FROM_EMAIL');
        }
    }
}
