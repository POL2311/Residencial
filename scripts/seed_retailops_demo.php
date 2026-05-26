<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script solo puede ejecutarse desde terminal.\n");
    exit(1);
}

$root = dirname(__DIR__);
require_once $root . '/config/config.php';

$operationalPath = $root . '/config/operational_mode.php';
$serviceProfilePath = $root . '/config/service_profile.php';
if (is_file($operationalPath)) {
    require_once $operationalPath;
}
if (is_file($serviceProfilePath)) {
    require_once $serviceProfilePath;
}

const DEMO_SEED_KEY = 'retailops_walmart_piloto';
const DEMO_ADMIN_EMAIL = 'admin.retailops.demo@osgate.local';
const DEMO_GUARD_EMAIL = 'guardia.retailops.demo@osgate.local';
const DEMO_PASSWORD = 'Demo12345!';
const DEMO_PERSON_PIN = '1234';

function seed_parse_args(array $argv): array
{
    $opts = [
        'dry_run' => false,
        'yes' => false,
        'nombre' => 'Walmart Piloto',
        'codigo' => null,
        'help' => false,
    ];

    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--dry-run') {
            $opts['dry_run'] = true;
            continue;
        }
        if ($arg === '--yes' || $arg === '-y') {
            $opts['yes'] = true;
            continue;
        }
        if ($arg === '--help' || $arg === '-h') {
            $opts['help'] = true;
            continue;
        }
        if (str_starts_with($arg, '--nombre=')) {
            $opts['nombre'] = trim(substr($arg, strlen('--nombre=')), "\"' \t\n\r\0\x0B") ?: 'Walmart Piloto';
            continue;
        }
        if (str_starts_with($arg, '--codigo=')) {
            $opts['codigo'] = trim(substr($arg, strlen('--codigo=')), "\"' \t\n\r\0\x0B") ?: null;
            continue;
        }

        throw new RuntimeException("Opción no reconocida: {$arg}");
    }

    return $opts;
}

function seed_usage(): void
{
    echo "Uso:\n";
    echo "  php scripts/seed_retailops_demo.php --dry-run\n";
    echo "  php scripts/seed_retailops_demo.php\n";
    echo "  php scripts/seed_retailops_demo.php --nombre=\"Walmart Piloto\"\n";
    echo "\nOpciones:\n";
    echo "  --dry-run     Ejecuta la lógica y hace rollback al final.\n";
    echo "  --nombre=...  Nombre del servicio/empresa demo.\n";
    echo "  --codigo=...  Código del servicio. Si se omite, se deriva del nombre.\n";
    echo "  --yes, -y     Omite confirmación interactiva en modo real.\n";
}

function seed_log(string $message): void
{
    echo "[INFO] {$message}\n";
}

function seed_ok(string $message): void
{
    echo "[OK]   {$message}\n";
}

function seed_warn(string $message): void
{
    echo "[WARN] {$message}\n";
}

function seed_slug(string $value, bool $upper = false, int $max = 80): string
{
    $value = trim($value);
    $value = strtr($value, [
        'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N',
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
    ]);
    $value = preg_replace('/[^A-Za-z0-9]+/', '-', $value) ?? '';
    $value = trim($value, '-');
    $value = $value !== '' ? $value : 'demo';
    $value = substr($value, 0, $max);
    return $upper ? strtoupper($value) : strtolower($value);
}

function seed_service_code(string $nombre, ?string $codigo): string
{
    if ($codigo !== null && trim($codigo) !== '') {
        return seed_slug($codigo, true, 50);
    }
    if (strcasecmp(trim($nombre), 'Walmart Piloto') === 0) {
        return 'WALMART-PILOTO';
    }
    return seed_slug($nombre, true, 50);
}

function seed_table_columns(PDO $pdo, string $table): array
{
    static $cache = [];
    if (isset($cache[$table])) {
        return $cache[$table];
    }

    $stmt = $pdo->query("DESCRIBE `{$table}`");
    $cols = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $cols[(string)$row['Field']] = $row;
    }
    $cache[$table] = $cols;
    return $cols;
}

function seed_has_col(array $columns, string $column): bool
{
    return array_key_exists($column, $columns);
}

function seed_require_cols(array $columns, string $table, array $required): void
{
    $missing = array_values(array_filter($required, static fn(string $col): bool => !array_key_exists($col, $columns)));
    if ($missing) {
        throw new RuntimeException("La tabla {$table} no tiene columnas requeridas: " . implode(', ', $missing));
    }
}

function seed_pick_data(array $columns, array $data): array
{
    return array_filter(
        $data,
        static fn(string $col): bool => array_key_exists($col, $columns),
        ARRAY_FILTER_USE_KEY
    );
}

function seed_insert(PDO $pdo, string $table, array $columns, array $data): int
{
    $data = seed_pick_data($columns, $data);
    if (!$data) {
        throw new RuntimeException("No hay columnas válidas para insertar en {$table}.");
    }
    $names = array_keys($data);
    $sql = sprintf(
        "INSERT INTO `%s` (`%s`) VALUES (:%s)",
        $table,
        implode('`, `', $names),
        implode(', :', $names)
    );
    $stmt = $pdo->prepare($sql);
    $stmt->execute($data);
    return (int)$pdo->lastInsertId();
}

