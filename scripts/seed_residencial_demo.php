<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script solo puede ejecutarse desde terminal.\n");
    exit(1);
}

$root = dirname(__DIR__);
require_once $root . '/config/config.php';

$optionalConfigFiles = [
    '/config/api_helpers.php',
    '/config/operational_mode.php',
    '/config/service_profile.php',
    '/config/residencial_helpers.php',
    '/config/resident_access.php',
    '/config/comunicados_helpers.php',
];

foreach ($optionalConfigFiles as $relativePath) {
    $path = $root . $relativePath;
    if (is_file($path)) {
        require_once $path;
    }
}

const RES_DEMO_SEED_KEY = 'residencial_las_palmas';
const RES_DEMO_PASSWORD = 'Demo12345!';
const RES_DEMO_ADMIN_EMAIL = 'admin.residencial.demo@osgate.local';
const RES_DEMO_GUARD_EMAIL = 'guardia.residencial.demo@osgate.local';

function res_seed_parse_args(array $argv): array
{
    $opts = [
        'dry_run' => false,
        'yes' => false,
        'nombre' => 'Residencial Las Palmas Demo',
        'codigo' => 'RES-PALMAS-DEMO',
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
            $opts['nombre'] = trim(substr($arg, strlen('--nombre=')), "\"' \t\n\r\0\x0B") ?: 'Residencial Las Palmas Demo';
            if ($opts['codigo'] === 'RES-PALMAS-DEMO') {
                $opts['codigo'] = null;
            }
            continue;
        }
        if (str_starts_with($arg, '--codigo=')) {
            $opts['codigo'] = trim(substr($arg, strlen('--codigo=')), "\"' \t\n\r\0\x0B") ?: null;
            continue;
        }

        throw new RuntimeException("Opcion no reconocida: {$arg}");
    }

    return $opts;
}

function res_seed_usage(): void
{
    echo "Uso:\n";
    echo "  php scripts/seed_residencial_demo.php --dry-run\n";
    echo "  php scripts/seed_residencial_demo.php --yes\n";
    echo "  php scripts/seed_residencial_demo.php --nombre=\"Residencial Las Palmas Demo\" --codigo=\"RES-PALMAS-DEMO\" --yes\n";
    echo "\nOpciones:\n";
    echo "  --dry-run      Ejecuta la logica y hace rollback al final.\n";
    echo "  --nombre=...   Nombre del residencial demo.\n";
    echo "  --codigo=...   Codigo del residencial. Si se omite, se deriva del nombre.\n";
    echo "  --yes, -y      Omite confirmacion interactiva en modo real.\n";
}

function res_seed_log(string $message): void
{
    echo "[INFO] {$message}\n";
}

function res_seed_ok(string $message): void
{
    echo "[OK]   {$message}\n";
}

function res_seed_warn(string $message): void
{
    echo "[WARN] {$message}\n";
}

function res_seed_slug(string $value, bool $upper = false, int $max = 80): string
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

function res_seed_service_code(string $nombre, ?string $codigo): string
{
    if ($codigo !== null && trim($codigo) !== '') {
        return res_seed_slug($codigo, true, 50);
    }
    if (strcasecmp(trim($nombre), 'Residencial Las Palmas Demo') === 0) {
        return 'RES-PALMAS-DEMO';
    }
    return res_seed_slug($nombre, true, 50);
}

function res_seed_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = :table
    ");
    $stmt->execute(['table' => $table]);
    return (int)$stmt->fetchColumn() > 0;
}

function res_seed_table_columns(PDO $pdo, string $table): array
{
    static $cache = [];
    if (isset($cache[$table])) {
        return $cache[$table];
    }
    if (!res_seed_table_exists($pdo, $table)) {
        throw new RuntimeException("La tabla {$table} no existe.");
    }
    $stmt = $pdo->query("DESCRIBE `{$table}`");
    $cols = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $cols[(string)$row['Field']] = $row;
    }
    $cache[$table] = $cols;
    return $cols;
}

function res_seed_has_col(array $columns, string $column): bool
{
    return array_key_exists($column, $columns);
}

function res_seed_require_cols(array $columns, string $table, array $required): void
{
    $missing = array_values(array_filter($required, static fn(string $col): bool => !array_key_exists($col, $columns)));
    if ($missing) {
        throw new RuntimeException("La tabla {$table} no tiene columnas requeridas: " . implode(', ', $missing));
    }
}

function res_seed_pick_data(array $columns, array $data): array
{
    return array_filter(
        $data,
        static fn(string $col): bool => array_key_exists($col, $columns),
        ARRAY_FILTER_USE_KEY
    );
}

function res_seed_with_insert_timestamps(array $columns, array $data): array
{
    $now = date('Y-m-d H:i:s');
    if (res_seed_has_col($columns, 'created_at') && !array_key_exists('created_at', $data)) {
        $data['created_at'] = $now;
    }
    if (res_seed_has_col($columns, 'updated_at') && !array_key_exists('updated_at', $data)) {
        $data['updated_at'] = $now;
    }
    return $data;
}

function res_seed_with_update_timestamp(array $columns, array $data): array
{
    if (res_seed_has_col($columns, 'updated_at') && !array_key_exists('updated_at', $data)) {
        $data['updated_at'] = date('Y-m-d H:i:s');
    }
    return $data;
}

