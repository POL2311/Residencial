<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

function report_period_config(string $raw): array
{
    $value = strtolower(trim($raw));
    if ($value === 'all') {
        return [
            'value' => 'all',
            'label' => 'Histórico completo',
            'days' => null,
            'since' => null,
            'compare_since' => null,
            'compare_until' => null,
            'bucket' => 'month',
        ];
    }

    $days = in_array($value, ['7', '30', '90'], true) ? (int)$value : 30;
    $today = new DateTimeImmutable('today');
    $since = $today->modify('-' . ($days - 1) . ' days')->setTime(0, 0, 0);
    $compareUntil = $since->modify('-1 second');
    $compareSince = $since->modify('-' . $days . ' days');

    return [
        'value' => (string)$days,
        'label' => "Últimos {$days} días",
        'days' => $days,
        'since' => $since,
        'compare_since' => $compareSince,
        'compare_until' => $compareUntil,
        'bucket' => $days <= 30 ? 'day' : 'month',
    ];
}

function report_change_percent(int $current, int $previous): ?float
{
    if ($previous === 0) {
        return $current > 0 ? 100.0 : 0.0;
    }
    return round((($current - $previous) / $previous) * 100, 1);
}

function report_format_change(?float $value): string
{
    if ($value === null) return 'Sin comparación disponible';
    if ($value > 0) return '+' . $value . '% vs periodo previo';
    if ($value < 0) return $value . '% vs periodo previo';
    return 'Sin cambio vs periodo previo';
}