function seed_update(PDO $pdo, string $table, array $columns, array $data, string $where, array $params): void
{
    $data = seed_pick_data($columns, $data);
    if (!$data) {
        return;
    }
    $sets = [];
    foreach (array_keys($data) as $col) {
        $sets[] = "`{$col}` = :set_{$col}";
        $params["set_{$col}"] = $data[$col];
    }
    $sql = "UPDATE `{$table}` SET " . implode(', ', $sets) . " WHERE {$where}";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
}

function seed_fetch_one(PDO $pdo, string $sql, array $params = []): ?array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function seed_fetch_id(PDO $pdo, string $sql, array $params = []): int
{
    $row = seed_fetch_one($pdo, $sql, $params);
    return $row ? (int)reset($row) : 0;
}

function seed_bump(array &$stats, string $bucket, string $status): void
{
    $stats[$bucket][$status] = ($stats[$bucket][$status] ?? 0) + 1;
}

function seed_ensure_service(PDO $pdo, string $nombre, string $codigo, array &$stats): int
{
    $cols = seed_table_columns($pdo, 'residenciales');
    seed_require_cols($cols, 'residenciales', ['id', 'nombre', 'codigo']);

    $data = [
        'nombre' => $nombre,
        'codigo' => $codigo,
        'tipo' => 'otro',
        'modo_operacion' => 'retailops',
        'pais' => 'México',
        'estado' => 'Nuevo León',
        'ciudad' => 'Monterrey',
        'colonia' => 'Demo RetailOps',
        'calle' => 'Piloto Retail',
        'numero_exterior' => '100',
        'numero_interior' => null,
        'codigo_postal' => '64000',
        'nombre_contacto' => 'Operaciones Walmart Piloto',
        'telefono_contacto' => '8110000000',
        'email_contacto' => 'operaciones.retailops.demo@osgate.local',
        'estatus_plan' => 'activo',
        'zona_horaria' => 'America/Mexico_City',
        'permite_qr' => 1,
        'permite_trabajadores_recurrentes' => 1,
        'activo' => 1,
    ];

    $existing = seed_fetch_one($pdo, "SELECT id FROM residenciales WHERE codigo = :codigo LIMIT 1", ['codigo' => $codigo]);
    if ($existing) {
        seed_update($pdo, 'residenciales', $cols, $data, 'id = :id', ['id' => (int)$existing['id']]);
        seed_bump($stats, 'servicios', 'updated');
        return (int)$existing['id'];
    }

    $id = seed_insert($pdo, 'residenciales', $cols, $data);
    seed_bump($stats, 'servicios', 'created');
    return $id;
}

function seed_upsert_service_profile(PDO $pdo, int $rid, array &$stats): void
{
    $cols = seed_table_columns($pdo, 'residenciales_servicio_config');
    seed_require_cols($cols, 'residenciales_servicio_config', ['residencial_id']);

    $on = [
        'habilita_admin_operativo',
        'habilita_guardia',
        'habilita_unidades',
        'habilita_guardias_catalogo',
        'habilita_guardias_admin_actions',
        'habilita_comunicados',
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
    ];
    $off = [
        'habilita_residente',
        'habilita_residentes_catalogo',
        'habilita_autos',
        'habilita_visitas_residente',
        'habilita_paqueteria',
        'habilita_pagos',
        'habilita_servicios_directorio',
    ];

    $data = ['residencial_id' => $rid, 'preset_servicio' => 'retailops'];
    foreach ($on as $flag) {
        $data[$flag] = 1;
    }
    foreach ($off as $flag) {
        $data[$flag] = 0;
    }

    $exists = seed_fetch_id($pdo, "SELECT id FROM residenciales_servicio_config WHERE residencial_id = :rid LIMIT 1", ['rid' => $rid]);
    if ($exists > 0) {
        unset($data['residencial_id']);
        seed_update($pdo, 'residenciales_servicio_config', $cols, $data, 'residencial_id = :rid', ['rid' => $rid]);
        seed_bump($stats, 'perfiles_servicio', 'updated');
        return;
    }

    seed_insert($pdo, 'residenciales_servicio_config', $cols, $data);
    seed_bump($stats, 'perfiles_servicio', 'created');
}

function seed_ensure_area(PDO $pdo, int $rid, array $area, array &$stats): int
{
    $cols = seed_table_columns($pdo, 'areas_operativas');
    seed_require_cols($cols, 'areas_operativas', ['residencial_id', 'nombre']);

    $existing = seed_fetch_one(
        $pdo,
        "SELECT id FROM areas_operativas WHERE residencial_id = :rid AND nombre = :nombre LIMIT 1",
        ['rid' => $rid, 'nombre' => $area['nombre']]
    );

    $data = [
        'residencial_id' => $rid,
        'nombre' => $area['nombre'],
        'codigo' => $area['codigo'] ?? seed_slug($area['nombre'], true, 20),
        'tipo' => $area['tipo'] ?? 'operativa',
        'descripcion' => $area['descripcion'] ?? 'Área operativa demo RetailOps.',
        'activo' => 1,
    ];

    if ($existing) {
        seed_update($pdo, 'areas_operativas', $cols, $data, 'id = :id', ['id' => (int)$existing['id']]);
        seed_bump($stats, 'areas', 'reused');
        return (int)$existing['id'];
    }

    $id = seed_insert($pdo, 'areas_operativas', $cols, $data);
    seed_bump($stats, 'areas', 'created');
    return $id;
}