function res_seed_insert(PDO $pdo, string $table, array $columns, array $data): int
{
    $data = res_seed_pick_data($columns, res_seed_with_insert_timestamps($columns, $data));
    if (!$data) {
        throw new RuntimeException("No hay columnas validas para insertar en {$table}.");
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

function res_seed_update(PDO $pdo, string $table, array $columns, array $data, string $where, array $params): void
{
    $data = res_seed_pick_data($columns, res_seed_with_update_timestamp($columns, $data));
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

function res_seed_fetch_one(PDO $pdo, string $sql, array $params = []): ?array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function res_seed_fetch_id(PDO $pdo, string $sql, array $params = []): int
{
    $row = res_seed_fetch_one($pdo, $sql, $params);
    return $row ? (int)reset($row) : 0;
}

function res_seed_bump(array &$stats, string $bucket, string $status): void
{
    $stats[$bucket][$status] = ($stats[$bucket][$status] ?? 0) + 1;
}

function res_seed_enum_values(array $columns, string $column): ?array
{
    if (!isset($columns[$column]['Type'])) {
        return null;
    }
    $type = (string)$columns[$column]['Type'];
    if (!preg_match("/^enum\\((.*)\\)$/i", $type, $m)) {
        return null;
    }
    $raw = str_getcsv($m[1], ',', "'");
    return array_map('strval', $raw);
}

function res_seed_pick_enum(array $columns, string $column, array $preferred, string $fallback): string
{
    $values = res_seed_enum_values($columns, $column);
    if (!$values) {
        return $preferred[0] ?? $fallback;
    }
    foreach ($preferred as $value) {
        if (in_array($value, $values, true)) {
            return $value;
        }
    }
    return in_array($fallback, $values, true) ? $fallback : (string)$values[0];
}

function res_seed_confirm_or_exit(bool $dryRun, bool $yes, string $name, string $code): void
{
    if ($dryRun || $yes) {
        return;
    }
    res_seed_warn("Modo real: se insertaran/actualizaran datos demo para {$name} ({$code}).");
    fwrite(STDOUT, "Escribe SI para continuar: ");
    $answer = trim((string)fgets(STDIN));
    if ($answer !== 'SI') {
        res_seed_warn('Operacion cancelada por el usuario.');
        exit(2);
    }
}

function res_seed_ensure_schema_helpers(PDO $pdo): void
{
    $helpers = [
        'operational_schema_ensure',
        'service_profile_schema_ensure',
        'resident_access_ensure_schema',
        'comunicados_schema_ensure',
        'residential_home_services_schema_ensure',
    ];

    foreach ($helpers as $helper) {
        if (function_exists($helper)) {
            res_seed_log("Ejecutando {$helper}(\$pdo).");
            $helper($pdo);
        }
    }

    if (function_exists('resident_vehicle_access_schema_ensure')) {
        $autosTable = function_exists('resolve_autos_table') ? resolve_autos_table($pdo) : null;
        res_seed_log('Ejecutando resident_vehicle_access_schema_ensure($pdo).');
        resident_vehicle_access_schema_ensure($pdo, $autosTable);
    }
}

function res_seed_ensure_service(PDO $pdo, string $nombre, string $codigo, array &$stats): int
{
    $cols = res_seed_table_columns($pdo, 'residenciales');
    res_seed_require_cols($cols, 'residenciales', ['id', 'nombre', 'codigo']);

    $data = [
        'nombre' => $nombre,
        'codigo' => $codigo,
        'tipo' => 'residencial',
        'modo_operacion' => 'residencial',
        'pais' => 'Mexico',
        'estado' => 'Estado de Mexico',
        'ciudad' => 'Tultepec',
        'colonia' => 'Las Palmas',
        'calle' => 'Avenida Principal',
        'numero_exterior' => '100',
        'numero_interior' => null,
        'codigo_postal' => '54980',
        'nombre_contacto' => 'Administracion Las Palmas',
        'telefono_contacto' => '5512345678',
        'email_contacto' => 'admin.laspalmas.demo@osgate.local',
        'estatus_plan' => 'activo',
        'zona_horaria' => 'America/Mexico_City',
        'permite_qr' => 1,
        'permite_trabajadores_recurrentes' => 1,
        'activo' => 1,
    ];

    $existing = res_seed_fetch_one($pdo, "SELECT id FROM residenciales WHERE codigo = :codigo LIMIT 1", ['codigo' => $codigo]);
    if ($existing) {
        res_seed_update($pdo, 'residenciales', $cols, $data, 'id = :id', ['id' => (int)$existing['id']]);
        res_seed_bump($stats, 'residenciales', 'updated');
        return (int)$existing['id'];
    }

    $id = res_seed_insert($pdo, 'residenciales', $cols, $data);
    res_seed_bump($stats, 'residenciales', 'created');
    return $id;
}

function res_seed_upsert_service_profile(PDO $pdo, int $rid, array &$stats): void
{
    $cols = res_seed_table_columns($pdo, 'residenciales_servicio_config');
    res_seed_require_cols($cols, 'residenciales_servicio_config', ['residencial_id']);

    $on = [
        'habilita_admin_operativo',
        'habilita_guardia',
        'habilita_residente',
        'habilita_unidades',
        'habilita_residentes_catalogo',
        'habilita_guardias_catalogo',
        'habilita_guardias_admin_actions',
        'habilita_autos',
        'habilita_visitas_residente',
        'habilita_paqueteria',
        'habilita_pagos',
        'habilita_comunicados',
        'habilita_servicios_directorio',
        'habilita_control_acceso',
        'habilita_incidencias',
        'habilita_herramientas',
        'habilita_bitacora_operativa',
    ];
    $off = [
        'habilita_rondines',
        'habilita_reportes_operativos',
        'habilita_proveedores',
        'habilita_ordenes_servicio',
    ];

    $data = ['residencial_id' => $rid, 'preset_servicio' => 'residencial'];
    foreach ($on as $flag) {
        $data[$flag] = 1;
    }
    foreach ($off as $flag) {
        $data[$flag] = 0;
    }

    $exists = res_seed_fetch_id($pdo, "SELECT residencial_id FROM residenciales_servicio_config WHERE residencial_id = :rid LIMIT 1", ['rid' => $rid]);
    if ($exists > 0) {
        unset($data['residencial_id']);
        res_seed_update($pdo, 'residenciales_servicio_config', $cols, $data, 'residencial_id = :rid', ['rid' => $rid]);
        res_seed_bump($stats, 'perfil_servicio', 'updated');
        return;
    }

    res_seed_insert($pdo, 'residenciales_servicio_config', $cols, $data);
    res_seed_bump($stats, 'perfil_servicio', 'created');
}

function res_seed_ensure_role(PDO $pdo, string $role, string $description, array &$stats): int
{
    $id = res_seed_fetch_id($pdo, "SELECT id FROM tipos_usuario WHERE nombre = :nombre LIMIT 1", ['nombre' => $role]);
    if ($id > 0) {
        res_seed_bump($stats, 'roles', 'reused');
        return $id;
    }

    $cols = res_seed_table_columns($pdo, 'tipos_usuario');
    res_seed_require_cols($cols, 'tipos_usuario', ['nombre']);
    $id = res_seed_insert($pdo, 'tipos_usuario', $cols, [
        'nombre' => $role,
        'descripcion' => $description,
    ]);
    res_seed_bump($stats, 'roles', 'created');
    return $id;
}

function res_seed_ensure_user(PDO $pdo, int $roleId, array $user, array &$stats): int
{
    $cols = res_seed_table_columns($pdo, 'users');
    res_seed_require_cols($cols, 'users', ['tipo_usuario_id', 'name', 'email']);
    $passwordCol = res_seed_has_col($cols, 'password_hash') ? 'password_hash' : (res_seed_has_col($cols, 'password') ? 'password' : null);
    if ($passwordCol === null) {
        throw new RuntimeException('La tabla users no tiene password_hash ni password.');
    }

    $data = [
        'tipo_usuario_id' => $roleId,
        'name' => $user['name'],
        'email' => $user['email'],
        'telefono' => $user['telefono'] ?? null,
        $passwordCol => password_hash(RES_DEMO_PASSWORD, PASSWORD_DEFAULT),
        'is_active' => 1,
        'guardia_en_servicio' => !empty($user['guardia_en_servicio']) ? 1 : 0,
    ];

    $existing = res_seed_fetch_one($pdo, "SELECT id FROM users WHERE email = :email LIMIT 1", ['email' => $user['email']]);
    if ($existing) {
        res_seed_update($pdo, 'users', $cols, $data, 'id = :id', ['id' => (int)$existing['id']]);
        res_seed_bump($stats, 'usuarios', 'updated');
        return (int)$existing['id'];
    }

    $id = res_seed_insert($pdo, 'users', $cols, $data);
    res_seed_bump($stats, 'usuarios', 'created');
    return $id;
}

function res_seed_ensure_user_assignment(PDO $pdo, int $userId, int $rid, array &$stats): void
{
    $cols = res_seed_table_columns($pdo, 'usuarios_residenciales');
    res_seed_require_cols($cols, 'usuarios_residenciales', ['user_id', 'residencial_id']);
    $existing = res_seed_fetch_id(
        $pdo,
        "SELECT id FROM usuarios_residenciales WHERE user_id = :uid AND residencial_id = :rid LIMIT 1",
        ['uid' => $userId, 'rid' => $rid]
    );
    if ($existing > 0) {
        res_seed_update($pdo, 'usuarios_residenciales', $cols, ['es_principal' => 1], 'id = :id', ['id' => $existing]);
        res_seed_bump($stats, 'asignaciones_usuario', 'reused');
        return;
    }

    res_seed_insert($pdo, 'usuarios_residenciales', $cols, [
        'user_id' => $userId,
        'residencial_id' => $rid,
        'es_principal' => 1,
    ]);
    res_seed_bump($stats, 'asignaciones_usuario', 'created');
}

function res_seed_ensure_unit(PDO $pdo, int $rid, array $unit, array &$stats): int
{
    $cols = res_seed_table_columns($pdo, 'unidades');
    res_seed_require_cols($cols, 'unidades', ['residencial_id', 'clave']);

    $existing = res_seed_fetch_one(
        $pdo,
        "SELECT id FROM unidades WHERE residencial_id = :rid AND clave = :clave LIMIT 1",
        ['rid' => $rid, 'clave' => $unit['clave']]
    );
    $data = [
        'residencial_id' => $rid,
        'tipo' => $unit['tipo'],
        'clave' => $unit['clave'],
        'calle' => $unit['calle'] ?? null,
        'numero_exterior' => $unit['numero_exterior'] ?? null,
        'numero_interior' => $unit['numero_interior'] ?? null,
        'edificio' => $unit['edificio'] ?? null,
        'piso' => $unit['piso'] ?? null,
        'torre' => $unit['torre'] ?? ($unit['edificio'] ?? null),
        'nivel' => $unit['nivel'] ?? ($unit['piso'] ?? null),
        'referencia' => $unit['referencia'] ?? null,
        'activo' => 1,
    ];

    if ($existing) {
        res_seed_update($pdo, 'unidades', $cols, $data, 'id = :id', ['id' => (int)$existing['id']]);
        res_seed_bump($stats, 'unidades', 'reused');
        return (int)$existing['id'];
    }

    $id = res_seed_insert($pdo, 'unidades', $cols, $data);
    res_seed_bump($stats, 'unidades', 'created');
    return $id;
}

function res_seed_ensure_resident_unit(PDO $pdo, int $userId, int $unitId, array &$stats): void
{
    $cols = res_seed_table_columns($pdo, 'residentes_unidades');
    res_seed_require_cols($cols, 'residentes_unidades', ['user_id', 'unidad_id']);

    $existing = res_seed_fetch_id(
        $pdo,
        "SELECT id FROM residentes_unidades WHERE user_id = :uid AND unidad_id = :unidad LIMIT 1",
        ['uid' => $userId, 'unidad' => $unitId]
    );
    $data = [
        'user_id' => $userId,
        'unidad_id' => $unitId,
        'es_titular' => 1,
        'activo' => 1,
        'acceso_baneado_manual' => 0,
        'acceso_baneo_motivo' => null,
        'acceso_baneado_at' => null,
        'acceso_estado_actualizado_at' => date('Y-m-d H:i:s'),
    ];

    if ($existing > 0) {
        unset($data['user_id'], $data['unidad_id']);
        res_seed_update($pdo, 'residentes_unidades', $cols, $data, 'id = :id', ['id' => $existing]);
        res_seed_bump($stats, 'residentes_unidades', 'reused');
        return;
    }

    res_seed_insert($pdo, 'residentes_unidades', $cols, $data);
    res_seed_bump($stats, 'residentes_unidades', 'created');
}

function res_seed_resolve_autos_table(PDO $pdo): ?string
{
    if (function_exists('resolve_autos_table')) {
        $table = resolve_autos_table($pdo);
        if ($table) {
            return $table;
        }
    }
    foreach (['autos', 'autos_residentes', 'autos_usuarios', 'vehiculos', 'vehiculos_residentes', 'residentes_autos'] as $table) {
        if (res_seed_table_exists($pdo, $table)) {
            return $table;
        }
    }
    return null;
}

function res_seed_ensure_auto(PDO $pdo, string $table, int $rid, array $auto, array &$stats): int
{
    $cols = res_seed_table_columns($pdo, $table);
    res_seed_require_cols($cols, $table, ['residencial_id', 'placas']);
    $placas = strtoupper(str_replace([' ', '-', '_'], '', (string)$auto['placas']));
    $existing = res_seed_fetch_one(
        $pdo,
        "SELECT id FROM `{$table}` WHERE residencial_id = :rid AND REPLACE(REPLACE(UPPER(placas), '-', ''), ' ', '') = :placas LIMIT 1",
        ['rid' => $rid, 'placas' => $placas]
    );
    $data = [
        'residencial_id' => $rid,
        'unidad_id' => $auto['unidad_id'],
        'propietario_user_id' => $auto['residente_id'],
        'user_id' => $auto['residente_id'],
        'residente_id' => $auto['residente_id'],
        'placas' => $placas,
        'modelo' => $auto['modelo'],
        'color' => $auto['color'],
        'tag_id' => $auto['tag_id'],
        'notas' => 'Auto demo residencial Las Palmas.',
        'activo' => 1,
    ];

    if ($existing) {
        res_seed_update($pdo, $table, $cols, $data, 'id = :id', ['id' => (int)$existing['id']]);
        res_seed_bump($stats, 'autos', 'reused');
        return (int)$existing['id'];
    }

    $id = res_seed_insert($pdo, $table, $cols, $data);
    res_seed_bump($stats, 'autos', 'created');
    return $id;
}

function res_seed_ensure_vehicle_access(PDO $pdo, int $rid, array $access, array &$stats): void
{
    if (!res_seed_table_exists($pdo, 'accesos_residentes')) {
        res_seed_warn('No existe accesos_residentes; se omiten accesos vehiculares demo.');
        return;
    }
    $cols = res_seed_table_columns($pdo, 'accesos_residentes');
    $metadata = [
        'demo_seed' => RES_DEMO_SEED_KEY,
        'demo_key' => $access['demo_key'],
        'servicio_codigo' => $access['servicio_codigo'],
    ];
    $needle = '%"demo_key":"' . $access['demo_key'] . '"%';
    $existing = res_seed_fetch_id(
        $pdo,
        "SELECT id FROM accesos_residentes WHERE residencial_id = :rid AND metadata_json LIKE :needle LIMIT 1",
        ['rid' => $rid, 'needle' => $needle]
    );
    if ($existing > 0) {
        res_seed_bump($stats, 'accesos_vehiculares', 'reused');
        return;
    }

    res_seed_insert($pdo, 'accesos_residentes', $cols, [
        'residencial_id' => $rid,
        'residente_id' => $access['residente_id'],
        'unidad_id' => $access['unidad_id'],
        'auto_id' => $access['auto_id'],
        'tag_id' => $access['tag_id'],
        'tipo_movimiento' => $access['tipo_movimiento'],
        'fecha_hora' => $access['fecha_hora'],
        'fuente' => 'demo_seed',
        'metadata_json' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
    ]);
    res_seed_bump($stats, 'accesos_vehiculares', 'created');
}

function res_seed_ensure_visit(PDO $pdo, int $rid, array $visit, array &$stats): int
{
    $cols = res_seed_table_columns($pdo, 'visitas');
    res_seed_require_cols($cols, 'visitas', ['residencial_id', 'unidad_id', 'residente_id', 'codigo_acceso']);
    $existing = res_seed_fetch_one($pdo, "SELECT id FROM visitas WHERE codigo_acceso = :code LIMIT 1", ['code' => $visit['codigo_acceso']]);
    $estado = res_seed_pick_enum($cols, 'estado', ['pendiente', $visit['estado'], 'activa', 'programada'], 'pendiente');
    $data = [
        'residencial_id' => $rid,
        'unidad_id' => $visit['unidad_id'],
        'residente_id' => $visit['residente_id'],
        'tipo' => $visit['tipo'] ?? 'visita',
        'nombre_visitante' => $visit['nombre_visitante'],
        'motivo' => $visit['motivo'],
        'placa_vehiculo' => $visit['placa_vehiculo'],
        'fecha_desde' => $visit['fecha_desde'],
        'fecha_hasta' => $visit['fecha_hasta'],
        'fecha_visita' => $visit['fecha_desde'],
        'hora_desde' => $visit['hora_desde'],
        'hora_hasta' => $visit['hora_hasta'],
        'hora_inicio' => $visit['hora_desde'],
        'hora_fin' => $visit['hora_hasta'],
        'uso_unico' => $visit['uso_unico'],
        'codigo_acceso' => $visit['codigo_acceso'],
        'estado' => $estado,
        'notas' => 'Visita demo Residencial Las Palmas.',
    ];

    if ($existing) {
        res_seed_update($pdo, 'visitas', $cols, $data, 'id = :id', ['id' => (int)$existing['id']]);
        res_seed_bump($stats, 'visitas', 'reused');
        return (int)$existing['id'];
    }

    $id = res_seed_insert($pdo, 'visitas', $cols, $data);
    res_seed_bump($stats, 'visitas', 'created');
    return $id;
}

function res_seed_ensure_guard_access(PDO $pdo, int $guardId, int $visitId, array $access, array &$stats): void
{
    if (!res_seed_table_exists($pdo, 'accesos_guardia')) {
        res_seed_warn('No existe accesos_guardia; se omite historial de accesos Guardia.');
        return;
    }
    $cols = res_seed_table_columns($pdo, 'accesos_guardia');
    $metadata = [
        'demo_seed' => RES_DEMO_SEED_KEY,
        'demo_key' => $access['demo_key'],
        'servicio_codigo' => $access['servicio_codigo'],
        'unidad_id' => $access['unidad_id'] ?? null,
        'residente_id' => $access['residente_id'] ?? null,
    ];
    $hasMeta = res_seed_has_col($cols, 'metadata_json');
    if ($hasMeta) {
        $needle = '%"demo_key":"' . $access['demo_key'] . '"%';
        $existing = res_seed_fetch_id(
            $pdo,
            "SELECT id FROM accesos_guardia WHERE metadata_json LIKE :needle LIMIT 1",
            ['needle' => $needle]
        );
        if ($existing > 0) {
            res_seed_bump($stats, 'accesos_guardia', 'reused');
            return;
        }
    }

    if (!$hasMeta) {
        $existing = res_seed_fetch_id(
            $pdo,
            "SELECT id FROM accesos_guardia WHERE visita_id = :vid AND guardia_id = :gid AND tipo_evento = :tipo AND resultado = :resultado LIMIT 1",
            [
                'vid' => $visitId,
                'gid' => $guardId,
                'tipo' => $access['tipo_evento'],
                'resultado' => $access['resultado'],
            ]
        );
        if ($existing > 0) {
            res_seed_bump($stats, 'accesos_guardia', 'reused');
            return;
        }
    }

    res_seed_insert($pdo, 'accesos_guardia', $cols, [
        'visita_id' => $visitId,
        'guardia_id' => $guardId,
        'tipo_evento' => $access['tipo_evento'],
        'resultado' => $access['resultado'],
        'observaciones' => $access['observaciones'],
        'origen_acceso' => $access['origen_acceso'] ?? 'visita_qr',
        'residente_id' => $access['residente_id'] ?? null,
        'unidad_id' => $access['unidad_id'] ?? null,
        'metadata_json' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
        'created_at' => $access['fecha_hora'] ?? date('Y-m-d H:i:s'),
        'fecha_hora' => $access['fecha_hora'] ?? date('Y-m-d H:i:s'),
    ]);
    res_seed_bump($stats, 'accesos_guardia', 'created');
}

function res_seed_demo_images(string $root, bool $dryRun, array &$stats): array
{
    $dir = $root . '/assets/uploads/demo_residencial';
    $publicBase = 'assets/uploads/demo_residencial';
    $images = [
        'mantenimiento_agua.svg' => ['#0f766e', 'Mantenimiento de agua'],
        'seguridad_acceso.svg' => ['#1d4ed8', 'Seguridad en accesos'],
        'reunion_vecinal.svg' => ['#7c3aed', 'Reunion vecinal'],
        'incidencia_lampara.svg' => ['#ca8a04', 'Incidencia luminaria'],
        'paquete_demo.svg' => ['#be123c', 'Paquete demo'],
    ];

    if ($dryRun) {
        foreach ($images as $file => $_) {
            res_seed_bump($stats, 'imagenes_demo', 'dry_run');
        }
        return array_map(static fn(string $file): string => $publicBase . '/' . $file, array_keys($images));
    }

    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException("No se pudo crear carpeta de imagenes demo: {$dir}");
    }

    $urls = [];
    foreach ($images as $file => [$color, $label]) {
        $path = $dir . '/' . $file;
        if (!is_file($path)) {
            $svg = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
                . '<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="675" viewBox="0 0 1200 675">'
                . '<rect width="1200" height="675" fill="' . htmlspecialchars($color, ENT_QUOTES, 'UTF-8') . '"/>'
                . '<rect x="70" y="70" width="1060" height="535" rx="28" fill="rgba(255,255,255,0.16)"/>'
                . '<text x="100" y="345" fill="#fff" font-family="Arial, sans-serif" font-size="64" font-weight="700">'
                . htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
                . '</text><text x="100" y="420" fill="#e5e7eb" font-family="Arial, sans-serif" font-size="32">Residencial Las Palmas Demo</text>'
                . '</svg>';
            file_put_contents($path, $svg);
            res_seed_bump($stats, 'imagenes_demo', 'created');
        } else {
            res_seed_bump($stats, 'imagenes_demo', 'reused');
        }
        $urls[$file] = $publicBase . '/' . $file;
    }

    return $urls;
}

function res_seed_ensure_comunicado(PDO $pdo, int $rid, int $adminId, array $comunicado, array &$stats): int
{
    if (!res_seed_table_exists($pdo, 'comunicados_residenciales')) {
        res_seed_warn('No existe comunicados_residenciales; se omiten comunicados demo.');
        return 0;
    }
    $cols = res_seed_table_columns($pdo, 'comunicados_residenciales');
    res_seed_require_cols($cols, 'comunicados_residenciales', ['residencial_id', 'titulo']);
    $existing = res_seed_fetch_one(
        $pdo,
        "SELECT id FROM comunicados_residenciales WHERE residencial_id = :rid AND titulo = :titulo LIMIT 1",
        ['rid' => $rid, 'titulo' => $comunicado['titulo']]
    );
    $data = [
        'residencial_id' => $rid,
        'titulo' => $comunicado['titulo'],
        'mensaje' => $comunicado['mensaje'],
        'imagen_url' => $comunicado['imagen_url'],
        'tipo' => $comunicado['tipo'],
        'prioridad' => $comunicado['prioridad'],
        'fecha_publicacion' => date('Y-m-d'),
        'fecha_expiracion' => null,
        'visible_para_residentes' => 1,
        'estado' => res_seed_pick_enum($cols, 'estado', ['publicado'], 'publicado'),
        'creado_por' => $adminId,
        'actualizado_por' => $adminId,
        'creado_at' => date('Y-m-d H:i:s'),
        'reglamentos_residenciales' => '',
    ];
    if ($existing) {
        res_seed_update($pdo, 'comunicados_residenciales', $cols, $data, 'id = :id', ['id' => (int)$existing['id']]);
        res_seed_bump($stats, 'comunicados', 'reused');
        return (int)$existing['id'];
    }
    $id = res_seed_insert($pdo, 'comunicados_residenciales', $cols, $data);
    res_seed_bump($stats, 'comunicados', 'created');
    return $id;
}

function res_seed_ensure_incident(PDO $pdo, int $rid, array $incident, array &$stats): int
{
    if (!res_seed_table_exists($pdo, 'incidencias')) {
        res_seed_warn('No existe incidencias; se omiten incidencias demo.');
        return 0;
    }
    $cols = res_seed_table_columns($pdo, 'incidencias');
    res_seed_require_cols($cols, 'incidencias', ['residencial_id', 'titulo', 'descripcion']);
    $existing = res_seed_fetch_one(
        $pdo,
        "SELECT id FROM incidencias WHERE residencial_id = :rid AND titulo = :titulo LIMIT 1",
        ['rid' => $rid, 'titulo' => $incident['titulo']]
    );
    $data = [
        'residencial_id' => $rid,
        'unidad_id' => $incident['unidad_id'],
        'residente_id' => $incident['residente_id'] ?? null,
        'guardia_id' => $incident['guardia_id'] ?? null,
        'tipo' => res_seed_pick_enum($cols, 'tipo', [$incident['tipo'], 'seguridad', 'mantenimiento', 'servicio', 'vecino', 'otro'], 'otro'),
        'titulo' => $incident['titulo'],
        'descripcion' => $incident['descripcion'],
        'prioridad' => res_seed_pick_enum($cols, 'prioridad', [$incident['prioridad'], 'media', 'baja', 'alta'], 'media'),
        'estado' => res_seed_pick_enum($cols, 'estado', [$incident['estado'], 'abierta'], 'abierta'),
        'origen_tipo' => 'demo_residencial',
    ];
    if ($existing) {
        res_seed_update($pdo, 'incidencias', $cols, $data, 'id = :id', ['id' => (int)$existing['id']]);
        res_seed_bump($stats, 'incidencias', 'reused');
        return (int)$existing['id'];
    }
    $id = res_seed_insert($pdo, 'incidencias', $cols, $data);
    res_seed_bump($stats, 'incidencias', 'created');
    return $id;
}

function res_seed_ensure_package(PDO $pdo, int $rid, array $package, array &$stats): int
{
    if (!res_seed_table_exists($pdo, 'paqueteria')) {
        res_seed_warn('No existe paqueteria; se omiten paquetes demo.');
        return 0;
    }
    $cols = res_seed_table_columns($pdo, 'paqueteria');
    res_seed_require_cols($cols, 'paqueteria', ['residencial_id', 'unidad_id']);
    $existing = res_seed_fetch_one(
        $pdo,
        "SELECT id FROM paqueteria WHERE codigo_rastreo = :codigo LIMIT 1",
        ['codigo' => $package['codigo_rastreo']]
    );
    $estado = res_seed_pick_enum($cols, 'estado', [$package['estado'], 'registrado', 'entregado', 'devuelto'], 'registrado');
    $data = [
        'residencial_id' => $rid,
        'unidad_id' => $package['unidad_id'],
        'residente_id' => $package['residente_id'],
        'guardia_id' => $package['guardia_id'],
        'empresa' => $package['empresa'],
        'descripcion' => $package['descripcion'],
        'codigo_rastreo' => $package['codigo_rastreo'],
        'estado' => $estado,
        'notas' => $package['notas'],
    ];
    if ($existing) {
        res_seed_update($pdo, 'paqueteria', $cols, $data, 'id = :id', ['id' => (int)$existing['id']]);
        res_seed_bump($stats, 'paqueteria', 'reused');
        return (int)$existing['id'];
    }
    $id = res_seed_insert($pdo, 'paqueteria', $cols, $data);
    res_seed_bump($stats, 'paqueteria', 'created');
    return $id;
}

function res_seed_resolve_payments_table(PDO $pdo): ?string
{
    foreach (['pagos_residentes', 'pagos', 'pagos_usuarios', 'residentes_pagos', 'pagos_users'] as $table) {
        if (res_seed_table_exists($pdo, $table)) {
            return $table;
        }
    }
    return null;
}

function res_seed_pick_col(array $columns, array $candidates): ?string
{
    foreach ($candidates as $candidate) {
        if (res_seed_has_col($columns, $candidate)) {
            return $candidate;
        }
    }
    return null;
}

function res_seed_ensure_payment(PDO $pdo, string $table, int $rid, array $payment, array &$stats): int
{
    $cols = res_seed_table_columns($pdo, $table);
    $colId = res_seed_pick_col($cols, ['id']);
    $colUser = res_seed_pick_col($cols, ['residente_id', 'user_id', 'usuario_id', 'owner_user_id', 'propietario_user_id']);
    $colResid = res_seed_pick_col($cols, ['residencial_id', 'residencia_id', 'residential_id']);
    $colUnidad = res_seed_pick_col($cols, ['unidad_id', 'unit_id']);
    $colMonto = res_seed_pick_col($cols, ['monto', 'amount', 'importe']);
    $colFecha = res_seed_pick_col($cols, ['fecha_pago', 'fecha', 'payment_date', 'date']);
    $colMetodo = res_seed_pick_col($cols, ['metodo', 'method', 'medio', 'forma_pago']);
    $colConcepto = res_seed_pick_col($cols, ['concepto', 'concept', 'descripcion', 'description', 'nota']);
    $colActivo = res_seed_pick_col($cols, ['activo', 'active', 'estatus', 'status']);

    if (!$colId || !$colUser || !$colMonto || !$colFecha) {
        res_seed_warn("La tabla {$table} no tiene columnas minimas para pagos; se omite pago demo.");
        return 0;
    }

    $where = ["`{$colUser}` = :uid", "`{$colMonto}` = :monto", "`{$colFecha}` = :fecha"];
    $params = ['uid' => $payment['residente_id'], 'monto' => $payment['monto'], 'fecha' => $payment['fecha']];
    if ($colResid) {
        $where[] = "`{$colResid}` = :rid";
        $params['rid'] = $rid;
    }
    if ($colUnidad) {
        $where[] = "`{$colUnidad}` = :unidad";
        $params['unidad'] = $payment['unidad_id'];
    }
    if ($colConcepto) {
        $where[] = "`{$colConcepto}` = :concepto";
        $params['concepto'] = $payment['concepto'];
    }

    $existing = res_seed_fetch_one($pdo, "SELECT `{$colId}` AS id FROM `{$table}` WHERE " . implode(' AND ', $where) . " LIMIT 1", $params);
    $data = [
        $colUser => $payment['residente_id'],
        $colMonto => $payment['monto'],
        $colFecha => $payment['fecha'],
    ];
    if ($colResid) {
        $data[$colResid] = $rid;
    }
    if ($colUnidad) {
        $data[$colUnidad] = $payment['unidad_id'];
    }
    if ($colMetodo) {
        $data[$colMetodo] = $payment['metodo'];
    }
    if ($colConcepto) {
        $data[$colConcepto] = $payment['concepto'];
    }
    if ($colActivo) {
        $data[$colActivo] = 1;
    }

    if ($existing) {
        res_seed_update($pdo, $table, $cols, $data, 'id = :id', ['id' => (int)$existing['id']]);
        res_seed_bump($stats, 'pagos', 'reused');
        return (int)$existing['id'];
    }
    $id = res_seed_insert($pdo, $table, $cols, $data);
    res_seed_bump($stats, 'pagos', 'created');
    return $id;
}

function res_seed_ensure_reglamento(PDO $pdo, int $rid, string $title, string $content, array &$stats): int
{
    if (!res_seed_table_exists($pdo, 'reglamentos_residenciales')) {
        res_seed_warn('No existe reglamentos_residenciales; se omite reglamento demo.');
        return 0;
    }
    $cols = res_seed_table_columns($pdo, 'reglamentos_residenciales');
    res_seed_require_cols($cols, 'reglamentos_residenciales', ['residencial_id', 'titulo', 'contenido']);
    $existing = res_seed_fetch_one($pdo, "SELECT id FROM reglamentos_residenciales WHERE residencial_id = :rid ORDER BY id DESC LIMIT 1", ['rid' => $rid]);
    $data = [
        'residencial_id' => $rid,
        'titulo' => $title,
        'contenido' => $content,
        'version_label' => 'Demo 1.0',
    ];
    if ($existing) {
        res_seed_update($pdo, 'reglamentos_residenciales', $cols, $data, 'id = :id', ['id' => (int)$existing['id']]);
        res_seed_bump($stats, 'reglamento', 'updated');
        return (int)$existing['id'];
    }
    $id = res_seed_insert($pdo, 'reglamentos_residenciales', $cols, $data);
    res_seed_bump($stats, 'reglamento', 'created');
    return $id;
}

function res_seed_ensure_directory_service(PDO $pdo, int $rid, array $service, array &$stats): int
{
    if (!res_seed_table_exists($pdo, 'home_servicios_residenciales')) {
        res_seed_warn('No existe home_servicios_residenciales; se omite directorio demo.');
        return 0;
    }
    $cols = res_seed_table_columns($pdo, 'home_servicios_residenciales');
    res_seed_require_cols($cols, 'home_servicios_residenciales', ['residencial_id', 'nombre']);
    $existing = res_seed_fetch_one(
        $pdo,
        "SELECT id FROM home_servicios_residenciales WHERE residencial_id = :rid AND nombre = :nombre LIMIT 1",
        ['rid' => $rid, 'nombre' => $service['nombre']]
    );
    $data = [
        'residencial_id' => $rid,
        'nombre' => $service['nombre'],
        'descripcion' => $service['descripcion'],
        'telefono' => $service['telefono'],
        'whatsapp' => $service['telefono'],
        'link_url' => null,
        'categoria' => $service['categoria'] ?? 'servicio',
        'orden' => $service['orden'] ?? 10,
        'activo' => 1,
    ];
    if ($existing) {
        res_seed_update($pdo, 'home_servicios_residenciales', $cols, $data, 'id = :id', ['id' => (int)$existing['id']]);
        res_seed_bump($stats, 'directorio', 'reused');
        return (int)$existing['id'];
    }
    $id = res_seed_insert($pdo, 'home_servicios_residenciales', $cols, $data);
    res_seed_bump($stats, 'directorio', 'created');
    return $id;
}

function res_seed_ensure_tool(PDO $pdo, int $rid, string $name, array &$stats): int
{
    if (!res_seed_table_exists($pdo, 'catalogo_herramientas')) {
        res_seed_warn('No existe catalogo_herramientas; se omiten herramientas demo.');
        return 0;
    }
    $cols = res_seed_table_columns($pdo, 'catalogo_herramientas');
    res_seed_require_cols($cols, 'catalogo_herramientas', ['residencial_id', 'nombre']);
    $existing = res_seed_fetch_one(
        $pdo,
        "SELECT id FROM catalogo_herramientas WHERE residencial_id = :rid AND nombre = :nombre LIMIT 1",
        ['rid' => $rid, 'nombre' => $name]
    );
    $data = [
        'residencial_id' => $rid,
        'nombre' => $name,
        'descripcion' => 'Herramienta demo residencial.',
        'activo' => 1,
    ];
    if ($existing) {
        res_seed_update($pdo, 'catalogo_herramientas', $cols, $data, 'id = :id', ['id' => (int)$existing['id']]);
        res_seed_bump($stats, 'herramientas', 'reused');
        return (int)$existing['id'];
    }
    $id = res_seed_insert($pdo, 'catalogo_herramientas', $cols, $data);
    res_seed_bump($stats, 'herramientas', 'created');
    return $id;
}

function res_seed_ensure_tool_loan(PDO $pdo, int $rid, array $loan, array &$stats): int
{
    if (!res_seed_table_exists($pdo, 'prestamos_herramientas') || empty($loan['herramienta_id'])) {
        res_seed_warn('No existe prestamos_herramientas o no hay herramienta; se omite prestamo demo.');
        return 0;
    }
    $cols = res_seed_table_columns($pdo, 'prestamos_herramientas');
    res_seed_require_cols($cols, 'prestamos_herramientas', ['residencial_id', 'herramienta_id']);
    $existing = res_seed_fetch_one(
        $pdo,
        "SELECT id FROM prestamos_herramientas WHERE residencial_id = :rid AND herramienta_id = :herramienta AND unidad_id = :unidad LIMIT 1",
        ['rid' => $rid, 'herramienta' => $loan['herramienta_id'], 'unidad' => $loan['unidad_id']]
    );
    $data = [
        'residencial_id' => $rid,
        'herramienta_id' => $loan['herramienta_id'],
        'guardia_id' => $loan['guardia_id'],
        'unidad_id' => $loan['unidad_id'],
        'residente_id' => $loan['residente_id'],
        'estado' => res_seed_pick_enum($cols, 'estado', ['devuelto', 'prestado'], 'devuelto'),
        'notas' => 'Prestamo demo residencial.',
        'prestado_at' => date('Y-m-d H:i:s', time() - 3600),
        'devuelto_at' => date('Y-m-d H:i:s', time() - 1200),
    ];
    if ($existing) {
        res_seed_update($pdo, 'prestamos_herramientas', $cols, $data, 'id = :id', ['id' => (int)$existing['id']]);
        res_seed_bump($stats, 'prestamos_herramientas', 'reused');
        return (int)$existing['id'];
    }
    $id = res_seed_insert($pdo, 'prestamos_herramientas', $cols, $data);
    res_seed_bump($stats, 'prestamos_herramientas', 'created');
    return $id;
}

function res_seed_insert_demo_event(PDO $pdo, int $rid, array $event, array &$stats): void
{
    if (!res_seed_table_exists($pdo, 'bitacora_operativa')) {
        res_seed_warn('No existe bitacora_operativa; se omiten eventos demo.');
        return;
    }
    $cols = res_seed_table_columns($pdo, 'bitacora_operativa');
    $needle = '%"demo_key":"' . $event['demo_key'] . '"%';
    $existing = res_seed_fetch_id(
        $pdo,
        "SELECT id FROM bitacora_operativa WHERE residencial_id = :rid AND metadata_json LIKE :needle LIMIT 1",
        ['rid' => $rid, 'needle' => $needle]
    );
    if ($existing > 0) {
        res_seed_bump($stats, 'bitacora', 'reused');
        return;
    }

    $metadata = $event['metadata'] ?? [];
    $metadata['demo_seed'] = RES_DEMO_SEED_KEY;
    $metadata['demo_key'] = $event['demo_key'];
    $metadata['servicio_codigo'] = $event['servicio_codigo'];

    res_seed_insert($pdo, 'bitacora_operativa', $cols, [
        'residencial_id' => $rid,
        'guardia_id' => $event['guardia_id'] ?? null,
        'tipo_origen' => $event['tipo_origen'],
        'origen_id' => $event['origen_id'] ?? null,
        'tipo_evento' => $event['tipo_evento'],
        'resultado' => $event['resultado'] ?? 'permitido',
        'observaciones' => $event['observaciones'],
        'metadata_json' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
        'fecha_hora' => $event['fecha_hora'] ?? date('Y-m-d H:i:s'),
    ]);
    res_seed_bump($stats, 'bitacora', 'created');
}

try {
    $opts = res_seed_parse_args($argv);
    if ($opts['help']) {
        res_seed_usage();
        exit(0);
    }

    if (!isset($pdo) || !$pdo instanceof PDO) {
        throw new RuntimeException('No se encontro $pdo desde config/config.php.');
    }

    $nombre = (string)$opts['nombre'];
    $codigo = res_seed_service_code($nombre, $opts['codigo']);
    res_seed_confirm_or_exit((bool)$opts['dry_run'], (bool)$opts['yes'], $nombre, $codigo);

    res_seed_log('Conexion cargada desde config/config.php.');
    res_seed_ensure_schema_helpers($pdo);

    $stats = [];
    $imageUrls = res_seed_demo_images($root, (bool)$opts['dry_run'], $stats);

    $pdo->beginTransaction();

    $rid = res_seed_ensure_service($pdo, $nombre, $codigo, $stats);
    res_seed_upsert_service_profile($pdo, $rid, $stats);

    $adminRole = res_seed_ensure_role($pdo, 'admin_residencial', 'Administrador residencial.', $stats);
    $guardRole = res_seed_ensure_role($pdo, 'guardia', 'Guardia de acceso.', $stats);
    $residentRole = res_seed_ensure_role($pdo, 'residente', 'Residente.', $stats);

    $adminId = res_seed_ensure_user($pdo, $adminRole, [
        'name' => 'Admin Las Palmas Demo',
        'email' => RES_DEMO_ADMIN_EMAIL,
        'telefono' => '5512345678',
    ], $stats);
    $guardId = res_seed_ensure_user($pdo, $guardRole, [
        'name' => 'Guardia Las Palmas Demo',
        'email' => RES_DEMO_GUARD_EMAIL,
        'telefono' => '5500000001',
        'guardia_en_servicio' => true,
    ], $stats);

    $residentUsers = [
        'Carlos Hernández Ruiz' => res_seed_ensure_user($pdo, $residentRole, [
            'name' => 'Carlos Hernández Ruiz',
            'email' => 'carlos.residente.demo@osgate.local',
            'telefono' => '5511111111',
        ], $stats),
        'Ana Martínez López' => res_seed_ensure_user($pdo, $residentRole, [
            'name' => 'Ana Martínez López',
            'email' => 'ana.residente.demo@osgate.local',
            'telefono' => '5522222222',
        ], $stats),
        'Jorge Ramírez Torres' => res_seed_ensure_user($pdo, $residentRole, [
            'name' => 'Jorge Ramírez Torres',
            'email' => 'jorge.residente.demo@osgate.local',
            'telefono' => '5533333333',
        ], $stats),
    ];

    foreach (array_merge([$adminId, $guardId], array_values($residentUsers)) as $userId) {
        res_seed_ensure_user_assignment($pdo, $userId, $rid, $stats);
    }

    $unitIds = [];
    $unitIds['CASA-101'] = res_seed_ensure_unit($pdo, $rid, [
        'tipo' => 'casa',
        'clave' => 'CASA-101',
        'calle' => 'Privada Palma Real',
        'numero_exterior' => '101',
        'referencia' => 'Frente al jardin central',
    ], $stats);
    $unitIds['CASA-102'] = res_seed_ensure_unit($pdo, $rid, [
        'tipo' => 'casa',
        'clave' => 'CASA-102',
        'calle' => 'Privada Palma Real',
        'numero_exterior' => '102',
        'referencia' => 'Cerca del acceso principal',
    ], $stats);
    $unitIds['DEP-201'] = res_seed_ensure_unit($pdo, $rid, [
        'tipo' => 'departamento',
        'clave' => 'DEP-201',
        'edificio' => 'Torre A',
        'torre' => 'Torre A',
        'piso' => 2,
        'nivel' => '2',
        'numero_interior' => '201',
        'referencia' => 'Segundo piso',
    ], $stats);

    res_seed_ensure_resident_unit($pdo, $residentUsers['Carlos Hernández Ruiz'], $unitIds['CASA-101'], $stats);
    res_seed_ensure_resident_unit($pdo, $residentUsers['Ana Martínez López'], $unitIds['CASA-102'], $stats);
    res_seed_ensure_resident_unit($pdo, $residentUsers['Jorge Ramírez Torres'], $unitIds['DEP-201'], $stats);

    $autosTable = res_seed_resolve_autos_table($pdo);
    $autoIds = [];
    if ($autosTable) {
        $autoIds['Carlos'] = res_seed_ensure_auto($pdo, $autosTable, $rid, [
            'residente_id' => $residentUsers['Carlos Hernández Ruiz'],
            'unidad_id' => $unitIds['CASA-101'],
            'placas' => 'NPA1234',
            'modelo' => 'Seat Ibiza FR 2016',
            'color' => 'Blanco',
            'tag_id' => 'TAG-CARLOS-101',
        ], $stats);
        $autoIds['Ana'] = res_seed_ensure_auto($pdo, $autosTable, $rid, [
            'residente_id' => $residentUsers['Ana Martínez López'],
            'unidad_id' => $unitIds['CASA-102'],
            'placas' => 'MNB5678',
            'modelo' => 'Mazda CX-5 2021',
            'color' => 'Gris',
            'tag_id' => 'TAG-ANA-102',
        ], $stats);
        $autoIds['Jorge'] = res_seed_ensure_auto($pdo, $autosTable, $rid, [
            'residente_id' => $residentUsers['Jorge Ramírez Torres'],
            'unidad_id' => $unitIds['DEP-201'],
            'placas' => 'LPM9012',
            'modelo' => 'Nissan Versa 2020',
            'color' => 'Azul',
            'tag_id' => 'TAG-JORGE-201',
        ], $stats);
    } else {
        res_seed_warn('No se encontro tabla de autos; se omiten autos demo.');
    }

    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    if ($autoIds) {
        $vehicleAccesses = [
            ['demo_key' => 'auto-carlos-entrada-hoy', 'residente_id' => $residentUsers['Carlos Hernández Ruiz'], 'unidad_id' => $unitIds['CASA-101'], 'auto_id' => $autoIds['Carlos'], 'tag_id' => 'TAG-CARLOS-101', 'tipo_movimiento' => 'ingreso', 'fecha_hora' => "{$today} 08:10:00", 'servicio_codigo' => $codigo],
            ['demo_key' => 'auto-carlos-salida-hoy', 'residente_id' => $residentUsers['Carlos Hernández Ruiz'], 'unidad_id' => $unitIds['CASA-101'], 'auto_id' => $autoIds['Carlos'], 'tag_id' => 'TAG-CARLOS-101', 'tipo_movimiento' => 'egreso', 'fecha_hora' => "{$today} 09:30:00", 'servicio_codigo' => $codigo],
            ['demo_key' => 'auto-ana-entrada-ayer', 'residente_id' => $residentUsers['Ana Martínez López'], 'unidad_id' => $unitIds['CASA-102'], 'auto_id' => $autoIds['Ana'], 'tag_id' => 'TAG-ANA-102', 'tipo_movimiento' => 'ingreso', 'fecha_hora' => "{$yesterday} 19:20:00", 'servicio_codigo' => $codigo],
            ['demo_key' => 'auto-jorge-entrada-hoy', 'residente_id' => $residentUsers['Jorge Ramírez Torres'], 'unidad_id' => $unitIds['DEP-201'], 'auto_id' => $autoIds['Jorge'], 'tag_id' => 'TAG-JORGE-201', 'tipo_movimiento' => 'ingreso', 'fecha_hora' => "{$today} 07:45:00", 'servicio_codigo' => $codigo],
        ];
        foreach ($vehicleAccesses as $access) {
            res_seed_ensure_vehicle_access($pdo, $rid, $access, $stats);
        }
    }

    $visitIds = [];
    $visitIds['carlos'] = res_seed_ensure_visit($pdo, $rid, [
        'unidad_id' => $unitIds['CASA-101'],
        'residente_id' => $residentUsers['Carlos Hernández Ruiz'],
        'nombre_visitante' => 'Luis Alberto Sánchez',
        'motivo' => 'Visita familiar',
        'placa_vehiculo' => 'ABC123',
        'fecha_desde' => $today,
        'fecha_hasta' => $today,
        'hora_desde' => '10:00:00',
        'hora_hasta' => '14:00:00',
        'uso_unico' => 1,
        'codigo_acceso' => 'demo-visita-carlos-101',
        'estado' => 'activa',
    ], $stats);
    $visitIds['ana'] = res_seed_ensure_visit($pdo, $rid, [
        'unidad_id' => $unitIds['CASA-102'],
        'residente_id' => $residentUsers['Ana Martínez López'],
        'nombre_visitante' => 'Paquetería Express',
        'motivo' => 'Entrega de paquete',
        'placa_vehiculo' => 'PKT456',
        'fecha_desde' => $today,
        'fecha_hasta' => $today,
        'hora_desde' => '12:00:00',
        'hora_hasta' => '15:00:00',
        'uso_unico' => 1,
        'codigo_acceso' => 'demo-visita-ana-102',
        'estado' => 'activa',
    ], $stats);
    $visitIds['jorge'] = res_seed_ensure_visit($pdo, $rid, [
        'unidad_id' => $unitIds['DEP-201'],
        'residente_id' => $residentUsers['Jorge Ramírez Torres'],
        'nombre_visitante' => 'Técnico Internet',
        'motivo' => 'Instalación de fibra óptica',
        'placa_vehiculo' => 'TEC789',
        'fecha_desde' => $tomorrow,
        'fecha_hasta' => $tomorrow,
        'hora_desde' => '09:00:00',
        'hora_hasta' => '11:00:00',
        'uso_unico' => 0,
        'codigo_acceso' => 'demo-visita-jorge-201',
        'estado' => 'programada',
    ], $stats);

    $guardAccesses = [
        ['demo_key' => 'visita-carlos-entrada', 'visit_id' => $visitIds['carlos'], 'tipo_evento' => 'entrada', 'resultado' => 'permitido', 'observaciones' => 'Visita Carlos autorizada entrada.', 'unidad_id' => $unitIds['CASA-101'], 'residente_id' => $residentUsers['Carlos Hernández Ruiz'], 'fecha_hora' => "{$today} 10:15:00", 'servicio_codigo' => $codigo],
        ['demo_key' => 'visita-carlos-salida', 'visit_id' => $visitIds['carlos'], 'tipo_evento' => 'salida', 'resultado' => 'permitido', 'observaciones' => 'Visita Carlos salida.', 'unidad_id' => $unitIds['CASA-101'], 'residente_id' => $residentUsers['Carlos Hernández Ruiz'], 'fecha_hora' => "{$today} 13:25:00", 'servicio_codigo' => $codigo],
        ['demo_key' => 'codigo-invalido-rechazado', 'visit_id' => 0, 'tipo_evento' => 'entrada', 'resultado' => 'denegado', 'observaciones' => 'Intento rechazado por código vencido o incorrecto.', 'unidad_id' => null, 'residente_id' => null, 'origen_acceso' => 'codigo_manual', 'fecha_hora' => "{$today} 14:10:00", 'servicio_codigo' => $codigo],
        ['demo_key' => 'paqueteria-registrada-acceso', 'visit_id' => $visitIds['ana'], 'tipo_evento' => 'entrada', 'resultado' => 'permitido', 'observaciones' => 'Paquetería registrada para CASA-102.', 'unidad_id' => $unitIds['CASA-102'], 'residente_id' => $residentUsers['Ana Martínez López'], 'fecha_hora' => "{$today} 12:05:00", 'servicio_codigo' => $codigo],
    ];
    foreach ($guardAccesses as $access) {
        res_seed_ensure_guard_access($pdo, $guardId, (int)$access['visit_id'], $access, $stats);
    }

    $comunicados = [
        ['titulo' => 'Mantenimiento programado de agua', 'mensaje' => 'El próximo sábado se realizará mantenimiento a la red hidráulica de 09:00 a 13:00. Se recomienda almacenar agua con anticipación.', 'tipo' => 'mantenimiento', 'prioridad' => 'alta', 'imagen_url' => $imageUrls['mantenimiento_agua.svg'] ?? null],
        ['titulo' => 'Reforzamiento de seguridad en accesos', 'mensaje' => 'A partir de esta semana se solicitará identificación a todas las visitas y proveedores.', 'tipo' => 'seguridad', 'prioridad' => 'media', 'imagen_url' => $imageUrls['seguridad_acceso.svg'] ?? null],
        ['titulo' => 'Reunión vecinal mensual', 'mensaje' => 'Se invita a los residentes a la reunión mensual en el salón de usos múltiples.', 'tipo' => 'general', 'prioridad' => 'baja', 'imagen_url' => $imageUrls['reunion_vecinal.svg'] ?? null],
    ];
    foreach ($comunicados as $comunicado) {
        res_seed_ensure_comunicado($pdo, $rid, $adminId, $comunicado, $stats);
    }

    $incidentIds = [];
    $incidentIds['lampara'] = res_seed_ensure_incident($pdo, $rid, [
        'residente_id' => $residentUsers['Carlos Hernández Ruiz'],
        'unidad_id' => $unitIds['CASA-101'],
        'titulo' => 'Lámpara fundida en pasillo',
        'tipo' => 'mantenimiento',
        'prioridad' => 'media',
        'estado' => 'abierta',
        'descripcion' => 'La lámpara del pasillo frente a CASA-101 no enciende desde anoche.',
    ], $stats);
    $incidentIds['ruido'] = res_seed_ensure_incident($pdo, $rid, [
        'residente_id' => $residentUsers['Ana Martínez López'],
        'unidad_id' => $unitIds['CASA-102'],
        'titulo' => 'Ruido excesivo en área común',
        'tipo' => 'vecino',
        'prioridad' => 'baja',
        'estado' => 'en_proceso',
        'descripcion' => 'Se reporta ruido después de las 23:00 en jardín central.',
    ], $stats);
    $incidentIds['vehiculo'] = res_seed_ensure_incident($pdo, $rid, [
        'guardia_id' => $guardId,
        'unidad_id' => $unitIds['DEP-201'],
        'titulo' => 'Vehículo mal estacionado',
        'tipo' => 'seguridad',
        'prioridad' => 'media',
        'estado' => 'cerrada',
        'descripcion' => 'Vehículo bloqueaba parcialmente el acceso al estacionamiento.',
    ], $stats);

    $packages = [
        ['unidad_id' => $unitIds['CASA-101'], 'residente_id' => $residentUsers['Carlos Hernández Ruiz'], 'guardia_id' => $guardId, 'empresa' => 'Amazon', 'descripcion' => 'Caja mediana', 'codigo_rastreo' => 'AMZ-DEMO-001', 'estado' => 'pendiente', 'notas' => 'Entregado en caseta a las 11:20'],
        ['unidad_id' => $unitIds['CASA-102'], 'residente_id' => $residentUsers['Ana Martínez López'], 'guardia_id' => $guardId, 'empresa' => 'Mercado Libre', 'descripcion' => 'Sobre amarillo', 'codigo_rastreo' => 'ML-DEMO-002', 'estado' => 'entregado', 'notas' => 'Residente confirmó recepción'],
        ['unidad_id' => $unitIds['DEP-201'], 'residente_id' => $residentUsers['Jorge Ramírez Torres'], 'guardia_id' => $guardId, 'empresa' => 'DHL', 'descripcion' => 'Paquete pequeño', 'codigo_rastreo' => 'DHL-DEMO-003', 'estado' => 'devuelto', 'notas' => 'No se localizó al residente'],
    ];
    $packageIds = [];
    foreach ($packages as $package) {
        $packageIds[$package['codigo_rastreo']] = res_seed_ensure_package($pdo, $rid, $package, $stats);
    }

    $paymentsTable = res_seed_resolve_payments_table($pdo);
    if ($paymentsTable) {
        $payments = [
            ['residente_id' => $residentUsers['Carlos Hernández Ruiz'], 'unidad_id' => $unitIds['CASA-101'], 'monto' => 1500, 'fecha' => date('Y-m-05'), 'metodo' => 'transferencia', 'concepto' => 'Mantenimiento mensual'],
            ['residente_id' => $residentUsers['Ana Martínez López'], 'unidad_id' => $unitIds['CASA-102'], 'monto' => 1500, 'fecha' => date('Y-m-06'), 'metodo' => 'efectivo', 'concepto' => 'Mantenimiento mensual'],
            ['residente_id' => $residentUsers['Jorge Ramírez Torres'], 'unidad_id' => $unitIds['DEP-201'], 'monto' => 1500, 'fecha' => date('Y-m-05', strtotime('-1 month')), 'metodo' => 'transferencia', 'concepto' => 'Mantenimiento mensual'],
        ];
        foreach ($payments as $payment) {
            res_seed_ensure_payment($pdo, $paymentsTable, $rid, $payment, $stats);
        }
    } else {
        res_seed_warn('No se encontro tabla de pagos; se omiten pagos demo.');
    }

    $reglamento = implode("\n", [
        '- Horario de visitas: 08:00 a 22:00.',
        '- Toda visita debe registrarse con QR o autorización del residente.',
        '- Proveedores deben presentar identificación.',
        '- Áreas comunes deben respetarse después de las 22:00.',
        '- La velocidad máxima dentro del residencial es 10 km/h.',
        '- Las mascotas deben estar siempre con correa.',
        '- Está prohibido obstruir accesos vehiculares.',
    ]);
    res_seed_ensure_reglamento($pdo, $rid, 'Reglamento Residencial Las Palmas', $reglamento, $stats);

    $directory = [
        ['nombre' => 'Plomero de confianza', 'descripcion' => 'Atención a fugas y mantenimiento hidráulico.', 'telefono' => '5510101001', 'categoria' => 'mantenimiento', 'orden' => 1],
        ['nombre' => 'Electricista', 'descripcion' => 'Servicios eléctricos residenciales.', 'telefono' => '5510101002', 'categoria' => 'mantenimiento', 'orden' => 2],
        ['nombre' => 'Jardinería', 'descripcion' => 'Cuidado de áreas verdes.', 'telefono' => '5510101003', 'categoria' => 'servicio', 'orden' => 3],
        ['nombre' => 'Administración', 'descripcion' => 'Oficina de administración del residencial.', 'telefono' => '5512345678', 'categoria' => 'administracion', 'orden' => 4],
        ['nombre' => 'Seguridad caseta', 'descripcion' => 'Caseta de vigilancia 24/7.', 'telefono' => '5510101005', 'categoria' => 'seguridad', 'orden' => 5],
    ];
    foreach ($directory as $service) {
        res_seed_ensure_directory_service($pdo, $rid, $service, $stats);
    }

    $toolIds = [];
    foreach (['Radio portátil', 'Escalera', 'Lámpara recargable', 'Kit de llaves', 'Extensión eléctrica'] as $toolName) {
        $toolIds[$toolName] = res_seed_ensure_tool($pdo, $rid, $toolName, $stats);
    }
    $loanId = res_seed_ensure_tool_loan($pdo, $rid, [
        'herramienta_id' => $toolIds['Escalera'] ?? 0,
        'guardia_id' => $guardId,
        'unidad_id' => $unitIds['CASA-101'],
        'residente_id' => $residentUsers['Carlos Hernández Ruiz'],
    ], $stats);

    $now = time();
    $bitacoraEvents = [
        ['demo_key' => 'entrada-visita-luis', 'tipo_origen' => 'visita', 'origen_id' => $visitIds['carlos'], 'tipo_evento' => 'entrada_visita', 'resultado' => 'permitido', 'observaciones' => 'Entrada visita Luis Alberto Sánchez a CASA-101.', 'guardia_id' => $guardId, 'fecha_hora' => date('Y-m-d H:i:s', $now - 7200), 'metadata' => ['unidad_id' => $unitIds['CASA-101'], 'residente_id' => $residentUsers['Carlos Hernández Ruiz']]],
        ['demo_key' => 'salida-visita-luis', 'tipo_origen' => 'visita', 'origen_id' => $visitIds['carlos'], 'tipo_evento' => 'salida_visita', 'resultado' => 'permitido', 'observaciones' => 'Salida visita Luis Alberto Sánchez de CASA-101.', 'guardia_id' => $guardId, 'fecha_hora' => date('Y-m-d H:i:s', $now - 6000), 'metadata' => ['unidad_id' => $unitIds['CASA-101'], 'residente_id' => $residentUsers['Carlos Hernández Ruiz']]],
        ['demo_key' => 'paquete-amazon-casa-101', 'tipo_origen' => 'paqueteria', 'origen_id' => $packageIds['AMZ-DEMO-001'] ?? null, 'tipo_evento' => 'paquete_recibido', 'resultado' => 'informativo', 'observaciones' => 'Amazon para CASA-101 recibido en caseta.', 'guardia_id' => $guardId, 'fecha_hora' => date('Y-m-d H:i:s', $now - 5400), 'metadata' => ['unidad_id' => $unitIds['CASA-101']]],
        ['demo_key' => 'incidencia-lampara-creada', 'tipo_origen' => 'incidencia', 'origen_id' => $incidentIds['lampara'] ?? null, 'tipo_evento' => 'incidencia_creada', 'resultado' => 'informativo', 'observaciones' => 'Incidencia de lámpara fundida creada.', 'guardia_id' => null, 'fecha_hora' => date('Y-m-d H:i:s', $now - 5000), 'metadata' => ['unidad_id' => $unitIds['CASA-101']]],
        ['demo_key' => 'herramienta-escalera-prestada', 'tipo_origen' => 'prestamo_herramienta', 'origen_id' => $loanId ?: null, 'tipo_evento' => 'herramienta_prestada', 'resultado' => 'informativo', 'observaciones' => 'Escalera prestada a CASA-101.', 'guardia_id' => $guardId, 'fecha_hora' => date('Y-m-d H:i:s', $now - 3600), 'metadata' => ['unidad_id' => $unitIds['CASA-101']]],
        ['demo_key' => 'herramienta-escalera-devuelta', 'tipo_origen' => 'prestamo_herramienta', 'origen_id' => $loanId ?: null, 'tipo_evento' => 'herramienta_devuelta', 'resultado' => 'informativo', 'observaciones' => 'Escalera devuelta por CASA-101.', 'guardia_id' => $guardId, 'fecha_hora' => date('Y-m-d H:i:s', $now - 2500), 'metadata' => ['unidad_id' => $unitIds['CASA-101']]],
        ['demo_key' => 'acceso-vehicular-carlos-tag', 'tipo_origen' => 'auto', 'origen_id' => $autoIds['Carlos'] ?? null, 'tipo_evento' => 'acceso_vehicular', 'resultado' => 'permitido', 'observaciones' => 'Auto Carlos entra con tag.', 'guardia_id' => $guardId, 'fecha_hora' => "{$today} 08:10:00", 'metadata' => ['unidad_id' => $unitIds['CASA-101']]],
        ['demo_key' => 'acceso-rechazado-codigo-invalido', 'tipo_origen' => 'acceso', 'origen_id' => null, 'tipo_evento' => 'acceso_rechazado', 'resultado' => 'denegado', 'observaciones' => 'Código inválido de visita.', 'guardia_id' => $guardId, 'fecha_hora' => date('Y-m-d H:i:s', $now - 1800), 'metadata' => ['unidad_id' => null]],
    ];
    foreach ($bitacoraEvents as $event) {
        $event['servicio_codigo'] = $codigo;
        res_seed_insert_demo_event($pdo, $rid, $event, $stats);
    }

    if ($opts['dry_run']) {
        $pdo->rollBack();
        res_seed_warn('DRY RUN: cambios de datos revertidos con rollback.');
    } else {
        $pdo->commit();
        res_seed_ok('Cambios confirmados con commit.');
    }

    echo "\nResumen Residencial Demo\n";
    echo "------------------------\n";
    echo "Residencial: {$nombre} ({$codigo})\n";
    echo "residencial_id: {$rid}\n";
    echo "Admin demo: " . RES_DEMO_ADMIN_EMAIL . " / " . RES_DEMO_PASSWORD . "\n";
    echo "Guardia demo: " . RES_DEMO_GUARD_EMAIL . " / " . RES_DEMO_PASSWORD . "\n";
    echo "Residentes demo:\n";
    echo "  carlos.residente.demo@osgate.local / " . RES_DEMO_PASSWORD . "\n";
    echo "  ana.residente.demo@osgate.local / " . RES_DEMO_PASSWORD . "\n";
    echo "  jorge.residente.demo@osgate.local / " . RES_DEMO_PASSWORD . "\n\n";
    foreach ($stats as $bucket => $values) {
        $created = (int)($values['created'] ?? 0);
        $updated = (int)($values['updated'] ?? 0);
        $reused = (int)($values['reused'] ?? 0);
        $dry = (int)($values['dry_run'] ?? 0);
        echo sprintf("- %-26s creados=%d actualizados=%d reutilizados=%d dry_run=%d\n", $bucket, $created, $updated, $reused, $dry);
    }
    echo "\nComandos:\n";
    echo "  php scripts/seed_residencial_demo.php --dry-run\n";
    echo "  php scripts/seed_residencial_demo.php --yes\n";
    echo "  php scripts/seed_residencial_demo.php --nombre=\"{$nombre}\" --codigo=\"{$codigo}\" --yes\n";
    exit(0);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "[ERROR] " . $e->getMessage() . "\n");
    exit(1);
}
