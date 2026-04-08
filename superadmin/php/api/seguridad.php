<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$action = sa_post_action('get');

try {
    if ($action === 'get') {
        $config = sa_fetch_config_seguridad($pdo);
        sa_json_out(true, [
            'data' => [
                'config' => $config,
                'csrf_token' => sa_csrf_token(),
            ],
        ]);
    }

    if ($action === 'save') {
        sa_require_csrf();
        $config = sa_fetch_config_seguridad($pdo);

        $maxIntentos = max(1, (int)($_POST['max_intentos_login'] ?? 5));
        $minBloqueo = max(0, (int)($_POST['minutos_bloqueo_login'] ?? 15));
        $minSesion = max(10, (int)($_POST['tiempo_sesion_minutos'] ?? 60));
        $logs = isset($_POST['registrar_logs_acceso']) && (string)$_POST['registrar_logs_acceso'] === '1' ? 1 : 0;

        $stmt = $pdo->prepare("
            UPDATE config_seguridad
            SET max_intentos_login = :max_intentos,
                minutos_bloqueo_login = :min_bloqueo,
                tiempo_sesion_minutos = :min_sesion,
                registrar_logs_acceso = :logs
            WHERE id = :id
        ");
        $stmt->execute([
            'max_intentos' => $maxIntentos,
            'min_bloqueo' => $minBloqueo,
            'min_sesion' => $minSesion,
            'logs' => $logs,
            'id' => $config['id'],
        ]);

        sa_json_out(true, ['message' => 'Configuración de seguridad actualizada correctamente.']);
    }

    sa_json_out(false, ['error' => 'Acción no soportada.'], 400);
} catch (Throwable $e) {
    sa_json_out(false, ['error' => 'Error: ' . $e->getMessage()], 500);
}