function seed_ensure_role(PDO $pdo, string $role, string $description, array &$stats): int
{
    $id = seed_fetch_id($pdo, "SELECT id FROM tipos_usuario WHERE nombre = :nombre LIMIT 1", ['nombre' => $role]);
    if ($id > 0) {
        seed_bump($stats, 'roles', 'reused');
        return $id;
    }

    $cols = seed_table_columns($pdo, 'tipos_usuario');
    seed_require_cols($cols, 'tipos_usuario', ['nombre']);
    $id = seed_insert($pdo, 'tipos_usuario', $cols, [
        'nombre' => $role,
        'descripcion' => $description,
    ]);
    seed_bump($stats, 'roles', 'created');
    return $id;
}

function seed_ensure_user(PDO $pdo, int $roleId, string $name, string $email, bool $isGuardia, array &$stats): int
{
    $cols = seed_table_columns($pdo, 'users');
    seed_require_cols($cols, 'users', ['tipo_usuario_id', 'name', 'email']);
    $passwordCol = seed_has_col($cols, 'password_hash') ? 'password_hash' : (seed_has_col($cols, 'password') ? 'password' : null);
    if ($passwordCol === null) {
        throw new RuntimeException('La tabla users no tiene password_hash ni password.');
    }

    $hash = password_hash(DEMO_PASSWORD, PASSWORD_DEFAULT);
    $data = [
        'tipo_usuario_id' => $roleId,
        'name' => $name,
        'email' => $email,
        'telefono' => $isGuardia ? '8110000002' : '8110000001',
        $passwordCol => $hash,
        'is_active' => 1,
        'guardia_en_servicio' => $isGuardia ? 1 : 0,
    ];

    $existing = seed_fetch_one($pdo, "SELECT id FROM users WHERE email = :email LIMIT 1", ['email' => $email]);
    if ($existing) {
        seed_update($pdo, 'users', $cols, $data, 'id = :id', ['id' => (int)$existing['id']]);
        seed_bump($stats, 'usuarios', 'updated');
        return (int)$existing['id'];
    }

    $id = seed_insert($pdo, 'users', $cols, $data);
    seed_bump($stats, 'usuarios', 'created');
    return $id;
}

function seed_ensure_user_assignment(PDO $pdo, int $userId, int $rid, array &$stats): void
{
    $existing = seed_fetch_id(
        $pdo,
        "SELECT id FROM usuarios_residenciales WHERE user_id = :uid AND residencial_id = :rid LIMIT 1",
        ['uid' => $userId, 'rid' => $rid]
    );
    $cols = seed_table_columns($pdo, 'usuarios_residenciales');
    if ($existing > 0) {
        seed_update($pdo, 'usuarios_residenciales', $cols, ['es_principal' => 1], 'id = :id', ['id' => $existing]);
        seed_bump($stats, 'asignaciones_usuario', 'reused');
        return;
    }
    seed_insert($pdo, 'usuarios_residenciales', $cols, [
        'user_id' => $userId,
        'residencial_id' => $rid,
        'es_principal' => 1,
    ]);
    seed_bump($stats, 'asignaciones_usuario', 'created');
}

function seed_ensure_person(PDO $pdo, int $rid, array $person, array $areasByName, array &$stats): int
{
    $cols = seed_table_columns($pdo, 'personas_recurrentes');
    seed_require_cols($cols, 'personas_recurrentes', ['residencial_id', 'nombre']);
    $empresa = (string)$person['empresa'];
    $existing = seed_fetch_one(
        $pdo,
        "SELECT id FROM personas_recurrentes WHERE residencial_id = :rid AND nombre = :nombre AND empresa = :empresa LIMIT 1",
        ['rid' => $rid, 'nombre' => $person['nombre'], 'empresa' => $empresa]
    );
    $slug = seed_slug((string)$person['nombre'] . '-' . $empresa, false, 45);
    $data = [
        'residencial_id' => $rid,
        'area_id' => $areasByName[$person['area']] ?? null,
        'nombre' => $person['nombre'],
        'telefono' => $person['telefono'] ?? '8110000000',
        'empresa' => $empresa,
        'puesto' => $person['puesto'],
        'notas' => 'Demo RetailOps Walmart Piloto.',
        'qr_token' => "demo-pr-{$rid}-{$slug}",
        'pin_hash' => password_hash(DEMO_PERSON_PIN, PASSWORD_DEFAULT),
        'activo' => 1,
        'esta_dentro' => 0,
    ];

    if ($existing) {
        seed_update($pdo, 'personas_recurrentes', $cols, $data, 'id = :id', ['id' => (int)$existing['id']]);
        seed_bump($stats, 'personas', 'reused');
        return (int)$existing['id'];
    }

    $id = seed_insert($pdo, 'personas_recurrentes', $cols, $data);
    seed_bump($stats, 'personas', 'created');
    return $id;
}

