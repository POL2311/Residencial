<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script solo puede ejecutarse desde terminal.\n");
    exit(1);
}

const DEFAULT_RETAILOPS_CODE = 'WALMART-PILOTO';

function preflight_parse_args(array $argv): array
{
    $opts = [
        'codigo' => DEFAULT_RETAILOPS_CODE,
        'db_host' => null,
        'db_name' => null,
        'db_user' => null,
        'db_pass' => '',
        'db_socket' => null,
        'xampp_local' => false,
        'help' => false,
    ];

    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--help' || $arg === '-h') {
            $opts['help'] = true;
            continue;
        }
        if (str_starts_with($arg, '--codigo=')) {
            $code = trim(substr($arg, strlen('--codigo=')), "\"' \t\n\r\0\x0B");
            $opts['codigo'] = $code !== '' ? strtoupper($code) : DEFAULT_RETAILOPS_CODE;
            continue;
        }
        if ($arg === '--xampp-local') {
            $opts['xampp_local'] = true;
            continue;
        }
        if (str_starts_with($arg, '--db-host=')) {
            $opts['db_host'] = trim(substr($arg, strlen('--db-host=')), "\"' \t\n\r\0\x0B") ?: null;
            continue;
        }
        if (str_starts_with($arg, '--db-name=')) {
            $opts['db_name'] = trim(substr($arg, strlen('--db-name=')), "\"' \t\n\r\0\x0B") ?: null;
            continue;
        }
        if (str_starts_with($arg, '--db-user=')) {
            $opts['db_user'] = trim(substr($arg, strlen('--db-user=')), "\"' \t\n\r\0\x0B") ?: null;
            continue;
        }
        if (str_starts_with($arg, '--db-pass=')) {
            $opts['db_pass'] = trim(substr($arg, strlen('--db-pass=')), "\"' \t\n\r\0\x0B");
            continue;
        }
        if (str_starts_with($arg, '--db-socket=')) {
            $opts['db_socket'] = trim(substr($arg, strlen('--db-socket=')), "\"' \t\n\r\0\x0B") ?: null;
            continue;
        }

        throw new RuntimeException("Opción no reconocida: {$arg}");
    }

    if ($opts['xampp_local']) {
        $opts['db_socket'] = $opts['db_socket'] ?: '/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock';
        $opts['db_name'] = $opts['db_name'] ?: 'residencial_app4';
        $opts['db_user'] = $opts['db_user'] ?: 'root';
        $opts['db_pass'] = (string)($opts['db_pass'] ?? '');
    }

    return $opts;
}

function preflight_usage(): void
{
    echo "Uso:\n";
    echo "  php scripts/check_retailops_ready.php\n";
    echo "  php scripts/check_retailops_ready.php --codigo=\"WALMART-PILOTO\"\n";
    echo "  php scripts/check_retailops_ready.php --xampp-local --codigo=\"WALMART-PILOTO\"\n";
    echo "\nOpciones DB opcionales para CLI/local:\n";
    echo "  --db-host=127.0.0.1 --db-name=residencial_app4 --db-user=root --db-pass=\"\"\n";
    echo "  --db-socket=/ruta/mysql.sock --db-name=residencial_app4 --db-user=root --db-pass=\"\"\n";
}

function preflight_using_db_override(array $opts): bool
{
    return (bool)$opts['xampp_local']
        || !empty($opts['db_socket'])
        || !empty($opts['db_host'])
        || !empty($opts['db_name'])
        || !empty($opts['db_user']);
}

