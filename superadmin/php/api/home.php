<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

try {
    $config = sa_fetch_config_general($pdo);

    $summary = [
        'residenciales_total' => 0,
        'residenciales_activos' => 0,
        'nuevos_este_mes' => 0,
        'usuarios_total' => 0,
        'accesos_hoy' => 0,
        'incidencias_hoy' => 0,
    ];

    $summary['residenciales_total'] = (int)$pdo->query("SELECT COUNT(*) FROM residenciales")->fetchColumn();
    $summary['residenciales_activos'] = (int)$pdo->query("SELECT COUNT(*) FROM residenciales WHERE activo = 1")->fetchColumn();
    $summary['nuevos_este_mes'] = (int)$pdo->query("
        SELECT COUNT(*)
        FROM residenciales
        WHERE created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
    ")->fetchColumn();
    $summary['usuarios_total'] = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

    try {
        $summary['accesos_hoy'] = (int)$pdo->query("
            SELECT COUNT(*)
            FROM accesos_guardia
            WHERE DATE(fecha_hora) = CURDATE()
        ")->fetchColumn();
    } catch (Throwable $e) {
        $summary['accesos_hoy'] = 0;
    }

    try {
        $summary['incidencias_hoy'] = (int)$pdo->query("
            SELECT COUNT(*)
            FROM incidencias
            WHERE DATE(created_at) = CURDATE()
        ")->fetchColumn();
    } catch (Throwable $e) {
        $summary['incidencias_hoy'] = 0;
    }

    $recentResidenciales = $pdo->query("
        SELECT r.id, r.nombre, r.codigo, r.ciudad, r.estado, r.estatus_plan, p.nombre AS plan_nombre, r.created_at
        FROM residenciales r
        LEFT JOIN planes p ON p.id = r.plan_id
        ORDER BY r.created_at DESC
        LIMIT 4
    ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $activity = [];

    $lastResidential = $pdo->query("
        SELECT nombre, created_at
        FROM residenciales
        ORDER BY created_at DESC
        LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC);
    if ($lastResidential) {
        $activity[] = [
            'title' => 'Nuevo residencial',
            'text' => sprintf('%s fue registrado recientemente.', (string)$lastResidential['nombre']),
            'date' => $lastResidential['created_at'] ?? null,
        ];
    }

    $lastUsers = $pdo->query("
        SELECT u.name, t.nombre AS rol, u.created_at
        FROM users u
        JOIN tipos_usuario t ON t.id = u.tipo_usuario_id
        ORDER BY u.created_at DESC
        LIMIT 3
    ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($lastUsers as $row) {
        $activity[] = [
            'title' => 'Usuario creado',
            'text' => sprintf('%s fue dado de alta con rol %s.', (string)$row['name'], (string)$row['rol']),
            'date' => $row['created_at'] ?? null,
        ];
    }

    $tips = [
        [
            'title' => 'Mantén asignaciones ordenadas',
            'text' => 'Revisa que cada usuario tenga un residencial principal correcto para evitar inconsistencias operativas.',
        ],
        [
            'title' => 'Valida planes y estatus',
            'text' => 'Un estatus de plan desactualizado puede afectar la lectura operativa del sistema completo.',
        ],
        [
            'title' => 'Supervisa seguridad global',
            'text' => 'Las políticas de login y sesión impactan a todos los paneles del ecosistema.',
        ],
    ];

    sa_json_out(true, [
        'data' => [
            'system_name' => $config['nombre_sistema'] ?? 'Sistema Residencial',
            'summary' => $summary,
            'recent_residenciales' => $recentResidenciales,
            'activity' => $activity,
            'tips' => $tips,
        ],
    ]);
} catch (Throwable $e) {
    sa_json_out(false, ['error' => 'Error: ' . $e->getMessage()], 500);
}