function seed_ensure_catalog_item(PDO $pdo, string $table, int $rid, array $item, string $bucket, array &$stats): int
{
    $cols = seed_table_columns($pdo, $table);
    seed_require_cols($cols, $table, ['residencial_id', 'nombre']);
    $existing = seed_fetch_one(
        $pdo,
        "SELECT id FROM `{$table}` WHERE residencial_id = :rid AND nombre = :nombre LIMIT 1",
        ['rid' => $rid, 'nombre' => $item['nombre']]
    );
    $data = [
        'residencial_id' => $rid,
        'nombre' => $item['nombre'],
        'categoria' => $item['categoria'] ?? null,
        'descripcion' => $item['descripcion'] ?? 'Registro demo RetailOps.',
        'activo' => 1,
    ];
    if ($existing) {
        seed_update($pdo, $table, $cols, $data, 'id = :id', ['id' => (int)$existing['id']]);
        seed_bump($stats, $bucket, 'reused');
        return (int)$existing['id'];
    }
    $id = seed_insert($pdo, $table, $cols, $data);
    seed_bump($stats, $bucket, 'created');
    return $id;
}

function seed_ensure_route(PDO $pdo, int $rid, string $name, int $createdBy, array &$stats): int
{
    $cols = seed_table_columns($pdo, 'rondines_rutas');
    seed_require_cols($cols, 'rondines_rutas', ['residencial_id', 'nombre']);
    $existing = seed_fetch_one(
        $pdo,
        "SELECT id FROM rondines_rutas WHERE residencial_id = :rid AND nombre = :nombre LIMIT 1",
        ['rid' => $rid, 'nombre' => $name]
    );
    $data = [
        'residencial_id' => $rid,
        'nombre' => $name,
        'descripcion' => "Ruta demo RetailOps: {$name}.",
        'activo' => 1,
        'created_by' => $createdBy,
    ];
    if ($existing) {
        seed_update($pdo, 'rondines_rutas', $cols, $data, 'id = :id', ['id' => (int)$existing['id']]);
        seed_bump($stats, 'rutas', 'reused');
        return (int)$existing['id'];
    }
    $id = seed_insert($pdo, 'rondines_rutas', $cols, $data);
    seed_bump($stats, 'rutas', 'created');
    return $id;
}

function seed_ensure_round_point(PDO $pdo, int $rid, array $point, array $areasByName, array &$stats): int
{
    $cols = seed_table_columns($pdo, 'rondines_puntos');
    seed_require_cols($cols, 'rondines_puntos', ['residencial_id', 'nombre', 'codigo_qr']);
    $existing = seed_fetch_one(
        $pdo,
        "SELECT id FROM rondines_puntos WHERE residencial_id = :rid AND nombre = :nombre LIMIT 1",
        ['rid' => $rid, 'nombre' => $point['nombre']]
    );
    $slug = seed_slug((string)$point['nombre'], false, 45);
    $data = [
        'residencial_id' => $rid,
        'area_id' => $areasByName[$point['area'] ?? $point['nombre']] ?? null,
        'nombre' => $point['nombre'],
        'descripcion' => $point['descripcion'] ?? 'Punto demo de rondín RetailOps.',
        'codigo_qr' => "demo-rp-{$rid}-{$slug}",
        'radio_metros' => (int)($point['radio'] ?? 50),
        'requiere_foto' => (int)($point['requiere_foto'] ?? 0),
        'requiere_observacion' => (int)($point['requiere_observacion'] ?? 0),
        'activo' => 1,
    ];
    if ($existing) {
        seed_update($pdo, 'rondines_puntos', $cols, $data, 'id = :id', ['id' => (int)$existing['id']]);
        seed_bump($stats, 'puntos_rondin', 'reused');
        return (int)$existing['id'];
    }
    $id = seed_insert($pdo, 'rondines_puntos', $cols, $data);
    seed_bump($stats, 'puntos_rondin', 'created');
    return $id;
}

function seed_ensure_route_point(PDO $pdo, int $routeId, int $pointId, int $order, array &$stats): void
{
    $cols = seed_table_columns($pdo, 'rondines_ruta_puntos');
    $existing = seed_fetch_id(
        $pdo,
        "SELECT id FROM rondines_ruta_puntos WHERE ruta_id = :rid AND punto_id = :pid LIMIT 1",
        ['rid' => $routeId, 'pid' => $pointId]
    );
    if ($existing > 0) {
        seed_update($pdo, 'rondines_ruta_puntos', $cols, ['orden' => $order], 'id = :id', ['id' => $existing]);
        seed_bump($stats, 'asociaciones_rondin', 'reused');
        return;
    }
    seed_insert($pdo, 'rondines_ruta_puntos', $cols, [
        'ruta_id' => $routeId,
        'punto_id' => $pointId,
        'orden' => $order,
    ]);
    seed_bump($stats, 'asociaciones_rondin', 'created');
}