function preflight_connect_from_options(array $opts): PDO
{
    $dbName = (string)($opts['db_name'] ?? '');
    $dbUser = (string)($opts['db_user'] ?? '');
    $dbPass = (string)($opts['db_pass'] ?? '');

    if ($dbName === '' || $dbUser === '') {
        throw new RuntimeException('Para usar opciones DB en CLI debes indicar --db-name y --db-user.');
    }

    if (!empty($opts['db_socket'])) {
        $dsn = 'mysql:unix_socket=' . (string)$opts['db_socket'] . ';dbname=' . $dbName . ';charset=utf8mb4';
    } else {
        $host = (string)($opts['db_host'] ?? '127.0.0.1');
        $dsn = 'mysql:host=' . $host . ';dbname=' . $dbName . ';charset=utf8mb4';
    }

    return new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

function preflight_load_config_pdo(string $root): PDO
{
    $GLOBALS['preflight_last_exception'] = null;

    if (!function_exists('app_log_exception')) {
        function app_log_exception(Throwable $e, string $context = ''): void
        {
            $GLOBALS['preflight_last_exception'] = $e;
            $prefix = $context !== '' ? '[' . $context . '] ' : '';
            fwrite(STDERR, $prefix . get_class($e) . ': ' . $e->getMessage() . "\n");
        }
    }
    if (!function_exists('app_abort')) {
        function app_abort(int $status, string $title, string $message, array $actions = []): void
        {
            $detail = $GLOBALS['preflight_last_exception'] instanceof Throwable
                ? ' Detalle: ' . $GLOBALS['preflight_last_exception']->getMessage()
                : '';
            throw new RuntimeException("{$title}: {$message}{$detail}", $status);
        }
    }
    if (!function_exists('app_ensure_session')) {
        function app_ensure_session(): void
        {
            // Los scripts CLI no necesitan abrir sesión web.
        }
    }

    require_once $root . '/config/config.php';
    if (!isset($GLOBALS['pdo']) || !$GLOBALS['pdo'] instanceof PDO) {
        throw new RuntimeException('No se encontró $pdo desde config/config.php.');
    }
    return $GLOBALS['pdo'];
}

function preflight_ident(string $identifier): string
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $identifier)) {
        throw new InvalidArgumentException("Identificador inválido: {$identifier}");
    }
    return $identifier;
}

function preflight_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = :table_name
    ");
    $stmt->execute(['table_name' => $table]);
    return (int)$stmt->fetchColumn() > 0;
}

function preflight_table_columns(PDO $pdo, string $table): array
{
    static $cache = [];
    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }
    if (!preflight_table_exists($pdo, $table)) {
        $cache[$table] = [];
        return [];
    }

    $stmt = $pdo->query('DESCRIBE `' . preflight_ident($table) . '`');
    $columns = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $columns[(string)$row['Field']] = $row;
    }
    $cache[$table] = $columns;
    return $columns;
}

function preflight_column_exists(PDO $pdo, string $table, string $column): bool
{
    return array_key_exists($column, preflight_table_columns($pdo, $table));
}

function preflight_column_nullable(PDO $pdo, string $table, string $column): bool
{
    $columns = preflight_table_columns($pdo, $table);
    return isset($columns[$column]) && strtoupper((string)$columns[$column]['Null']) === 'YES';
}

function preflight_fetch_one(PDO $pdo, string $sql, array $params = []): ?array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function preflight_fetch_value(PDO $pdo, string $sql, array $params = []): mixed
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

function preflight_count(PDO $pdo, string $table, string $where = '', array $params = []): int
{
    $sql = 'SELECT COUNT(*) FROM `' . preflight_ident($table) . '`';
    if ($where !== '') {
        $sql .= ' WHERE ' . $where;
    }
    return (int)preflight_fetch_value($pdo, $sql, $params);
}

function preflight_result(array &$stats, string $type, string $message): void
{
    $type = strtoupper($type);
    $stats[$type] = ($stats[$type] ?? 0) + 1;
    echo sprintf("[%s] %s\n", str_pad($type, 4), $message);
}

function check_ok(array &$stats, string $message): void
{
    preflight_result($stats, 'OK', $message);
}

function check_warn(array &$stats, string $message): void
{
    preflight_result($stats, 'WARN', $message);
}

function check_fail(array &$stats, string $message): void
{
    preflight_result($stats, 'FAIL', $message);
}

