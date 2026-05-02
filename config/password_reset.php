<?php
declare(strict_types=1);

require_once __DIR__ . '/mailer.php';

if (!function_exists('password_reset_ensure_schema')) {
    function password_reset_ensure_schema(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS password_reset_codes (
                id INT(11) NOT NULL AUTO_INCREMENT,
                user_id INT(11) NOT NULL,
                email VARCHAR(150) NOT NULL,
                codigo_hash VARCHAR(255) NOT NULL,
                expires_at DATETIME NOT NULL,
                used_at DATETIME DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                attempt_count INT(11) NOT NULL DEFAULT 0,
                last_attempt_at DATETIME DEFAULT NULL,
                PRIMARY KEY (id),
                KEY idx_password_reset_codes_email (email),
                KEY idx_password_reset_codes_user (user_id),
                KEY idx_password_reset_codes_expires (expires_at),
                CONSTRAINT fk_password_reset_codes_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
    }
}

if (!function_exists('password_reset_generic_message')) {
    function password_reset_generic_message(): string
    {
        return 'Si el correo existe y la cuenta está activa, te enviamos un código de recuperación.';
    }
}

if (!function_exists('password_reset_normalize_email')) {
    function password_reset_normalize_email(string $email): string
    {
        return strtolower(trim($email));
    }
}

if (!function_exists('password_reset_find_active_user')) {
    function password_reset_find_active_user(PDO $pdo, string $email): ?array
    {
        $stmt = $pdo->prepare("
            SELECT id, name, email
            FROM users
            WHERE LOWER(email) = :email
              AND is_active = 1
            LIMIT 1
        ");
        $stmt->execute(['email' => password_reset_normalize_email($email)]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ?: null;
    }
}

if (!function_exists('password_reset_session_key')) {
    function password_reset_session_key(): string
    {
        return 'password_reset_verified';
    }
}

if (!function_exists('password_reset_clear_verified_session')) {
    function password_reset_clear_verified_session(): void
    {
        unset($_SESSION[password_reset_session_key()]);
    }
}

if (!function_exists('password_reset_store_verified_session')) {
    function password_reset_store_verified_session(int $userId, string $email): void
    {
        $_SESSION[password_reset_session_key()] = [
            'user_id' => $userId,
            'email' => password_reset_normalize_email($email),
            'verified_until' => time() + (15 * 60),
        ];
    }
}

if (!function_exists('password_reset_get_verified_session')) {
    function password_reset_get_verified_session(): ?array
    {
        $data = $_SESSION[password_reset_session_key()] ?? null;
        if (!is_array($data)) {
            return null;
        }

        $verifiedUntil = (int)($data['verified_until'] ?? 0);
        if ($verifiedUntil <= time()) {
            password_reset_clear_verified_session();
            return null;
        }

        return $data;
    }
}

if (!function_exists('password_reset_request')) {
    function password_reset_request(PDO $pdo, string $email): array
    {
        password_reset_ensure_schema($pdo);
        app_mailer_ensure_schema($pdo);
        password_reset_clear_verified_session();
        $settings = app_mailer_fetch_settings($pdo);
        app_mailer_validate_settings($settings);

        $normalizedEmail = password_reset_normalize_email($email);
        $generic = password_reset_generic_message();
        $user = password_reset_find_active_user($pdo, $normalizedEmail);
        if (!$user) {
            return ['message' => $generic, 'sent' => false];
        }

        $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $codeHash = password_hash($code, PASSWORD_DEFAULT);

        $systemName = trim((string)($settings['nombre_sistema'] ?? 'Sistema Residencial'));
        $subject = $systemName . ' · Código para restablecer contraseña';
        $body = implode("\n", [
            'Hola ' . trim((string)$user['name']) . ',',
            '',
            'Recibimos una solicitud para restablecer tu contraseña en ' . $systemName . '.',
            'Tu código temporal es: ' . $code,
            '',
            'Este código vence en 15 minutos y solo puede usarse una vez.',
            'Si pediste más de un código, usa siempre el más reciente.',
            'Si tú no solicitaste este cambio, puedes ignorar este correo.',
            '',
            $systemName,
        ]);

        app_mailer_send_with_settings($settings, (string)$user['email'], (string)$user['name'], $subject, $body);

        $pdo->beginTransaction();
        try {
            $pdo->prepare("
                UPDATE password_reset_codes
                SET used_at = NOW()
                WHERE user_id = :user_id
                  AND used_at IS NULL
            ")->execute(['user_id' => $user['id']]);

            $stmt = $pdo->prepare("
                INSERT INTO password_reset_codes (user_id, email, codigo_hash, expires_at)
                VALUES (:user_id, :email, :codigo_hash, DATE_ADD(NOW(), INTERVAL 15 MINUTE))
            ");
            $stmt->execute([
                'user_id' => $user['id'],
                'email' => $normalizedEmail,
                'codigo_hash' => $codeHash,
            ]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return ['message' => $generic, 'sent' => true];
    }
}

if (!function_exists('password_reset_verify')) {
    function password_reset_verify(PDO $pdo, string $email, string $code): array
    {
        password_reset_ensure_schema($pdo);
        password_reset_clear_verified_session();

        $normalizedEmail = password_reset_normalize_email($email);
        $stmt = $pdo->prepare("
            SELECT id, user_id, email, codigo_hash, expires_at, attempt_count
            FROM password_reset_codes
            WHERE email = :email
              AND used_at IS NULL
              AND expires_at >= NOW()
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute(['email' => $normalizedEmail]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            error_log('[password-reset-verify] no-active-code email=' . $normalizedEmail);
            return ['ok' => false, 'error' => 'El código es inválido o ya venció.'];
        }

        $attemptCount = (int)($row['attempt_count'] ?? 0);
        if ($attemptCount >= 5) {
            $pdo->prepare("UPDATE password_reset_codes SET used_at = NOW() WHERE id = :id")->execute(['id' => $row['id']]);
            error_log('[password-reset-verify] max-attempts email=' . $normalizedEmail . ' code_id=' . (int)$row['id']);
            return ['ok' => false, 'error' => 'El código es inválido o ya venció.'];
        }

        if (!password_verify($code, (string)$row['codigo_hash'])) {
            $nextAttempts = $attemptCount + 1;
            $pdo->prepare("
                UPDATE password_reset_codes
                SET attempt_count = :attempt_count,
                    last_attempt_at = NOW(),
                    used_at = CASE WHEN :attempt_count >= 5 THEN NOW() ELSE used_at END
                WHERE id = :id
            ")->execute([
                'attempt_count' => $nextAttempts,
                'id' => $row['id'],
            ]);
            error_log('[password-reset-verify] invalid-code email=' . $normalizedEmail . ' code_id=' . (int)$row['id'] . ' attempts=' . $nextAttempts);

            return ['ok' => false, 'error' => 'El código es inválido o ya venció.'];
        }

        $pdo->prepare("
            UPDATE password_reset_codes
            SET used_at = NOW(),
                last_attempt_at = NOW()
            WHERE id = :id
        ")->execute(['id' => $row['id']]);

        password_reset_store_verified_session((int)$row['user_id'], $normalizedEmail);
        error_log('[password-reset-verify] verified email=' . $normalizedEmail . ' code_id=' . (int)$row['id']);

        return ['ok' => true];
    }
}

if (!function_exists('password_reset_update_password')) {
    function password_reset_update_password(PDO $pdo, string $newPassword): array
    {
        password_reset_ensure_schema($pdo);
        $verified = password_reset_get_verified_session();
        if (!$verified) {
            return ['ok' => false, 'error' => 'Primero valida tu código de recuperación.'];
        }

        $stmt = $pdo->prepare("
            SELECT id, is_active
            FROM users
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => (int)$verified['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user || (int)$user['is_active'] !== 1) {
            password_reset_clear_verified_session();
            return ['ok' => false, 'error' => 'La cuenta ya no está disponible para restablecer la contraseña.'];
        }

        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password_hash = :password_hash WHERE id = :id")->execute([
            'password_hash' => $passwordHash,
            'id' => (int)$verified['user_id'],
        ]);

        $pdo->prepare("
            UPDATE password_reset_codes
            SET used_at = NOW()
            WHERE user_id = :user_id
              AND used_at IS NULL
        ")->execute(['user_id' => (int)$verified['user_id']]);

        password_reset_clear_verified_session();

        return ['ok' => true];
    }
}