function seed_ensure_incident(PDO $pdo, int $rid, int $guardId, array $incident, array $areasByName, array &$stats): int
{
    $cols = seed_table_columns($pdo, 'incidencias');
    seed_require_cols($cols, 'incidencias', ['residencial_id', 'titulo', 'descripcion']);
    $existing = seed_fetch_one(
        $pdo,
        "SELECT id FROM incidencias WHERE residencial_id = :rid AND titulo = :titulo LIMIT 1",
        ['rid' => $rid, 'titulo' => $incident['titulo']]
    );
    $data = [
        'residencial_id' => $rid,
        'area_id' => $areasByName[$incident['area']] ?? null,
        'guardia_id' => $guardId,
        'tipo' => $incident['tipo'] ?? 'seguridad',
        'titulo' => $incident['titulo'],
        'descripcion' => $incident['descripcion'],
        'prioridad' => $incident['prioridad'],
        'estado' => $incident['estado'],
        'origen_tipo' => 'demo_retailops',
    ];
    if ($existing) {
        seed_update($pdo, 'incidencias', $cols, $data, 'id = :id', ['id' => (int)$existing['id']]);
        seed_bump($stats, 'incidencias', 'reused');
        return (int)$existing['id'];
    }
    $id = seed_insert($pdo, 'incidencias', $cols, $data);
    seed_bump($stats, 'incidencias', 'created');
    return $id;
}

function seed_insert_demo_event(PDO $pdo, int $rid, array $event, array &$stats): void
{
    $needle = '%"demo_key":"' . $event['demo_key'] . '"%';
    $existing = seed_fetch_id(
        $pdo,
        "SELECT id FROM bitacora_operativa WHERE residencial_id = :rid AND metadata_json LIKE :needle LIMIT 1",
        ['rid' => $rid, 'needle' => $needle]
    );
    if ($existing > 0) {
        seed_bump($stats, 'eventos_bitacora', 'reused');
        return;
    }

    $cols = seed_table_columns($pdo, 'bitacora_operativa');
    $metadata = $event['metadata'] ?? [];
    $metadata['demo_seed'] = DEMO_SEED_KEY;
    $metadata['demo_key'] = $event['demo_key'];
    seed_insert($pdo, 'bitacora_operativa', $cols, [
        'residencial_id' => $rid,
        'guardia_id' => $event['guardia_id'] ?? null,
        'tipo_origen' => $event['tipo_origen'],
        'origen_id' => $event['origen_id'] ?? null,
        'tipo_evento' => $event['tipo_evento'],
        'resultado' => $event['resultado'] ?? 'permitido',
        'persona_recurrente_id' => $event['persona_recurrente_id'] ?? null,
        'area_id' => $event['area_id'] ?? null,
        'observaciones' => $event['observaciones'],
        'metadata_json' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
        'fecha_hora' => $event['fecha_hora'] ?? date('Y-m-d H:i:s'),
    ]);
    seed_bump($stats, 'eventos_bitacora', 'created');
}

function seed_confirm_or_exit(bool $dryRun, bool $yes, string $name, string $code): void
{
    if ($dryRun || $yes) {
        return;
    }
    seed_warn("Modo real: se insertarán/actualizarán datos demo para {$name} ({$code}).");
    fwrite(STDOUT, "Escribe SI para continuar: ");
    $answer = trim((string)fgets(STDIN));
    if ($answer !== 'SI') {
        seed_warn('Operación cancelada por el usuario.');
        exit(2);
    }
}