function preflight_valid_values(PDO $pdo, string $table, string $column, int $rid, array $valid): array
{
    if (!preflight_table_exists($pdo, $table) || !preflight_column_exists($pdo, $table, $column)) {
        return [];
    }
    $table = preflight_ident($table);
    $column = preflight_ident($column);
    $stmt = $pdo->prepare("
        SELECT DISTINCT `{$column}` AS value
        FROM `{$table}`
        WHERE residencial_id = :rid
          AND `{$column}` IS NOT NULL
          AND `{$column}` <> ''
    ");
    $stmt->execute(['rid' => $rid]);
    $invalid = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $value = (string)$row['value'];
        if (!in_array($value, $valid, true)) {
            $invalid[] = $value;
        }
    }
    return array_values(array_unique($invalid));
}

try {
    $opts = preflight_parse_args($argv);
    if ($opts['help']) {
        preflight_usage();
        exit(0);
    }

    $root = dirname(__DIR__);
    $pdo = preflight_using_db_override($opts)
        ? preflight_connect_from_options($opts)
        : preflight_load_config_pdo($root);

    $stats = ['OK' => 0, 'WARN' => 0, 'FAIL' => 0];
    $codigo = (string)$opts['codigo'];

    echo "RetailOps Preflight\n";
    echo "Servicio: {$codigo}\n";
    echo str_repeat('-', 48) . "\n";

    $service = null;
    $rid = 0;
    if (!preflight_table_exists($pdo, 'residenciales')) {
        check_fail($stats, 'Falta tabla residenciales.');
    } elseif (!preflight_table_exists($pdo, 'residenciales_servicio_config')) {
        check_fail($stats, 'Falta tabla residenciales_servicio_config.');
    } else {
        $service = preflight_fetch_one(
            $pdo,
            "
                SELECT r.id, r.nombre, r.codigo, r.modo_operacion, r.activo, rsc.preset_servicio
                FROM residenciales r
                LEFT JOIN residenciales_servicio_config rsc ON rsc.residencial_id = r.id
                WHERE r.codigo = :codigo
                LIMIT 1
            ",
            ['codigo' => $codigo]
        );
        if (!$service) {
            check_fail($stats, "Servicio RetailOps no encontrado con código {$codigo}.");
        } else {
            $rid = (int)$service['id'];
            check_ok($stats, "Servicio encontrado: {$service['nombre']} (ID {$rid}).");
            (string)$service['modo_operacion'] === 'retailops'
                ? check_ok($stats, 'modo_operacion = retailops.')
                : check_fail($stats, "modo_operacion esperado retailops, actual {$service['modo_operacion']}.");
            (string)$service['preset_servicio'] === 'retailops'
                ? check_ok($stats, 'preset_servicio = retailops.')
                : check_fail($stats, "preset_servicio esperado retailops, actual " . ((string)$service['preset_servicio'] ?: 'NULL') . '.');
            (int)$service['activo'] === 1
                ? check_ok($stats, 'Servicio activo.')
                : check_fail($stats, 'Servicio no está activo.');
        }
    }

    $requiredFlags = [
        'habilita_admin_operativo',
        'habilita_guardia',
        'habilita_control_acceso',
        'habilita_incidencias',
        'habilita_personal_recurrente',
        'habilita_visitantes_rapidos',
        'habilita_materiales',
        'habilita_solicitudes_pendientes',
        'habilita_bitacora_operativa',
        'habilita_herramientas',
        'habilita_reportes_operativos',
        'habilita_rondines',
        'habilita_proveedores',
        'habilita_ordenes_servicio',
    ];

    if ($rid > 0) {
        $profileColumns = preflight_table_columns($pdo, 'residenciales_servicio_config');
        $profile = preflight_fetch_one($pdo, 'SELECT * FROM residenciales_servicio_config WHERE residencial_id = :rid LIMIT 1', ['rid' => $rid]);
        if (!$profile) {
            check_fail($stats, 'No existe configuración en residenciales_servicio_config para el servicio.');
        } else {
            $missingFlags = [];
            $disabledFlags = [];
            foreach ($requiredFlags as $flag) {
                if (!array_key_exists($flag, $profileColumns)) {
                    $missingFlags[] = $flag;
                    continue;
                }
                if ((int)($profile[$flag] ?? 0) !== 1) {
                    $disabledFlags[] = $flag;
                }
            }
            $missingFlags
                ? check_fail($stats, 'Faltan flags RetailOps: ' . implode(', ', $missingFlags) . '.')
                : check_ok($stats, 'Flags RetailOps existen.');
            $disabledFlags
                ? check_fail($stats, 'Módulos RetailOps apagados: ' . implode(', ', $disabledFlags) . '.')
                : check_ok($stats, 'Módulos RetailOps requeridos activos.');
        }
    }

    $requiredTables = [
        'areas_operativas',
        'personas_recurrentes',
        'visitantes_rapidos',
        'bitacora_operativa',
        'catalogo_materiales',
        'permisos_materiales',
        'catalogo_herramientas',
        'prestamos_herramientas',
        'rondines_rutas',
        'rondines_puntos',
        'rondines_ruta_puntos',
        'rondines_ejecuciones',
        'rondines_eventos',
        'proveedores',
        'proveedor_personas',
        'proveedor_documentos',
        'ordenes_servicio',
        'ordenes_servicio_evidencias',
    ];
    $missingTables = [];
    foreach ($requiredTables as $table) {
        if (!preflight_table_exists($pdo, $table)) {
            $missingTables[] = $table;
        }
    }
    $missingTables
        ? check_fail($stats, 'Faltan tablas requeridas: ' . implode(', ', $missingTables) . '.')
        : check_ok($stats, 'Tablas requeridas presentes.');

    $criticalColumns = [
        'personas_recurrentes' => ['estatus_cumplimiento', 'motivo_bloqueo', 'cumplimiento_actualizado_at', 'cumplimiento_actualizado_por'],
        'proveedores' => ['estatus_cumplimiento', 'motivo_bloqueo', 'cumplimiento_actualizado_at', 'cumplimiento_actualizado_por'],
        'prestamos_herramientas' => ['area_id', 'persona_recurrente_id', 'responsable_nombre', 'unidad_id'],
        'ordenes_servicio' => ['proveedor_id', 'persona_recurrente_id', 'area_id', 'folio', 'qr_token', 'estatus'],
        'rondines_puntos' => ['codigo_qr', 'latitud', 'longitud', 'radio_metros', 'requiere_foto', 'requiere_observacion'],
    ];
    $missingColumns = [];
    foreach ($criticalColumns as $table => $columns) {
        foreach ($columns as $column) {
            if (!preflight_column_exists($pdo, $table, $column)) {
                $missingColumns[] = "{$table}.{$column}";
            }
        }
    }
    $missingColumns
        ? check_fail($stats, 'Faltan columnas críticas: ' . implode(', ', $missingColumns) . '.')
        : check_ok($stats, 'Columnas críticas presentes.');

    if (preflight_column_exists($pdo, 'prestamos_herramientas', 'unidad_id')) {
        preflight_column_nullable($pdo, 'prestamos_herramientas', 'unidad_id')
            ? check_ok($stats, 'prestamos_herramientas.unidad_id es nullable.')
            : check_fail($stats, 'prestamos_herramientas.unidad_id no es nullable.');
    }

    if ($rid > 0) {
        $dataChecks = [
            ['areas_operativas', 'Áreas operativas', 5, 'activo = 1 AND residencial_id = :rid'],
            ['personas_recurrentes', 'Personas recurrentes', 3, 'activo = 1 AND residencial_id = :rid'],
            ['proveedores', 'Proveedores', 1, 'activo = 1 AND residencial_id = :rid'],
            ['rondines_rutas', 'Rutas de rondín', 1, 'activo = 1 AND residencial_id = :rid'],
            ['rondines_puntos', 'Puntos de rondín', 3, 'activo = 1 AND residencial_id = :rid'],
            ['catalogo_herramientas', 'Herramientas', 1, 'activo = 1 AND residencial_id = :rid'],
            ['catalogo_materiales', 'Materiales', 1, 'activo = 1 AND residencial_id = :rid'],
            ['bitacora_operativa', 'Eventos en bitácora', 5, 'residencial_id = :rid'],
        ];
        foreach ($dataChecks as [$table, $label, $min, $where]) {
            if (!preflight_table_exists($pdo, $table)) {
                continue;
            }
            $count = preflight_count($pdo, $table, $where, ['rid' => $rid]);
            $count >= $min
                ? check_ok($stats, "{$label}: {$count} encontrados.")
                : check_fail($stats, "{$label}: {$count} encontrados, mínimo {$min}.");
        }

        if (preflight_table_exists($pdo, 'tipos_usuario') && preflight_table_exists($pdo, 'users') && preflight_table_exists($pdo, 'usuarios_residenciales')) {
            $adminCount = (int)preflight_fetch_value(
                $pdo,
                "
                    SELECT COUNT(*)
                    FROM users u
                    JOIN tipos_usuario t ON t.id = u.tipo_usuario_id
                    JOIN usuarios_residenciales ur ON ur.user_id = u.id
                    WHERE ur.residencial_id = :rid
                      AND t.nombre = 'admin_residencial'
                ",
                ['rid' => $rid]
            );
            $guardCount = (int)preflight_fetch_value(
                $pdo,
                "
                    SELECT COUNT(*)
                    FROM users u
                    JOIN tipos_usuario t ON t.id = u.tipo_usuario_id
                    JOIN usuarios_residenciales ur ON ur.user_id = u.id
                    WHERE ur.residencial_id = :rid
                      AND t.nombre = 'guardia'
                ",
                ['rid' => $rid]
            );
            $adminCount >= 1 ? check_ok($stats, "Admin asignado: {$adminCount}.") : check_fail($stats, 'No hay admin_residencial asignado.');
            $guardCount >= 1 ? check_ok($stats, "Guardia asignado: {$guardCount}.") : check_fail($stats, 'No hay guardia asignado.');
        } else {
            check_fail($stats, 'No se pueden validar usuarios asignados: faltan users/tipos_usuario/usuarios_residenciales.');
        }

        if (preflight_table_exists($pdo, 'proveedor_personas')) {
            $providerPersonCount = (int)preflight_fetch_value(
                $pdo,
                "
                    SELECT COUNT(*)
                    FROM proveedor_personas pp
                    JOIN proveedores p ON p.id = pp.proveedor_id
                    JOIN personas_recurrentes pr ON pr.id = pp.persona_recurrente_id
                    WHERE p.residencial_id = :rid
                      AND pr.residencial_id = :rid
                      AND pp.activo = 1
                ",
                ['rid' => $rid]
            );
            $providerPersonCount >= 1
                ? check_ok($stats, "Proveedor con persona asociada: {$providerPersonCount} asociación(es).")
                : check_fail($stats, 'No hay proveedor con persona asociada.');
        }

        if (preflight_table_exists($pdo, 'rondines_ruta_puntos')) {
            $routePoints = (int)preflight_fetch_value(
                $pdo,
                "
                    SELECT COUNT(DISTINCT rrp.ruta_id)
                    FROM rondines_ruta_puntos rrp
                    JOIN rondines_rutas rr ON rr.id = rrp.ruta_id
                    WHERE rr.residencial_id = :rid
                ",
                ['rid' => $rid]
            );
            $routePoints >= 1
                ? check_ok($stats, 'Hay al menos una ruta con puntos asociados.')
                : check_fail($stats, 'No hay rutas con puntos asociados.');
        }

        if (preflight_table_exists($pdo, 'ordenes_servicio')) {
            $orderCount = (int)preflight_fetch_value(
                $pdo,
                "
                    SELECT COUNT(*)
                    FROM ordenes_servicio
                    WHERE residencial_id = :rid
                      AND estatus IN ('programada', 'en_proceso', 'pendiente_validacion', 'cerrada')
                ",
                ['rid' => $rid]
            );
            $orderCount >= 1
                ? check_ok($stats, "Órdenes de servicio: {$orderCount} encontrada(s).")
                : check_warn($stats, 'No hay orden de servicio creada.');
        }

        if (preflight_table_exists($pdo, 'personas_recurrentes') && preflight_column_exists($pdo, 'personas_recurrentes', 'qr_token')) {
            $missingPersonQr = preflight_count($pdo, 'personas_recurrentes', "residencial_id = :rid AND activo = 1 AND (qr_token IS NULL OR qr_token = '')", ['rid' => $rid]);
            $missingPersonQr === 0
                ? check_ok($stats, 'Personas recurrentes con qr_token.')
                : check_fail($stats, "Personas recurrentes sin qr_token: {$missingPersonQr}.");
        }
        if (preflight_table_exists($pdo, 'rondines_puntos') && preflight_column_exists($pdo, 'rondines_puntos', 'codigo_qr')) {
            $roundQr = preflight_fetch_one($pdo, "SELECT codigo_qr FROM rondines_puntos WHERE residencial_id = :rid AND codigo_qr <> '' LIMIT 1", ['rid' => $rid]);
            $roundQr
                ? check_ok($stats, 'QR rondín formable: op:round_point:' . $roundQr['codigo_qr'])
                : check_fail($stats, 'No hay codigo_qr en puntos de rondín.');
        }
        if (preflight_table_exists($pdo, 'ordenes_servicio') && preflight_column_exists($pdo, 'ordenes_servicio', 'qr_token')) {
            $orderQr = preflight_fetch_one($pdo, "SELECT qr_token FROM ordenes_servicio WHERE residencial_id = :rid AND qr_token <> '' LIMIT 1", ['rid' => $rid]);
            $orderQr
                ? check_ok($stats, 'QR orden formable: op:so:' . $orderQr['qr_token'])
                : check_warn($stats, 'No hay qr_token de orden de servicio para formar op:so:*.');
        }

        $validCompliance = ['autorizado', 'pendiente', 'bloqueado', 'documento_vencido', 'fuera_de_horario'];
        $invalidProviders = preflight_valid_values($pdo, 'proveedores', 'estatus_cumplimiento', $rid, $validCompliance);
        $invalidPersons = preflight_valid_values($pdo, 'personas_recurrentes', 'estatus_cumplimiento', $rid, $validCompliance);
        $invalidProviders
            ? check_fail($stats, 'Proveedores con estatus_cumplimiento inválido: ' . implode(', ', $invalidProviders) . '.')
            : check_ok($stats, 'Semáforo de proveedores con valores válidos.');
        $invalidPersons
            ? check_fail($stats, 'Personas recurrentes con estatus_cumplimiento inválido: ' . implode(', ', $invalidPersons) . '.')
            : check_ok($stats, 'Semáforo de personas recurrentes con valores válidos.');
    }

    if (preflight_table_exists($pdo, 'residenciales') && preflight_table_exists($pdo, 'residenciales_servicio_config')) {
        $residential = preflight_fetch_one(
            $pdo,
            "
                SELECT r.id, r.nombre, r.modo_operacion,
                       rsc.habilita_rondines,
                       rsc.habilita_proveedores,
                       rsc.habilita_ordenes_servicio
                FROM residenciales r
                JOIN residenciales_servicio_config rsc ON rsc.residencial_id = r.id
                WHERE r.modo_operacion = 'residencial'
                LIMIT 1
            "
        );
        if (!$residential) {
            check_warn($stats, 'No hay residencial normal para validar separación.');
        } else {
            $badResidentialFlags = [];
            foreach (['habilita_rondines', 'habilita_proveedores', 'habilita_ordenes_servicio'] as $flag) {
                if ((int)($residential[$flag] ?? 0) !== 0) {
                    $badResidentialFlags[] = $flag;
                }
            }
            $badResidentialFlags
                ? check_fail($stats, 'Residencial normal con módulos RetailOps activos: ' . implode(', ', $badResidentialFlags) . '.')
                : check_ok($stats, "Residencial separado OK: {$residential['nombre']}.");
        }
    }

    echo str_repeat('-', 48) . "\n";
    echo sprintf("Resumen: OK=%d WARN=%d FAIL=%d\n", $stats['OK'], $stats['WARN'], $stats['FAIL']);
    if ($stats['FAIL'] > 0) {
        echo "NO LISTO, bloqueantes encontrados\n";
        exit(1);
    }

    echo "LISTO PARA PRUEBA MANUAL\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "[ERROR] " . $e->getMessage() . "\n");
    exit(1);
}
