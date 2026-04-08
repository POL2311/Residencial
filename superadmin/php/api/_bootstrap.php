<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../../../config/auth.php';
require_once __DIR__ . '/../../../config/config.php';

require_login();
require_role(['super_admin']);

if (!function_exists('sa_json_out')) {
    function sa_json_out(bool $ok, array $extra = [], int $status = 200): void
    {
        http_response_code($status);
        echo json_encode(array_merge(['ok' => $ok], $extra), JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (!function_exists('sa_clean_str')) {
    function sa_clean_str($value, int $max = 255): string
    {
        $value = trim((string)($value ?? ''));
        $value = preg_replace('/\s+/', ' ', $value);
        if (mb_strlen($value) > $max) {
            $value = mb_substr($value, 0, $max);
        }
        return $value;
    }
}

if (!function_exists('sa_post_action')) {
    function sa_post_action(string $default = 'get'): string
    {
        return (string)($_POST['action'] ?? $_GET['action'] ?? $default);
    }
}

if (!function_exists('sa_current_user')) {
    function sa_current_user(PDO $pdo): array
    {
        $uid = (int)(current_user()['id'] ?? 0);
        $stmt = $pdo->prepare("SELECT id, name, email, telefono FROM users WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $uid]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            sa_json_out(false, ['error' => 'Usuario no encontrado.'], 404);
        }
        return $user;
    }
}

if (!function_exists('sa_csrf_token')) {
    function sa_csrf_token(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
        }
        return (string)$_SESSION['csrf_token'];
    }
}

if (!function_exists('sa_require_csrf')) {
    function sa_require_csrf(): void
    {
        $sent = (string)($_POST['csrf_token'] ?? '');
        $expected = sa_csrf_token();
        if ($sent === '' || !hash_equals($expected, $sent)) {
            sa_json_out(false, ['error' => 'Sesión inválida. Recarga la página e inténtalo de nuevo.'], 419);
        }
    }
}

if (!function_exists('sa_table_exists')) {
    function sa_table_exists(PDO $pdo, string $table): bool
    {
        try {
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table
            ");
            $stmt->execute(['table' => $table]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('sa_fetch_config_general')) {
    function sa_fetch_config_general(PDO $pdo): array
    {
        $stmt = $pdo->query("SELECT * FROM config_general ORDER BY id ASC LIMIT 1");
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($config) {
            return $config;
        }

        $pdo->exec("
            INSERT INTO config_general (nombre_sistema, empresa, email_soporte)
            VALUES ('Sistema Residencial', 'Tu Empresa', 'soporte@tuempresa.com')
        ");
        $stmt = $pdo->query("SELECT * FROM config_general ORDER BY id ASC LIMIT 1");
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }
}

if (!function_exists('sa_fetch_config_seguridad')) {
    function sa_fetch_config_seguridad(PDO $pdo): array
    {
        $stmt = $pdo->query("SELECT * FROM config_seguridad ORDER BY id ASC LIMIT 1");
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($config) {
            return $config;
        }

        $pdo->exec("
            INSERT INTO config_seguridad (max_intentos_login, minutos_bloqueo_login, tiempo_sesion_minutos, registrar_logs_acceso)
            VALUES (5, 15, 60, 1)
        ");
        $stmt = $pdo->query("SELECT * FROM config_seguridad ORDER BY id ASC LIMIT 1");
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }
}