try {
    $opts = seed_parse_args($argv);
    if ($opts['help']) {
        seed_usage();
        exit(0);
    }

    if (!isset($pdo) || !$pdo instanceof PDO) {
        throw new RuntimeException('No se encontró $pdo desde config/config.php.');
    }

    $nombre = (string)$opts['nombre'];
    $codigo = seed_service_code($nombre, $opts['codigo']);
    seed_confirm_or_exit((bool)$opts['dry_run'], (bool)$opts['yes'], $nombre, $codigo);

    seed_log('Conexión cargada desde config/config.php.');
    if (function_exists('operational_schema_ensure')) {
        seed_log('Ejecutando operational_schema_ensure($pdo).');
        operational_schema_ensure($pdo);
    }
    if (function_exists('service_profile_schema_ensure')) {
        seed_log('Ejecutando service_profile_schema_ensure($pdo).');
        service_profile_schema_ensure($pdo);
    }

    $stats = [];
    $pdo->beginTransaction();

    $rid = seed_ensure_service($pdo, $nombre, $codigo, $stats);
    seed_upsert_service_profile($pdo, $rid, $stats);

    $areas = [
        ['nombre' => 'Acceso Proveedores', 'codigo' => 'ACC-PROV'],
        ['nombre' => 'Recibo de Mercancía', 'codigo' => 'REC-MERC'],
        ['nombre' => 'Bodega General', 'codigo' => 'BOD-GRAL'],
        ['nombre' => 'Refrigeración', 'codigo' => 'REFRIG'],
        ['nombre' => 'Cuarto Eléctrico', 'codigo' => 'CTO-ELEC'],
        ['nombre' => 'Estacionamiento', 'codigo' => 'ESTAC'],
        ['nombre' => 'Línea de Cajas', 'codigo' => 'CAJAS'],
        ['nombre' => 'CCTV / Monitoreo', 'codigo' => 'CCTV'],
        ['nombre' => 'Salidas de Emergencia', 'codigo' => 'SAL-EMER'],
        ['nombre' => 'Oficinas Administrativas', 'codigo' => 'OF-ADM'],
    ];
    $areaIds = [];
    foreach ($areas as $area) {
        $areaIds[$area['nombre']] = seed_ensure_area($pdo, $rid, $area, $stats);
    }

    $adminRole = seed_ensure_role($pdo, 'admin_residencial', 'Administrador operativo de servicio.', $stats);
    $guardRole = seed_ensure_role($pdo, 'guardia', 'Guardia u operador de seguridad.', $stats);
    $adminId = seed_ensure_user($pdo, $adminRole, 'Admin Walmart Piloto', DEMO_ADMIN_EMAIL, false, $stats);
    $guardId = seed_ensure_user($pdo, $guardRole, 'Guardia Walmart Piloto', DEMO_GUARD_EMAIL, true, $stats);
    seed_ensure_user_assignment($pdo, $adminId, $rid, $stats);
    seed_ensure_user_assignment($pdo, $guardId, $rid, $stats);

    $persons = [
        ['nombre' => 'Juan Carlos Pérez López', 'empresa' => 'Refrigeración del Norte', 'puesto' => 'Técnico externo', 'area' => 'Refrigeración'],
        ['nombre' => 'Luis Fernando Gómez Ruiz', 'empresa' => 'Seguridad CCTV MX', 'puesto' => 'Contratista', 'area' => 'CCTV / Monitoreo'],
        ['nombre' => 'María Fernanda Torres Díaz', 'empresa' => 'Limpieza Integral Pro', 'puesto' => 'Supervisora externa', 'area' => 'Bodega General'],
        ['nombre' => 'Roberto Sánchez Vega', 'empresa' => 'Electromantenimiento SA', 'puesto' => 'Técnico eléctrico', 'area' => 'Cuarto Eléctrico'],
    ];
    $personIds = [];
    foreach ($persons as $person) {
        $personIds[$person['nombre']] = seed_ensure_person($pdo, $rid, $person, $areaIds, $stats);
    }

    $tools = [
        'Radio portátil', 'Escalera telescópica', 'Kit eléctrico básico', 'Lámpara recargable',
        'Chaleco reflejante', 'Detector de voltaje', 'Pinzas de presión', 'Llave ajustable',
        'Extensión eléctrica', 'Cinta de seguridad',
    ];
    $toolIds = [];
    foreach ($tools as $tool) {
        $toolIds[$tool] = seed_ensure_catalog_item($pdo, 'catalogo_herramientas', $rid, ['nombre' => $tool], 'herramientas', $stats);
    }

    $materials = [
        ['nombre' => 'Refacción compresor refrigerador', 'categoria' => 'Refrigeración'],
        ['nombre' => 'Cableado eléctrico calibre 12', 'categoria' => 'Eléctrico'],
        ['nombre' => 'Cámara CCTV tipo domo', 'categoria' => 'CCTV'],
        ['nombre' => 'Fuente de poder 12V', 'categoria' => 'CCTV'],
        ['nombre' => 'Kit de limpieza industrial', 'categoria' => 'Limpieza'],
        ['nombre' => 'Tornillería para rack', 'categoria' => 'Mantenimiento'],
        ['nombre' => 'Cinta antiderrapante', 'categoria' => 'Seguridad'],
        ['nombre' => 'Lámpara LED de emergencia', 'categoria' => 'Emergencia'],
    ];
    $materialIds = [];
    foreach ($materials as $material) {
        $materialIds[$material['nombre']] = seed_ensure_catalog_item($pdo, 'catalogo_materiales', $rid, $material, 'materiales', $stats);
    }

    $configuredPoints = [
        ['nombre' => 'Entrada Bodega', 'area' => 'Bodega General', 'requiere_foto' => 1, 'requiere_observacion' => 0, 'radio' => 50],
        ['nombre' => 'Cuarto Eléctrico', 'area' => 'Cuarto Eléctrico', 'requiere_foto' => 1, 'requiere_observacion' => 1, 'radio' => 30],
        ['nombre' => 'Refrigeración Lácteos', 'area' => 'Refrigeración', 'requiere_foto' => 1, 'requiere_observacion' => 1, 'radio' => 40],
        ['nombre' => 'Acceso Proveedores', 'area' => 'Acceso Proveedores', 'requiere_foto' => 0, 'requiere_observacion' => 0, 'radio' => 50],
        ['nombre' => 'Salida Emergencia Norte', 'area' => 'Salidas de Emergencia', 'requiere_foto' => 1, 'requiere_observacion' => 1, 'radio' => 30],
    ];
    $routeDefinitions = [
        'Ronda apertura' => ['Acceso Proveedores', 'Recibo de Mercancía', 'Bodega General', 'Línea de Cajas', 'Salidas de Emergencia'],
        'Ronda nocturna' => ['Acceso Proveedores', 'Bodega General', 'Refrigeración', 'Cuarto Eléctrico', 'Estacionamiento', 'CCTV / Monitoreo'],
        'Ronda seguridad bodega' => ['Recibo de Mercancía', 'Bodega General', 'Salidas de Emergencia'],
    ];

    $pointDefsByName = [];
    foreach ($configuredPoints as $point) {
        $pointDefsByName[$point['nombre']] = $point;
    }
    foreach ($routeDefinitions as $pointNames) {
        foreach ($pointNames as $pointName) {
            if (!isset($pointDefsByName[$pointName])) {
                $pointDefsByName[$pointName] = [
                    'nombre' => $pointName,
                    'area' => $pointName,
                    'requiere_foto' => 0,
                    'requiere_observacion' => 0,
                    'radio' => 50,
                ];
            }
        }
    }
    $pointIds = [];
    foreach ($pointDefsByName as $pointName => $point) {
        $pointIds[$pointName] = seed_ensure_round_point($pdo, $rid, $point, $areaIds, $stats);
    }
    $routeIds = [];
    foreach (array_keys($routeDefinitions) as $routeName) {
        $routeIds[$routeName] = seed_ensure_route($pdo, $rid, $routeName, $adminId, $stats);
    }
    foreach ($routeDefinitions as $routeName => $pointNames) {
        foreach (array_values($pointNames) as $index => $pointName) {
            seed_ensure_route_point($pdo, $routeIds[$routeName], $pointIds[$pointName], $index + 1, $stats);
        }
    }

    $incidents = [
        ['titulo' => 'Puerta de bodega abierta', 'area' => 'Bodega General', 'prioridad' => 'alta', 'estado' => 'abierta', 'tipo' => 'seguridad', 'descripcion' => 'Puerta de bodega detectada abierta fuera de horario operativo.'],
        ['titulo' => 'Falla en refrigerador de lácteos', 'area' => 'Refrigeración', 'prioridad' => 'alta', 'estado' => 'en_proceso', 'tipo' => 'mantenimiento', 'descripcion' => 'Temperatura irregular reportada en zona de lácteos.'],
        ['titulo' => 'Proveedor fuera de horario', 'area' => 'Acceso Proveedores', 'prioridad' => 'media', 'estado' => 'cerrada', 'tipo' => 'seguridad', 'descripcion' => 'Proveedor llegó fuera de ventana autorizada; evento atendido.'],
        ['titulo' => 'Luz encendida en cuarto eléctrico', 'area' => 'Cuarto Eléctrico', 'prioridad' => 'media', 'estado' => 'abierta', 'tipo' => 'mantenimiento', 'descripcion' => 'Luz de cuarto eléctrico quedó encendida tras revisión.'],
    ];
    $incidentIds = [];
    foreach ($incidents as $incident) {
        $incidentIds[$incident['titulo']] = seed_ensure_incident($pdo, $rid, $guardId, $incident, $areaIds, $stats);
    }

    $now = time();
    $events = [
        ['demo_key' => 'entrada-proveedor-refrigeracion', 'tipo_origen' => 'persona_recurrente', 'origen_id' => $personIds['Juan Carlos Pérez López'] ?? null, 'tipo_evento' => 'entrada', 'persona_recurrente_id' => $personIds['Juan Carlos Pérez López'] ?? null, 'area_id' => $areaIds['Refrigeración'] ?? null, 'guardia_id' => $guardId, 'observaciones' => 'Entrada proveedor refrigeración.', 'fecha_hora' => date('Y-m-d H:i:s', $now - 7200)],
        ['demo_key' => 'salida-proveedor-refrigeracion', 'tipo_origen' => 'persona_recurrente', 'origen_id' => $personIds['Juan Carlos Pérez López'] ?? null, 'tipo_evento' => 'salida', 'persona_recurrente_id' => $personIds['Juan Carlos Pérez López'] ?? null, 'area_id' => $areaIds['Refrigeración'] ?? null, 'guardia_id' => $guardId, 'observaciones' => 'Salida proveedor refrigeración.', 'fecha_hora' => date('Y-m-d H:i:s', $now - 5400)],
        ['demo_key' => 'entrada-cctv', 'tipo_origen' => 'persona_recurrente', 'origen_id' => $personIds['Luis Fernando Gómez Ruiz'] ?? null, 'tipo_evento' => 'entrada', 'persona_recurrente_id' => $personIds['Luis Fernando Gómez Ruiz'] ?? null, 'area_id' => $areaIds['CCTV / Monitoreo'] ?? null, 'guardia_id' => $guardId, 'observaciones' => 'Entrada CCTV.', 'fecha_hora' => date('Y-m-d H:i:s', $now - 5000)],
        ['demo_key' => 'prestamo-herramienta', 'tipo_origen' => 'herramienta', 'origen_id' => $toolIds['Radio portátil'] ?? null, 'tipo_evento' => 'prestamo_herramienta', 'area_id' => $areaIds['CCTV / Monitoreo'] ?? null, 'guardia_id' => $guardId, 'observaciones' => 'Préstamo herramienta demo.', 'fecha_hora' => date('Y-m-d H:i:s', $now - 4500)],
        ['demo_key' => 'devolucion-herramienta', 'tipo_origen' => 'herramienta', 'origen_id' => $toolIds['Radio portátil'] ?? null, 'tipo_evento' => 'devolucion_herramienta', 'area_id' => $areaIds['CCTV / Monitoreo'] ?? null, 'guardia_id' => $guardId, 'observaciones' => 'Devolución herramienta demo.', 'fecha_hora' => date('Y-m-d H:i:s', $now - 3200)],
        ['demo_key' => 'entrada-material', 'tipo_origen' => 'material', 'origen_id' => $materialIds['Refacción compresor refrigerador'] ?? null, 'tipo_evento' => 'entrada_material', 'area_id' => $areaIds['Recibo de Mercancía'] ?? null, 'guardia_id' => $guardId, 'observaciones' => 'Entrada material demo.', 'fecha_hora' => date('Y-m-d H:i:s', $now - 3000)],
        ['demo_key' => 'salida-material', 'tipo_origen' => 'material', 'origen_id' => $materialIds['Cableado eléctrico calibre 12'] ?? null, 'tipo_evento' => 'salida_material', 'area_id' => $areaIds['Cuarto Eléctrico'] ?? null, 'guardia_id' => $guardId, 'observaciones' => 'Salida material demo.', 'fecha_hora' => date('Y-m-d H:i:s', $now - 2600)],
        ['demo_key' => 'rondin-iniciado', 'tipo_origen' => 'rondin', 'origen_id' => $routeIds['Ronda nocturna'] ?? null, 'tipo_evento' => 'rondin_iniciado', 'area_id' => $areaIds['Acceso Proveedores'] ?? null, 'guardia_id' => $guardId, 'observaciones' => 'Rondín iniciado.', 'fecha_hora' => date('Y-m-d H:i:s', $now - 2200)],
        ['demo_key' => 'rondin-punto-correcto', 'tipo_origen' => 'rondin', 'origen_id' => $pointIds['Acceso Proveedores'] ?? null, 'tipo_evento' => 'rondin_punto_correcto', 'area_id' => $areaIds['Acceso Proveedores'] ?? null, 'guardia_id' => $guardId, 'observaciones' => 'Punto correcto demo.', 'fecha_hora' => date('Y-m-d H:i:s', $now - 2000)],
        ['demo_key' => 'rondin-punto-anomalia', 'tipo_origen' => 'rondin', 'origen_id' => $pointIds['Cuarto Eléctrico'] ?? null, 'tipo_evento' => 'rondin_punto_anomalia', 'resultado' => 'alerta', 'area_id' => $areaIds['Cuarto Eléctrico'] ?? null, 'guardia_id' => $guardId, 'observaciones' => 'Punto con anomalía demo.', 'fecha_hora' => date('Y-m-d H:i:s', $now - 1800)],
        ['demo_key' => 'rondin-finalizado', 'tipo_origen' => 'rondin', 'origen_id' => $routeIds['Ronda nocturna'] ?? null, 'tipo_evento' => 'rondin_finalizado', 'area_id' => $areaIds['CCTV / Monitoreo'] ?? null, 'guardia_id' => $guardId, 'observaciones' => 'Rondín finalizado.', 'fecha_hora' => date('Y-m-d H:i:s', $now - 1600)],
    ];
    foreach ($events as $event) {
        $event['metadata'] = array_merge($event['metadata'] ?? [], ['servicio_codigo' => $codigo]);
        seed_insert_demo_event($pdo, $rid, $event, $stats);
    }

    if ($opts['dry_run']) {
        $pdo->rollBack();
        seed_warn('DRY RUN: cambios revertidos con rollback.');
    } else {
        $pdo->commit();
        seed_ok('Cambios confirmados con commit.');
    }

    echo "\nResumen RetailOps Demo\n";
    echo "----------------------\n";
    echo "Servicio: {$nombre} ({$codigo})\n";
    echo "residencial_id: {$rid}\n";
    echo "Admin demo: " . DEMO_ADMIN_EMAIL . " / " . DEMO_PASSWORD . "\n";
    echo "Guardia demo: " . DEMO_GUARD_EMAIL . " / " . DEMO_PASSWORD . "\n";
    echo "PIN personal recurrente demo: " . DEMO_PERSON_PIN . "\n\n";
    foreach ($stats as $bucket => $values) {
        $created = (int)($values['created'] ?? 0);
        $updated = (int)($values['updated'] ?? 0);
        $reused = (int)($values['reused'] ?? 0);
        echo sprintf("- %-24s creados=%d actualizados=%d reutilizados=%d\n", $bucket, $created, $updated, $reused);
    }
    echo "\nComandos:\n";
    echo "  php scripts/seed_retailops_demo.php --dry-run\n";
    echo "  php scripts/seed_retailops_demo.php --yes\n";
    echo "  php scripts/seed_retailops_demo.php --nombre=\"{$nombre}\" --yes\n";
    exit(0);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "[ERROR] " . $e->getMessage() . "\n");
    exit(1);
}