function report_build_series(PDO $pdo, array $period): array
{
    $bucket = $period['bucket'];
    $labels = [];
    $residencialesMap = [];
    $usuariosMap = [];

    if ($bucket === 'day') {
        $start = $period['since'];
        $end = new DateTimeImmutable('today');
        for ($cursor = $start; $cursor <= $end; $cursor = $cursor->modify('+1 day')) {
            $key = $cursor->format('Y-m-d');
            $labels[$key] = $cursor->format('d M');
            $residencialesMap[$key] = 0;
            $usuariosMap[$key] = 0;
        }

        $stmt = $pdo->prepare("
            SELECT DATE(created_at) AS bucket_key, COUNT(*) AS total
            FROM residenciales
            WHERE created_at >= :since
            GROUP BY DATE(created_at)
            ORDER BY bucket_key ASC
        ");
        $stmt->execute(['since' => $start->format('Y-m-d H:i:s')]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $key = (string)($row['bucket_key'] ?? '');
            if ($key !== '' && array_key_exists($key, $residencialesMap)) {
                $residencialesMap[$key] = (int)$row['total'];
            }
        }

        $stmt = $pdo->prepare("
            SELECT DATE(created_at) AS bucket_key, COUNT(*) AS total
            FROM users
            WHERE created_at >= :since
            GROUP BY DATE(created_at)
            ORDER BY bucket_key ASC
        ");
        $stmt->execute(['since' => $start->format('Y-m-d H:i:s')]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $key = (string)($row['bucket_key'] ?? '');
            if ($key !== '' && array_key_exists($key, $usuariosMap)) {
                $usuariosMap[$key] = (int)$row['total'];
            }
        }
    } else {
        $start = $period['since'] instanceof DateTimeImmutable
            ? $period['since']->modify('first day of this month')
            : (new DateTimeImmutable('first day of this month'))->modify('-11 months');
        $end = new DateTimeImmutable('first day of this month');

        for ($cursor = $start; $cursor <= $end; $cursor = $cursor->modify('+1 month')) {
            $key = $cursor->format('Y-m');
            $labels[$key] = $cursor->format('M Y');
            $residencialesMap[$key] = 0;
            $usuariosMap[$key] = 0;
        }

        $stmt = $pdo->prepare("
            SELECT DATE_FORMAT(created_at, '%Y-%m') AS bucket_key, COUNT(*) AS total
            FROM residenciales
            WHERE created_at >= :since
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY bucket_key ASC
        ");
        $stmt->execute(['since' => $start->format('Y-m-d H:i:s')]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $key = (string)($row['bucket_key'] ?? '');
            if ($key !== '' && array_key_exists($key, $residencialesMap)) {
                $residencialesMap[$key] = (int)$row['total'];
            }
        }

        $stmt = $pdo->prepare("
            SELECT DATE_FORMAT(created_at, '%Y-%m') AS bucket_key, COUNT(*) AS total
            FROM users
            WHERE created_at >= :since
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY bucket_key ASC
        ");
        $stmt->execute(['since' => $start->format('Y-m-d H:i:s')]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $key = (string)($row['bucket_key'] ?? '');
            if ($key !== '' && array_key_exists($key, $usuariosMap)) {
                $usuariosMap[$key] = (int)$row['total'];
            }
        }
    }

    return [
        'labels' => array_values($labels),
        'residenciales' => array_values($residencialesMap),
        'usuarios' => array_values($usuariosMap),
    ];
}

try {
    $period = report_period_config((string)($_GET['period'] ?? $_POST['period'] ?? '30'));
    $whereRange = '';
    $paramsRange = [];

    if ($period['since'] instanceof DateTimeImmutable) {
        $whereRange = ' WHERE created_at >= :since ';
        $paramsRange['since'] = $period['since']->format('Y-m-d H:i:s');
    }

    $metrics = [
        'residenciales_activos' => (int)$pdo->query("SELECT COUNT(*) FROM residenciales WHERE activo = 1")->fetchColumn(),
        'usuarios_activos' => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn(),
        'residenciales_periodo' => 0,
        'usuarios_periodo' => 0,
    ];

    if ($whereRange !== '') {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM residenciales {$whereRange}");
        $stmt->execute($paramsRange);
        $metrics['residenciales_periodo'] = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users {$whereRange}");
        $stmt->execute($paramsRange);
        $metrics['usuarios_periodo'] = (int)$stmt->fetchColumn();
    } else {
        $metrics['residenciales_periodo'] = (int)$pdo->query("SELECT COUNT(*) FROM residenciales")->fetchColumn();
        $metrics['usuarios_periodo'] = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    }

    $rolesSql = "
        SELECT t.nombre AS rol, COUNT(*) AS total
        FROM users u
        JOIN tipos_usuario t ON t.id = u.tipo_usuario_id
    ";
    if ($whereRange !== '') {
        $rolesSql .= " WHERE u.created_at >= :since ";
    }
    $rolesSql .= " GROUP BY t.nombre ORDER BY total DESC, t.nombre ASC";

    $stmt = $pdo->prepare($rolesSql);
    $stmt->execute($paramsRange);
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $resSql = "
        SELECT nombre, codigo, ciudad, estado, pais, estatus_plan, created_at
        FROM residenciales
        {$whereRange}
        ORDER BY created_at DESC
        LIMIT 60
    ";
    $stmt = $pdo->prepare($resSql);
    $stmt->execute($paramsRange);
    $latestResidenciales = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $usersSql = "
        SELECT u.name, u.email, t.nombre AS rol, u.is_active, u.created_at
        FROM users u
        JOIN tipos_usuario t ON t.id = u.tipo_usuario_id
    ";
    if ($whereRange !== '') {
        $usersSql .= " WHERE u.created_at >= :since ";
    }
    $usersSql .= " ORDER BY u.created_at DESC LIMIT 60";
    $stmt = $pdo->prepare($usersSql);
    $stmt->execute($paramsRange);
    $latestUsers = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $topRole = $roles[0]['rol'] ?? 'Sin datos';
    $topRoleCount = (int)($roles[0]['total'] ?? 0);
    $activeRate = $metrics['usuarios_activos'] > 0
        ? round(($metrics['usuarios_activos'] / max(1, (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn())) * 100, 1)
        : 0.0;

    $summary = [
        [
            'title' => 'Crecimiento de residenciales',
            'value' => (string)$metrics['residenciales_periodo'],
            'detail' => $period['label'],
        ],
        [
            'title' => 'Usuarios registrados',
            'value' => (string)$metrics['usuarios_periodo'],
            'detail' => $period['label'],
        ],
        [
            'title' => 'Rol con mayor volumen',
            'value' => $topRole,
            'detail' => $topRoleCount > 0 ? $topRoleCount . ' usuarios' : 'Sin actividad reciente',
        ],
        [
            'title' => 'Tasa de usuarios activos',
            'value' => $activeRate . '%',
            'detail' => 'Sobre la base actual de usuarios',
        ],
    ];

    if ($period['compare_since'] instanceof DateTimeImmutable && $period['compare_until'] instanceof DateTimeImmutable) {
        $compareParams = [
            'since' => $period['compare_since']->format('Y-m-d H:i:s'),
            'until' => $period['compare_until']->format('Y-m-d H:i:s'),
        ];
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM residenciales WHERE created_at BETWEEN :since AND :until");
        $stmt->execute($compareParams);
        $previousResidenciales = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE created_at BETWEEN :since AND :until");
        $stmt->execute($compareParams);
        $previousUsers = (int)$stmt->fetchColumn();

        $summary[0]['trend'] = report_format_change(report_change_percent($metrics['residenciales_periodo'], $previousResidenciales));
        $summary[1]['trend'] = report_format_change(report_change_percent($metrics['usuarios_periodo'], $previousUsers));
    } else {
        $summary[0]['trend'] = 'Comparación no disponible en histórico completo';
        $summary[1]['trend'] = 'Comparación no disponible en histórico completo';
    }
    $summary[2]['trend'] = $topRoleCount > 0 ? 'Distribución dominante del rango' : 'Sin datos para comparar';
    $summary[3]['trend'] = $activeRate >= 70 ? 'Base saludable de accesos activos' : 'Conviene revisar activaciones e inactivos';

    $rangeMeta = [
        ['label' => 'Periodo analizado', 'value' => $period['label']],
        ['label' => 'Residenciales en rango', 'value' => (string)$metrics['residenciales_periodo']],
        ['label' => 'Usuarios en rango', 'value' => (string)$metrics['usuarios_periodo']],
        ['label' => 'Rol más visible', 'value' => $topRole],
    ];

    sa_json_out(true, [
        'data' => [
            'period' => [
                'value' => $period['value'],
                'label' => $period['label'],
            ],
            'metrics' => $metrics,
            'roles' => $roles,
            'latest_residenciales' => $latestResidenciales,
            'latest_users' => $latestUsers,
            'summary' => $summary,
            'range_meta' => $rangeMeta,
            'series' => report_build_series($pdo, $period),
        ],
    ]);
} catch (Throwable $e) {
    app_json_exception($e, 'No pudimos cargar los reportes.');
}
